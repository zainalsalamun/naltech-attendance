<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\User;
use App\Services\ApprovalWorkflow;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ApprovalWorkflowController extends Controller
{
    private function ready(): void
    {
        abort_unless(Schema::hasTable('approval_workflows'), 503, 'Alur approval menunggu penerapan migrasi.');
    }

    public function index(Request $request)
    {
        $this->ready();
        $companyId = $request->user()->company_id;
        $policies = DB::table('approval_workflows')->where('company_id', $companyId)->whereIn('id', DB::table('approval_workflows')->selectRaw('MAX(id)')->where('company_id', $companyId)->groupBy('kind'))->get()->keyBy('kind');
        $reviewers = User::where('company_id', $companyId)->whereIn('role', ApprovalWorkflow::ROLES)->orderBy('name')->get()->filter(fn ($user) => $user->isEmploymentActive());
        $history = DB::table('approval_workflows as w')->join('users as u', 'u.id', '=', 'w.actor_id')->where('w.company_id', $companyId)->orderByDesc('w.id')->select('w.*', 'u.name as actor_name')->paginate(15);

        return view('pages.approval-workflows.index', compact('policies', 'reviewers', 'history'));
    }

    public function store(Request $request)
    {
        $this->ready();
        abort_unless(in_array($request->user()->role, ['admin', 'super admin', 'hrd'], true), 403);
        if ($request->has('draft')) {
            $request->validate(['draft' => 'required|array|max:4', 'draft.*' => 'array', 'draft.*.label' => 'nullable|string|max:80']);
            $request->merge(['steps' => array_values(array_filter($request->input('draft'), fn ($step) => trim($step['label'] ?? '') !== ''))]);
        }
        $data = $request->validate([
            'kind' => 'required|in:permission,overtime,reimbursement',
            'expected_id' => 'required|integer|min:0',
            'steps' => 'sometimes|array|max:4',
            'steps.*.type' => 'required|in:manager,user',
            'steps.*.label' => 'required|string|max:80',
            'steps.*.reviewer_id' => 'nullable|integer|min:1',
            'steps.*.fallback_id' => 'required|integer|min:1',
            'confirm' => 'accepted',
        ]);
        DB::transaction(function () use ($request, $data) {
            Company::whereKey($request->user()->company_id)->lockForUpdate()->firstOrFail();
            $actor = User::whereKey($request->user()->id)->lockForUpdate()->firstOrFail();
            abort_unless($actor->isEmploymentActive() && in_array($actor->role, ['admin', 'super admin', 'hrd'], true), 403);
            $latest = DB::table('approval_workflows')->where('company_id', $actor->company_id)->where('kind', $data['kind'])->max('id') ?? 0;
            abort_unless((int) $latest === (int) $data['expected_id'], 409, 'Konfigurasi berubah. Muat ulang halaman.');
            $steps = array_values($data['steps'] ?? []);
            foreach ($steps as &$step) {
                $step = array_intersect_key($step, array_flip(['label', 'type', 'reviewer_id', 'fallback_id']));
                $step['reviewer_id'] = $step['type'] === 'manager' ? null : ($step['reviewer_id'] ?? null);
                abort_if($step['type'] === 'user' && ! $step['reviewer_id'], 422, 'Pilih peninjau utama.');
                foreach (array_filter([$step['reviewer_id'], $step['fallback_id']]) as $id) {
                    $user = User::where('company_id', $actor->company_id)->whereKey($id)->first();
                    abort_unless($user && $user->isEmploymentActive() && in_array($user->role, ApprovalWorkflow::ROLES, true), 422, 'Peninjau harus pengelola aktif di perusahaan ini.');
                }
                abort_if($step['reviewer_id'] && (int) $step['reviewer_id'] === (int) $step['fallback_id'], 422, 'Pengganti harus berbeda dari peninjau utama.');
                $step['reviewer_id'] = $step['reviewer_id'] ? (int) $step['reviewer_id'] : null;
                $step['fallback_id'] = (int) $step['fallback_id'];
            }
            unset($step);
            DB::table('approval_workflows')->insert(['company_id' => $actor->company_id, 'kind' => $data['kind'], 'steps' => json_encode($steps), 'actor_id' => $actor->id, 'created_at' => now()]);
        });

        return back()->with('success', 'Alur disimpan untuk pengajuan baru. Pengajuan berjalan mempertahankan alurnya.');
    }
}
