<?php

namespace PDPhilip\OmniCron\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use PDPhilip\OmniCron\OmniCronServiceProvider;

class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [OmniCronServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');
    }

    protected function setUp(): void
    {
        parent::setUp();

        $migration = include __DIR__.'/../database/migrations/create_omnicron_runs_table.php.stub';
        $migration->up();
    }
}
