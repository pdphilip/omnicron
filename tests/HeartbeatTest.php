<?php

use PDPhilip\OmniCron\Run\Run;
use PDPhilip\OmniCron\Tests\Fixtures\HourlyTask;

beforeEach(function () {
    config()->set('omnicron.tasks', [HourlyTask::class]);
    config()->set('omnicron.endpoint.secret', 'shh');
});

// ======================================================================
// The secret gate
// ======================================================================

it('is public when no secret is configured - the secret is opt-in', function () {
    config()->set('omnicron.endpoint.secret', null);

    $this->getJson('/omnicron/tick')->assertOk();
});

it('refuses a missing or wrong secret', function () {
    $this->getJson('/omnicron/tick')->assertStatus(403);
    $this->getJson('/omnicron/tick', ['X-OmniCron-Secret' => 'wrong'])->assertStatus(403);
});

it('accepts the secret as a header or a token param', function () {
    $this->getJson('/omnicron/tick', ['X-OmniCron-Secret' => 'shh'])->assertOk();
    $this->getJson('/omnicron/tick?token=shh')->assertOk();
});

// ======================================================================
// The endpoints
// ======================================================================

it('ticks over http and returns what happened - the response is the remote log', function () {
    $response = $this->getJson('/omnicron/tick', ['X-OmniCron-Secret' => 'shh'])->assertOk();

    $response->assertJson(['checked' => 1, 'due' => 1, 'ran' => 1, 'failed' => 0]);
    $response->assertJsonPath('tasks.0.task', 'hourly-task');
    $response->assertJsonPath('tasks.0.output.worked', true);

    expect(Run::query()->count())->toBe(1);
});

it('reports health per task', function () {
    $this->getJson('/omnicron/status', ['X-OmniCron-Secret' => 'shh'])
        ->assertOk()
        ->assertJsonPath('tasks.0.task', 'hourly-task')
        ->assertJsonPath('tasks.0.schedule', '0 * * * *')
        ->assertJsonStructure(['tasks' => [['is_due', 'is_stale', 'is_stuck']], 'stale', 'stuck']);
});

it('runs one task by hand and 404s an unknown key', function () {
    $this->getJson('/omnicron/run/hourly-task', ['X-OmniCron-Secret' => 'shh'])
        ->assertOk()
        ->assertJsonPath('state', 'ok');

    $run = Run::query()->sole();
    expect($run->manual)->toBeTrue()
        ->and($run->trigger)->toBe('endpoint');

    $this->getJson('/omnicron/run/nope', ['X-OmniCron-Secret' => 'shh'])->assertStatus(404);
});
