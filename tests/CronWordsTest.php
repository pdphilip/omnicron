<?php

use PDPhilip\OmniCron\Schedule\CronWords;

it('says common schedules in words', function (string $expression, string $words) {
    expect(CronWords::toWords($expression))->toBe($words);
})->with([
    ['* * * * *', 'Every minute'],
    ['*/15 * * * *', 'Every 15 minutes'],
    ['0 * * * *', 'Every hour'],
    ['5 * * * *', 'Hourly at :05'],
    ['20 * * * *', 'Hourly at :20'],
    ['0 */6 * * *', 'Every 6 hours at :00'],
    ['0 3 * * *', 'Daily at 03:00'],
    ['40 2 * * *', 'Daily at 02:40'],
    ['0 6 * * 1', 'Mondays at 06:00'],
    ['0 6 * * 1,3', 'Mondays, Wednesdays at 06:00'],
    ['0 6 * * 1,2,3,4,5', 'Weekdays at 06:00'],
    ['0 8 * * 0,6', 'Weekends at 08:00'],
    ['0 4 1 * *', 'Monthly on the 1st at 04:00'],
    ['0 4 1 */3 *', 'Every 3 months on the 1st at 04:00'],
]);

it('appends the timezone when it is not UTC', function () {
    expect(CronWords::toWords('0 6 * * *', 'Africa/Johannesburg'))
        ->toBe('Daily at 06:00 (Africa/Johannesburg)');
});

it('hands back the raw expression rather than guessing', function () {
    expect(CronWords::toWords('17 3,9 * 2 5'))->toBe('17 3,9 * 2 5')
        ->and(CronWords::toWords('not cron'))->toBe('not cron');
});
