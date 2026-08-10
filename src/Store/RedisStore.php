<?php

namespace PDPhilip\OmniCron\Store;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;
use PDPhilip\OmniCron\Job\JobRow;
use PDPhilip\OmniCron\Job\RedisJob;
use PDPhilip\OmniCron\OmniTask;
use PDPhilip\OmniCron\Run\RedisRun;
use PDPhilip\OmniCron\Run\RunRow;
use PDPhilip\OmniCron\Run\RunState;
use PDPhilip\OmniCron\Run\Trigger;

/**
 * Runs in Redis - the Horizon way: zero migrations, nothing to install,
 * history capped per task rather than kept.
 *
 * Know what you are trading. Shared managed Redis usually runs an eviction
 * policy, and a run log is exactly the kind of data it evicts first under
 * memory pressure - i.e. the log vanishes when a backed-up queue means you
 * most need it. Perfect for trying the package and for teams that treat run
 * history as recent-only; graduate to a durable store when "what did it
 * return three weeks ago" starts mattering.
 *
 * Layout: one capped list per task (newest first) of JSON rows. The RUNNING
 * row is pushed BEFORE the work starts, same as every other store - the
 * crash evidence survives as long as the list does.
 */
class RedisStore implements RunStore
{
    public function job(OmniTask $task): JobRow
    {
        $data = $this->redis()->hgetall($this->jobKey($task->key())) ?: [];

        return new RedisJob(
            key: $task->key(),
            paused: ($data['paused'] ?? '0') === '1',
            scheduleOverride: ($data['schedule_override'] ?? '') ?: null,
            store: $this,
        );
    }

    public function saveJob(RedisJob $job): void
    {
        $this->redis()->hmset($this->jobKey($job->jobKey()), $job->toArray());
    }

    private function jobKey(string $taskKey): string
    {
        return config('omnicron.redis.job_prefix', 'omnicron:jobs:').$taskKey;
    }

    public function open(OmniTask $task, Trigger $trigger = Trigger::SCHEDULE): RunRow
    {
        $run = new RedisRun(
            id: (string) Str::uuid(),
            task: $task->key(),
            state: RunState::RUNNING,
            started_at: time(),
            host: gethostname() ?: null,
            trigger: $trigger->value,
            manual: $trigger->isManual(),
            store: $this,
        );

        $key = $this->key($task->key());
        $this->redis()->lpush($key, json_encode($run->toArray()));
        $this->redis()->ltrim($key, 0, $this->maxRuns() - 1);

        return $run;
    }

    /** A closed run rewrites its own list entry, found by id. */
    public function rewrite(RedisRun $run): void
    {
        $key = $this->key($run->task);
        $rows = $this->redis()->lrange($key, 0, -1);

        foreach ($rows as $index => $json) {
            $decoded = json_decode($json, true);
            if (($decoded['id'] ?? null) === $run->id) {
                $this->redis()->lset($key, $index, json_encode($run->toArray()));

                return;
            }
        }
        // Trimmed away while running (a very busy task on a very small cap) -
        // the close is dropped with the row, which is the cap's contract.
    }

    public function lastStartFor(OmniTask $task): ?int
    {
        return $this->latestFor($task)?->started_at;
    }

    public function latestFor(OmniTask $task): ?RunRow
    {
        $json = $this->redis()->lindex($this->key($task->key()), 0);

        return $json ? RedisRun::fromArray(json_decode($json, true), $this) : null;
    }

    public function lastSuccessFor(OmniTask $task): ?RunRow
    {
        foreach ($this->rows($task->key()) as $run) {
            if ($run->state === RunState::OK) {
                return $run;
            }
        }

        return null;
    }

    public function history(?OmniTask $task = null, int $limit = 50): Collection
    {
        $rows = $task
            ? $this->rows($task->key())
            : $this->allRows();

        return $rows->sortByDesc('started_at')->take($limit)->values();
    }

    public function prune(int $beforeTs): int
    {
        $removed = 0;
        foreach ($this->taskKeys() as $key) {
            foreach ($this->redis()->lrange($key, 0, -1) as $json) {
                $decoded = json_decode($json, true);
                $finished = ($decoded['state'] ?? '') !== RunState::RUNNING->value;
                if ($finished && ($decoded['started_at'] ?? 0) < $beforeTs) {
                    $removed += (int) $this->redis()->lrem($key, 1, $json);
                }
            }
        }

        return $removed;
    }

    // ======================================================================
    // Plumbing
    // ======================================================================

    /** @return Collection<int, RedisRun> */
    private function rows(string $taskKey): Collection
    {
        return collect($this->redis()->lrange($this->key($taskKey), 0, -1))
            ->map(fn ($json) => RedisRun::fromArray(json_decode($json, true), $this));
    }

    /** @return Collection<int, RedisRun> */
    private function allRows(): Collection
    {
        return collect($this->taskKeys())
            ->flatMap(fn ($key) => collect($this->redis()->lrange($key, 0, -1)))
            ->map(fn ($json) => RedisRun::fromArray(json_decode($json, true), $this));
    }

    /** @return array<int, string> */
    private function taskKeys(): array
    {
        $prefix = config('database.redis.options.prefix', '');
        $keys = $this->redis()->keys($this->key('*'));

        // phpredis returns keys WITH the connection prefix; commands add it
        // again, so strip it before reuse.
        return array_map(
            fn ($key) => $prefix && str_starts_with($key, $prefix) ? substr($key, strlen($prefix)) : $key,
            $keys,
        );
    }

    private function key(string $taskKey): string
    {
        return config('omnicron.redis.key_prefix', 'omnicron:runs:').$taskKey;
    }

    private function maxRuns(): int
    {
        return (int) config('omnicron.redis.max_runs', 200);
    }

    private function redis()
    {
        return Redis::connection(config('omnicron.redis.connection'));
    }
}
