<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ApprovalWorkflow
{
    public const ROLES = ['admin', 'super admin', 'hrd', 'supervisor'];

    public function attach(Model $row): void
    {
        if (! Schema::hasTable('approval_workflows') || ! Schema::hasColumn($row->getTable(), 'approval_steps')) {
            return;
        }
        $kind = array_search($row->getTable(), ['permission' => 'permissions', 'overtime' => 'overtimes', 'reimbursement' => 'reimbursements'], true);
        $owner = User::findOrFail($row->user_id);
        $policy = DB::table('approval_workflows')->where('company_id', $owner->company_id)->where('kind', $kind)->latest('id')->first();
        // Empty versions explicitly restore the previous single-decision process for future requests.
        $steps = $policy ? json_decode($policy->steps, true) : [];
        if (! $steps || ($row->status && $row->status !== 'pending') || $row->is_approved) {
            return;
        }
        foreach ($steps as &$step) {
            $step['reviewer_id'] = $step['type'] === 'manager' ? $owner->manager_id : $step['reviewer_id'];
        }
        unset($step);
        $row->approval_steps = $steps;
        $row->approval_step = 0;
        $row->approval_reviewer_ids = array_values(array_unique(array_map('intval', array_filter(array_merge(array_column($steps, 'reviewer_id'), array_column($steps, 'fallback_id'))))));
    }

    public function reviewer(Model $row): ?User
    {
        $step = ($row->approval_steps ?? [])[$row->approval_step] ?? null;
        if (! $step) {
            return null;
        }
        $companyId = $row->user()->value('company_id');
        foreach ([$step['reviewer_id'], $step['fallback_id']] as $id) {
            if (! $id || (int) $id === (int) $row->user_id) {
                continue;
            }
            $user = User::where('company_id', $companyId)->whereKey($id)->first();
            if ($user && $user->isEmploymentActive() && in_array($user->role, self::ROLES, true)) {
                return $user;
            }
        }

        return null;
    }
}
