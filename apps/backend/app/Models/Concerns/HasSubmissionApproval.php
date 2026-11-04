<?php

namespace App\Models\Concerns;

use App\Models\User;
use App\Services\ApprovalWorkflow;
use App\Services\SubmissionApproval;
use Carbon\CarbonImmutable;

trait HasSubmissionApproval
{
    public static function bootHasSubmissionApproval(): void
    {
        static::creating(fn ($row) => app(ApprovalWorkflow::class)->attach($row));
    }

    public function initializeHasSubmissionApproval(): void
    {
        $this->mergeCasts(['approval_version' => 'integer', 'approval_legacy' => 'boolean', 'approval_steps' => 'array', 'approval_reviewer_ids' => 'array', 'approval_step' => 'integer']);
        $this->append(['decision_time', 'approval_progress']);
    }

    public function reviewer(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function getApprovalProgressAttribute(): ?array
    {
        if (! $this->approval_steps) {
            return null;
        }

        return ['current' => min((int) $this->approval_step + 1, count($this->approval_steps)), 'total' => count($this->approval_steps), 'label' => $this->status === 'pending' ? ($this->approval_steps[$this->approval_step]['label'] ?? null) : null, 'steps' => array_column($this->approval_steps, 'label')];
    }

    public function getDecisionTimeAttribute(): ?string
    {
        if (! $this->reviewed_at) {
            return null;
        }
        $company = $this->relationLoaded('user') ? $this->user?->company : ($this->user_id ? \App\Models\User::find($this->user_id)?->company : null);
        $tz = $company ? app(\App\Services\WorkCalendar::class)->timezone($company) : config('app.timezone');

        return CarbonImmutable::parse($this->reviewed_at, config('app.timezone'))->setTimezone($tz)->toIso8601String();
    }

    public function getActiveApproverAttribute(): ?User
    {
        return app(ApprovalWorkflow::class)->reviewer($this);
    }

    public function canBeDecidedBy(\App\Models\User $actor): bool
    {
        return app(SubmissionApproval::class)->canDecide($actor, $this);
    }

    public function whyCannotBeDecidedBy(\App\Models\User $actor): ?string
    {
        return app(SubmissionApproval::class)->whyCannotDecide($actor, $this);
    }

    public function actionTypeOf(\App\Models\User $actor): ?string
    {
        return app(SubmissionApproval::class)->actionType($actor, $this);
    }

    public function scopeWithActionContext($query, \App\Models\User $actor, ?string $context = 'all')
    {
        if (! in_array($context, ['actionable', 'waiting_others'], true)) {
            return $query;
        }

        $table = $query->getModel()->getTable();

        if (! $actor->isEmploymentActive()) {
            return $context === 'actionable'
                ? $query->whereRaw('0 = 1')
                : $query->whereIn($table.'.status', ['pending', 'cancellation_requested']);
        }

        $actorId = (int) $actor->id;
        $isManagerOrAdmin = in_array($actor->role, ['admin', 'super admin', 'hrd'], true);
        $isSupervisor = ($actor->role === 'supervisor');
        $isPermission = ($table === 'permissions');

        $driver = $query->getConnection()->getDriverName();

        // Check if approval_steps has at least one step configured (treats [] and NULL identically as without workflow)
        if ($driver === 'sqlite') {
            $hasWorkflowCondition = "({$table}.approval_steps IS NOT NULL AND json_extract({$table}.approval_steps, '$[0]') IS NOT NULL)";
            $stepIdx = "COALESCE({$table}.approval_step, 0)";
            $reviewerIdSql = "COALESCE(CAST(json_extract({$table}.approval_steps, '$[' || {$stepIdx} || '].reviewer_id') AS INTEGER), 0)";
            $fallbackIdSql = "COALESCE(CAST(json_extract({$table}.approval_steps, '$[' || {$stepIdx} || '].fallback_id') AS INTEGER), 0)";
        } else {
            $hasWorkflowCondition = "({$table}.approval_steps IS NOT NULL AND JSON_EXTRACT({$table}.approval_steps, '$[0]') IS NOT NULL)";
            $stepIdx = "COALESCE({$table}.approval_step, 0)";
            $reviewerIdSql = "COALESCE(CAST(JSON_UNQUOTE(JSON_EXTRACT({$table}.approval_steps, CONCAT('$[', {$stepIdx}, '].reviewer_id'))) AS UNSIGNED), 0)";
            $fallbackIdSql = "COALESCE(CAST(JSON_UNQUOTE(JSON_EXTRACT({$table}.approval_steps, CONCAT('$[', {$stepIdx}, '].fallback_id'))) AS UNSIGNED), 0)";
        }

        // Subquery ensuring the primary reviewer is an active, unarchived user in the submitter's company with an authorized role
        $validPrimaryReviewerExists = "EXISTS (
            SELECT 1 FROM users u_rev
            JOIN users u_owner ON u_owner.id = {$table}.user_id
            WHERE u_rev.id = {$reviewerIdSql}
              AND u_rev.company_id = u_owner.company_id
              AND u_rev.archived_at IS NULL
              AND (u_rev.employment_status = 'active' OR u_rev.employment_status IS NULL)
              AND u_rev.role IN ('admin', 'super admin', 'hrd', 'supervisor')
        )";

        $actionableCallback = function ($q) use (
            $table, $actorId, $isManagerOrAdmin, $isSupervisor, $isPermission,
            $hasWorkflowCondition, $reviewerIdSql, $fallbackIdSql, $validPrimaryReviewerExists
        ) {
            // Rule 1: Self-approval is strictly forbidden
            $q->where($table.'.user_id', '!=', $actorId);

            $q->where(function ($sub) use (
                $table, $actorId, $isManagerOrAdmin, $isSupervisor, $isPermission,
                $hasWorkflowCondition, $reviewerIdSql, $fallbackIdSql, $validPrimaryReviewerExists
            ) {
                // Case 1: Cancellation requested (permission only)
                if ($isPermission && ($isManagerOrAdmin || $isSupervisor)) {
                    $sub->orWhere(function ($cQ) use ($table, $actorId, $isManagerOrAdmin, $isSupervisor) {
                        $cQ->where($table.'.status', 'cancellation_requested');
                        if ($isSupervisor && ! $isManagerOrAdmin) {
                            $cQ->whereExists(function ($ex) use ($table, $actorId) {
                                $ex->selectRaw('1')
                                    ->from('users')
                                    ->whereColumn('users.id', $table.'.user_id')
                                    ->where('users.manager_id', $actorId);
                            });
                        }
                    });
                }

                // Case 2: Pending with multi-step workflow (approval_steps has elements)
                $sub->orWhere(function ($wfQ) use ($table, $actorId, $hasWorkflowCondition, $reviewerIdSql, $fallbackIdSql, $validPrimaryReviewerExists) {
                    $wfQ->where($table.'.status', 'pending')
                        ->whereRaw($hasWorkflowCondition)
                        ->where(function ($rQ) use ($table, $actorId, $reviewerIdSql, $fallbackIdSql, $validPrimaryReviewerExists) {
                            // Actor is primary reviewer
                            $rQ->whereRaw("{$reviewerIdSql} = ?", [$actorId])
                                // Or actor is fallback reviewer while primary reviewer is missing, owner, or inactive/archived/foreign
                                ->orWhere(function ($fbQ) use ($table, $actorId, $reviewerIdSql, $fallbackIdSql, $validPrimaryReviewerExists) {
                                    $fbQ->whereRaw("{$fallbackIdSql} = ?", [$actorId])
                                        ->where(function ($invQ) use ($table, $reviewerIdSql, $validPrimaryReviewerExists) {
                                            $invQ->whereRaw("{$reviewerIdSql} = 0")
                                                ->orWhereRaw("{$reviewerIdSql} = {$table}.user_id")
                                                ->orWhereRaw("NOT ({$validPrimaryReviewerExists})");
                                        });
                                });
                        });
                });

                // Case 3: Pending without workflow (null OR empty array '[]')
                if ($isManagerOrAdmin || $isSupervisor) {
                    $sub->orWhere(function ($singleQ) use ($table, $actorId, $isManagerOrAdmin, $isSupervisor, $hasWorkflowCondition) {
                        $singleQ->where($table.'.status', 'pending')
                            ->whereRaw("NOT ({$hasWorkflowCondition})");
                        if ($isSupervisor && ! $isManagerOrAdmin) {
                            $singleQ->whereExists(function ($ex) use ($table, $actorId) {
                                $ex->selectRaw('1')
                                    ->from('users')
                                    ->whereColumn('users.id', $table.'.user_id')
                                    ->where('users.manager_id', $actorId);
                            });
                        }
                    });
                }
            });
        };

        if ($context === 'actionable') {
            return $query->where($actionableCallback);
        }

        // $context === 'waiting_others'
        // Uses explicit boolean comparison ensuring rows with empty/null reviewer are NEVER lost due to SQL three-valued logic (NULL)
        return $query->whereIn($table.'.status', ['pending', 'cancellation_requested'])
            ->whereNot($actionableCallback);
    }

    public function scopeVisibleTo($query, \App\Models\User $actor)
    {
        if (in_array($actor->role, ['admin', 'super admin', 'hrd'], true)) {
            return $query;
        }

        return $query->where(fn ($query) => $query->whereHas('user', fn ($q) => $q->where(fn ($q) => $q->where('id', $actor->id)->when($actor->role === 'supervisor' && \Illuminate\Support\Facades\Schema::hasColumn('users', 'manager_id'), fn ($q) => $q->orWhere('manager_id', $actor->id))))->when(\Illuminate\Support\Facades\Schema::hasColumn($query->getModel()->getTable(), 'approval_reviewer_ids'), fn ($q) => $q->orWhereJsonContains('approval_reviewer_ids', (int) $actor->id)));
    }

    public function getApprovalLabelAttribute(): string
    {
        return [
            'pending' => 'Menunggu persetujuan',
            'approved' => 'Disetujui',
            'rejected' => 'Ditolak',
            'cancelled' => 'Dibatalkan',
            'cancellation_requested' => 'Menunggu pembatalan',
            'needs_review' => 'Perlu ditinjau',
        ][$this->status] ?? 'Status belum dikenali';
    }
}
