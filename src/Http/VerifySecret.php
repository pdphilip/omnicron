<?php

namespace PDPhilip\OmniCron\Http;

use Closure;
use Illuminate\Http\Request;

/**
 * Shared-secret gate for the heartbeat endpoints.
 *
 * Opt-in: no configured secret means the endpoints are public - the tick is
 * idempotent (due-ness, locks and the post-lock recheck decide what runs)
 * and some setups want a zero-config heartbeat. Set OMNICRON_SECRET and
 * every request must present it. Do set it when any task is gated to
 * production or does something a stranger should not trigger by hand:
 * /run/{task} bypasses both the schedule and the environment gate.
 *
 * The secret rides the X-OmniCron-Secret header, or ?token= for scheduling
 * services that only accept a plain url.
 */
class VerifySecret
{
    public const HEADER = 'X-OmniCron-Secret';

    public function handle(Request $request, Closure $next)
    {
        $secret = config('omnicron.endpoint.secret');

        // No secret configured = deliberately public.
        if (! $secret) {
            return $next($request);
        }

        $provided = $request->header(self::HEADER) ?? $request->query('token');

        if (! is_string($provided) || ! hash_equals($secret, $provided)) {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        return $next($request);
    }
}
