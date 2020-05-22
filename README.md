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
* Hook into each step **before** or **after** validation to interact with the form or return a reponse.

## Installation

```shell script
composer require bayareawebpro/laravel-multistep-forms
```

### Example Usage

```php
<?php

use BayAreaWebPro\MultiStepForms\MultiStepForm as Form;

// Render a view with data.
return Form::make('my-form', [
        'title' => 'MultiStep Form'
    ])

    // Namespace the session data.
    ->namespaced('my-session-key')

    // Before x step validation...
    ->beforeStep('*', function (Form $form) {
       logger('before*', $form->toArray());
    })

    // After x step...
    ->onStep('*', function (Form $form) {
       logger('on*', $form->toArray());
    })

    // Validate Step 1
    ->addStep(1, [
        'rules' => ['name' => 'required'],
        'messages' => ['name.required' => 'Your name is required silly.'],
    ])

    // Validate Step 2
    ->addStep(2, [
        'rules' => ['role' => 'required|string'],
        'data' => ['roles' => Roles::forSelection()]
    ])

    // Add non-validated step...
    ->addStep(3,[
       'data' => ['message' => "Great Job, Your Done!"]
    ])

    // Tap Invokable Class __invoke(Form $form)
    ->tap(new InvokableClass)

    // After step validation...
    ->onStep(3, function (Form $form) {
       logger('onStep3', $form->toArray());
       if($form->request->get('submit') === 'reset'){
            $form->reset();
       }else{
           return response('OK');
       }
    })
;
```

---

### Make New Instance

Make a new instance of the builder class with optional view and data array.  You 
should always set the `namespace` for the form session to avoid conflicts with 
other parts of your application that use the session store. 

* `GET` requests will load the form state and data for the saved current step or fallback to step 1.
* `POST`,`PUT`,`PATCH` etc.. will validate and process the request for any step and proceed to the next configured step.

```php
<?php

use BayAreaWebPro\MultiStepForms\MultiStepForm as Form;

$form = Form::make('onboarding.start', [
	'title' => 'Setup your account'
]);

$form->namespaced('onboarding');
```

---

### Configure Steps

Define the rules, messages and data for the step. Data will be merged 
with any view data defined in the `make` method.

**Use an array**: 

```php
$form->addStep(1, [
    'rules' => [
        'name' => 'required|string'
    ],
    'messages' => [
        'name.required' => 'Your name is required silly.'
    ],
    'data' => [
        'placeholders' => [
            'name' => 'Enter your name.'
        ]
    ],
])
```

**Or use a class** that returns an array (recommended)

```php
$form->addStep(1, MyStep1::make());
```

---

### Before Step Hooks

Define a callback to fired **before** a step has been validated.  Step Number or * for all.

> Return a response from this hook to return early before validation occurs.

 `beforeStep($step, Closure $closure)`

---

### On Step Hooks

Define a callback to fired **after** a step has been validated.  Step Number or * for all.

> Return a response from this hook to return early before validation occours.

`onStep($step, Closure $closure)`

---

### Helper Methods

#### `stepConfig(?int $step = null)`

Get the current step config, or a specific step config.

#### `getValue(string $key, $fallback = null)`

Get a field value from the form state (session / old input) or fallback to a default.

#### `setValue(string $key, $value)`

Set a field value from the session form state.

#### `currentStep()`

Get the current step number.

#### `isStep(int $step = 1)`

Get the current step number.

#### `isLastStep()`

Determine if the current step the last step.

#### `isPast(int $step, $truthy = true, $falsy = false)`

Determine if the current step in the past and optionally pass through values (class helper).

#### `isActive(int $step, $truthy = true, $falsy = false)`

Determine if the current step is active and optionally pass through values (class helper).

#### `isFuture(int $step, $truthy = true, $falsy = false)`

Determine if the current step in the future and optionally pass through values (class helper).

#### `reset($data = [])`

Reset the form state to defaults passing an optional array of data to seed.

#### `tap(new Invokable)`

Tap into the builder instance with invokeable classes that will be pass an instance of the form.

#### `toCollection`

Get the array representation of the form state as a collection.

