<?php

use BayAreaWebPro\MultiStepForms\Tests\Fixtures\Invoke;
use BayAreaWebPro\MultiStepForms\MultiStepForm;
use Illuminate\Support\Facades\Route;

Route::any('/hooks', function(){
    return MultiStepForm::make('form', [
        'title' => 'MultiStep Form'
    ])
    ->namespaced('test')
    ->tap(new Invoke)
    ->beforeStep('*', function (MultiStepForm $form) {
        $form->setValue('before', true);
        if($form->request->filled('before*')){
            return response('before*');
        }
    })
    ->beforeStep(1, function (MultiStepForm $form) {
        if($form->request->filled('before1')){
            return response('before1');
        }
    })
    ->onStep('*', function (MultiStepForm $form) {
        if($form->request->filled('on*')){
            return response('on*');
        }
    })
    ->onStep(1, function (MultiStepForm $form) {
        if($form->request->filled('on1')){
            return response('on1');
        }
    })
    ;
})
->middleware('web')
->name('hooks');


Route::any('/', function(){
    return MultiStepForm::make('form', [
        'title' => 'MultiStep Form'
    ])
    ->namespaced('test')
    ->addStep(1, [
        'rules' => ['name' => 'required'],
        'data' => ['title' => 'MultiStep Form | Step 1']
    ])
    ->addStep(2, [
        'rules' => ['role' => 'required']
    ])
    ->addStep(3)
    ->onStep(3, function (MultiStepForm $form) {
        if($form->request->get('submit') === 'reset'){
            $form->reset();
        }else{
            return response('OK');
        }
    });
})
->middleware('web')
->name('submit');