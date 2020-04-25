# Laravel MultiStep Forms

![](https://github.com/bayareawebpro/laravel-multistep-forms/workflows/ci/badge.svg)
![](https://codecov.io/gh/bayareawebpro/laravel-multistep-forms/branch/master/graph/badge.svg)
![](https://img.shields.io/github/v/release/bayareawebpro/laravel-multistep-forms.svg)
![](https://img.shields.io/packagist/dt/bayareawebpro/laravel-multistep-forms.svg)
![](https://img.shields.io/badge/License-MIT-success.svg)

> https://packagist.org/packages/bayareawebpro/laravel-multistep-forms

Multi-step Form Builder is a "responsable" class that can be returned from 
controllers.  You can specify a view in the "make" method or it will return json.
You can submit to the same route multiple times and it will merge each request into a 
namespace session key.  You can then hook into each step to perform an action after validation.

## Installation
```
composer require bayareawebpro/laravel-multistep-forms
```

### Example Usage

```php
Route::any('my-form', function(){
    // Render a view with data.
    return MultiStepForm::make('form', [
            'title' => 'MultiStep Form'
        ])
        // Namespace the session data.
        ->namespaced('my-session-key')
        // After every step...
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
            'rules' => ['role' => 'required']
        ])
        // Add non-validated step...
        ->addStep(3)
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

### Methods
- ```toArray```
- ```toCollection```
- ```addStep(int $step, array $config = [])``` //Rules, Messages & Supporting Data
- ```onStep($step, \Closure $closure)``` // Step Number or * for all.
- ```currentStep``` Step value
- ```isStep(int $step = 1)``` Conditional
- ```stepConfig(int $step = 1)``` Get step configuration
- ```getValue(string $key, $fallback = null)``` Get form data
- ```setValue(string $key, $value)``` Set form data
- ```reset($data = [])``` //Form State

### Example View
```blade
<h1>{{ $title }}</h1>
<form method="post" action="{{ route('submit') }}">
    @csrf
    <input
        type="hidden"
        name="form_step"
        value="{{ $form->currentStep() }}">

    @switch($form->currentStep())
        @case(1)
        <label>Name</label>
        <input
            type="text"
            name="name"
            value="{{ $form->getValue('name') }}">
            {{ $errors->first('name') }}
        @break
        @case(2)
        <label>Role</label>
        <input
            type="text"
            name="role"
            value="{{ $form->getValue('role') }}">
            {{ $errors->first('role') }}
        @break
        @case(3)
            Name: {{ $form->getValue('name') }}<br>
            Role: {{ $form->getValue('role') }}<br>
        @break
    @endswitch

    @if($form->isStep(3))
   <button type="submit" name="submit">Save</button>
   <button type="submit" name="submit" value="reset">Reset</button>
   @else
   <button type="submit">Continue</button>
   @endif
   <hr>
   {{ $form->toCollection() }}
</form>
```

### Vue Example
```html
<div id="app">
    <v-form action="{{ route('submit') }}">
        <template v-slot:default="{form, errors, reset}">
            <template v-if="form.form_step === 1">
                <input v-model="form.name" placeholder="name">
                <div v-if="errors.name">@{{ errors.name[0] }}</div>
            </template>
            <template v-if="form.form_step === 2">
                <input v-model="form.role" placeholder="role">
                <div v-if="errors.role">@{{ errors.role[0] }}</div>
            </template>
            <template v-if="form.form_step === 3">
                Name: @{{ form.name }}<br>
                Role: @{{ form.role }}<br>
            </template>
            <template v-if="form.form_step === 3">
                <template v-if="form.message">
                   @{{ form.message }}
                </template>
                <template v-else>
                    <button type="submit">Save</button>
                    <button type="button" @click="reset">Reset</button>
                </template>
            </template>
            <button v-else type="submit">Continue</button>
            <br><code v-text="form"></code>
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
                    form: {form_step: 1}
                }),
                template: `<form @submit.prevent="submit"><slot :reset="reset" :form="form" :errors="errors"></slot></form>`,
                methods:{
                    reset(){
                        this.form.submit = 'reset'
                        this.submit()
                    },
                    submit(){
                        axios
                            .post(this.action, this.form)
                            .then(({data})=>this.form = data)
                            .catch(({response})=>this.errors=response.data.errors)
                    }
                },
                created(){
                    axios
                        .get(this.action)
                        .then(({data})=>this.form = {...this.form,...data})
                        .catch(({response})=>this.errors=response.data.errors)
                }
            },
        }
    })
</script>
```