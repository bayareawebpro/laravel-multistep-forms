<?php declare(strict_types=1);

namespace BayAreaWebPro\MultiStepForms;

use Closure;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

use Illuminate\Http\Response;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\Rule;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\View;
use Illuminate\Session\Store as Session;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Responsable;

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
    protected ?Closure $completeCallback = null;

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

    public function withData(array $data = []): self
    {
        $this->data = array_merge($this->data, $data);
        return $this;
    }

    protected function validate(): array
    {
        $step = $this->stepConfig($this->requestedStep());

        return $this->request->validate(
            array_merge($step->get('rules', []), [
                'form_step' => ['required', 'numeric', Rule::in(range(1, $this->getNextAccessibleStep()))],
            ]),
            $step->get('messages', [])
        );
    }

    protected function getNextAccessibleStep(): int
    {
        if ($this->isFuture($nextStep = $this->currentStep() + 1)) {
            return $nextStep;
        }
        return $this->currentStep();
    }

    protected function handleShow(): Response|JsonResponse
    {
        if ($this->usesViews() && !$this->needsJsonResponse()) {

            return new Response(View::make($this->view, $this->getData([
                'form' => $this,
            ])));
        }

        return $this->getJsonResponse();
    }

    protected function handleDelete(): RedirectResponse|JsonResponse
    {
        $this->reset();

        return $this->getModificationResponse();
    }

    protected function handleModification(): mixed
    {
        $callbackResponse = (
            $this->handleBefore('*') ??
            $this->handleBefore($this->requestedStep())
        );

        if ($callbackResponse) {
            return $callbackResponse;
        }

        if ($this->wasReset) {
            return $this->getModificationResponse();
        }

        $this->handleSave($this->validate());

        $afterResponse = (
            $this->handleAfter('*') ??
            $this->handleAfter($this->currentStep())
        );

        $isLastStep = $this->isLastStep();

        if (!$isLastStep) {
            $this->incrementStep();
        }

        if ($afterResponse) {
            return $afterResponse;
        }

        if ($isLastStep) {
            $completedCallback = $this->handleCompleteCallback();

            $this->reset();

            if ($completedCallback) {
                return $completedCallback;
            }
        }

        return $this->getModificationResponse();
    }

    public function toResponse($request = null): mixed
    {
        $this->request = ($request ?? $this->request);

        $this->setupSession();

        return match (true) {
            $this->isDeleteRequest() => $this->handleDelete(),
            $this->isModificationRequest() => $this->handleModification(),
            $this->isNavigationRequest() => $this->handleNavigation(),
            default => $this->handleShow(),
        };
    }

    protected function getData(array $data = []): array
    {
        return [...$data, ...Collection::make($this->data)
            ->merge($this->stepConfig()->get('data', []))
            ->map(fn($value)=>is_callable($value) ? call_user_func($value, $this) : $value)];
    }

    protected function isShowRequest(): bool
    {
        return $this->request->isMethod('GET');
    }

    protected function isModificationRequest(): bool
    {
        return in_array($this->request->method(), [
            'POST', 'PUT', 'PATCH'
        ]);
    }

    protected function getModificationResponse(): RedirectResponse|JsonResponse
    {
        if ($this->usesViews() && !$this->needsJsonResponse()) {
            return Redirect::back();
        }

        return $this->getJsonResponse();
    }

    protected function isDeleteRequest(): bool
    {
        return $this->request->isMethod('DELETE') || $this->request->boolean('reset');
    }

    protected function getJsonResponse(): JsonResponse
    {
        return new JsonResponse((object)[
            'data' => $this->getData(),
            'form' => $this->toCollection(),
        ]);
    }

    protected function isNavigationRequest(): bool
    {
        return
            $this->isShowRequest() &&
            $this->request->filled('form_step') &&
            $this->requestedStep() !== $this->currentStep();
    }

    protected function handleNavigation(): RedirectResponse|JsonResponse
    {
        if($this->isPreviousStepRequest()){
            $this->setValue('form_step', $this->requestedStep());
        }

        if($this->usesViews() && !$this->needsJsonResponse()){
            return Redirect::back();
        }

        return $this->getJsonResponse();
    }

    protected function isPreviousStepRequest(): bool
    {
        return (
            $this->canGoBack &&
            $this->isPast($this->requestedStep())
        );
    }

    public function canNavigateBack(bool $enabled = true): self
    {
        $this->canGoBack = $enabled;
        return $this;
    }

    protected function needsJsonResponse(): bool
    {
        return $this->request->isJson() || $this->request->wantsJson() || $this->request->expectsJson() || $this->request->isXmlHttpRequest();
    }

    protected function usesViews(): bool
    {
        return is_string($this->view);
    }

    public function isActive(int $step, $active = true, $fallback = false): mixed
    {
        return $this->isStep($step) ? $active : $fallback;
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

    public function getValue(string $key, $fallback = null): mixed
    {
        return $this->session->get("{$this->namespace}.$key", $this->session->getOldInput($key, $fallback));
    }

    public function hasValue(string $key): bool
    {
        return $this->session->has("{$this->namespace}.$key");
    }

    public function setValue(string $key, mixed $value): self
    {
        $this->session->put("{$this->namespace}.$key", $value);

        return $this;
    }

    public function prevStepUrl(): ?string
    {
        if (!$this->canGoBack || !$this->isPast($prevStep = ($this->currentStep() - 1))) {
            return null;
        }
        return url($this->request->fullUrlWithQuery(['form_step' => $prevStep]));
    }

    protected function incrementStep(): self
    {
        if (!$this->isStep($this->lastStep())) {
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

    protected function handleBefore(int|string $key): mixed
    {
        if ($callback = $this->before->get($key)) {
            return call_user_func($callback, $this);
        }
        return null;
    }

    protected function handleAfter(int|string $key): mixed
    {
        if ($callback = $this->after->get($key)) {
            return call_user_func($callback, $this);
        }
        return null;
    }

    public function onComplete(Closure $callback): self
    {
        $this->completeCallback = $callback;
        return $this;
    }

    protected function handleCompleteCallback(): mixed
    {
        if (is_callable($this->completeCallback)) {
            return call_user_func($this->completeCallback, $this);
        }
        return null;
    }

    public function toCollection(): Collection
    {
        return Collection::make($this->session->get($this->namespace, []));
    }

    public function toArray(): array
    {
        return $this->toCollection()->toArray();
    }
}
