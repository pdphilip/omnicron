<?php

it('scaffolds into the configured task namespace, deriving the directory from it', function () {
    config()->set('omnicron.task_namespace', 'App\CronJobs');
    $path = app_path('CronJobs/ScaffoldProbe.php');
    @unlink($path);

    $this->artisan('omnicron:task', ['name' => 'ScaffoldProbe'])->assertSuccessful();

    expect(file_get_contents($path))
        ->toContain('namespace App\CronJobs;')
        ->toContain('class ScaffoldProbe extends OmniTask');

    unlink($path);
    @rmdir(app_path('CronJobs'));
});

it('refuses to overwrite an existing task class', function () {
    config()->set('omnicron.task_namespace', 'App\CronJobs');
    $path = app_path('CronJobs/ScaffoldProbe.php');

    $this->artisan('omnicron:task', ['name' => 'ScaffoldProbe'])->assertSuccessful();
    $this->artisan('omnicron:task', ['name' => 'ScaffoldProbe'])->assertFailed();

    unlink($path);
    @rmdir(app_path('CronJobs'));
});

it('defaults to the App\CronJobs namespace', function () {
    expect(config('omnicron.task_namespace'))->toBe('App\CronJobs');
});

it('derives the directory from the psr-4 map rather than assuming app_path', function () {
    $base = sys_get_temp_dir().'/omnicron-psr4-'.getmypid();
    @mkdir($base, 0755, true);
    file_put_contents($base.'/composer.json', json_encode([
        'autoload' => ['psr-4' => ['App\\' => 'src/App/']],
    ]));
    $this->app->setBasePath($base);

    $this->artisan('omnicron:task', ['name' => 'PsrProbe'])->assertSuccessful();

    expect(file_get_contents($base.'/src/App/CronJobs/PsrProbe.php'))
        ->toContain('namespace App\CronJobs;')
        ->toContain('class PsrProbe extends OmniTask');

    unlink($base.'/src/App/CronJobs/PsrProbe.php');
    unlink($base.'/composer.json');
    @rmdir($base.'/src/App/CronJobs');
    @rmdir($base.'/src/App');
    @rmdir($base.'/src');
    @rmdir($base);
});
