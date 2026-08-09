<?php

namespace PDPhilip\OmniCron\Store;

use Illuminate\Support\Collection;
use PDPhilip\OmniCron\OmniTask;
use PDPhilip\OmniCron\Run\Run;
use PDPhilip\OmniCron\Run\RunState;

class DatabaseStore implements RunStore
{
    public function open(OmniTask $task, bool $manual = false): Run
    {
        $run = new Run;
        $run->task = $task->key();
        $run->state = RunState::RUNNING;
        $run->started_at = time();
        $run->host = gethostname() ?: null;
        $run->manual = $manual;
        $run->save();

        return $run;
    }

    public function lastStartFor(OmniTask $task): ?int
    {
        return $this->latestFor($task)?->started_at;
    }

    public function latestFor(OmniTask $task): ?Run
    {
        return Run::query()->where('task', $task->key())->orderByDesc('started_at')->first();
    }

    public function lastSuccessFor(OmniTask $task): ?Run
    {
        return Run::query()
            ->where('task', $task->key())
            ->where('state', RunState::OK->value)
            ->orderByDesc('started_at')
            ->first();
    }

    public function history(?OmniTask $task = null, int $limit = 50): Collection
    {
        return Run::query()
            ->when($task, fn ($query) => $query->where('task', $task->key()))
            ->orderByDesc('started_at')
            ->limit($limit)
            ->get();
    }

    public function prune(int $beforeTs): int
    {
        // RUNNING rows are kept whatever their age - an orphaned one is the
        // only evidence of a crash, and pruning it erases the incident.
        return Run::query()
            ->where('started_at', '<', $beforeTs)
            ->where('state', '!=', RunState::RUNNING->value)
            ->delete();
    }
}
