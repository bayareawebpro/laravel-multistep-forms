<?php declare(strict_types=1);

namespace BayAreaWebPro\MultiStepFormsTests\Unit;

use BayAreaWebPro\MultiStepForms\MultiStepForm;
use BayAreaWebPro\MultiStepFormsTests\Fixtures\Invoke;
use BayAreaWebPro\MultiStepFormsTests\TestCase;

class HooksTest extends TestCase
{

    public function test_before_save()
    {
        $this->setupForm(function (MultiStepForm $form) {
            $form->addStep(1);
            $form->addStep(2);
            $form->beforeSave(function (array $data){
                $data['saved'] = true;
                return $data;
            });
        });

        $this
            ->post(route('test', ['form_step' => 1]))
            ->assertSessionHasAll([
                'test.saved' => true
            ]);
    }

    public function test_invokable_configuration()
    {
        $this->setupForm(function (MultiStepForm $form) {
            $form->tap(new Invoke);
        });
        $this
            ->post(route('test', ['form_step' => 1]))
            ->assertSessionHasAll([
                'test.invoked' => true
            ]);

    }


    public function test_hook_called_and_can_return_response()
    {
        //before:any
        $this->setupForm(function (MultiStepForm $form) {
            $form->beforeStep('*', function (MultiStepForm $form) {
                return response('test');
            });
        });
        $this
            ->post(route('test', ['form_step' => 1]))
            ->assertContent('test');


        //before:step
        $this->setupForm(function (MultiStepForm $form) {
            $form->beforeStep(1, function (MultiStepForm $form) {
                return response('test');
            });
        });
        $this
            ->post(route('test', ['form_step' => 1]))
            ->assertContent('test');


        //on:any
        $this->setupForm(function (MultiStepForm $form) {
            $form->onStep('*', function (MultiStepForm $form) {
                return response('test');
            });
        });
        $this
            ->post(route('test', ['form_step' => 1]))
            ->assertContent('test');

        //on:step
        $this->setupForm(function (MultiStepForm $form) {
            $form->onStep(1, function (MultiStepForm $form) {
                return response('test');
            });
        });
        $this
            ->post(route('test', ['form_step' => 1]))
            ->assertContent('test');

        //on:completed
        $this->setupForm(function (MultiStepForm $form) {
            $form->addStep(1);
            $form->onComplete(function (MultiStepForm $form) {
                return response('test');
            });
        });
        $this
            ->post(route('test', ['form_step' => 1]))
            ->assertContent('test');

    }
}
