<?php

namespace PDPhilip\OmniCron\Http;

use Cron\CronExpression;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use PDPhilip\OmniCron\OmniCron;
use PDPhilip\OmniCron\OmniTask;
use PDPhilip\OmniCron\Run\RunState;
use PDPhilip\OmniCron\Run\Trigger;
use PDPhilip\OmniCron\Schedule\CronWords;

/**
 * The dashboard: one self-contained page plus the JSON it polls. Everything
 * a row shows is shaped here - the page renders strings, it decides nothing.
 * Served entirely by the package (Horizon-style), so it works identically
 * whatever the host app is built with.
 */
class DashboardController
{
    public function __construct(
        private readonly OmniCron $omnicron,
    ) {}

    public function index(): View
    {
        return view('omnicron::dashboard');
    }

    /** Health per task, dashboard-shaped. */
    public function overview(): JsonResponse
    {
        $now = time();
        $status = $this->omnicron->status();

        $tasks = array_map(function (array $row) use ($now) {
            $task = $this->omnicron->find($row['task']);
            $nextRunAt = $this->omnicron->nextRunAt($task, $now);

            return [
                ...$row,
                'health' => $this->health($row),
                'health_label' => $this->healthLabel($row),
                'schedule_words' => CronWords::toWords($row['schedule'], $row['timezone']),
                'last_success_ago' => $row['last_success_at'] ? $this->ago($now - $row['last_success_at']) : null,
                'next_run_at' => $nextRunAt,
                'next_run_in' => $this->in($nextRunAt - $now),
                'duration_label' => $this->duration($row['last_duration_ms']),
                'uptime' => $this->uptime($task, $row, $now),
                'last_runs' => $this->lastRuns($task),
            ];
        }, $status['tasks']);

        return response()->json([
            'tasks' => $tasks,
            'stale' => $status['stale'],
            'stuck' => $status['stuck'],
            'generated_at' => date('H:i:s', $now),
        ]);
    }

    /** The run log, newest first, optionally one task. */
    public function runs(Request $request): JsonResponse
    {
        $task = $request->query('task') ? $this->omnicron->find($request->query('task')) : null;
        $runs = $this->omnicron->store()->history($task, 50);
        $now = time();

        return response()->json([
            'runs' => $runs->map(fn ($run) => [
                'id' => (string) $run->getKey(),
                'task' => $run->task,
                'label' => $this->omnicron->find($run->task)?->label() ?? $run->task,
                'state' => $run->state->value,
                'state_label' => $run->state->label(),
                'when' => $this->ago($now - $run->started_at).' ago',
                'started_ts' => $run->started_at,
                'started_at' => date('Y-m-d H:i:s', $run->started_at),
                'duration_ms' => $run->duration_ms,
                'duration_label' => $run->durationLabel(),
                'output' => $run->output,
                'error' => $run->error,
                'host' => $run->host,
                'trigger' => $run->trigger,
                'manual' => (bool) $run->manual,
            ])->values(),
        ]);
    }

    /**
     * Upcoming executions, soonest first - what FastCron calls the queue.
     * Paused tasks schedule nothing, so they do not appear.
     */
    public function queue(Request $request): JsonResponse
    {
        $only = $request->query('task');
        $upcoming = [];

        foreach ($this->omnicron->tasks() as $task) {
            if ($only && $task->key() !== $only) {
                continue;
            }
            if ($this->omnicron->store()->job($task)->isPaused()) {
                continue;
            }

            $expression = new CronExpression($this->omnicron->expressionFor($task));
            foreach ($expression->getMultipleRunDates(12, 'now', false, false, $task->timezone()) as $date) {
                $upcoming[] = [
                    'task' => $task->key(),
                    'label' => $task->label(),
                    'execute_ts' => $date->getTimestamp(),
                    'execute_at' => gmdate('Y-m-d H:i:s', $date->getTimestamp()),
                ];
            }
        }

        usort($upcoming, fn (array $a, array $b) => $a['execute_ts'] <=> $b['execute_ts']);

        return response()->json(['queue' => array_slice($upcoming, 0, $only ? 12 : 20)]);
    }

