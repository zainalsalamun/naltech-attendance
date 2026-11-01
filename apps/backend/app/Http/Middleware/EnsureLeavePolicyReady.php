<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureLeavePolicyReady
{
    public function handle(Request $request, Closure $next)
    {
        \App\Services\LeavePolicy::ready();

        return $next($request);
    }
}
