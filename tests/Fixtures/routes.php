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
    ->onStep('*', function (MultiStepForm $form) {
        if($form->request->filled('on*')){
            return response('on*');
        }
    })
    ->addStep(1)
    ->onStep(1, function (MultiStepForm $form) {
        if($form->request->filled('on1')){
            return response('on1');
        }
    })
    ->beforeStep(1, function (MultiStepForm $form) {
        if($form->request->filled('before1')){
            return response('before1');
        }
    })
    ->addStep(2)
    ->onStep(2, function (MultiStepForm $form) {
        if($form->request->filled('on2')){
            return response('on2');
        }
    })
    ->beforeStep(2, function (MultiStepForm $form) {
        if($form->request->filled('before2')){
            return response('before2');
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
    ->canNavigateBack(true)
    ->namespaced('test')
    ->addStep(1, [
        'rules' => ['name' => 'required'],
        'data' => ['title' => 'MultiStep Form | Step 1']
    ])
    ->addStep(2, [
        'rules' => ['role' => 'required'],
        'data' => ['title' => 'MultiStep Form | Step 2']
    ])
    ->onStep(2, function (MultiStepForm $form) {


        throw_if($form->isFuture(4), \Exception::class,"Step 4 not future.");
        throw_if($form->isLastStep(), \Exception::class,"Step 2 not last.");
        throw_unless($form->isFuture(3), \Exception::class,"Step 3 future.");
    })
    ->addStep(3)
    ->beforeStep('*', function (MultiStepForm $form) {
        if($form->request->get('submit') === 'reset'){
            $form->reset(['reset' => true]);
        }
    })
    ->onStep(3, function (MultiStepForm $form) {
        throw_unless($form->isActive(3), \Exception::class,"Step 3 active.");
        throw_if($form->isActive(2), \Exception::class,"Step 2 not active.");

        throw_unless($form->isPast(1), \Exception::class,"Step 1 not past.");
        throw_if($form->isPast(3), \Exception::class,"Step 3 not past.");
        return response('OK');
    });
})
->middleware('web')
->name('submit');