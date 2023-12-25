<?php declare(strict_types=1);

namespace BayAreaWebPro\MultiStepFormsTests\Unit;

use BayAreaWebPro\MultiStepForms\MultiStepForm;
use BayAreaWebPro\MultiStepFormsTests\TestCase;
use Illuminate\Support\Facades\App;

class StepFlow extends TestCase
{

    public function test_step_url()
    {
        $this->setupForm(function (MultiStepForm $form) {
            $form
                ->canNavigateBack()
                ->addStep(1)
                ->addStep(2)
                ->addStep(3);
        });

        /**
         * @var MultiStepForm $form
         */
        $form = $this->app->make(MultiStepForm::class);

        $this->withSession([
            'test.form_step' => 2,
        ]);

        $this->assertSame(
            $form->request->fullUrlWithQuery(['form_step' => 1]),
            $form->prevStepUrl()
        );

        $this->withSession([
            'test.form_step' => 1,
        ]);

        $this->assertNull($form->prevStepUrl());
    }


    public function test_step_helpers()
    {
        $this->setupForm(function (MultiStepForm $form) {
            $form
                ->addStep(1)
                ->addStep(2, [
                    'data' => ['test' => true]
                ])
                ->addStep(3);
        });

        $this->withSession([
            'test.form_step'  => 2,
            'test.test_value' => true,
        ]);

        /**
         * @var MultiStepForm $form
         */
        $form = App::make(MultiStepForm::class);

        $this->assertSame('value', $form->isPast(1, 'value', 'fallback'));
        $this->assertSame('value', $form->isActive(2, 'value', 'fallback'));
        $this->assertSame('value', $form->isFuture(3, 'value', 'fallback'));

        $this->assertSame('fallback', $form->isPast(10, 'value', 'fallback'));
        $this->assertSame('fallback', $form->isActive(10, 'value', 'fallback'));
        $this->assertSame('fallback', $form->isFuture(10, 'value', 'fallback'));

        $this->assertTrue($form->hasValue('test_value'));
        $this->assertTrue($form->stepConfig()->get('data')['test']);

    }

    public function test_step_incremented()
    {
        $this->setupForm(function (MultiStepForm $form) {
            $form
                ->canNavigateBack()
                ->addStep(1)
                ->addStep(2)
                ->addStep(3);
        });

        foreach (range(1, 2) as $step) {
            $this
                ->postJson(route('test'), [
                    'form_step' => $step
                ])
                ->assertSessionHasAll([
                    'test.form_step' => $step + 1,
                ])
                ->assertJson([
                    'form' => [
                        'form_step' => $step + 1
                    ]
                ]);
        }

        $this
            ->post(route('test'), [
                'form_step' => 3
            ])
            ->assertSessionHasAll([
                'test.form_step' => 1,
            ]);
    }
}
