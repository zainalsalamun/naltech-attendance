<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureWorkCalendarReady
{
    public function handle(Request $request, Closure $next)
    {
        abort_unless(app(\App\Services\WorkCalendar::class)->ready(), 503, 'Kalender kerja menunggu penerapan migrasi oleh pengelola.');

        return $next($request);
    }
}
