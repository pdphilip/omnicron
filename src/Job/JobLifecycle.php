<?php

namespace PDPhilip\OmniCron\Job;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * The control-row behaviour, extracted so any Eloquent flavour can be the
 * registry - the same pattern as RunsLifecycle on the log side. A row is
 * find-or-created per registered task by the store, keyed by the task's key.
 */
trait JobLifecycle
{
    public function initializeJobLifecycle(): void
    {
        $this->mergeCasts(['paused' => 'boolean']);
        $this->guarded = [];
    }

    public function getTable(): string
    {
        return config('omnicron.jobs_table', 'omnicron_jobs');
    }

    public function jobKey(): string
    {
        return $this->key;
    }

    public function isPaused(): bool
    {
        return (bool) $this->paused;
    }

    public function scheduleOverride(): ?string
    {
        return $this->schedule_override ?: null;
    }

    public function pause(): void
    {
        $this->paused = true;
        $this->save();
    }

    public function resume(): void
    {
        $this->paused = false;
        $this->save();
    }

    public function overrideSchedule(?string $expression): void
    {
        $this->schedule_override = $expression;
        $this->save();
    }

    // ======================================================================
    // The relationship the registry exists for
    // ======================================================================

    /** Every run this job has ever logged, through whichever log model is configured. */
    public function runs(): HasMany
    {
        return $this->hasMany(config('omnicron.model'), 'task', 'key')->orderByDesc('started_at');
    }

    public function latestRun(): HasOne
    {
        return $this->hasOne(config('omnicron.model'), 'task', 'key')->latest('started_at');
    }
}
