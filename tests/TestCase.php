<?php
namespace BayAreaWebPro\MultiStepForms\Tests;

use Illuminate\Support\Str;
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
        $this->app['config']->set('app.debug', true);
        $this->app['config']->set('app.key', Str::random(32));
        require __DIR__.'/Fixtures/routes.php';
    }
}
