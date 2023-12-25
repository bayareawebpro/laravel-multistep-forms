<?php declare(strict_types=1);

namespace BayAreaWebPro\MultiStepFormsTests\Unit;

use BayAreaWebPro\MultiStepForms\MultiStepForm;
use BayAreaWebPro\MultiStepFormsTests\TestCase;

class DeleteTest extends TestCase
{
    public function test_reset_before_step()
    {
        $this->setupForm(function (MultiStepForm $form) {
            $form
                ->addStep(1)
                ->addStep(2)
                ->beforeStep(2, function (MultiStepForm $form){
                    $form->reset([
                        'name'=> 'Updated',
                    ]);
                })
                ->addStep(3)
            ;
        });

        $this->withSession([
            'test.form_step' => 1,
            'test.name'      => 'John',
        ]);

        $this
            ->post(route('test'), [
                'form_step' => 2
            ], [
                'HTTP_REFERER' => route('test')
            ])
            ->assertRedirect(route('test'))
            ->assertSessionHasAll([
                'test.form_step' => 1,
                'test.name'=> 'Updated',
            ]);
    }
    public function test_delete()
    {
        $this->setupForm(function (MultiStepForm $form) {
            $form
                ->addStep(1)
                ->addStep(2)
                ->addStep(3);
        });

        $this->withSession([
            'test.form_step' => 3,
            'test.name'      => 'John',
        ]);

        $this
            ->delete(route('test'), [], [
                'HTTP_REFERER' => route('test')
            ])
            ->assertRedirect(route('test'))
            ->assertSessionHasAll([
                'test.form_step' => 1,
            ])
            ->assertSessionMissing([
                'test.name',
            ]);
    }
}
