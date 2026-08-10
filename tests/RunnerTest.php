<?php

use Illuminate\Support\Facades\Cache;
use PDPhilip\OmniCron\OmniCron;
use PDPhilip\OmniCron\Run\Run;
use PDPhilip\OmniCron\Run\RunState;
use PDPhilip\OmniCron\Tests\Fixtures\CustomRun;
use PDPhilip\OmniCron\Tests\Fixtures\FailingTask;
use PDPhilip\OmniCron\Tests\Fixtures\HourlyTask;
use PDPhilip\OmniCron\Tests\Fixtures\ProductionOnlyTask;

function engine(): OmniCron
{
    return app(OmniCron::class);
}

beforeEach(function () {
    config()->set('omnicron.tasks', [HourlyTask::class]);
});

// ======================================================================
// Due-ness
// ======================================================================

it('considers a never-run task due immediately', function () {
    expect(engine()->isDue(new HourlyTask))->toBeTrue();
});

it('measures due-ness from the last start, not the schedule matching this minute', function () {
    $task = new HourlyTask;
    $run = engine()->store()->open($task);
    // Started at 10:30:00 UTC - the hourly slot comes round at 11:00.
    $run->update(['started_at' => mktime(10, 30, 0, 6, 15, 2026)]);

    expect(engine()->isDue($task, mktime(10, 59, 0, 6, 15, 2026)))->toBeFalse()
        ->and(engine()->isDue($task, mktime(11, 0, 0, 6, 15, 2026)))->toBeTrue();
});

it('counts a failed run as a start so a dying task is not retried every tick', function () {
    $task = new HourlyTask;
    $run = engine()->store()->open($task);
    $run->fail('died', microtime(true));
    $run->update(['started_at' => mktime(10, 30, 0, 6, 15, 2026)]);

    expect(engine()->isDue($task, mktime(10, 45, 0, 6, 15, 2026)))->toBeFalse();
});

// ======================================================================
// The run lifecycle
// ======================================================================

it('runs a due task and logs the run with its output', function () {
    $result = engine()->run(new HourlyTask);

    expect($result['ran'])->toBeTrue()
        ->and($result['state'])->toBe('ok')
        ->and($result['output'])->toBe(['worked' => true]);

    $run = Run::query()->sole();
    expect($run->state)->toBe(RunState::OK)
        ->and($run->output)->toBe(['worked' => true])
        ->and($run->duration_ms)->not->toBeNull()
        ->and($run->finished_at)->not->toBeNull();
});

it('records a failure with its error instead of losing it', function () {
    $result = engine()->run(new FailingTask);

    expect($result['ran'])->toBeTrue()
        ->and($result['state'])->toBe('failed')
        ->and($result['error'])->toBe('the disk is on fire');

    $run = Run::query()->sole();
    expect($run->state)->toBe(RunState::FAILED)
        ->and($run->error)->toBe('the disk is on fire');
});

it('rechecks due-ness after winning the lock so parallel ticks cannot double-run a fast task', function () {
    $task = new HourlyTask;
    // Another machine's tick ran the task a moment ago - after our due()
    // check but before we reached its lock.
    engine()->store()->open($task)->succeed(['worked' => true], microtime(true));

    $result = engine()->run($task);

    expect($result)->toBe(['task' => 'hourly-task', 'ran' => false, 'state' => 'already_ran'])
        ->and(Run::query()->count())->toBe(1);

    // Manual stays explicit intent - it ignores the schedule entirely.
    expect(engine()->run($task, manual: true)['state'])->toBe('ok');
});

it('refuses to double-run a locked task and writes no row for the refusal', function () {
    $task = new HourlyTask;
    $held = Cache::lock('omnicron:task:'.$task->key(), 60);
    expect($held->get())->toBeTrue();

    $result = engine()->run($task);

    expect($result)->toBe(['task' => 'hourly-task', 'ran' => false, 'state' => 'locked'])
        ->and(Run::query()->count())->toBe(0);

    $held->release();
});

// ======================================================================
// The tick
// ======================================================================

it('ticks: runs what is due and reports what happened', function () {
    config()->set('omnicron.tasks', [HourlyTask::class, FailingTask::class]);

    $result = engine()->tick();

    expect($result['checked'])->toBe(2)
        ->and($result['due'])->toBe(2)
        ->and($result['ran'])->toBe(2)
        ->and($result['failed'])->toBe(1)
        ->and(Run::query()->count())->toBe(2);
});

it('skips environment-gated tasks on tick but allows them manually', function () {
    config()->set('omnicron.tasks', [ProductionOnlyTask::class]);

    // The test environment is not production - the tick must not touch it.
    expect(engine()->due())->toBe([]);

    // A human running it by hand is explicit intent.
    $result = engine()->run(new ProductionOnlyTask, manual: true);
    expect($result['state'])->toBe('ok');
    expect(Run::query()->sole()->manual)->toBeTrue();
});

// ======================================================================
// Health
// ======================================================================

it('derives a staleness threshold that exceeds one scheduled interval', function () {
    $task = new HourlyTask;

    expect(engine()->staleAfterSeconds($task))->toBeGreaterThan(engine()->scheduledInterval($task));
});

it('flags a never-succeeded task stale and an abandoned run stuck', function () {
    $task = new HourlyTask;
    $run = engine()->store()->open($task);
    // Abandoned: still RUNNING long past its lock window.
    $run->update(['started_at' => time() - $task->lockSeconds() - 60]);

    $status = engine()->status();
    $row = $status['tasks'][0];

    expect($row['is_stale'])->toBeTrue()
        ->and($row['is_stuck'])->toBeTrue()
        ->and($status['stuck'])->toBe(1);
});

// ======================================================================
// Pruning
// ======================================================================

it('prunes old finished runs but never a RUNNING row - that is crash evidence', function () {
    $task = new HourlyTask;

    $old = engine()->store()->open($task);
    $old->succeed(['done' => 1], microtime(true));
    $old->update(['started_at' => time() - 200 * 86400]);

    $abandoned = engine()->store()->open($task);
    $abandoned->update(['started_at' => time() - 200 * 86400]);

    $removed = engine()->store()->prune(time() - 90 * 86400);

    expect($removed)->toBe(1)
        ->and(Run::query()->sole()->state)->toBe(RunState::RUNNING);
});

// ======================================================================
// The swappable model
// ======================================================================

it('logs through a swapped run model - how a MongoDB app keeps its log in Mongo', function () {
    config()->set('omnicron.model', CustomRun::class);

    $result = engine()->run(new HourlyTask);

    expect($result['state'])->toBe('ok')
        ->and(engine()->store()->latestFor(new HourlyTask))
        ->toBeInstanceOf(CustomRun::class);
});
