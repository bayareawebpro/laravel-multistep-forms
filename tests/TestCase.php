<?php
namespace BayAreaWebPro\MultiStepFormsTests;

use BayAreaWebPro\MultiStepFormsTests\Fixtures\Invoke;
use BayAreaWebPro\MultiStepForms\MultiStepForm;
use Orchestra\Testbench\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Load package service provider
     * @param \Illuminate\Foundation\Application $app
     * @return array
     */
    protected function getPackageProviders($app)
    {
        return [];
    }

    /**
     * Load package alias
     * @param \Illuminate\Foundation\Application $app
     * @return array
     */
    protected function getPackageAliases($app)
    {
        return [];
    }

    protected function setupForm(\Closure $closure)
    {
        $form = MultiStepForm::make('form', [
            'title' => 'Test'
        ])->namespaced('test');

        call_user_func($closure, $form);

        $this->app->instance(MultiStepForm::class, $form);

    }

    /**
     * Define routes setup.
     *
     * @param  \Illuminate\Routing\Router  $router
     * @return void
     */
    protected function defineRoutes($router)
    {
        $router->any('multi-step-form', function () {
            return $this->app->make(MultiStepForm::class);
        })
        ->middleware('web')
        ->name('test');
    }

    /**
     * Setup the test environment.
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->app['config']->set('view.paths', [
            __DIR__.'/Fixtures/views'
        ]);
    }
}
