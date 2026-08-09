<?php

namespace PDPhilip\OmniCron\Schedule;

use Cron\CronExpression;
use InvalidArgumentException;

/**
 * A fluent schedule that compiles to a cron expression.
 *
 * Reading a chain narrows from coarse to fine, the way you would say it out
 * loud: "every Monday, every 5 hours, every 10 minutes".
 *
 *   $this->everyMonday()->everyHour(5)->everyMinute(10);
 *
 * compiles to the cron expression: every 10th minute, every 5th hour, Mondays.
 *
 * WHY IT COMPILES TO CRON rather than computing next-run itself: the
 * expression is the thing operators already know how to read. It can be shown
 * on a dashboard, pasted into crontab.guru, and reasoned about by someone who
 * has never seen this package. The cost is that a handful of phrasings are
 * not expressible - "every 90 minutes" has no cron form - and those throw
 * rather than silently doing something else.
 *
 * DEFAULTS ARE THE QUIET PART. A bare schedule is midnight daily, so
 * everyMonday() means Monday at 00:00 rather than every minute of Monday.
 * Calling a finer-grained method widens the coarser ones only when you have
 * not set them yourself: everyMinute() alone means every minute of every
 * hour, but everyHour(6)->everyMinute(10) keeps your 6-hour step.
 */
class Schedule
{
    private string $minute = '0';

    private string $hour = '0';

    private string $dayOfMonth = '*';

    private string $month = '*';

    private string $dayOfWeek = '*';

    /** @var array<string, bool> which fields the caller set explicitly */
    private array $touched = [];

    private string $timezone = 'UTC';

    private ?string $raw = null;

    // ======================================================================
    // Minutes
    // ======================================================================

    public function everyMinute(?int $step = null): static
    {
        $this->minute = $this->step($step, 'minute', 59);
        $this->touch('minute');
        $this->widen('hour', '*');

        return $this;
    }

    /** A specific minute past the hour. */
    public function minuteAt(int $minute): static
    {
        $this->guardRange($minute, 0, 59, 'minute');
        $this->minute = (string) $minute;
        $this->touch('minute');

        return $this;
    }

    // ======================================================================
    // Hours
    // ======================================================================

    public function everyHour(?int $step = null): static
    {
        $this->hour = $this->step($step, 'hour', 23);
        $this->touch('hour');
        $this->widen('minute', '0');

        return $this;
    }

    public function hourAt(int $hour): static
    {
        $this->guardRange($hour, 0, 23, 'hour');
        $this->hour = (string) $hour;
        $this->touch('hour');
        $this->widen('minute', '0');

        return $this;
    }

    /** Clock time, 'HH:MM'. */
    public function at(string $time): static
    {
        if (! preg_match('/^([01]?\d|2[0-3]):([0-5]\d)$/', $time, $m)) {
            throw new InvalidArgumentException("Expected a time like '06:30', got '{$time}'");
        }

        return $this->hourAt((int) $m[1])->minuteAt((int) $m[2]);
    }

    // ======================================================================
    // Days
    // ======================================================================

    public function everyDay(?int $step = null): static
    {
        $this->dayOfMonth = $this->step($step, 'day', 31);
        $this->touch('dayOfMonth');
        $this->widen('hour', '0');
        $this->widen('minute', '0');

        return $this;
    }

    public function dayAt(int $day): static
    {
        $this->guardRange($day, 1, 31, 'day');
        $this->dayOfMonth = (string) $day;
        $this->touch('dayOfMonth');

        return $this;
    }

    public function everyMonth(?int $step = null): static
    {
        $this->month = $this->step($step, 'month', 12);
        $this->touch('month');
        $this->widen('hour', '0');
        $this->widen('minute', '0');

        return $this;
    }

    // ======================================================================
    // Weekdays
    // ======================================================================

    public function everyMonday(): static
    {
        return $this->onDays(1);
    }

    public function everyTuesday(): static
    {
        return $this->onDays(2);
    }

    public function everyWednesday(): static
    {
        return $this->onDays(3);
    }

    public function everyThursday(): static
    {
        return $this->onDays(4);
    }

    public function everyFriday(): static
    {
        return $this->onDays(5);
    }

    public function everySaturday(): static
    {
        return $this->onDays(6);
    }

    public function everySunday(): static
    {
        return $this->onDays(0);
    }

    public function everyWeekday(): static
    {
        return $this->onDays(1, 2, 3, 4, 5);
    }

    public function everyWeekend(): static
    {
        return $this->onDays(6, 0);
    }

    /** Cron day-of-week numbers, Sunday = 0. */
    public function onDays(int ...$days): static
    {
        foreach ($days as $day) {
            $this->guardRange($day, 0, 7, 'day of week');
        }

        $existing = $this->isTouched('dayOfWeek') && $this->dayOfWeek !== '*'
            ? explode(',', $this->dayOfWeek)
            : [];

        $merged = array_values(array_unique([...$existing, ...array_map('strval', $days)]));
        sort($merged);

        $this->dayOfWeek = implode(',', $merged);
        $this->touch('dayOfWeek');

        return $this;
    }

    // ======================================================================
    // Escape hatch + timezone
    // ======================================================================

    /** Set the expression directly when the fluent form cannot say it. */
    public function cron(string $expression): static
    {
        if (! CronExpression::isValidExpression($expression)) {
            throw new InvalidArgumentException("Invalid cron expression: '{$expression}'");
        }

        $this->raw = $expression;

        return $this;
    }

    public function timezone(string $timezone): static
    {
        if (! in_array($timezone, timezone_identifiers_list(), true)) {
            throw new InvalidArgumentException("Unknown timezone: '{$timezone}'");
        }

        $this->timezone = $timezone;

        return $this;
    }

    // ======================================================================
    // Output
    // ======================================================================

    public function expression(): string
    {
        return $this->raw ?? implode(' ', [
            $this->minute,
            $this->hour,
            $this->dayOfMonth,
            $this->month,
            $this->dayOfWeek,
        ]);
    }

    public function getTimezone(): string
    {
        return $this->timezone;
    }

    /** True once anything has been said - an untouched schedule is not a schedule. */
    public function isDefined(): bool
    {
        return $this->raw !== null || $this->touched !== [];
    }

    public function toCronExpression(): CronExpression
    {
        return new CronExpression($this->expression());
    }

    // ======================================================================
    // Internals
    // ======================================================================

    private function step(?int $step, string $field, int $max): string
    {
        if ($step === null) {
            return '*';
        }

        $this->guardRange($step, 1, $max, $field.' step');

        return $step === 1 ? '*' : '*/'.$step;
    }

    private function touch(string $field): void
    {
        $this->touched[$field] = true;
    }

    private function isTouched(string $field): bool
    {
        return $this->touched[$field] ?? false;
    }

    /**
     * Adjust a coarser field the caller has not spoken about. everyMinute()
     * should mean every minute of every hour, not every minute of midnight.
     */
    private function widen(string $field, string $value): void
    {
        if ($this->isTouched($field)) {
            return;
        }

        $this->{$field} = $value;
    }

    private function guardRange(int $value, int $min, int $max, string $label): void
    {
        if ($value < $min || $value > $max) {
            throw new InvalidArgumentException("Expected {$label} between {$min} and {$max}, got {$value}");
        }
    }
}
