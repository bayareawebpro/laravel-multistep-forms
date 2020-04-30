<?php

namespace BayAreaWebPro\MultiStepForms\Tests\Fixtures;

use BayAreaWebPro\MultiStepForms\MultiStepForm;

class Invoke
{
    public function __invoke(MultiStepForm $instance)
    {
        if($instance->request->filled('invoke')){
            $instance->setValue('invoke', true);
        }
    }
}