<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class EnsureEmployeeLifecycleReady
{
    public function handle(Request $request, Closure $next)
    {
        abort_unless(Schema::hasColumns('users', ['employee_number', 'employment_status', 'joined_on', 'left_on', 'archived_at', 'deactivated_at', 'auth_epoch', 'organization_unit_id', 'manager_id'])
            && Schema::hasTable('employee_lifecycle_events') && Schema::hasTable('organization_units'), 503,
            'Modul kepegawaian menunggu penerapan migrasi oleh pengelola.');

        return $next($request);
    }
}
