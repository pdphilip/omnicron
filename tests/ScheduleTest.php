<?php

use Cron\CronExpression;
use Illuminate\Support\Str;
use PDPhilip\OmniCron\OmniTask;
use PDPhilip\OmniCron\Schedule\Schedule;

/**
 * The schedule is the part a user reads out loud and assumes they understood.
 * Every case here is a phrasing someone will write on day one.
 */
function schedule(): Schedule
{
    return new Schedule;
}

it('reads a full chain the way it is spoken', function () {
    // "every Monday, run every 5 hours, every 10 minutes within that hour"
    expect(schedule()->everyMonday()->everyHour(5)->everyMinute(10)->expression())
        ->toBe('*/10 */5 * * 1');
});

it('treats a bare weekday as midnight, not every minute of that day', function () {
    expect(schedule()->everyMonday()->expression())->toBe('0 0 * * 1');
});

it('widens the hour when asked for every minute', function () {
    expect(schedule()->everyMinute()->expression())->toBe('* * * * *');
});

it('keeps the hour on zero when asked for every hour', function () {
    expect(schedule()->everyHour()->expression())->toBe('0 * * * *');
});

it('preserves an explicit step when a finer field is set afterwards', function () {
    expect(schedule()->everyHour(6)->everyMinute(10)->expression())->toBe('*/10 */6 * * *');
});

it('accepts clock times', function () {
    expect(schedule()->at('06:30')->expression())->toBe('30 6 * * *');
    expect(schedule()->everyMonday()->at('06:00')->expression())->toBe('0 6 * * 1');
});

it('merges multiple weekdays', function () {
    expect(schedule()->everyWeekday()->expression())->toBe('0 0 * * 1,2,3,4,5');
    expect(schedule()->everyMonday()->everyFriday()->expression())->toBe('0 0 * * 1,5');
});

it('treats a step of one as every', function () {
    expect(schedule()->everyMinute(1)->expression())->toBe('* * * * *');
});

it('produces expressions cron actually accepts', function () {
    $chains = [
        schedule()->everyMinute(),
        schedule()->everyMonday()->everyHour(5)->everyMinute(10),
        schedule()->everyWeekend()->at('23:45'),
        schedule()->everyMonth(3)->dayAt(1)->at('04:00'),
    ];

    foreach ($chains as $s) {
        expect(CronExpression::isValidExpression($s->expression()))->toBeTrue($s->expression());
    }
});

it('knows when nothing has been said', function () {
    expect(schedule()->isDefined())->toBeFalse()
        ->and(schedule()->everyMinute()->isDefined())->toBeTrue();
});

it('defaults to UTC and accepts a real timezone', function () {
    expect(schedule()->getTimezone())->toBe('UTC')
        ->and(schedule()->timezone('Africa/Johannesburg')->getTimezone())->toBe('Africa/Johannesburg');
});

it('rejects nonsense rather than guessing', function () {
    expect(fn () => schedule()->minuteAt(60))->toThrow(InvalidArgumentException::class);
    expect(fn () => schedule()->hourAt(24))->toThrow(InvalidArgumentException::class);
    expect(fn () => schedule()->at('6:00pm'))->toThrow(InvalidArgumentException::class);
    expect(fn () => schedule()->timezone('Mars/Olympus'))->toThrow(InvalidArgumentException::class);
    expect(fn () => schedule()->cron('not a cron'))->toThrow(InvalidArgumentException::class);
});

it('allows a raw expression for things the fluent form cannot say', function () {
    expect(schedule()->cron('0 0 1 */3 *')->expression())->toBe('0 0 1 */3 *');
});

it('derives a task key from the class name', function () {
    $task = new class extends OmniTask
    {
        public function schedule(Schedule $schedule): void
        {
            $schedule->everyHour();
        }

        public function execute(): array
        {
            return [];
        }

        public function key(): string
        {
            return Str::kebab('CloseBillingCycles');
        }
    };

    expect($task->key())->toBe('close-billing-cycles');
});
