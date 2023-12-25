<?php declare(strict_types=1);

namespace BayAreaWebPro\MultiStepFormsTests\Unit;

use BayAreaWebPro\MultiStepForms\MultiStepForm;
use BayAreaWebPro\MultiStepFormsTests\TestCase;

class DataTest extends TestCase
{
    public function test_data_injection()
    {
        $this->withoutExceptionHandling();

        $this->setupForm(function (MultiStepForm $form) {

            $form->withData([
                'title' => 'test:global:data',
                'lazy'  => fn() => 'lazy:data',
            ]);

            $form->addStep(1, [
                'data' => [
                    'description' => 'test:step:data',
                    'lazyStep'    => fn() => 'lazy:data',
                ],
            ]);
        });

        $this->get(route('test'))
            ->assertOk()
            ->assertViewIs('form')
            ->assertViewHasAll([
                'title'       => 'test:global:data',
                'description' => 'test:step:data',
                'lazy'        => 'lazy:data',
                'lazyStep'    => 'lazy:data',
                'form',
            ]);

        $this->json('GET', route('test'))
            ->assertOk()
            ->assertJson([
                'data' => [
                    'title'       => 'test:global:data',
                    'description' => 'test:step:data',
                    'lazy'        => 'lazy:data',
                ],
                'form' => [
                    'form_step' => 1
                ],
            ]);
    }

}
