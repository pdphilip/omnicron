<?php

namespace PDPhilip\OmniCron\Run;

/**
 * One run, wherever it lives. Eloquent flavours (SQL, Mongo, ES) satisfy
 * this through RunsLifecycle; the Redis store's rows implement it directly.
 * The engine only ever touches this surface.
 *
 * @property string $task
 * @property RunState $state
 * @property int $started_at
 * @property int|null $finished_at
 * @property int|null $duration_ms
 * @property array|null $output
 * @property string|null $error
 * @property string|null $host
 * @property ?string $trigger
 * @property bool $manual
 */
interface RunRow
{
    public function succeed(array $output, float $startedMicrotime): void;

    public function fail(string $error, float $startedMicrotime): void;

    public function durationLabel(): ?string;

    /** No return type - Eloquent's own getKey() declares none. */
    public function getKey();
}
