<?php

use Illuminate\Support\Facades\Gate;
use PDPhilip\OmniCron\OmniCron;
use PDPhilip\OmniCron\Tests\Fixtures\HourlyTask;

beforeEach(function () {
    config()->set('omnicron.tasks', [HourlyTask::class]);
    // The dashboard rides ['web'] in real apps; the tests exercise the
    // authorization middleware itself, not the host session stack.
    config()->set('omnicron.dashboard.middleware', []);
});

// ======================================================================
// Authorization - the Horizon convention
// ======================================================================

it('denies the dashboard outside local when no gate is defined', function () {
    $this->get('/omnicron/dashboard')->assertStatus(403);
});

it('denies when the gate says no and allows when it says yes', function () {
    Gate::define('viewOmniCron', fn ($user = null) => false);
    $this->get('/omnicron/dashboard')->assertStatus(403);

    Gate::define('viewOmniCron', fn ($user = null) => true);
    $this->get('/omnicron/dashboard')->assertOk()->assertSee('OmniCron');
});

it('opens freely in the local environment', function () {
    app()->detectEnvironment(fn () => 'local');

    $this->get('/omnicron/dashboard')->assertOk();
});

// ======================================================================
// The dashboard API
// ======================================================================

it('serves the overview the cards render from', function () {
    Gate::define('viewOmniCron', fn ($user = null) => true);
    app(OmniCron::class)->run(new HourlyTask);

    $this->getJson('/omnicron/dashboard/api/overview')
        ->assertOk()
        ->assertJsonPath('tasks.0.task', 'hourly-task')
        ->assertJsonPath('tasks.0.schedule', '0 * * * *')
        ->assertJsonPath('tasks.0.health', 'ok')
        ->assertJsonPath('tasks.0.health_label', 'Healthy')
        ->assertJsonStructure(['tasks' => [['next_run_in', 'last_success_ago']], 'stale', 'stuck', 'generated_at']);
});

it('marks a never-run task idle rather than pretending it is healthy', function () {
    Gate::define('viewOmniCron', fn ($user = null) => true);

    $this->getJson('/omnicron/dashboard/api/overview')
        ->assertJsonPath('tasks.0.health', 'idle')
        ->assertJsonPath('tasks.0.health_label', 'Never run');
});

it('serves the run log, filterable by task', function () {
    Gate::define('viewOmniCron', fn ($user = null) => true);
    app(OmniCron::class)->run(new HourlyTask);

    $this->getJson('/omnicron/dashboard/api/runs')
        ->assertOk()
        ->assertJsonPath('runs.0.task', 'hourly-task')
        ->assertJsonPath('runs.0.state', 'ok')
        ->assertJsonPath('runs.0.output.worked', true);

    $this->getJson('/omnicron/dashboard/api/runs?task=nope')
        ->assertOk()
        ->assertJsonCount(1, 'runs');
});

it('triggers a manual run from the dashboard and 404s unknown tasks', function () {
    Gate::define('viewOmniCron', fn ($user = null) => true);

    $this->postJson('/omnicron/dashboard/api/run/hourly-task')
        ->assertOk()
        ->assertJsonPath('state', 'ok');

    $this->postJson('/omnicron/dashboard/api/run/nope')->assertStatus(404);
});
