# Laravel MultiStep Forms

![](https://github.com/bayareawebpro/laravel-multistep-forms/workflows/ci/badge.svg)
![](https://codecov.io/gh/bayareawebpro/laravel-multistep-forms/branch/master/graph/badge.svg)
![](https://img.shields.io/github/v/release/bayareawebpro/laravel-multistep-forms.svg)
![](https://img.shields.io/packagist/dt/bayareawebpro/laravel-multistep-forms.svg)
![](https://img.shields.io/badge/License-MIT-success.svg)

> https://packagist.org/packages/bayareawebpro/laravel-multistep-forms

Multi-step Form Builder is a "responsable" (https://laravel-news.com/laravel-5-5-responsable) class that can be returned from 
controllers.  You can specify a view in the "make" method or it will return json.
You can submit to the same route multiple times and it will merge each request into a 
namespace session key.  You can then hook into each step to perform an action after validation.

## Installation

```shell script
composer require bayareawebpro/laravel-multistep-forms
```

### Example Usage

```php
<?php

Route::any('my-form', function(){

    // Render a view with data.
    return MultiStepForm::make('form', [
            'title' => 'MultiStep Form'
        ])

        // Namespace the session data.
        ->namespaced('my-session-key')

        // Before x step validation...
        ->beforeStep('*', function (MultiStepForm $form) {
           logger('form', $form->toArray());
        })

        // After x step...
        ->onStep('*', function (MultiStepForm $form) {
           logger('form', $form->toArray());
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
           'data' => ['message' => "Great Job!"]
        ])

        // Tap Invokable Class __invoke(MultiStepForm $form)
        ->tap(new InvokableClass)

        // After step validation...
        ->onStep(3, function (MultiStepForm $form) {
           if($form->request->get('submit') === 'reset'){
                $form->reset();
           }else{
               return response('OK');
           }
        });
})->name('submit');
```

#### Methods

#### `make(string $viewName, $data = [])`

Make a new instance of the builder class with optional view and data array.  

> Note data can be defined per-step as well.

#### `addStep(int $step, array $config = [])`

Define the rules, messages and data for the step.

```php
<?php

MultiStepForm::make()
    ->addStep(1, [
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
;
```

#### `onStep($step, \Closure $closure)`

Define a callback to fired **after** a step has been validated.  Step Number or * for all.

#### `beforeStep($step, \Closure $closure)`

Define a callback to fired **before** a step has been validated.  Step Number or * for all.

#### `currentStep()`

Get the current step number.

#### `isStep(int $step = 1)`

Get the current step number.

#### `isLastStep()`

Get the last step (highest) integer.

#### `stepConfig(?int $step = null)`

Get the current step config, or a specific step config.

#### `getValue(string $key, $fallback = null)`

Get a field value from the session form state.

#### `setValue(string $key, $value)`

Set a field value from the session form state.

#### `toArray`

Get the array representation of the form state.

#### `toCollection`

Get the array representation of the form state as a collection.

#### `reset($data = [])`

Reset the form state to defaults passing an optional array of data to seed.


#### `tap(new Invokable)`

Tap into the builder instance with invokeable classes that will be pass an 
instance of the form allowing you to extract logic for reusability.

--- 
### Example View

```blade

<form method="post" action="{{ route('submit') }}">
    <input type="hidden" name="form_step" value="{{ $form->currentStep() }}">
    @csrf
    
    @switch($form->currentStep())
    
        @case(1)
            <label>Name</label>
            <input type="text" name="name" value="{{ $form->getValue('name', old('name')) }}">
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
    
    @if($form->isStep($form->lastStep()))
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