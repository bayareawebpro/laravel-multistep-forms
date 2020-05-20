<?php declare(strict_types=1);

namespace BayAreaWebPro\MultiStepForms\Tests\Unit;

use BayAreaWebPro\MultiStepForms\Tests\TestCase;

class JsonTest extends TestCase
{
    public function test_step1_fetch()
    {
        $this
            ->json('GET', route('submit'))
            ->assertJsonFragment([
                'data' => [
                    'title' => 'MultiStep Form | Step 1',
                ],
            ])
            ->assertOk();
    }

    public function test_step1_errors()
    {
        $this
            ->json('POST', route('submit'), [])
            ->assertJsonValidationErrors([
                'name', 'form_step',
            ]);
    }

    public function test_step1_success()
    {
        $this
            ->json('POST', route('submit'), [
                'form_step' => 1,
                'name'      => 'test',
            ])
            ->assertJsonFragment([
                'form' => [
                    'form_step' => 2,
                    'name'      => 'test',
                ],
                'data' => [
                    'title' => 'MultiStep Form | Step 2',
                ],
            ])
            ->assertOk();
    }

    public function test_step2_errors()
    {
        $this
            ->json('POST', route('submit'), [
                'form_step' => 2,
            ])
            ->assertJsonValidationErrors([
                'role',
            ]);
    }

    public function test_step2_success()
    {
        $this
            ->json('POST', route('submit'), [
                'form_step' => 2,
                'role'      => 'test',
            ])
            ->assertJsonFragment([
                'form_step' => 3,
                'role'      => 'test',
            ])
            ->assertOk();
    }

    public function test_step3_returns_ok_response_on_success()
    {
        $this
            ->post(route('submit'), [
                'form_step' => 3,
            ])
            ->assertSessionDoesntHaveErrors(['form_step'])
            ->assertSessionHas('test.form_step', 3)
            ->assertSee('OK')
            ->assertOk();
    }

    public function test_step3_json_returns_ok_response_on_success()
    {
        $this
            ->json('POST', route('submit'), [
                'form_step' => 3,
            ])
            ->assertSee('OK');
    }

    public function test_step3_resets_form_session()
    {
        $this
            ->post(route('submit'), [
                'form_step' => 3,
                'submit'    => 'reset',
            ])
            ->assertSessionDoesntHaveErrors(['form_step'])
            ->assertSessionHas('test.form_step', 1)
            ->assertSessionHas('test.reset', true)
            ->assertRedirect(route('submit'));
    }

    public function test_step3_json_resets_form_session()
    {
        $this
            ->json('POST', route('submit'), [
                'form_step' => 3,
                'submit'    => 'reset',
            ])
            ->assertSessionHas('test.form_step', 1)
            ->assertSessionHas('test.reset', true)
            ->assertJsonFragment([
                'form' => [
                    'form_step' => 1,
                    'reset'     => true,
                ],
                'data' => [
                    'title' => 'MultiStep Form | Step 1',
                ],
            ])
            ->assertOk();
    }
}
