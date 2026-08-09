<?php

namespace PDPhilip\OmniCron\Http;

use Closure;
use Illuminate\Http\Request;

/**
 * Shared-secret gate for the heartbeat endpoints.
 *
 * FAILS CLOSED: no configured secret means every request is refused. A
 * stopped scheduler is loud - the heartbeat service reports the failures -
 * but an open one is silent, and these urls can run anything you scheduled.
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

        if (! $secret) {
            return response()->json(['error' => 'OmniCron secret is not configured'], 503);
        }

        $provided = $request->header(self::HEADER) ?? $request->query('token');

        if (! is_string($provided) || ! hash_equals($secret, $provided)) {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        return $next($request);
    }
}
