<?php

namespace PDPhilip\OmniCron\Job;

/**
 * The control row for one registered task - the operational layer OVER the
 * code. The task class stays the source of truth for what runs and when;
 * this row is where an operator pauses it or overrides its schedule without
 * a deploy. Eloquent flavours satisfy this through JobLifecycle; the Redis
 * store's rows implement it directly.
 */
interface JobRow
{
    public function jobKey(): string;

    public function isPaused(): bool;

    /** The operator's cron expression, or null when the code's schedule rules. */
    public function scheduleOverride(): ?string;

    public function pause(): void;

    public function resume(): void;

    /** Null restores the code-defined schedule. */
    public function overrideSchedule(?string $expression): void;
}
