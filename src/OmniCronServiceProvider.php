<?php

namespace PDPhilip\OmniCron;

use PDPhilip\OmniCron\Commands\ListCommand;
use PDPhilip\OmniCron\Commands\MakeTaskCommand;
use PDPhilip\OmniCron\Commands\PruneCommand;
use PDPhilip\OmniCron\Commands\RunCommand;
use PDPhilip\OmniCron\Commands\TickCommand;
use PDPhilip\OmniCron\Store\DatabaseStore;
use PDPhilip\OmniCron\Store\RunStore;
use Spatie\LaravelPackageTools\Commands\InstallCommand;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class OmniCronServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('omnicron')
            ->hasConfigFile()
            ->hasMigration('create_omnicron_runs_table')
            ->hasCommands([
                MakeTaskCommand::class,
                TickCommand::class,
                RunCommand::class,
                ListCommand::class,
                PruneCommand::class,
            ])
            ->hasInstallCommand(function (InstallCommand $command) {
                $command
                    ->publishConfigFile()
                    ->publishMigrations()
                    ->askToRunMigrations()
                    ->askToStarRepoOnGitHub('pdphilip/omnicron');
            });
    }

    public function packageRegistered(): void
    {
        $this->app->singleton(RunStore::class, DatabaseStore::class);
        $this->app->singleton(OmniCron::class);
    }

    public function packageBooted(): void
    {
        if (config('omnicron.endpoint.enabled', true)) {
            $this->loadRoutesFrom(__DIR__.'/../routes/omnicron.php');
        }
    }
}
