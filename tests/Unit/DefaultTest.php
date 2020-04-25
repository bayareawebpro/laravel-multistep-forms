<?php declare(strict_types=1);

namespace BayAreaWebPro\MultiStepForms\Tests\Unit;

use BayAreaWebPro\MultiStepForms\Tests\TestCase;

class DefaultTest extends TestCase
{
    public function test_step1_returns_views()
    {
        $this->startSession();
        $this
            ->get(route('submit'))
            ->assertViewIs('form')
            ->assertSee('MultiStep Form');
    }

    public function test_step1_returns_json()
    {
        $this->startSession();
        $this
            ->json('GET', route('submit'), [], ['Content-Type' => 'application/json'])
            ->assertOk();
    }

    public function test_step1_has_errors_and_redirects_back()
    {
        $this
            ->post(route('submit'))
            ->assertRedirect(route('submit'))
            ->assertSessionHasErrors([
                'form_step',
                'name',
            ]);
    }

    public function test_step1_has_json_errors()
    {
        $this
            ->json('POST', route('submit'), [], ['Content-Type' => 'application/json'])
            ->assertJsonValidationErrors([
                'name',
                'form_step',
            ]);
    }

    public function test_step1_redirects_back_on_success()
    {
        $this
            ->post(route('submit'), [
                'form_step' => 1,
                'name'      => 'test',
            ])
            ->assertSessionDoesntHaveErrors(['name', 'form_step'])
            ->assertSessionHas('test.form_step', 2)
            ->assertSessionHas('test.name', 'test')
            ->assertRedirect(route('submit'));
    }

    public function test_step1_returns_json_on_success()
    {
        $this
            ->json('POST', route('submit'), [
                'form_step' => 1,
                'name'      => 'test',
            ], ['Content-Type' => 'application/json'])
            ->assertJsonFragment([
                'name'     => 'test',
            ]);
    }

    public function test_step2_has_errors_and_redirects_back()
    {
        $this
            ->post(route('submit'), [
                'form_step' => 2,
            ])
            ->assertRedirect(route('submit'))
            ->assertSessionHasErrors([
                'role'
            ]);
    }

    public function test_step2_returns_json_errors()
    {
        $this
            ->json('POST', route('submit'), [
                'form_step' => 2,
            ], ['Content-Type' => 'application/json'])
            ->assertJsonValidationErrors([
                'role'
            ]);
    }

    public function test_step2_redirects_back_on_success()
    {
        $this
            ->post(route('submit'), [
                'form_step' => 2,
                'role'      => 'test',
            ])
            ->assertSessionDoesntHaveErrors(['role', 'form_step'])
            ->assertSessionHas('test.form_step', 3)
            ->assertSessionHas('test.role', 'test')
            ->assertRedirect(route('submit'));
    }

    public function test_step2_returns_json_on_success()
    {
        $this
            ->json('POST', route('submit'), [
                'form_step' => 2,
                'role'      => 'test',
            ], ['Content-Type' => 'application/json'])
            ->assertJsonFragment([
                'form_step' => 3,
                'role'      => 'test',
            ]);
    }

    public function test_step3_returns_ok_response_on_success()
    {
        $this
            ->post(route('submit'), [
                'form_step' => 3,
            ])
            ->assertSessionDoesntHaveErrors(['form_step'])
            ->assertSessionHas('test.form_step', 3)
            ->assertSee('OK');
    }

    public function test_step3_json_returns_ok_response_on_success()
    {
        $this
            ->json('POST', route('submit'), [
                'form_step' => 3,
            ], ['Content-Type' => 'application/json'])
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
            ->assertRedirect(route('submit'));
    }
}
