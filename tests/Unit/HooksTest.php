<?php declare(strict_types=1);

namespace BayAreaWebPro\MultiStepForms\Tests\Unit;

use BayAreaWebPro\MultiStepForms\Tests\TestCase;

class HooksTest extends TestCase
{
    public function test_tap()
    {
        $this
            ->json('GET', route('hooks'),[
                'invoke' =>true,
            ])
            ->assertSessionHas('test.invoke')
            ->assertOk()
        ;
    }

    public function test_wildcard_before()
    {
        $this
            ->json('POST', route('hooks'),[
                'before*' =>true,
            ])
            ->assertSee('before*')
            ->assertOk()
        ;
    }

    public function test_wildcard_on()
    {
        $this
            ->json('POST', route('hooks'),[
                'on*' =>true,
                'form_step' =>1,
                'name' =>'name',
            ])
            ->assertSee('on*')
            ->assertOk()
        ;
    }

    public function test_step_before()
    {
        $this->startSession();
        $this
            ->json('POST', route('hooks'),[
                'before1' =>true,
            ])
            ->assertSee('before1')
            ->assertOk()
        ;
    }

    public function test_step_on()
    {
        $this->startSession();
        $this
            ->json('POST', route('hooks'),[
                'on1' =>true,
                'form_step' =>1,
                'name' =>'name',
            ])
            ->assertSee('on1')
            ->assertOk()
        ;
    }
}
