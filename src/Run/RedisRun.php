<?php

namespace PDPhilip\OmniCron\Run;

use PDPhilip\OmniCron\Store\RedisStore;

/**
 * One run living in Redis - a plain value object, no Eloquent. Closing it
 * writes itself back through the store, which rewrites its list entry.
 */
class RedisRun implements RunRow
{
    public function __construct(
        public string $id,
        public string $task,
        public RunState $state,
        public int $started_at,
        public ?int $finished_at = null,
        public ?int $duration_ms = null,
        public ?array $output = null,
        public ?string $error = null,
        public ?string $host = null,
        public ?string $trigger = null,
        public bool $manual = false,
        private ?RedisStore $store = null,
    ) {}

    public function succeed(array $output, float $startedMicrotime): void
    {
        $this->close(RunState::OK, $startedMicrotime);
        $this->output = $output;
        $this->store?->rewrite($this);
    }

    public function fail(string $error, float $startedMicrotime): void
    {
        $this->close(RunState::FAILED, $startedMicrotime);
        $this->error = mb_substr($error, 0, 2000);
        $this->store?->rewrite($this);
    }

    private function close(RunState $state, float $startedMicrotime): void
    {
        $this->state = $state;
        $this->finished_at = time();
        $this->duration_ms = (int) round((microtime(true) - $startedMicrotime) * 1000);
    }

    public function durationLabel(): ?string
    {
        if ($this->duration_ms === null) {
            return null;
        }

        return $this->duration_ms < 1000 ? $this->duration_ms.'ms' : round($this->duration_ms / 1000, 1).'s';
    }

    public function getKey()
    {
        return $this->id;
    }

    // ======================================================================
    // Wire format
    // ======================================================================

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'task' => $this->task,
            'state' => $this->state->value,
            'started_at' => $this->started_at,
            'finished_at' => $this->finished_at,
            'duration_ms' => $this->duration_ms,
            'output' => $this->output,
            'error' => $this->error,
            'host' => $this->host,
            'trigger' => $this->trigger,
            'manual' => $this->manual,
        ];
    }

    public static function fromArray(array $data, ?RedisStore $store = null): self
    {
        return new self(
            id: $data['id'],
            task: $data['task'],
            state: RunState::from($data['state']),
            started_at: (int) $data['started_at'],
            finished_at: $data['finished_at'] ?? null,
            duration_ms: $data['duration_ms'] ?? null,
            output: $data['output'] ?? null,
            error: $data['error'] ?? null,
            host: $data['host'] ?? null,
            trigger: $data['trigger'] ?? null,
            manual: (bool) ($data['manual'] ?? false),
            store: $store,
        );
    }
}
