<?php

namespace PDPhilip\OmniCron\Http;

use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use PDPhilip\OmniCron\OmniCron;

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

            return [
                ...$row,
                'health' => $this->health($row),
                'health_label' => $this->healthLabel($row),
                'last_success_ago' => $row['last_success_at'] ? $this->ago($now - $row['last_success_at']) : null,
                'next_run_in' => $this->in($this->omnicron->nextRunAt($task, $now) - $now),
                'duration_label' => $this->duration($row['last_duration_ms']),
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
                'started_at' => date('Y-m-d H:i:s', $run->started_at),
                'duration_label' => $run->durationLabel(),
                'output' => $run->output,
                'error' => $run->error,
                'host' => $run->host,
                'manual' => (bool) $run->manual,
            ])->values(),
        ]);
    }

    /** Fire one task from the dashboard - manual, so the environment gate is bypassed. */
    public function run(string $task): JsonResponse
    {
        $found = $this->omnicron->find($task);

        if (! $found) {
            return response()->json(['error' => 'Unknown task: '.$task], 404);
        }

        return response()->json($this->omnicron->run($found, manual: true));
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
