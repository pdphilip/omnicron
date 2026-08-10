<?php

use Illuminate\Support\Facades\Gate;
use PDPhilip\OmniCron\OmniCron;
use PDPhilip\OmniCron\Run\Trigger;
use PDPhilip\OmniCron\Tests\Fixtures\FailingTask;
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

it('publishes the app-side gate provider - the Horizon convention', function () {
    $target = base_path('app/Providers/OmniCronServiceProvider.php');
    @unlink($target);

    $this->artisan('vendor:publish', ['--tag' => 'omnicron-provider'])->assertSuccessful();

    expect(file_get_contents($target))->toContain("Gate::define('viewOmniCron'");
    unlink($target);
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
        ->assertJsonPath('tasks.0.schedule_words', 'Every hour')
        ->assertJsonPath('tasks.0.health', 'ok')
        ->assertJsonPath('tasks.0.health_label', 'Healthy')
        ->assertJsonPath('tasks.0.uptime.up', true)
        ->assertJsonPath('tasks.0.last_runs.0.state', 'ok')
        ->assertJsonStructure(['tasks' => [['next_run_in', 'next_run_at', 'last_success_ago', 'last_runs']], 'stale', 'stuck', 'generated_at']);
});

it('measures uptime from the last failure', function () {
    Gate::define('viewOmniCron', fn ($user = null) => true);
    config()->set('omnicron.tasks', [FailingTask::class]);
    app(OmniCron::class)->run(new FailingTask, Trigger::APP);

    $this->getJson('/omnicron/dashboard/api/overview')
        ->assertJsonPath('tasks.0.uptime.up', false);
});

it('serves the queue of upcoming executions, soonest first, skipping paused tasks', function () {
    Gate::define('viewOmniCron', fn ($user = null) => true);

    $response = $this->getJson('/omnicron/dashboard/api/queue')->assertOk();
    $rows = $response->json('queue');

    expect($rows)->toHaveCount(12)
        ->and($rows[0]['task'])->toBe('hourly-task')
        ->and($rows[1]['execute_ts'] - $rows[0]['execute_ts'])->toBe(3600)
        ->and($rows[0]['execute_ts'])->toBeGreaterThan(time());

    app(OmniCron::class)->pause(new HourlyTask);
    $this->getJson('/omnicron/dashboard/api/queue')->assertJsonCount(0, 'queue');
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

it('pauses and overrides from the dashboard, rejecting junk cron', function () {
    Gate::define('viewOmniCron', fn ($user = null) => true);

    $this->postJson('/omnicron/dashboard/api/job/hourly-task', ['paused' => true])->assertOk();
    $this->getJson('/omnicron/dashboard/api/overview')
        ->assertJsonPath('tasks.0.paused', true)
        ->assertJsonPath('tasks.0.health_label', 'Paused');

    $this->postJson('/omnicron/dashboard/api/job/hourly-task', ['schedule_override' => '*/10 * * * *'])->assertOk();
    $this->getJson('/omnicron/dashboard/api/overview')
        ->assertJsonPath('tasks.0.schedule', '*/10 * * * *')
        ->assertJsonPath('tasks.0.schedule_overridden', true)
        ->assertJsonPath('tasks.0.schedule_in_code', '0 * * * *');

    $this->postJson('/omnicron/dashboard/api/job/hourly-task', ['schedule_override' => 'not-cron'])
        ->assertStatus(422);
    $this->postJson('/omnicron/dashboard/api/job/nope', ['paused' => true])->assertStatus(404);
});
