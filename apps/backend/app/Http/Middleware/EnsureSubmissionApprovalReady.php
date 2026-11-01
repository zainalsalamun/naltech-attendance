<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class EnsureSubmissionApprovalReady
{
    public function handle(Request $request, Closure $next)
    {
        foreach (['permissions', 'overtimes', 'reimbursements'] as $table) {
            abort_unless(Schema::hasColumns($table, ['status', 'approval_version', 'approval_legacy', 'reviewed_by', 'reviewed_at', 'decision_reason', 'approval_steps', 'approval_reviewer_ids', 'approval_step']), 503, 'Approval menunggu penerapan migrasi oleh pengelola.');
        }
        abort_unless(Schema::hasTable('approval_workflows') && Schema::hasTable('submission_approval_events') && Schema::hasColumn('users', 'manager_id'), 503, 'Approval menunggu penerapan migrasi oleh pengelola.');

        return $next($request);
    }
}
