<?php declare(strict_types=1);

namespace BayAreaWebPro\MultiStepFormsTests\Unit;

use BayAreaWebPro\MultiStepForms\MultiStepForm;
use BayAreaWebPro\MultiStepFormsTests\TestCase;

class ValidationTest extends TestCase
{
    public function test_validation()
    {
        $this->setupForm(function (MultiStepForm $form) {
            $form
                ->addStep(1, [
                    'rules' => [
                        'name' => ['required']
                    ],
                    'messages' => [
                        'name.required' => 'test:message',
                        'form_step.required' => 'test:message'
                    ],
                ])
                ->addStep(2);
        });

        $this
            ->post(route('test'))
            ->assertSessionHasErrors([
                'name' => 'test:message',
                'form_step' => 'test:message',
            ]);
    }
}
