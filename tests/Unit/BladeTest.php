<?php declare(strict_types=1);

namespace BayAreaWebPro\MultiStepForms\Tests\Unit;

use BayAreaWebPro\MultiStepForms\Tests\TestCase;

class BladeTest extends TestCase
{
    public function test_step1_view()
    {
        $this
            ->get(route('submit'))
            ->assertViewIs('form')
            ->assertViewHas('title', 'MultiStep Form | Step 1')
            ->assertSee('MultiStep Form | Step 1')
            ->assertSessionHas('test.form_step', 1)
            ->assertOk();
    }

    public function test_step1_errors()
    {
        $this
            ->post(route('submit'))
            ->assertRedirect(route('submit'))
            ->assertSessionHasErrors([
                'form_step',
                'name',
            ]);
    }

    public function test_step1_success()
    {
        $this
            ->post(route('submit'), [
                'form_step' => 1,
                'name'      => 'test',
            ])
            ->assertSessionDoesntHaveErrors([
                'name',
                'form_step'
            ])
            ->assertSessionHas('test.form_step', 2)
            ->assertSessionHas('test.name', 'test')
            ->assertRedirect(route('submit'));
    }

    public function test_step2_errors()
    {
        $this
            ->post(route('submit'), [
                'form_step' => 2,
            ])
            ->assertRedirect(route('submit'))
            ->assertSessionHasErrors([
                'role',
            ]);
    }

    public function test_step2_success()
    {
        $this
            ->post(route('submit'), [
                'form_step' => 2,
                'role'      => 'test',
            ])
            ->assertSessionDoesntHaveErrors([
                'role', 'form_step'
            ])
            ->assertSessionHas('test.form_step', 3)
            ->assertSessionHas('test.role', 'test')
            ->assertRedirect(route('submit'));
    }

    public function test_step3_unvalidated_success()
    {
        $this
            ->post(route('submit'), [
                'form_step' => 3,
            ])
            ->assertSessionHas('test.form_step', 3)
            ->assertSee('OK')
            ->assertOk();
    }

    public function test_step_reset()
    {
        $this
            ->post(route('submit'), [
                'form_step' => 1,
                'submit'    => 'reset',
            ])
            ->assertSessionDoesntHaveErrors([
                'name',
                'form_step'
            ])
            ->assertSessionHas('test.form_step', 1)
            ->assertSessionHas('test.reset', true)
            ->assertRedirect(route('submit'));
    }
}
