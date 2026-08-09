<?php

namespace PDPhilip\OmniCron\Run;

use Illuminate\Database\Eloquent\Model;

/**
 * One run of one task - the durable log row.
 *
 * The row is opened in RUNNING before the work begins and closed by
 * succeed()/fail() afterwards. A run that never closes is the crash
 * evidence the try/catch cannot produce.
 *
 * @property int $id
 * @property string $task
 * @property RunState $state
 * @property int $started_at
 * @property int|null $finished_at
 * @property int|null $duration_ms
 * @property array|null $output
 * @property string|null $error
 * @property string|null $host
 * @property bool $manual
 */
class Run extends Model
{
    protected $guarded = [];

    protected $casts = [
        'state' => RunState::class,
        'output' => 'array',
        'manual' => 'boolean',
        'started_at' => 'integer',
        'finished_at' => 'integer',
        'duration_ms' => 'integer',
    ];

    public function getTable(): string
    {
        return config('omnicron.table', 'omnicron_runs');
    }

    public function succeed(array $output, float $startedMicrotime): void
    {
        $this->close(RunState::OK, $startedMicrotime);
        $this->output = $output;
        $this->save();
    }

    public function fail(string $error, float $startedMicrotime): void
    {
        $this->close(RunState::FAILED, $startedMicrotime);
        $this->error = mb_substr($error, 0, 2000);
        $this->save();
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
        if ($this->duration_ms < 1000) {
            return $this->duration_ms.'ms';
        }

        return round($this->duration_ms / 1000, 1).'s';
    }
}
