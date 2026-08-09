<?php

namespace PDPhilip\OmniCron;

use Illuminate\Support\Str;
use PDPhilip\OmniCron\Schedule\Schedule;

/**
 * One scheduled task.
 *
 *   class PurgeSessions extends OmniTask
 *   {
 *       public function schedule(Schedule $schedule): void
 *       {
 *           $schedule->everyMonday()->everyHour(5)->everyMinute(10);
 *       }
 *
 *       public function execute(): array
 *       {
 *           return ['deleted' => Session::purgeExpired()];
 *       }
 *   }
 *
 * WHAT YOU RETURN IS THE LOG. The array from execute() is stored as JSON on
 * the run, which is why it should say what happened rather than that it
 * happened. 'deleted => 0' is a useful fact; an empty return tells you
 * nothing three weeks later when you are trying to work out when a number
 * started drifting.
 *
 * Throwing marks the run failed and records the message. Do not catch and
 * return an error shape - a failure that reports itself as success is worse
 * than a crash.
 */
abstract class OmniTask
{
    /** Cached so repeated reads during a tick do not rebuild the expression. */
    private ?Schedule $resolved = null;

    /** Describe when this runs. */
    abstract public function schedule(Schedule $schedule): void;

    /**
     * Do the work. The returned array is serialised to JSON and stored.
     *
     * @return array<string, mixed>
     */
    abstract public function execute(): array;

    // ======================================================================
    // Identity
    // ======================================================================

    /**
     * Stable key used for locking, log rows and urls. Derived from the class
     * name, so renaming a task starts a new history - override to keep one.
     */
    public function key(): string
    {
        return Str::kebab(class_basename(static::class));
    }

    public function label(): string
    {
        return Str::headline(class_basename(static::class));
    }

    public function description(): ?string
    {
        return null;
    }

    // ======================================================================
    // Policy - override per task
    // ======================================================================

    /**
     * How long one run may hold its lock. A run outliving this is presumed
     * dead and may be retried, so set it well above the slowest honest run.
     */
    public function lockSeconds(): int
    {
        return 300;
    }

    /**
     * Flag the task unhealthy when it has not succeeded in this long. Must
     * exceed one interval, or a healthy task reads as permanently overdue.
     * Null derives it from the schedule.
     */
    public function staleAfterSeconds(): ?int
    {
        return null;
    }

    /**
     * Environments this task may run in from a tick. Anything that emails
     * people or spends money belongs in production only - a scheduler that
     * behaves identically everywhere will eventually mail your customers
     * from a laptop. Manual runs ignore this.
     *
     * @return array<int, string>|null null means anywhere
     */
    public function environments(): ?array
    {
        return null;
    }

    /**
     * Run through the queue instead of inline in the tick request.
     * Reserved: v0 runs every task inline - queued dispatch is on the
     * roadmap, and declaring it now keeps task classes forward-compatible.
     */
    public function queued(): bool
    {
        return false;
    }

    // ======================================================================
    // Resolution
    // ======================================================================

    public function resolveSchedule(): Schedule
    {
        if ($this->resolved instanceof Schedule) {
            return $this->resolved;
        }

        $schedule = new Schedule;
        $this->schedule($schedule);

        return $this->resolved = $schedule;
    }

    public function expression(): string
    {
        return $this->resolveSchedule()->expression();
    }

    public function timezone(): string
    {
        return $this->resolveSchedule()->getTimezone();
    }
}
