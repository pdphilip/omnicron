<?php

namespace PDPhilip\OmniCron\Store;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use PDPhilip\OmniCron\Job\CronJob;
use PDPhilip\OmniCron\Job\JobRow;
use PDPhilip\OmniCron\OmniTask;
use PDPhilip\OmniCron\Run\Run;
use PDPhilip\OmniCron\Run\RunRow;
use PDPhilip\OmniCron\Run\RunState;
use PDPhilip\OmniCron\Run\Trigger;

/**
 * Runs in Eloquent. Which Eloquent is the app's choice: the bundled Run
 * model (SQL, migration included) by default, or any model carrying
 * RunsLifecycle via config 'omnicron.model' - which is how a MongoDB app
 * keeps its run log in Mongo.
 */
class DatabaseStore implements RunStore
{
    public function job(OmniTask $task): JobRow
    {
        $class = config('omnicron.job_model', CronJob::class);

        return $class::query()->firstOrCreate(
            ['key' => $task->key()],
            ['class' => get_class($task), 'paused' => false],
        );
    }

    public function open(OmniTask $task, Trigger $trigger = Trigger::SCHEDULE): RunRow
    {
        $run = $this->model();
        $run->task = $task->key();
        $run->state = RunState::RUNNING;
        $run->started_at = time();
        $run->host = gethostname() ?: null;
        $run->trigger = $trigger->value;
        $run->manual = $trigger->isManual();
        $run->save();

        return $run;
    }

    public function lastStartFor(OmniTask $task): ?int
    {
        return $this->latestFor($task)?->started_at;
    }

    public function latestFor(OmniTask $task): ?RunRow
    {
        return $this->query()->where('task', $task->key())->orderByDesc('started_at')->first();
    }

    public function lastSuccessFor(OmniTask $task): ?RunRow
    {
        return $this->query()
            ->where('task', $task->key())
            ->where('state', RunState::OK->value)
            ->orderByDesc('started_at')
            ->first();
    }

    public function history(?OmniTask $task = null, int $limit = 50): Collection
    {
        return $this->query()
            ->when($task, fn ($query) => $query->where('task', $task->key()))
            ->orderByDesc('started_at')
            ->limit($limit)
            ->get();
    }

    public function prune(int $beforeTs): int
    {
        // RUNNING rows are kept whatever their age - an orphaned one is the
        // only evidence of a crash, and pruning it erases the incident.
        return $this->query()
            ->where('started_at', '<', $beforeTs)
            ->where('state', '!=', RunState::RUNNING->value)
            ->delete();
    }

    private function model(): Model
    {
        $class = config('omnicron.model', Run::class);

        return new $class;
    }

    private function query(): Builder
    {
        return $this->model()->newQuery();
    }
}
