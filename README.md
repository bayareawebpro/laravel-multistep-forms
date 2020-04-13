# Laravel Simple CSV

![](https://github.com/bayareawebpro/laravel-multistep-forms/workflows/ci/badge.svg)
![](https://img.shields.io/badge/License-MIT-success.svg)
![](https://img.shields.io/packagist/dt/bayareawebpro/laravel-multistep-forms.svg)
![](https://img.shields.io/github/v/release/bayareawebpro/laravel-multistep-forms.svg)

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
            'rules' => ['name' => 'required']
        ])
        // Validate Step 2
        ->addStep(2, [
            'rules' => ['role' => 'required']
        ])
        // Add non-validated step...
        ->addStep(3)->onStep(3, function (MultiStepForm $form) {
           if($form->request->get('submit') === 'reset'){
                $form->reset();
           }else{
               return response('OK');
           }
        });
})->name('submit');
```

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