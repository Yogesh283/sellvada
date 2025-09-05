<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class AdminOnly
{
    public function handle(Request $request, Closure $next)
    {
        // Assumes aapke users table me `is_admin` column hai
        if (! $request->user() || ! $request->user()->is_admin) {
            abort(403, 'Unauthorized'); // forbidden
        }

        return $next($request);
    }
}
