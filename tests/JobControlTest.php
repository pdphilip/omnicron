<?php

use PDPhilip\OmniCron\Job\CronJob;
use PDPhilip\OmniCron\OmniCron;
use PDPhilip\OmniCron\Run\Run;
use PDPhilip\OmniCron\Tests\Fixtures\HourlyTask;

function control(): OmniCron
{
    return app(OmniCron::class);
}

beforeEach(function () {
    config()->set('omnicron.tasks', [HourlyTask::class]);
});

// ======================================================================
// The registry row
// ======================================================================

it('creates the control row lazily, recording which class the key belongs to', function () {
    $job = control()->store()->job(new HourlyTask);

    expect($job)->toBeInstanceOf(CronJob::class)
        ->and($job->key)->toBe('hourly-task')
        ->and($job->class)->toBe(HourlyTask::class)
        ->and($job->isPaused())->toBeFalse()
        ->and(CronJob::query()->count())->toBe(1);

    // Asking again reuses the same row - one per task, ever.
    control()->store()->job(new HourlyTask);
    expect(CronJob::query()->count())->toBe(1);
});

it('relates a job to its run log - CronJob and its runs are the two models', function () {
    control()->run(new HourlyTask);
    control()->run(new HourlyTask);

    $job = control()->store()->job(new HourlyTask);

    expect($job->runs)->toHaveCount(2)
        ->and($job->runs->first())->toBeInstanceOf(Run::class)
        ->and($job->latestRun->task)->toBe('hourly-task');
});

// ======================================================================
// Pause
// ======================================================================

it('pauses a task out of the tick but never out of a manual run', function () {
    control()->pause(new HourlyTask);

    expect(control()->due())->toBe([]);

    // A human pressing the button is explicit intent - same rule as environments().
    $result = control()->run(new HourlyTask, manual: true);
    expect($result['state'])->toBe('ok');
});

it('resumes a paused task back into the tick', function () {
    control()->pause(new HourlyTask);
    control()->resume(new HourlyTask);

    expect(control()->due())->toHaveCount(1);
});

it('reports paused in status so every surface can show it', function () {
    control()->pause(new HourlyTask);

    expect(control()->status()['tasks'][0]['paused'])->toBeTrue();
});

// ======================================================================
// Schedule override
// ======================================================================

it('lets an operator override the schedule without a deploy, and clear it back', function () {
    $task = new HourlyTask;
    $run = control()->store()->open($task);
    $run->update(['started_at' => mktime(10, 30, 0, 6, 15, 2026)]);

    // Hourly in code: not due at 10:36.
    expect(control()->isDue($task, mktime(10, 36, 0, 6, 15, 2026)))->toBeFalse();

    // Overridden to every 5 minutes: 10:36 is past the 10:35 slot.
    control()->overrideSchedule($task, '*/5 * * * *');
    expect(control()->expressionFor($task))->toBe('*/5 * * * *')
        ->and(control()->isDue($task, mktime(10, 36, 0, 6, 15, 2026)))->toBeTrue();

    $row = control()->status()['tasks'][0];
    expect($row['schedule'])->toBe('*/5 * * * *')
        ->and($row['schedule_overridden'])->toBeTrue()
        ->and($row['schedule_in_code'])->toBe('0 * * * *');

    // Clearing restores the code's schedule.
    control()->overrideSchedule($task, null);
    expect(control()->expressionFor($task))->toBe('0 * * * *')
        ->and(control()->status()['tasks'][0]['schedule_overridden'])->toBeFalse();
});

it('refuses an invalid cron expression instead of silently breaking due-ness', function () {
    control()->overrideSchedule(new HourlyTask, 'every tuesday-ish');
})->throws(InvalidArgumentException::class);
