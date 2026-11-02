<?php

namespace App\Services;

use App\Models\Company;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class EmployeeLifecycle
{
    private function snapshot(User $user): array
    {
        return array_intersect_key($user->attributesToArray(), array_flip(['employee_number', 'employment_status', 'joined_on', 'left_on', 'deactivated_at', 'archived_at', 'role', 'organization_unit_id', 'manager_id']));
    }

    public function save(User $actor, ?User $target, array $data, ?string $reason, bool $archive = false): User
    {
        return DB::transaction(function () use ($actor, $target, $data, $reason, $archive) {
            // Serialize admin transitions within one company, including last-admin checks.
            Company::whereKey($actor->company_id)->lockForUpdate()->firstOrFail();
            $actor = User::whereKey($actor->id)->lockForUpdate()->firstOrFail();
            abort_unless($actor->isEmploymentActive(), 401);
            $user = $target ? User::whereKey($target->id)->lockForUpdate()->firstOrFail() : new User;
            abort_unless(! $target || (int) $user->company_id === (int) $actor->company_id, 404);
            $hasVersionCol = Schema::hasTable('users') && Schema::hasColumn('users', 'version');
            if ($target && $hasVersionCol) {
                if (! array_key_exists('version', $data) || $data['version'] === null) {
                    throw ValidationException::withMessages(['version' => 'Versi data karyawan wajib disertakan.']);
                }
                if ((int) $data['version'] !== (int) ($user->version ?? 0)) {
                    abort(409, 'Data karyawan telah diperbarui oleh pengguna lain (konflik versi). Muat ulang halaman untuk meninjau perubahan terbaru.');
                }
            }
            unset($data['version']);
            abort_unless(in_array($actor->role, ['admin', 'super admin', 'hrd', 'supervisor'], true), 403);
            abort_unless(in_array($actor->role, ['admin', 'super admin'], true) || (! $target || $user->role === 'staff'), 403);
            abort_unless(in_array($actor->role, ['admin', 'super admin'], true) || ($data['role'] ?? $user->role ?? 'staff') === 'staff', 403);
            $before = $target ? $this->snapshot($user) : null;
            $status = $archive ? 'inactive' : ($data['employment_status'] ?? $user->employment_status ?? 'active');
            if ($status === 'inactive' && (int) $user->id === (int) $actor->id) {
                throw ValidationException::withMessages(['employment_status' => 'Anda tidak dapat menonaktifkan atau mengarsipkan akun sendiri.']);
            }
            $role = $data['role'] ?? $user->role ?? 'staff';
            if ($target && $user->isEmploymentActive() && in_array($user->role, ['admin', 'super admin'], true)
                && ($status !== 'active' || ! in_array($role, ['admin', 'super admin'], true))) {
                $otherAdmin = User::where('company_id', $actor->company_id)->where('id', '!=', $user->id)->whereIn('role', ['admin', 'super admin'])
                    ->where('employment_status', 'active')->whereNull('archived_at')->exists();
                if (! $otherAdmin) {
                    throw ValidationException::withMessages(['role' => 'Pertahankan setidaknya satu admin aktif di perusahaan.']);
                }
            }
            $transition = $target && ($user->employment_status !== $status || ($archive && ! $user->archived_at) || ($user->archived_at && $status === 'active'));
            if (($transition || (! $target && $status === 'inactive')) && trim($reason ?? '') === '') {
                throw ValidationException::withMessages(['lifecycle_reason' => 'Isi alasan perubahan status kepegawaian.']);
            }
            // The company lock also serializes organization writes and graph changes.
            if (array_key_exists('organization_unit_id', $data) && $data['organization_unit_id'] !== null) {
                $unit = \App\Models\OrganizationUnit::where('company_id', $actor->company_id)->find($data['organization_unit_id']);
                if (! $unit || (! $unit->is_active && (int) $user->organization_unit_id !== (int) $unit->id)) {
                    throw ValidationException::withMessages(['organization_unit_id' => 'Pilih unit aktif dalam perusahaan.']);
                }
            }
            $managerId = array_key_exists('manager_id', $data) ? $data['manager_id'] : $user->manager_id;
            $seen = [];
            while ($managerId) {
                if ((int) $managerId === (int) $user->id || isset($seen[$managerId])) {
                    throw ValidationException::withMessages(['manager_id' => 'Hubungan atasan tidak boleh membentuk lingkaran atau menunjuk diri sendiri.']);
                }
                $seen[$managerId] = true;
                $manager = User::where('company_id', $actor->company_id)->find($managerId);
                if (! $manager) {
                    throw ValidationException::withMessages(['manager_id' => 'Atasan harus berasal dari perusahaan yang sama.']);
                }
                // Retain an existing inactive link for history, but never assign one anew.
                if (count($seen) === 1 && ! $manager->isEmploymentActive()
                    && (int) $user->manager_id !== (int) $managerId) {
                    throw ValidationException::withMessages(['manager_id' => 'Pilih atasan yang aktif.']);
                }
                $managerId = $manager->manager_id;
            }
            if ($target && array_key_exists('shift_id', $data) && (int) $data['shift_id'] !== (int) $user->shift_id && app(EffectiveShift::class)->ready() && DB::table('employee_shift_assignments')->where('company_id', $actor->company_id)->where('user_id', $user->id)->exists()) {
                throw ValidationException::withMessages(['shift_id' => 'Gunakan Jadwal efektif untuk mengganti penempatan shift karyawan ini.']);
            }
            $user->forceFill($data);
            $user->company_id = $actor->company_id;
            $user->role = $role;
            $user->employment_status = $status;
            if ($archive && ! $user->archived_at) {
                $user->archived_at = now();
            }
            if ($status === 'inactive' && ! $user->deactivated_at) {
                $user->deactivated_at = now();
            }
            if ($status === 'active') {
                $user->archived_at = null;
                $user->deactivated_at = null;
            }
            $isDirty = $user->isDirty() || ($target && $before !== $this->snapshot($user));
            if ($target && $hasVersionCol && $isDirty) {
                $user->version = (int) ($user->version ?? 0) + 1;
            } elseif (! $target && $hasVersionCol) {
                $user->version = 0;
            }
            $user->save();
            $after = $this->snapshot($user);
            if ($before !== $after) {
                DB::table('employee_lifecycle_events')->insert([
                    'company_id' => $actor->company_id, 'user_id' => $user->id, 'actor_id' => $actor->id,
                    'action' => ! $target ? 'created' : ($archive ? 'archived' : ($transition ? ($status === 'active' ? 'reactivated' : 'deactivated') : 'updated')),
                    'reason' => $reason, 'before' => $before === null ? null : json_encode($before),
                    'after' => json_encode($after), 'created_at' => now(),
                ]);
            }
            if ($status !== 'active' || $transition || ($target && ($user->wasChanged('password') || $user->wasChanged('role')))) {
                $user->tokens()->delete();
                DB::table('sessions')->where('user_id', $user->id)->delete();
                $user->forceFill(['remember_token' => null, 'fcm_token' => null, 'auth_epoch' => (int) $user->auth_epoch + 1])->save();
            }

            return $user;
        });
    }
}
