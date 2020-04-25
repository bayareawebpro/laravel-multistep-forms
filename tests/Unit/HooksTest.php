<?php declare(strict_types=1);

namespace BayAreaWebPro\MultiStepForms\Tests\Unit;

use BayAreaWebPro\MultiStepForms\Tests\TestCase;

class HooksTest extends TestCase
{
    public function test_wildcard_before()
    {
        $this->startSession();
        $this
            ->json('POST', route('hooks'),[
                'before*' =>true,
            ], ['Content-Type' => 'application/json'])
            ->assertOk()
            ->assertSee('before*')
        ;
    }

    public function test_wildcard_on()
    {
        $this->startSession();
        $this
            ->json('POST', route('hooks'),[
                'on*' =>true,
                'form_step' =>1,
                'name' =>'name',
            ], ['Content-Type' => 'application/json'])
            ->assertOk()
            ->assertSee('on*')
        ;
    }

    public function test_step_before()
    {
        $this->startSession();
        $this
            ->json('POST', route('hooks'),[
                'before1' =>true,
            ], ['Content-Type' => 'application/json'])
            ->assertOk()
            ->assertSee('before1')
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
            ], ['Content-Type' => 'application/json'])
            ->assertOk()
            ->assertSee('on1')
        ;
    }
}
