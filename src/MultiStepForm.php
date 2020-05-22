<?php declare(strict_types=1);

namespace BayAreaWebPro\MultiStepForms;

use Closure;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;

use Illuminate\Validation\Rule;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\View;
use Illuminate\Session\Store as Session;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Responsable;
use Illuminate\Contracts\View\View as ViewContract;

class MultiStepForm implements Responsable, Arrayable
{
    protected string $namespace = 'multistep-form';
    protected bool $wasReset = false;
    protected bool $canGoBack = false;
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
     * @return ViewContract|JsonResponse|RedirectResponse|Response
     */
    protected function renderResponse()
    {
        // Verify Backwards Navigation.
        $shouldGoBack = $this->shouldNavigateBack();

        if(!$this->usesViews() || $this->needsJsonResponse()){
            return new JsonResponse([
                'data' => $this->getData(),
                'form' => $this->toArray(),
            ]);
        }

        // Handle Backwards Navigation / Remove Url Parameters.
        if ($shouldGoBack) {
            return redirect($this->request->path());
        }

        // Redirect back after submission to allow page refresh without re-submission.
        if (!$this->request->isMethod('GET')) {
            return redirect()->back();
        }

        // Default to view.
        return View::make($this->view, $this->getData([
            'form' => $this,
        ]));
    }

    /**
     * Get the configuration & view data.
     * @param array $merge
     * @return array
     */
    protected function getData(array $merge = []): array
    {
        return array_merge($this->data, $this->stepConfig()->get('data', []), $merge);
    }

    /**
     * Validate the request.
     * @return array
     */
    protected function validate(): array
    {
        $step = $this->stepConfig((int)$this->request->get('form_step', 1));

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
     * Allow Navigate Back
     * @param bool $enabled
     * @return $this
     */
    public function canNavigateBack(bool $enabled = true): self
    {
        $this->canGoBack = $enabled;
        return $this;
    }

    /**
     * Handle Backwards Navigation.
     * @return bool|int
     */
    protected function shouldNavigateBack()
    {
        if (
            $this->canGoBack &&
            $this->request->isMethod('GET') &&
            $this->request->filled('form_step')
        ) {
            $step = (int) $this->request->get('form_step', 1);
            if ($this->steps->has($step) && $this->isPast($step)) {
                $this->setValue('form_step', $step);
            }
            return true;
        }
        return false;
    }

    /**
     * Request needs JSON response.
     * @return bool
     */
    public function needsJsonResponse(): bool
    {
        return $this->request->wantsJson() || $this->request->isXmlHttpRequest();
    }

    /**
     * Request needs JSON response.
     * @return bool
     */
    public function usesViews(): bool
    {
        return is_string($this->view);
    }

    /**
     * Is Active Condition
     * @param int $step
     * @param mixed|null $active
     * @param mixed|null $fallback
     * @return mixed
     */
    public function isActive(int $step, $active = true, $fallback = false)
    {
        if ($this->isStep($step)) {
            return $active;
        }
        return $fallback;
    }

    /**
     * Is Future Condition
     * @param int $step
     * @param mixed|null $active
     * @param mixed|null $fallback
     * @return mixed
     */
    public function isFuture(int $step, $active = true, $fallback = false)
    {
        if ($this->steps->has($step) && $this->currentStep() < $step) {
            return $active;
        }
        return $fallback;
    }

    /**
     * Is Past Condition
     * @param int $step
     * @param mixed $active
     * @param mixed $fallback
     * @return mixed
     */
    public function isPast(int $step, $active = true, $fallback = false)
    {
        if ($this->steps->has($step) && $this->currentStep() > $step) {
            return $active;
        }
        return $fallback;
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

    /**
     * Setup the session if it hasn't been started.
     */
    protected function setupSession(): void
    {
        if (!is_numeric($this->getValue('form_step', false))) {
            $this->setValue('form_step', 1);
        }
    }

    /**
     * Set the session namespace.
     * @param string $namespace
     * @return $this
     */
    public function namespaced(string $namespace): self
    {
        $this->namespace = $namespace;
        $this->setupSession();
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
        return $this->session->get("{$this->namespace}.$key", $this->session->getOldInput($key, $fallback));
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
            $this->setValue('form_step', 1 + (int) $this->request->get('form_step', 1));
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
}