    /** Fire one task from the dashboard - explicit intent, so schedule and environment gates are bypassed. */
    public function run(string $task): JsonResponse
    {
        $found = $this->omnicron->find($task);

        if (! $found) {
            return response()->json(['error' => 'Unknown task: '.$task], 404);
        }

        return response()->json($this->omnicron->run($found, Trigger::DASHBOARD));
    }

    /**
     * Operator controls: pause/resume, or override the schedule without a
     * deploy (empty override restores the code's).
     */
    public function updateJob(Request $request, string $task): JsonResponse
    {
        $found = $this->omnicron->find($task);

        if (! $found) {
            return response()->json(['error' => 'Unknown task: '.$task], 404);
        }

        if ($request->has('paused')) {
            $request->boolean('paused') ? $this->omnicron->pause($found) : $this->omnicron->resume($found);
        }

        if ($request->has('schedule_override')) {
            $expression = trim((string) $request->input('schedule_override')) ?: null;
            try {
                $this->omnicron->overrideSchedule($found, $expression);
            } catch (\InvalidArgumentException $e) {
                return response()->json(['error' => $e->getMessage()], 422);
            }
        }

        return response()->json(['ok' => true]);
    }

    // ======================================================================
    // Row rendering
    // ======================================================================

    /** Worst thing wins - except paused, which is a chosen state, not a bad one. */
    private function health(array $row): string
    {
        return match (true) {
            $row['paused'] => 'idle',
            $row['is_stuck'] => 'danger',
            $row['last_state'] === 'failed' => 'danger',
            $row['last_state'] === null => 'idle',
            $row['is_stale'] => 'warning',
            default => 'ok',
        };
    }

    private function healthLabel(array $row): string
    {
        return match (true) {
            $row['paused'] => 'Paused',
            $row['is_stuck'] => 'Stuck mid-run',
            $row['last_state'] === 'failed' => 'Last run failed',
            $row['last_state'] === null => 'Never run',
            $row['is_stale'] => 'Overdue',
            default => 'Healthy',
        };
    }

    /**
     * FastCron's UP badge: how long since the task last failed - or since
     * its oldest retained run when nothing ever has. A task whose latest
     * run failed is DOWN, measured from that failure.
     */
    private function uptime(OmniTask $task, array $row, int $now): ?array
    {
        $lastFailure = $this->omnicron->store()->lastFailureFor($task);

        if ($row['last_state'] === RunState::FAILED->value && $lastFailure) {
            return ['up' => false, 'label' => $this->ago($now - $lastFailure->started_at)];
        }

        $since = $lastFailure?->started_at ?? $this->omnicron->store()->firstStartFor($task);

        return $since ? ['up' => true, 'label' => $this->ago($now - $since)] : null;
    }

    /** The strip: the last dozen runs, oldest first, as colored ticks. */
    private function lastRuns(OmniTask $task): array
    {
        return $this->omnicron->store()->history($task, 12)
            ->reverse()
            ->map(fn ($run) => [
                'state' => $run->state->value,
                'started_ts' => $run->started_at,
                'duration_ms' => $run->duration_ms,
            ])
            ->values()
            ->all();
    }

    private function duration(?int $ms): ?string
    {
        if ($ms === null) {
            return null;
        }

        return $ms < 1000 ? $ms.'ms' : round($ms / 1000, 1).'s';
    }

    private function ago(int $seconds): string
    {
        return match (true) {
            $seconds < 60 => $seconds.'s',
            $seconds < 3600 => intdiv($seconds, 60).'m',
            $seconds < 86400 => intdiv($seconds, 3600).'h '.intdiv($seconds % 3600, 60).'m',
            default => intdiv($seconds, 86400).'d',
        };
    }

    private function in(int $seconds): string
    {
        return 'in '.$this->ago(max(0, $seconds));
    }
}
