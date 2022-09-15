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
    public Collection $after;
    public Collection $before;
    public Collection $steps;
    public Request $request;
    public Session $session;
    public array $data;
    public ?string $view;
    protected bool $wasReset = false;
    protected bool $canGoBack = false;
    protected string $namespace = 'multistep-form';
    protected ?Closure $beforeSaveCallback = null;

    public function __construct(Request $request, Session $session, array $data = [], ?string $view = null)
    {
        $this->after = new Collection;
        $this->before = new Collection;
        $this->steps = new Collection;
        $this->request = $request;
        $this->session = $session;
        $this->view = $view;
        $this->data = $data;
    }

    public static function make(?string $view = null, array $data = []): self
    {
        return app(static::class, [
            'view' => $view,
            'data' => $data,
        ]);
    }

    protected function handleRequest(): Response|ViewContract|JsonResponse|RedirectResponse
    {
        $this->setupSession();

        if (!$this->request->isMethod('GET')) {

            if ($response = (
                $this->handleBefore('*') ??
                $this->handleBefore($this->requestedStep())
            )) {
                return $response;
            }

            if (!$this->wasReset) {

                $this->handleSave($this->validate());

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

    public function renderResponse(): ViewContract|JsonResponse|RedirectResponse
    {
        $shouldGoBack = $this->shouldNavigateBack();

        if (!$this->usesViews() || $this->needsJsonResponse()) {
            return new JsonResponse((object)[
                'data' => $this->getData(),
                'form' => $this->toArray(),
            ]);
        }

        if ($shouldGoBack) {
            return redirect($this->request->path());
        }

        if (!$this->request->isMethod('GET')) {
            return redirect()->back();
        }

        return View::make($this->view, $this->getData([
            'form' => $this,
        ]));
    }

    public function withData(array $data = []): self
    {
        $this->data = array_merge($this->data, $data);
        return $this;
    }

    protected function getData(array $data = []): array
    {
        return array_merge($this->data, $this->stepConfig()->get('data', []), $data);
    }

    protected function validate(): array
    {
        $step = $this->stepConfig($this->requestedStep());

        return $this->request->validate(
            array_merge($step->get('rules', []), [
                'form_step' => ['required', 'numeric', Rule::in(range(1, $this->lastStep()))],
            ]),
            $step->get('messages', [])
        );
    }

    public function toResponse($request = null)
    {
        $this->request = ($request ?? $this->request);

        return $this->handleRequest();
    }

    public function canNavigateBack(bool $enabled = true): self
    {
        $this->canGoBack = $enabled;
        return $this;
    }

    protected function shouldNavigateBack(): bool
    {
        if (
            $this->canGoBack &&
            $this->request->isMethod('GET') &&
            $this->request->filled('form_step')
        ) {
            $step = $this->requestedStep();
            if ($this->steps->has($step) && $this->isPast($step)) {
                $this->setValue('form_step', $step);
            }
            return true;
        }
        return false;
    }

    public function needsJsonResponse(): bool
    {
        return $this->request->wantsJson() || $this->request->isXmlHttpRequest();
    }

    public function usesViews(): bool
    {
        return is_string($this->view);
    }

    public function isActive(int $step, $active = true, $fallback = false)
    {
        if ($this->isStep($step)) {
            return $active;
        }
        return $fallback;
    }

    public function isFuture(int $step, $active = true, $fallback = false)
    {
        if ($this->steps->has($step) && $this->currentStep() < $step) {
            return $active;
        }
        return $fallback;
    }

    public function isPast(int $step, $active = true, $fallback = false)
    {
        if ($this->steps->has($step) && $this->currentStep() > $step) {
            return $active;
        }
        return $fallback;
    }

    public function toArray(): array
    {
        return $this->session->get($this->namespace, []);
    }

    public function toCollection(): Collection
    {
        return Collection::make($this->toArray());
    }

    protected function setupSession(): void
    {
        if (!is_numeric($this->getValue('form_step', false))) {
            $this->setValue('form_step', 1);
        }
    }

    public function namespaced(string $namespace): self
    {
        $this->namespace = $namespace;

        return $this;
    }

    public function tap($closure): self
    {
        call_user_func($closure, $this);

        return $this;
    }


    public function beforeStep(int|string $step, Closure $closure): self
    {
        $this->before->put($step, $closure);

        return $this;
    }

    public function onStep(int|string $step, Closure $closure): self
    {
        $this->after->put($step, $closure);

        return $this;
    }

    public function addStep(int $step, array $config = []): self
    {
        $this->steps->put($step, $config);

        return $this;
    }

    public function currentStep(): int
    {
        return (int)$this->session->get("{$this->namespace}.form_step", 1);
    }

    public function requestedStep(): int
    {
        return (int)$this->request->get("form_step", 1);
    }

    public function stepConfig(?int $step = null): Collection
    {
        return Collection::make($this->steps->get($step ?? $this->currentStep()));
    }

    public function isStep(int $step = 1): bool
    {
        return $this->currentStep() === $step;
    }

    public function getValue(string $key, $fallback = null)
    {
        return $this->session->get("{$this->namespace}.$key", $this->session->getOldInput($key, $fallback));
    }

    public function setValue(string $key, $value): self
    {
        $this->session->put("{$this->namespace}.$key", $value);

        return $this;
    }

    protected function nextStep(): self
    {
        if (!$this->wasReset && !$this->isStep($this->lastStep())) {
            $this->setValue('form_step', 1 + $this->requestedStep());
        }

        return $this;
    }

    public function lastStep(): int
    {
        return $this->steps->keys()->filter(fn($value) => is_int($value))->max() ?: 1;
    }

    public function isLastStep(): bool
    {
        return $this->isStep($this->lastStep());
    }

    public function beforeSave(Closure $callback): self
    {
        $this->beforeSaveCallback = $callback;

        return $this;
    }

    protected function handleSave(array $data = []): self
    {
        if (is_callable($this->beforeSaveCallback)) {
            $data = call_user_func($this->beforeSaveCallback, $data);
        }

        $this->save($data);

        return $this;
    }

    public function save(array $data = []): self
    {
        $this->session->put($this->namespace, array_merge(
            $this->session->get($this->namespace, []), $data
        ));

        return $this;
    }

    public function reset(array $data = []): self
    {
        $this->session->put($this->namespace, array_merge($data, ['form_step' => 1]));
        $this->wasReset = true;

        return $this;
    }

    protected function handleBefore(int|string $key)
    {
        if ($callback = $this->before->get($key)) {
            return $callback($this);
        }
    }

    protected function handleAfter(int|string $key)
    {
        if ($callback = $this->after->get($key)) {
            return $callback($this);
        }
    }
}