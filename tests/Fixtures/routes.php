<?php

use BayAreaWebPro\MultiStepForms\MultiStepForm;
use Illuminate\Support\Facades\Route;

Route::any('/', function(){
    // Render a view with data.
    return MultiStepForm::make('form', [
        'title' => 'MultiStep Form'
    ])
    // Namespace the session data.
    ->namespaced('test')
    // After every step...
    ->beforeStep('*', function (MultiStepForm $form) {
        if($form->request->filled('wildcard-before')){
            return response('beforeStep');
        }
    })
    // After every step...
    ->onStep('*', function (MultiStepForm $form) {
        $form->setValue('wildcard', $form->currentStep());
        if($form->request->filled('wildcard-response')){
            return response('onStep');
        }
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
})
->middleware('web')->name('submit');