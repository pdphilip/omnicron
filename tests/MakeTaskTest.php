<?php

it('scaffolds into the configured task namespace, deriving the directory from it', function () {
    config()->set('omnicron.task_namespace', 'App\Crons');
    $path = app_path('Crons/ScaffoldProbe.php');
    @unlink($path);

    $this->artisan('omnicron:task', ['name' => 'ScaffoldProbe'])->assertSuccessful();

    expect(file_get_contents($path))
        ->toContain('namespace App\Crons;')
        ->toContain('class ScaffoldProbe extends OmniTask');

    unlink($path);
    @rmdir(app_path('Crons'));
});

it('refuses to overwrite an existing task class', function () {
    config()->set('omnicron.task_namespace', 'App\Crons');
    $path = app_path('Crons/ScaffoldProbe.php');

    $this->artisan('omnicron:task', ['name' => 'ScaffoldProbe'])->assertSuccessful();
    $this->artisan('omnicron:task', ['name' => 'ScaffoldProbe'])->assertFailed();

    unlink($path);
    @rmdir(app_path('Crons'));
});
