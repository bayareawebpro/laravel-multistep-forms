# Laravel MultiStep Forms

![](https://github.com/bayareawebpro/laravel-multistep-forms/workflows/ci/badge.svg)
![](https://codecov.io/gh/bayareawebpro/laravel-multistep-forms/branch/master/graph/badge.svg)
![](https://img.shields.io/github/v/release/bayareawebpro/laravel-multistep-forms.svg)
![](https://img.shields.io/packagist/dt/bayareawebpro/laravel-multistep-forms.svg)
![](https://img.shields.io/badge/License-MIT-success.svg)

> https://packagist.org/packages/bayareawebpro/laravel-multistep-forms

Multistep Form Builder is a "[responsable](https://laravel-news.com/laravel-5-5-responsable)" class that can be returned from controllers.

* Specify a view to use Blade or go headless with JSON for use with Javascript frameworks.
* Configure the rules, messages and supporting data for each step with simple arrays.
* Submit to the same route multiple times to merge each validated request into a namespaced session key.
* Hook into each step **before** or **after** validation to interact with the form or return a response.

## Installation

```shell script
composer require bayareawebpro/laravel-multistep-forms
```

### Example Usage

```php
<?php

use BayAreaWebPro\MultiStepForms\MultiStepForm;

// Render a view with data.
return Form::make('my-form', [
        'title' => 'MultiStep Form'
    ])

    // Namespace the session data.
    ->namespaced('my-session-key')

    // Allow backwards navigation via get request. ?form_step=x
    ->canNavigateBack(true)

    // Tap invokable Class __invoke(Form $form)
    ->tap(new InvokableClass)

    // Before x step validation...
    ->beforeStep(1, function (MultiStepForm $form) {
        // Maybe return early or redirect?
    })
    // Before all step validation...
    ->beforeStep('*', function (MultiStepForm $form) {
        // Maybe return early or redirect?
    })

    // Validate Step 1
    ->addStep(1, [
        'rules' => ['name' => 'required'],
        'messages' => ['name.required' => 'Your name is required.'],
    ])

    // Validate Step 2
    ->addStep(2, [
        'rules' => ['role'  => 'required|string'],
        'data'  => ['roles' => fn()=>Role::forSelection()] // Lazy Loaded Closure
    ])

    // Add non-validated step...
    ->addStep(3,[
       'data' => ['message' => "Great Job, Your Done!"]
    ])

    // After step validation...
    ->onStep(3, function (MultiStepForm $form) {
        // Specific step, logic if needed.
    })
    ->onStep('*', function (MultiStepForm $form) {
        // All steps, logic if needed.
    })
   
    // Modify data before saved to session after each step.
    ->beforeSave(function(array $data) {
    
        // Transform non-serializable objects to paths, array data etc...
        return $data;
    })
   
    // Modify data before saved to session after each step.
    ->onComplete(function(MultiStepForm $form) {
    
        // Final submission logic.
    })
;
```

---

### Make New Instance

Make a new instance of the builder class with optional view and data array. You
should always set the `namespace` for the form session to avoid conflicts with
other parts of your application that use the session store.

* `GET` requests will load the form state and data for the saved current step or fallback to step 1.
* `POST`,`PUT`,`PATCH` etc... will validate and process the request for any step and proceed to the next configured step.
* `DELETE` will reset the session state and redirect back (blade), or return a `JsonResponse`.
* Backwards navigation (via get param) can be enabled via the `canNavigateBack` method.

```php
<?php

use BayAreaWebPro\MultiStepForms\MultiStepForm;

$form = MultiStepForm::make('onboarding.start', [
    'title' => 'Setup your account'
]);

$form->namespaced('onboarding');
$form->canNavigateBack(true);
```

---

### Configure Steps

Define the rules, messages and data for the step. Data will be merged
with any view data defined in the `make` method and be included in the `JsonResponse`.

** Use a `Closure` to lazy load data per-key.

**Use an array**:

```php
$form->addStep(2, [
    'rules' => [
        'role' => 'required|string'
    ],
    'messages' => [
        'role.required' => 'Your name is required.'
    ],
    'data' => [
        'roles' => fn() => Role::query()...,
    ],
])
```

**Or use an invokable class** (recommended)

```php
use BayAreaWebPro\MultiStepForms\MultiStepForm;

class ProfileStep
{
    public function __construct(private int $step)
    {
        //
    }
    
    public function __invoke(MultiStepForm $form) 
    {
        $form->addStep($this->step, [
            'rules' => [
                'name' => 'required|string'
            ],
            'messages' => [
                'name.required' => 'Your name is required.'
            ],
            'data' => [
                'placeholders' => [
                    'name' => 'Enter your name.'
                ]
            ],
        ]);
    }
}
```

```php
$form->tap(new ProfileStep(1));
```

---

### BeforeStep / OnStep Hooks

Define a callback to fired **before** a step has been validated. Step Number or * for all.

- Use a step integer, or asterisk (*) for all steps.
- You can return a response from these hooks.

```php
$form->beforeStep('*', function(MultiStepForm $form){
    //
});
$form->onStep('*', function(MultiStepForm $form){
    //
});
$form->onComplete(function(MultiStepForm $form){
    //
});
```

### Handle UploadedFiles

Specify a callback used to transform UploadedFiles into paths.

```php
use Illuminate\Http\UploadedFile;

$form->beforeSave(function(array $data){
    if($data['avatar'] instanceof UploadedFile){
        $data['avatar'] = $data['avatar']->store('avatars');
    }
    return $data;
});
```

### Reset / Clear Form

- Ajax: Submit a DELETE request to the form route.
- Blade: Use an additional submit button that passes a boolean (truthy) value.

```
<button type="submit" name="reset" value="1">Reset</button>
```

### JSON Response Schema

The response returned will have two properties:

```json
{
  "form": {
    "form_step": 1
  },
  "data": {}
}
```

### Public Helper Methods


#### stepConfig
Get the current step configuration (default), or pass an integer for a specific step:
```php
$form->stepConfig(2): Collection
```

#### getValue
Get a field value (session / old input) or fallback:

```php
$form->getValue('name', 'John Doe'): mixed
```

#### setValue
Set a field value and store in the session:
```php
$form->setValue('name', 'Jane Doe'): MultiStepForm
```

#### save
Merge and save key/values array directly to the session (does not fire `beforeSaveCallback`):

```php
$form->save(['name' => 'Jane Doe']): MultiStepForm
```

#### reset

Reset the form state to defaults passing an optional array of data to seed.

```php
$form->reset(['name' => 'Jane Doe']): MultiStepForm
```

#### withData
Add additional non-form data to all views and responses:

```php
$form->withData(['date' => now()->toDateString()]);
```

#### currentStep
Get the current saved step number:

```php
$form->currentStep(): int
```

#### requestedStep
Get the incoming client-requested step number:

```php
$form->requestedStep(): int
```

#### isStep
Is the current step the provided step:

```php
$form->isStep(3): bool
```

#### prevStepUrl
Get the previous step url.

```php
$form->prevStepUrl(): string|null
```

#### lastStep
Get the last step number:

```php
$form->lastStep(): int
```

#### isLastStep
Is the current step the last step:

```php
$form->isLastStep(): bool
```

#### isPast,isActive,isFuture

```php
// Boolean Usage
$form->isPast(2): bool
$form->isActive(2): bool
$form->isFuture(2): bool

// Usage as HTML Class Helpers
$form->isPast(2, 'truthy-class', 'falsy-class'): string
$form->isActive(2, 'truthy-class', 'falsy-class'): string
$form->isFuture(2, 'truthy-class', 'falsy-class'): string
```

--- 

### Blade Example

Data will be injected into the view as well as the form itself allowing you to access the form values and other helper methods.

```php
<?php
use BayAreaWebPro\MultiStepForms\MultiStepForm as Form;

$form = Form::make('my-view', $data);
$form->namespaced('onboarding');
$form->canNavigateBack(true);
```

```blade
<form method="post" action="{{ route('submit') }}">
    <input type="hidden" name="form_step" value="{{ $form->currentStep() }}">
    @csrf
    <a
        href="{{ route('submit', ['form_step' => 1]) }}"
        class="{{ $form->isPast(1, 'text-blue-500', $form->isActive(1, 'font-bold', 'disabled')) }}">
        Step 1
    </a>
    <a
        href="{{ route('submit', ['form_step' => 2]) }}"
        class="{{ $form->isPast(2, 'text-blue-500', $form->isActive(2, 'font-bold', 'disabled')) }}">
        Step 2
    </a>
    <a
        href="{{ route('submit', ['form_step' => 3]) }}"
        class="{{ $form->isPast(3, 'text-blue-500', $form->isActive(3, 'font-bold', 'disabled')) }}">
        Step 3
    </a>

    
    @switch($form->currentStep())
    
        @case(1)
            <label>Name</label>
            <input type="text" name="name" value="{{ $form->getValue('name') }}">
            @error('name') 
                <p>{{ $errors->first('name') }}</p>
            @enderror
        @break
    
        @case(2)
            <label>Role</label>
            <input type="text" name="role" value="{{ $form->getValue('role') }}">
             @error('role') 
                <p>{{ $errors->first('role') }}</p>
            @enderror
        @break
    
        @case(3)
            <p>Review your submission:</p>
            <p>
             Name: {{ $form->getValue('name') }}<br>
             Role: {{ $form->getValue('role') }}<br>
            </p>
        @break
    
    @endswitch
    
    @if($form->isLastStep())
        <button type="submit" name="submit">Save</button>
        <button type="submit" name="reset" value="1">Reset</button>
    @else
        <button type="submit" name="submit">Continue</button>
    @endif

</form>
```

### Vue Example

Form state and data will be returned as JSON when no view is
specified or the request prefers JSON. You can combine both
techniques to use Vue within blade as well.

```html

<v-form action="{{ route('submit') }}">
    <template v-slot:default="{form, options, errors, reset, back}">

        <h1 class="font-black my-3">
            @{{ options.title }}
        </h1>

        <p v-if="options.message" role="alert" class="bg-gray-200 p-4 my-5 font-bold text-blue-500">
            @{{ options.message }}
        </p>

        <template v-if="form.form_step < 4">
            <a
                @click="back(1)"
                :class="{'text-blue-500': form.form_step > 1, 'font-bold': form.form_step === 1}">
                Step 1
            </a>
            <a
                @click="back(2)"
                :class="{'text-blue-500': form.form_step > 2, 'font-bold': form.form_step === 2}">
                Step 2
            </a>
            <a
                @click="back(3)"
                :class="{'text-blue-500': form.form_step > 3, 'font-bold': form.form_step === 3}">
                Step 3
            </a>
        </template>

        <template v-if="form.form_step === 1">

            <v-input
                name="name"
                label="Name"
                :errors="errors"
                v-model="form.name">
            </v-input>

            <v-select
                name="name"
                label="Name"
                :errors="errors"
                :options="options.roles"
                v-model="form.role">
            </v-select>

            <x-action>Continue</x-action>
        </template>

        <template v-if="form.form_step === 2">
            <v-input
                name="email"
                label="Email"
                :errors="errors"
                v-model="form.email">
            </v-input>
            <v-input
                name="phone"
                label="Phone"
                :errors="errors"
                v-model="form.phone">
            </v-input>
            <x-action>Continue</x-action>
        </template>

        <template v-if="form.form_step === 3">
            <v-input
                name="bio"
                label="Bio"
                :errors="errors"
                v-model="form.bio">
            </v-input>
            <v-input
                name="notify"
                label="Notify"
                :errors="errors"
                v-model="form.notify">
            </v-input>
            <x-action>Continue</x-action>
        </template>

        <template v-if="form.form_step === 4">
            <h3>Review Submission</h3>
            <p>
                Name: @{{ form.name }}<br>
                Role: @{{ form.role }}<br>
                Email: @{{ form.email }}<br>
                Phone: @{{ form.phone }}<br>
            </p>
            <x-action>Save</x-action>
            <x-action @click="reset">Reset</x-action>
        </template>

        <template v-if="form.form_step === 5">
            <x-action>Done</x-action>
        </template>

    </template>
</v-form>
```

#### Example Form Component

```vue

<script>
  export default {
    name: 'Form',
    props: ['action'],
    data: () => ({
      errors: {},
      options: {},
      form: {form_step: 1},
    }),
    methods: {
      reset() {
        this.form.reset = 1
        this.submit()
      },
      back(step) {
        if (step < this.form.form_step) {
          this.fetch({form_step: step})
        }
      },
      fetch(params = {}) {
        axios
            .get(this.action, {params})
            .then(this.onResponse)
            .catch(this.onError)
      },
      submit() {
        axios
            .post(this.action, this.form)
            .then(this.onResponse)
            .catch(this.onError)
      },
      onError({response}) {
        this.errors = (response.data.errors || response.data.exception)
      },
      onResponse({data}) {
        this.errors = {}
        this.options = (data.data || {})
        this.form = (data.form || {})
      },
    },
    created() {
      this.fetch()
    }
  }
</script>
<template>
  <form @submit.prevent="submit">
    <slot :reset="reset" :back="back" :form="form" :options="options" :errors="errors"/>
  </form>
</template>
```

#### Example Input Component

```vue
<script>
  export default {
    name: "Input",
    props: ['name', 'label', 'value', 'errors'],
    computed: {
      field: {
        get() {
          return this.value
        },
        set(val) {
          return this.$emit('input', val)
        }
      }
    }
  }
</script>
<template>
  <label class="block my-4">
        <span class="text-gray-700 font-bold">
            {{ label || name }}
        </span>
    <input
        type="text"
        v-model="field"
        class="form-input block w-full mt-2">
    <div v-if="errors[name]" class="text-red-500 text-xs my-2">
      {{ errors[name][0] }}
    </div>
  </label>
</template>
```

#### Example Select Component

```vue
<script>
  export default {
    name: "Select",
    props: ['name', 'label', 'value', 'errors', 'options'],
    computed: {
      field: {
        get() {
          return this.value
        },
        set(val) {
          return this.$emit('input', val)
        }
      }
    }
  }
</script>
<template>
  <label class="block">
    <span class="text-gray-700">{{ label || name }}</span>
    <select v-model="field" class="form-select mt-1 block w-full">
      <option disabled value="">Please select one</option>
      <option v-for="option in options" :value="option">
        {{ option }}
      </option>
    </select>
    <div v-if="errors[name]" class="text-red-500 text-xs my-2">
      {{ errors[name][0] }}
    </div>
  </label>
</template>
```
