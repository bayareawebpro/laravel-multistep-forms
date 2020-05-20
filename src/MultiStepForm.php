<?php declare(strict_types=1);

namespace BayAreaWebPro\MultiStepForms;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\View;

use Illuminate\Session\Store as Session;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Responsable;

class MultiStepForm implements Responsable, Arrayable
{
    public string $namespace = 'multistep-form';
    public bool $wasReset = false;
    public Collection $after;
    public Collection $before;
    public Collection $steps;
    public Request $request;
    public Session $session;
    public array $data;
    public $view;

    /**
     * MultiStepForm constructor.
     * @param Request $request
     * @param Session $session
     * @param array $data
     * @param string|null $view
     */
    public function __construct(Request $request, Session $session, $data = [], ?string $view = null)
    {
        $this->after = new Collection;
        $this->before = new Collection;
        $this->steps = new Collection;
        $this->request = $request;
        $this->session = $session;
        $this->view = $view;
        $this->data = $data;
    }

    /**
     * Make MultiStepForm Instance
     * @param null|string $view
     * @param array $data
     * @return static
     */
    public static function make(?string $view = null, array $data = []): self
    {
        return app(static::class, [
            'view' => $view,
            'data' => $data,
        ]);
    }

    /**
     * Set the session namespace.
     * @param string $namespace
     * @return $this
     */
    public function namespaced(string $namespace): self
    {
        $this->namespace = $namespace;
        return $this;
    }

    /**
     * Tap into instance (invokable Classes).
     * @param Closure|mixed $closure
     * @return $this
     */
    public function tap($closure)
    {
        $closure($this);
        return $this;
    }

    /**
     * Add Before Step callback
     * @param int|string $step
     * @param Closure $closure
     * @return $this
     */
    public function beforeStep($step, Closure $closure): self
    {
        $this->before->put($step, $closure);
        return $this;
    }

    /**
     * Add Step callback
     * @param int|string $step
     * @param Closure $closure
     * @return $this
     */
    public function onStep($step, Closure $closure): self
    {
        $this->after->put($step, $closure);
        return $this;
    }

    /**
     * Add step configuration.
     * @param int $step
     * @param array $config
     * @return $this
     */
    public function addStep(int $step, array $config = []): self
    {
        $this->steps->put($step, $config);
        return $this;
    }

    /**
     * Get Current Step
     * @return int
     */
    public function currentStep(): int
    {
        // Override the current step when reset.
        if ($this->wasReset) return 1;

        // Pull from request or fallback to session.
        return (int)$this->session->get("{$this->namespace}.form_step", 1);
    }

    /**
     * Get the current step config or by number.
     * @param int $step
     * @return Collection
     */
    public function stepConfig(?int $step = null): Collection
    {
        return Collection::make($this->steps->get($step ?? $this->currentStep()));
    }

    /**
     * Determine the current step.
     * @param int $step
     * @return bool
     */
    public function isStep(int $step = 1): bool
    {
        return $this->currentStep() === $step;
    }

    /**
     * Get v value.
     * @param string $key
     * @param mixed|null $fallback
     * @return mixed
     */
    public function getValue(string $key, $fallback = null)
    {
        return $this->session->get("{$this->namespace}.$key", $fallback);
    }

    /**
     * Set session value.
     * @param string $key
     * @param mixed $value
     * @return $this
     */
    public function setValue(string $key, $value): self
    {
        $this->session->put("{$this->namespace}.$key", $value);
        return $this;
    }

    /**
     * Increment the current step to the next.
     * @return $this
     */
    protected function nextStep(): self
    {
        if (!$this->wasReset && !$this->isStep($this->lastStep())) {
            $this->session->increment("{$this->namespace}.form_step");
        }
        return $this;
    }

    /**
     * Get the Last Step Number
     * @return int
     */
    public function lastStep(): int
    {
        return $this->steps->keys()->max(fn($value) => $value) ?? 1;
    }

    /**
     * Is the current step the last?
     * @return bool
     */
    public function isLastStep(): bool
    {
        return $this->isStep($this->lastStep());
    }

    /**
     * Save the validation data to the session.
     * @param array $data
     * @return $this
     */
    protected function save(array $data = []): self
    {
        $this->session->put($this->namespace, array_merge(
            $this->session->get($this->namespace, []), $data
        ));
        return $this;
    }

    /**
     * Reset session state.
     * @param array $data
     * @return $this
     */
    public function reset($data = []): self
    {
        $this->session->put($this->namespace, array_merge($data, ['form_step' => 1]));
        $this->wasReset = true;
        return $this;
    }

    /**
     * Handle "Before" Callback
     * @param int|string $key
     * @return mixed
     */
    protected function handleBefore($key)
    {
        if ($callback = $this->before->get($key)) {
            return $callback($this);
        }
    }

    /**
     * Handle "After" Callback
     * @param int|string $key
     * @return mixed
     */
    protected function handleAfter($key)
    {
        if ($callback = $this->after->get($key)) {
            return $callback($this);
        }
    }

    /**
     * Handle the validated request.
     * @return mixed
     */
    protected function handleRequest()
    {
        if (!$this->request->isMethod('GET')) {

            if ($response = (
                $this->handleBefore('*') ??
                $this->handleBefore($this->currentStep())
            )) {
                return $response;
            }

            if (!$this->wasReset) {

                $this->save($this->validate());

                if ($response = (
                    $this->handleAfter('*') ??
                    $this->handleAfter($this->currentStep())
                )) {
                    return $response;
                }

                $this->nextStep();

            }
        }

        return $this->renderResponse();
    }

    /**
     * Render the request as a response.
     * @return \Illuminate\Contracts\View\View|\Illuminate\Http\RedirectResponse|Response
     */
    protected function renderResponse()
    {
        // Setup the session if not already set.
        if (!$this->getValue('form_step', false)) {
            $this->setValue('form_step', 1);
        }

        // Render as JSON Response.
        if ($this->needsJsonResponse() || !is_string($this->view)) {
            return new Response([
                'data' => array_merge($this->data, $this->stepConfig()->get('data', [])),
                'form' => $this->toArray(),
            ]);
        }

        // Redirect back after submission.
        if (!$this->request->isMethod('GET')) {
            return redirect()->back();
        }

        // Default to view.
        return View::make($this->view, array_merge($this->data, [
            'form' => $this,
        ]));
    }

    /**
     * Validate the request.
     * @return array
     */
    protected function validate(): array
    {
        $step = $this->stepConfig($this->request->get('form_step', 1));

        return $this->request->validate(
            array_merge($step->get('rules', []), [
                'form_step' => ['required', 'numeric', Rule::in(range(1, $this->lastStep()))],
            ]),
            $step->get('messages', [])
        );
    }

    /**
     * Create an HTTP response that represents the object.
     * @param \Illuminate\Http\Request|null $request
     * @return \Illuminate\Http\Response
     */
    public function toResponse($request = null)
    {
        $this->request = ($request ?? $this->request);

        return $this->handleRequest();
    }

    /**
     * Request needs JSON response.
     * @return bool
     */
    protected function needsJsonResponse(): bool
    {
        return $this->request->wantsJson() || $this->request->isXmlHttpRequest();
    }

    /**
     * Get the instance as an array.
     * @return array
     */
    public function toArray(): array
    {
        return $this->session->get($this->namespace, []);
    }

    /**
     * Get the instance as an Collection.
     * @return Collection
     */
    public function toCollection(): Collection
    {
        return Collection::make($this->toArray());
    }
}
