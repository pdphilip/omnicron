<?php

namespace PDPhilip\OmniCron\Run;

/**
 * The run lifecycle, extracted so any Eloquent flavour can be the log.
 *
 * A MongoDB app makes its own model the run row in five lines:
 *
 *   use MongoDB\Laravel\Eloquent\Model;
 *   use PDPhilip\OmniCron\Run\RunsLifecycle;
 *
 *   class OmniCronRun extends Model
 *   {
 *       use RunsLifecycle;
 *   }
 *
 * and points config('omnicron.model') at it. Collections are schemaless, so
 * no migration is needed - declare indexes on (task, started_at) however
 * your app manages them.
 */
trait RunsLifecycle
{
    public function initializeRunsLifecycle(): void
    {
        $this->mergeCasts([
            'state' => RunState::class,
            'output' => 'array',
            'manual' => 'boolean',
            'started_at' => 'integer',
            'finished_at' => 'integer',
            'duration_ms' => 'integer',
        ]);
        $this->guarded = [];
    }

    public function getTable(): string
    {
        return config('omnicron.table', 'omnicron_runs');
    }

    public function succeed(array $output, float $startedMicrotime): void
    {
        $this->closeRun(RunState::OK, $startedMicrotime);
        $this->output = $output;
        $this->persistClose();
    }

    public function fail(string $error, float $startedMicrotime): void
    {
        $this->closeRun(RunState::FAILED, $startedMicrotime);
        $this->error = mb_substr($error, 0, 2000);
        $this->persistClose();
    }

    private function closeRun(RunState $state, float $startedMicrotime): void
    {
        $this->state = $state;
        $this->finished_at = time();
        $this->duration_ms = (int) round((microtime(true) - $startedMicrotime) * 1000);
    }

    /**
     * How the close reaches storage. A plain save for most flavours; stores
     * where a second save on the same instance is unsafe (Elasticsearch's
     * stale sequence numbers) override this with a targeted update.
     */
    protected function persistClose(): void
    {
        $this->save();
    }

    public function durationLabel(): ?string
    {
        if ($this->duration_ms === null) {
            return null;
        }
        if ($this->duration_ms < 1000) {
            return $this->duration_ms.'ms';
        }

        return round($this->duration_ms / 1000, 1).'s';
    }
}
