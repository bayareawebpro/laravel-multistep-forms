<?php declare(strict_types=1);

namespace BayAreaWebPro\MultiStepForms;

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
    static string $namespace = 'multistep-form';

    public $callbacks;
    public $request;
    public $session;
    public $steps;
    public $data;
    public $view;

    public function __construct(
        Request $request,
        Session $session,
        $data = [],
        $view = null
    ){
        $this->callbacks = new Collection;
        $this->steps = new Collection;
        $this->request = $request;
        $this->session = $session;
        $this->view = $view;
        $this->data = $data;
    }

    public static function make($view = null, array $data = []): self
    {
        return app(static::class, [
            'view' => $view,
            'data' => $data,
        ]);
    }

    public function namespaced(string $namespace): self
    {
        static::$namespace = $namespace;
        return $this;
    }

    public function toResponse($request = null)
    {
        $this->request = $request ?? $this->request;
        if($this->request->isMethod('GET')) {
            return $this->renderRequest();
        }
        return $this->handleRequest();
    }

    protected function renderRequest()
    {
        if(is_string($this->view) && !$this->request->wantsJson()){
            return View::make($this->view, array_merge($this->data, ['form' => $this]));
        }
        return new Response($this->toArray());
    }

    protected function handleRequest()
    {
        $this->validate();
        $this->nextStep();
        if ($response = $this->handleCallback('*')) {
            return $response;
        }
        if ($response = $this->handleCallback($this->currentStep())) {
            return $response;
        }
        if (!$this->request->wantsJson()) {
            return redirect()->back();
        }
        return new Response($this->toArray());
    }

    public function toArray(): array
    {
        return $this->session->get(static::$namespace, []);
    }

    public function toCollection(): Collection
    {
        return Collection::make($this->toArray());
    }

    public function addStep(int $step, array $config = []): self
    {
        $this->steps->put($step, $config);
        return $this;
    }

    public function onStep($step, \Closure $closure): self
    {
        $this->callbacks->put($step, $closure);
        return $this;
    }

    public function reset($data = []): self
    {
        $this->session->put(static::$namespace, array_merge($data, [
            'form_step' => 1
        ]));
        return $this;
    }

    public function currentStep(): int
    {
        return (int)$this->request->get('form_step',
            $this->session->get(static::$namespace . ".form_step", 1)
        );
    }

    public function stepConfig(int $step = 1): Collection
    {
        return Collection::make($this->steps->get($step));
    }

    public function isStep(int $step = 1): bool
    {
        return $this->currentStep() === $step;
    }

    public function getValue(string $key, $fallback = null)
    {
        return $this->session->get(static::$namespace . ".$key", $fallback);
    }

    public function setValue(string $key, $value): self
    {
        $this->session->put(static::$namespace . ".$key", $value);
        return $this;
    }

    protected function nextStep(): self
    {
        if (!$this->isStep($this->steps->count())) {
            $this->session->increment(static::$namespace . '.form_step');
        }
        return $this;
    }

    protected function save(array $data): self
    {
        $this->session->put(static::$namespace, array_merge(
            $this->session->get(static::$namespace, []), $data,
            ['form_step' => $this->currentStep()]
        ));
        $this->session->save();
        return $this;
    }

    protected function handleCallback($key)
    {
        if ($callback = $this->callbacks->get($key)) {
            return $callback($this);
        }
    }

    protected function validate(): self
    {
        $step = $this->stepConfig($this->currentStep());
        $this->save($this->request->validate(
            array_merge($step->get('rules', []), [
                'form_step' => ['required', 'numeric', Rule::in(range(1, $this->steps->count()))],
            ]),
            $step->get('messages', [])
        ));
        return $this;
    }
}
