<?php

namespace PDPhilip\OmniCron\Job;

use PDPhilip\OmniCron\Store\RedisStore;

/** The control row in Redis - one hash per task, no Eloquent. */
class RedisJob implements JobRow
{
    public function __construct(
        private readonly string $key,
        private bool $paused,
        private ?string $scheduleOverride,
        private readonly RedisStore $store,
    ) {}

    public function jobKey(): string
    {
        return $this->key;
    }

    public function isPaused(): bool
    {
        return $this->paused;
    }

    public function scheduleOverride(): ?string
    {
        return $this->scheduleOverride;
    }

    public function pause(): void
    {
        $this->paused = true;
        $this->store->saveJob($this);
    }

    public function resume(): void
    {
        $this->paused = false;
        $this->store->saveJob($this);
    }

    public function overrideSchedule(?string $expression): void
    {
        $this->scheduleOverride = $expression;
        $this->store->saveJob($this);
    }

    public function toArray(): array
    {
        return [
            'paused' => $this->paused ? '1' : '0',
            'schedule_override' => $this->scheduleOverride ?? '',
        ];
    }
}
