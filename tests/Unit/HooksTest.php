<?php declare(strict_types=1);

namespace BayAreaWebPro\MultiStepForms\Tests\Unit;

use BayAreaWebPro\MultiStepForms\Tests\TestCase;

class HooksTest extends TestCase
{
    public function test_before_save()
    {
        $this->withSession(['test' => []]);
        $this
            ->json('POST', route('hooks'), [
                'form_step' => 1,
            ])
            ->assertOk()
            ->assertSessionHas('test.before_save');
    }

    public function test_tap()
    {
        $this
            ->json('GET', route('hooks'), [
                'invoke' => true,
            ])
            ->assertOk()
            ->assertSessionHas('test.invoke');
    }

    public function test_wildcard_before()
    {
        $this
            ->json('POST', route('hooks'), [
                'before*' => true,
            ])
            ->assertSee('before*')
            ->assertOk();
    }

    public function test_wildcard_on()
    {
        $this
            ->json('POST', route('hooks'), [
                'on*'       => true,
                'form_step' => 1,
            ])
            ->assertSee('on*')
            ->assertOk();
    }

    public function test_step_before()
    {
        $this->startSession();

        $this
            ->json('POST', route('hooks'), [
                'form_step' => 1,
                'on1'       => true,
            ])
            ->assertDontSee('before1')
            ->assertSee('on1')
            ->assertOk();
        $this
            ->json('POST', route('hooks'), [
                'before1'   => true,
                'form_step' => 1
            ])
            ->assertSee('before1')
            ->assertOk();
    }

    public function test_step_before2()
    {
        $this->withSession(['test' => ['form_step' => 1]]);
        $this
            ->json('POST', route('hooks'), [
                'form_step' => 2
            ])
            ->assertDontSee('before2')
            ->assertOk();
        $this
            ->json('POST', route('hooks'), [
                'before2'   => true,
                'form_step' => 2
            ])
            ->assertSee('before2')
            ->assertOk();
    }
}
