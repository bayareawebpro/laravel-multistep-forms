<?php declare(strict_types=1);

namespace BayAreaWebPro\MultiStepFormsTests\Unit;

use BayAreaWebPro\MultiStepForms\MultiStepForm;
use BayAreaWebPro\MultiStepFormsTests\TestCase;

class GuardTest extends TestCase
{
    public function test_can_access_previous_step()
    {
        $this->withSession([
            'test.form_step' => 2,
        ]);

        $this->setupForm(function (MultiStepForm $form) {
            $form
                ->canNavigateBack()
                ->addStep(1)
                ->addStep(2);
        });


        $this
            ->get(route('test', [
                'form_step' => 1,
            ]), [
                'HTTP_REFERER' => route('test')
            ])
            ->assertRedirect(route('test'))
            ->assertSessionHas([
                'test.form_step' => 1
            ]);

        $this
            ->getJson(route('test',[
                'form_step' => 1,
            ]))
            ->assertSessionHas([
                'test.form_step' => 1
            ]);
    }

    public function test_cannot_access_previous_step()
    {
        $this->withSession([
            'test.form_step' => 2,
        ]);

        $this->setupForm(function (MultiStepForm $form) {
            $form
                ->addStep(1)
                ->addStep(2);
        });


        $this->get(route('test', [
            'form_step' => 1,
        ]), [
            'HTTP_REFERER' => route('test')
        ])
        ->assertRedirect(route('test'))
        ->assertSessionHas([
            'test.form_step' => 2
        ]);

        $this->getJson(route('test',[
            'form_step' => 1,
        ]))
        ->assertSessionHas([
            'test.form_step' => 2
        ]);
    }

    public function test_cannot_access_future_step()
    {
        $this->setupForm(function (MultiStepForm $form) {
            $form
                ->addStep(1)
                ->addStep(2)
            ;
        });


        $this->get(route('test', [
            'form_step' => 2,
        ]), [
            'HTTP_REFERER' => route('test')
        ])
        ->assertRedirect(route('test'))
        ->assertSessionHas([
            'test.form_step' => 1
        ]);

        $this->getJson(route('test',[
            'form_step' => 2,
        ]))
        ->assertSessionHas([
            'test.form_step' => 1
        ]);
    }


}
