<?php

namespace BayAreaWebPro\MultiStepFormsTests\Fixtures;

use BayAreaWebPro\MultiStepForms\MultiStepForm;

class Invoke
{
    public function __invoke(MultiStepForm $form): void
    {
        $form->addStep(1);
        $form->onStep(1, fn()=>$form->setValue('invoked', true));
    }
}