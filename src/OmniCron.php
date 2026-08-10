<?php

namespace PDPhilip\OmniCron;

use Cron\CronExpression;
use Illuminate\Support\Facades\Cache;
use PDPhilip\OmniCron\Run\RunState;
use PDPhilip\OmniCron\Run\Trigger;
use PDPhilip\OmniCron\Store\RunStore;
use Throwable;

/**
 * The engine. One tick (from a heartbeat url, the artisan command, or your
 * own kernel schedule) checks every registered task, runs whatever is due,
 * and logs each run durably.
 *
 * The semantics that matter, all deliberate:
 *
 * - Due-ness is measured from the last START, not the last finish - a task
 *   that keeps crashing is not retried on every tick, and a missed minute
 *   fires late rather than skipping the slot.
 * - The run row is written BEFORE the work starts. A task that throws can
 *   report itself; one that is OOM-killed cannot - it leaves a RUNNING row,
 *   which is what stuck-detection reads.
 * - Locks are per task, not global, so a slow report does not block a cheap
 *   purge. Multi-VM safe on any atomic-lock cache store.
 * - environments() gates the tick, never the manual run - anything that
 *   emails people or spends money declares where it may fire, and a human
 *   pressing the button is explicit intent.
 */
class OmniCron
{
    public function __construct(
        private readonly RunStore $store,
    ) {}

    // ======================================================================
    // Registry
    // ======================================================================

    /** @return array<int, OmniTask> */
    public function tasks(): array
    {
        return array_map(
            fn (string $class) => app($class),
            config('omnicron.tasks', []),
        );
    }

    public function find(string $key): ?OmniTask
    {
        foreach ($this->tasks() as $task) {
            if ($task->key() === $key) {
                return $task;
            }
        }

        return null;
    }

    // ======================================================================
    // Due-ness
    // ======================================================================

    /**
     * A task is due when its schedule has come round since it last started.
     * Never-run tasks are due immediately.
     */
    public function isDue(OmniTask $task, ?int $now = null): bool
    {
        $now ??= time();
        $last = $this->store->lastStartFor($task);

        if ($last === null) {
            return true;
        }

        $expression = new CronExpression($this->expressionFor($task));
        $nextAfterLast = $expression
            ->getNextRunDate('@'.$last, 0, false, $task->timezone())
            ->getTimestamp();

        return $nextAfterLast <= $now;
    }

    /**
     * The schedule that actually rules: an operator's stored override wins
     * over the code (an invalid override is ignored rather than obeyed);
     * otherwise the task class speaks.
     */
    public function expressionFor(OmniTask $task): string
    {
        $override = $this->store->job($task)->scheduleOverride();

        if ($override && CronExpression::isValidExpression($override)) {
            return $override;
        }

        return $task->expression();
    }

    /** @return array<int, OmniTask> */
    public function due(?int $now = null): array
    {
        $due = [];
        foreach ($this->tasks() as $task) {
            if (! $this->allowedHere($task)) {
                continue;
            }
            // Paused gates the tick only - a manual run is explicit intent.
            if ($this->store->job($task)->isPaused()) {
                continue;
            }
            if ($this->isDue($task, $now)) {
                $due[] = $task;
            }
        }

        return $due;
    }

    private function allowedHere(OmniTask $task): bool
    {
        $environments = $task->environments();

        return $environments === null || in_array(app()->environment(), $environments, true);
    }

    // ======================================================================
    // Running
    // ======================================================================

    /**
     * Run one task now. Returns a result array that says what happened -
     * including 'locked' when another machine already holds it (no row is
     * written for that; nothing ran).
     *
     * @return array<string, mixed>
     */
    public function run(OmniTask $task, Trigger $trigger = Trigger::SCHEDULE): array
    {
        $lock = Cache::store(config('omnicron.cache_store'))
            ->lock(config('omnicron.lock_prefix', 'omnicron:task:').$task->key(), $task->lockSeconds());

        if (! $lock->get()) {
            return ['task' => $task->key(), 'ran' => false, 'state' => 'locked'];
        }

        // Won the lock - but a parallel tick may have fully run a fast task
        // between our due check and now, releasing its lock already. Recheck
        // against the store (last start moved) so simultaneous ticks from
        // parallel machines cannot double-fire. Manual runs skip this - they
        // ignore the schedule by design.
        if ($trigger === Trigger::SCHEDULE && ! $this->isDue($task)) {
            $lock->release();

            return ['task' => $task->key(), 'ran' => false, 'state' => 'already_ran'];
        }

        // Claimed before the work starts - an unfinished run must leave a row.
        $run = $this->store->open($task, $trigger);
        $started = microtime(true);

        try {
            $output = $task->execute();
            $run->succeed($output, $started);

            return [
                'task' => $task->key(),
                'ran' => true,
                'state' => RunState::OK->value,
                'duration_ms' => $run->duration_ms,
                'output' => $output,
            ];
        } catch (Throwable $e) {
            $run->fail($e->getMessage(), $started);

            return [
                'task' => $task->key(),
                'ran' => true,
                'state' => RunState::FAILED->value,
                'duration_ms' => $run->duration_ms,
                'error' => $e->getMessage(),
            ];
        } finally {
            $lock->release();
        }
    }

