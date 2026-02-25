<?php

namespace OpenSwag\Laravel\Tests;

use Orchestra\Testbench\TestCase as OrchestraTestCase;

abstract class TestCase extends OrchestraTestCase
{
    protected function getPackageProviders($app): array
    {
        return [
            \OpenSwag\Laravel\OpenSwagServiceProvider::class,
        ];
    }

    protected function getPackageAliases($app): array
    {
        return [
            'OpenSwag' => \OpenSwag\Laravel\Facades\OpenSwag::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('openswag', require __DIR__ . '/../config/openswag.php');
    }
}
