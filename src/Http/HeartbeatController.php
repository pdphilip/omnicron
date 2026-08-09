<?php

namespace PDPhilip\OmniCron\Http;

use Illuminate\Http\JsonResponse;
use PDPhilip\OmniCron\OmniCron;

/**
 * The three urls a scheduling service needs. What each returns is designed
 * to be captured by the caller - the JSON is the remote-side log, the same
 * way an external cron service stores what your url replied.
 */
class HeartbeatController
{
    public function __construct(
        private readonly OmniCron $omnicron,
    ) {}

    /** Hit every minute - runs whatever is due and says what happened. */
    public function tick(): JsonResponse
    {
        return response()->json($this->omnicron->tick());
    }

    /** Health per task - due, stale, stuck. */
    public function status(): JsonResponse
    {
        return response()->json($this->omnicron->status());
    }

    /** Fire one task by hand. Bypasses the environment gate - explicit intent. */
    public function run(string $task): JsonResponse
    {
        $found = $this->omnicron->find($task);

        if (! $found) {
            return response()->json(['error' => 'Unknown task: '.$task], 404);
        }

        return response()->json($this->omnicron->run($found, manual: true));
    }
}