#### `toArray`

Get the array representation of the form state.

--- 

### Blade Example

Data will be injected into the view as well as the form itself allowing you to access the form values and other helper methods.

```php
<?php
use BayAreaWebPro\MultiStepForms\MultiStepForm as Form;

$form = Form::make('my-view', $data)->namespaced('onboarding');
```

```blade
{{ $form->toCollection() }}
{{ $myDataKey }}
```

```blade

<form method="post" action="{{ route('submit') }}">
    <input type="hidden" name="form_step" value="{{ $form->currentStep() }}">
    @csrf


    @if($form->isFuture(4))
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
    @endif

    
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
        <button type="submit" name="submit" value="reset">Reset</button>
    @else
        <button type="submit" name="submit">Continue</button>
    @endif

    <hr>

    {{ $form->toCollection() }}
</form>
```

### Vue Example

Form state and data will be returned as JSON when no view is 
specified or the request prefers JSON.  You can combine both 
techniques to use Vue within blade as well.

```php
<?php

use BayAreaWebPro\MultiStepForms\MultiStepForm as Form;

$form = Form::make()->namespaced('my-session-key');
```

#### JSON Response Schema

The response returned will have two properties: 

```json
{
	"form": {},
	"data": {}
}
```

```html
<div id="app">
    <v-form action="{{ route('submit') }}">
        <template v-slot:default="{form, options, errors, reset}">
            <template v-if="form.form_step === 1">
                <label>Name</label>
                <input v-model="form.name" placeholder="name">
                <div v-if="errors.name">@{{ errors.name[0] }}</div>

                <label>Role</label>
                <select v-model="form.role">
                    <option disabled value="">Please select one</option>
                    <option v-for="option in options.roles" :value="option">@{{ option }}</option>
                </select>
                <div v-if="errors.role">@{{ errors.role[0] }}</div>
            </template>
            <template v-if="form.form_step === 2">
                <label>Email</label>
                <input v-model="form.email" placeholder="email">
                <div v-if="errors.email">@{{ errors.email[0] }}</div>

                <label>Phone</label>
                <input v-model="form.phone" placeholder="phone">
                <div v-if="errors.phone">@{{ errors.phone[0] }}</div>
            </template>
            <template v-if="form.form_step === 3">
                <p>
                    Name: @{{ form.name }}<br>
                    Role: @{{ form.role }}<br>
                    Email: @{{ form.email }}<br>
                    Phone: @{{ form.phone }}<br>
                </p>
                <p v-if="options.message">
                   @{{ form.message }}
                </p>
                <button type="submit">Save</button>
            </template>
            <button v-else type="submit">Continue</button>
            <br><pre><code v-text="form"></code></pre>
        </template>
    </v-form>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/axios/0.19.2/axios.min.js" integrity="sha256-T/f7Sju1ZfNNfBh7skWn0idlCBcI3RwdLSS4/I7NQKQ=" crossorigin="anonymous"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/vue/2.6.11/vue.min.js" integrity="sha256-ngFW3UnAN0Tnm76mDuu7uUtYEcG3G5H1+zioJw3t+68=" crossorigin="anonymous"></script>
<script>
    new Vue({
        el: '#app',
        components:{
            'v-form': {
                props: ['action'],
                data: ()=>({
                    errors:{},
                    options: {},
                    form: {form_step: 1},
                }),
                template: `<form @submit.prevent="submit"><slot :reset="reset" :form="form" :options="options" :errors="errors"/></form>`,
                methods:{
                    reset(){
                        this.form.submit = 'reset'
                        this.submit()
                    },
                    fetch(){
                        axios
                            .get(this.action)
                            .then(this.onResponse)
                            .catch(this.onError)
                    },
                    submit(){
                        axios
                            .post(this.action, this.form)
                            .then(this.onResponse)
                            .catch(this.onError)
                    },
                    onError({response}){
                        this.errors=response.data.errors
                    },
                    onResponse({data}){
                        this.form = (data.form || {})
                        this.options = (data.data || {})
                    },
                },
                created(){
                    this.fetch()
                }
            },
        }
    })
</script>
```