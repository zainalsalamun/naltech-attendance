<?php

namespace App\Http\Controllers;

use App\Models\Shift;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpKernel\Exception\HttpException;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $validated = $request->validate(['name' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(['active', 'inactive', 'archived', 'all'])]]);
        $search = trim($validated['name'] ?? '');
        $status = $validated['status'] ?? 'active';
        $users = User::with(['organizationUnit' => fn ($q) => $q->where('company_id', $request->user()->company_id), 'manager' => fn ($q) => $q->where('company_id', $request->user()->company_id), 'shift' => fn ($query) => $query->where('company_id', $request->user()->company_id)])
            ->when($search !== '', fn ($query) => $query->where(fn ($q) => $q->where('name', 'like', '%'.$search.'%')->orWhere('employee_number', 'like', '%'.$search.'%')))
            ->when($status === 'active', fn ($q) => $q->where('employment_status', 'active')->whereNull('archived_at'))
            ->when($status === 'inactive', fn ($q) => $q->where('employment_status', 'inactive')->whereNull('archived_at'))
            ->when($status === 'archived', fn ($q) => $q->whereNotNull('archived_at'))
            ->orderByDesc('id')->paginate(10)->withQueryString();

        return view('pages.users.index', compact('users', 'search', 'status'));
    }

    public function create()
    {
        $shifts = Shift::orderBy('name')->get();

        return view('pages.users.create', array_merge(compact('shifts'), $this->organizationOptions()));
    }

    private function organizationOptions(?User $user = null): array
    {
        $companyId = request()->user()->company_id;
        $units = \App\Models\OrganizationUnit::where('company_id', $companyId)
            ->where(fn ($q) => $q->where('is_active', true)->orWhere('id', $user?->organization_unit_id))->orderBy('name')->get();
        $managers = User::where('company_id', $companyId)->where('id', '!=', $user?->id ?? 0)
            ->where(fn ($q) => $q->where(fn ($a) => $a->where('employment_status', 'active')->whereNull('archived_at'))->orWhere('id', $user?->manager_id))
            ->orderBy('name')->get(['id', 'name', 'employment_status', 'archived_at']);

        return compact('units', 'managers');
    }

    private function validatedUser(Request $request, ?User $user = null): array
    {
        if (is_string($request->input('employee_number'))) {
            $request->merge(['employee_number' => strtoupper(trim($request->input('employee_number')))]);
        }
        if ($user) {
            $this->authorizeTarget($request, $user);
        }
        $hasVersionCol = \Illuminate\Support\Facades\Schema::hasTable('users') && \Illuminate\Support\Facades\Schema::hasColumn('users', 'version');
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user?->id)],
            'password' => [$user ? 'nullable' : 'required', 'string', 'min:8'],
            'phone' => ['nullable', 'string', 'max:255'],
            'position' => ['nullable', 'string', 'max:255'],
            'department' => ['nullable', 'string', 'max:255'],
            'organization_unit_id' => ['nullable', 'integer'],
            'manager_id' => ['nullable', 'integer'],
            'role' => ['nullable', Rule::in(['admin', 'super admin', 'hrd', 'supervisor', 'staff'])],
            'shift_id' => ['nullable', Rule::exists('shifts', 'id')->where('company_id', $request->user()->company_id)],
            'employee_number' => [$user ? 'nullable' : 'required', 'string', 'max:50', 'regex:~^[A-Z0-9][A-Z0-9._/-]*$~D',
                Rule::unique('users', 'employee_number')->where('company_id', $request->user()->company_id)->ignore($user?->id)],
            'employment_status' => ['sometimes', Rule::in(['active', 'inactive'])],
            'joined_on' => ['nullable', 'date_format:Y-m-d'],
            'left_on' => ['nullable', 'date_format:Y-m-d'],
            'lifecycle_reason' => ['nullable', 'string', 'max:500'],
            'version' => $user && $hasVersionCol ? ['required', 'integer'] : ['nullable', 'integer'],
        ]);
        // A supervisor must not promote themselves or reset an HR/admin account
        // to gain access to private biometric references.
        abort_unless(in_array($request->user()->role, ['admin', 'super admin'], true)
            || ($data['role'] ?? 'staff') === 'staff', 403);

        $joined = array_key_exists('joined_on', $data) ? $data['joined_on'] : $user?->joined_on?->format('Y-m-d');
        $left = array_key_exists('left_on', $data) ? $data['left_on'] : $user?->left_on?->format('Y-m-d');
        if ($left && $joined && $left < $joined) {
            throw \Illuminate\Validation\ValidationException::withMessages(['left_on' => 'Tanggal keluar tidak boleh sebelum tanggal bergabung.']);
        }
        if ($left && ($data['employment_status'] ?? $user?->employment_status ?? 'active') === 'active') {
            throw \Illuminate\Validation\ValidationException::withMessages(['left_on' => 'Akun aktif tidak dapat memiliki tanggal keluar. Kosongkan untuk mengaktifkan kembali.']);
        }

        return $data;
    }

    private function persist(Request $request, ?User $user, array $data, bool $archive = false): User
    {
        $reason = $data['lifecycle_reason'] ?? null;
        unset($data['lifecycle_reason']);
        try {
            return app(\App\Services\EmployeeLifecycle::class)->save($request->user(), $user, $data, $reason, $archive);
        } catch (\Illuminate\Database\UniqueConstraintViolationException $exception) {
            $field = str_contains($exception->getMessage(), 'employee_number') ? 'employee_number' : 'email';
            throw \Illuminate\Validation\ValidationException::withMessages([$field => 'Identitas sudah digunakan. Muat ulang dan gunakan nilai unik.']);
        }
    }

    private function authorizeTarget(Request $request, User $user): void
    {
        abort_unless(in_array($request->user()->role, ['admin', 'super admin'], true) || $user->role === 'staff', 403);
    }

    public function store(Request $request)
    {
        $data = $this->validatedUser($request);
        $data['role'] = $data['role'] ?? 'staff';
        $data['password'] = Hash::make($data['password']);
        $this->persist($request, null, $data);

        return redirect()->route('users.index')->with('success', 'Karyawan berhasil ditambahkan.');
    }

    public function edit(User $user)
    {
        $this->authorizeTarget(request(), $user);
        $shifts = Shift::orderBy('name')->get();

        $lifecycleEvents = \Illuminate\Support\Facades\DB::table('employee_lifecycle_events as e')
            ->leftJoin('users as actor', 'actor.id', '=', 'e.actor_id')
            ->where('e.company_id', request()->user()->company_id)->where('e.user_id', $user->id)
            ->select('e.action', 'e.reason', 'e.created_at', 'actor.name as actor_name')->orderByDesc('e.id')->limit(10)->get();

        $hasEffectiveShift = app(\App\Services\EffectiveShift::class)->ready() && \Illuminate\Support\Facades\DB::table('employee_shift_assignments')->where('company_id', $user->company_id)->where('user_id', $user->id)->exists();

        return view('pages.users.edit', array_merge(compact('user', 'shifts', 'lifecycleEvents', 'hasEffectiveShift'), $this->organizationOptions($user)));
    }

    public function update(Request $request, User $user)
    {
        $data = $this->validatedUser($request, $user);
        if (! empty($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        } else {
            unset($data['password']);
        }
        $data['role'] = $data['role'] ?? $user->role;
        try {
            $saved = $this->persist($request, $user, $data);
        } catch (HttpException $e) {
            if ($e->getStatusCode() === 409 && ! $request->expectsJson()) {
                return back()->withInput()->with('conflict', $e->getMessage());
            }
            throw $e;
        }

        if (! $saved->wasChanged()) {
            return redirect()->route('users.index')->with('info', 'Tidak ada perubahan pada data karyawan.');
        }

        return redirect()->route('users.index')->with('success', 'Data karyawan berhasil diperbarui.');
    }

    public function destroy(Request $request, User $user)
    {
        $this->authorizeTarget($request, $user);
        $hasVersionCol = \Illuminate\Support\Facades\Schema::hasTable('users') && \Illuminate\Support\Facades\Schema::hasColumn('users', 'version');
        $data = $request->validate([
            'lifecycle_reason' => ['required', 'string', 'max:500'],
            'left_on' => ['nullable', 'date_format:Y-m-d'],
            'version' => $hasVersionCol ? ['required', 'integer'] : ['nullable', 'integer'],
            'archive_user_id' => ['nullable', 'integer'],
        ]);
        unset($data['archive_user_id']);
        if (trim($data['lifecycle_reason']) === '') {
            throw \Illuminate\Validation\ValidationException::withMessages(['lifecycle_reason' => 'Isi alasan arsip kepegawaian.']);
        }
        if (($data['left_on'] ?? null) && $user->joined_on && $data['left_on'] < $user->joined_on->format('Y-m-d')) {
            throw \Illuminate\Validation\ValidationException::withMessages(['left_on' => 'Tanggal keluar tidak boleh sebelum tanggal bergabung.']);
        }
        try {
            $this->persist($request, $user, $data, true);
        } catch (HttpException $e) {
            if ($e->getStatusCode() === 409 && ! $request->expectsJson()) {
                return back()->withInput()->with('conflict', $e->getMessage());
            }
            throw $e;
        }

        return redirect()->route('users.index')->with('success', 'Karyawan diarsipkan. Riwayat tetap tersimpan dan akses akun dicabut.');
    }
}
