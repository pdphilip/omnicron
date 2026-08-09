<?php

namespace PDPhilip\OmniCron\Http;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Who may see the dashboard. The Horizon convention: open in local, and in
 * every other environment a `viewOmniCron` gate must exist and pass -
 * undefined means denied, so deploying the package can never expose the
 * dashboard by accident.
 *
 *   Gate::define('viewOmniCron', fn ($user) => $user->isAdmin());
 */
class AuthorizeDashboard
{
    public function handle(Request $request, Closure $next)
    {
        if (app()->environment('local')) {
            return $next($request);
        }

        if (Gate::has('viewOmniCron') && Gate::allows('viewOmniCron')) {
            return $next($request);
        }

        abort(403);
    }
}