    /**
     * The heartbeat. Hit it every minute from anywhere; it decides what runs.
     *
     * @return array<string, mixed>
     */
    public function tick(): array
    {
        $due = $this->due();
        $results = [];
        $failed = 0;

        foreach ($due as $task) {
            $result = $this->run($task);
            $results[] = $result;
            if (($result['state'] ?? null) === RunState::FAILED->value) {
                $failed++;
            }
        }

        return [
            'checked' => count($this->tasks()),
            'due' => count($due),
            'ran' => count(array_filter($results, fn ($r) => $r['ran'])),
            'failed' => $failed,
            'tasks' => $results,
        ];
    }

    // ======================================================================
    // Health
    // ======================================================================

    /**
     * Per-task health. Staleness measures from the last SUCCESSFUL start -
     * a task can be running and failing all day; that is not healthy.
     *
     * @return array<string, mixed>
     */
    public function status(): array
    {
        $now = time();
        $rows = [];
        $stale = 0;
        $stuck = 0;

        foreach ($this->tasks() as $task) {
            $job = $this->store->job($task);
            $latest = $this->store->latestFor($task);
            $lastOk = $this->store->lastSuccessFor($task);
            $sinceOk = $lastOk ? $now - $lastOk->started_at : null;

            $isStale = $sinceOk === null || $sinceOk > $this->staleAfterSeconds($task);
            $isStuck = $latest !== null
                && $latest->state === RunState::RUNNING
                && ($now - $latest->started_at) > $task->lockSeconds();

            $stale += $isStale ? 1 : 0;
            $stuck += $isStuck ? 1 : 0;

            $rows[] = [
                'task' => $task->key(),
                'label' => $task->label(),
                'description' => $task->description(),
                'schedule' => $this->expressionFor($task),
                'schedule_overridden' => $job->scheduleOverride() !== null,
                'schedule_in_code' => $task->expression(),
                'paused' => $job->isPaused(),
                'timezone' => $task->timezone(),
                'last_state' => $latest?->state->value,
                'last_success_at' => $lastOk?->started_at,
                'last_duration_ms' => $latest?->duration_ms,
                'is_due' => $this->isDue($task, $now),
                'is_stale' => $isStale,
                'is_stuck' => $isStuck,
            ];
        }

        return ['tasks' => $rows, 'stale' => $stale, 'stuck' => $stuck];
    }

    /**
     * A task's staleness threshold. When it does not declare one, derive
     * double the scheduled interval - staleness must exceed one interval or
     * a healthy task reads as permanently overdue.
     */
    public function staleAfterSeconds(OmniTask $task): int
    {
        $declared = $task->staleAfterSeconds();
        if ($declared !== null) {
            return $declared;
        }

        return $this->scheduledInterval($task) * 2;
    }

    /** Seconds between two consecutive scheduled runs. */
    public function scheduledInterval(OmniTask $task): int
    {
        $expression = new CronExpression($this->expressionFor($task));
        $first = $expression->getNextRunDate('now', 0, false, $task->timezone());
        $second = $expression->getNextRunDate($first, 0, false, $task->timezone());

        return max(60, $second->getTimestamp() - $first->getTimestamp());
    }

    /** When the schedule next comes round, as a unix timestamp. */
    public function nextRunAt(OmniTask $task, ?int $now = null): int
    {
        $expression = new CronExpression($this->expressionFor($task));

        return $expression
            ->getNextRunDate('@'.($now ?? time()), 0, false, $task->timezone())
            ->getTimestamp();
    }

    // ======================================================================
    // Operator controls
    // ======================================================================

    public function pause(OmniTask $task): void
    {
        $this->store->job($task)->pause();
    }

    public function resume(OmniTask $task): void
    {
        $this->store->job($task)->resume();
    }

    /**
     * Store an operator schedule, or null to restore the code's. Invalid
     * expressions throw here rather than silently breaking due-ness.
     */
    public function overrideSchedule(OmniTask $task, ?string $expression): void
    {
        if ($expression !== null && ! CronExpression::isValidExpression($expression)) {
            throw new \InvalidArgumentException('Not a valid cron expression: '.$expression);
        }

        $this->store->job($task)->overrideSchedule($expression);
    }

    public function store(): RunStore
    {
        return $this->store;
    }
}
