<?php

namespace PDPhilip\OmniCron\Store;

use Illuminate\Support\Collection;
use PDPhilip\OmniCron\OmniTask;
use PDPhilip\OmniCron\Run\RunRow;

/**
 * Where run history lives. The database driver is the default - durable and
 * queryable, which is the point of keeping a log at all. The interface stays
 * narrow so other homes (Redis for zero-migration installs, a remote
 * collector) can slot in without touching the runner.
 *
 * Rows are typed as RunRow - the surface the engine touches - because the
 * home is swappable: any Eloquent flavour via config 'omnicron.model', or
 * the Redis store's own rows.
 */
interface RunStore
{
    /** Claim the run BEFORE the work starts - a crash must leave a trace. */
    public function open(OmniTask $task, bool $manual = false): RunRow;

    /**
     * The unix timestamp the task last STARTED, including failed and
     * abandoned runs - due-ness measures from here, so a task that keeps
     * dying is not retried on every tick.
     */
    public function lastStartFor(OmniTask $task): ?int;

    public function latestFor(OmniTask $task): ?RunRow;

    public function lastSuccessFor(OmniTask $task): ?RunRow;

    /** @return Collection<int, RunRow> newest first */
    public function history(?OmniTask $task = null, int $limit = 50): Collection;

    /** Delete finished runs that started before the timestamp. Returns rows removed. */
    public function prune(int $beforeTs): int;
}
