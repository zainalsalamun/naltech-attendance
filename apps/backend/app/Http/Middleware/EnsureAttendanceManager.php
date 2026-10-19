<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureAttendanceManager
{
    public function handle(Request $request, Closure $next)
    {
        abort_unless(in_array($request->user()?->role, ['admin', 'super admin', 'hrd', 'supervisor'], true), 403);
        return $next($request);
    }
}
