<?php

namespace App\Http\Controllers;

use App\Models\Shift;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $validated = $request->validate(['name' => ['nullable', 'string', 'max:100']]);
        $search = trim($validated['name'] ?? '');
        $users = User::with(['shift' => fn ($query) => $query->where('company_id', $request->user()->company_id)])
            ->when($search !== '', fn ($query) => $query->where('name', 'like', '%'.$search.'%'))
            ->orderByDesc('id')->paginate(10)->withQueryString();

        return view('pages.users.index', compact('users', 'search'));
    }

    public function create()
    {
        $shifts = Shift::orderBy('name')->get();

        return view('pages.users.create', compact('shifts'));
    }

    private function validatedUser(Request $request, ?User $user = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user?->id)],
            'password' => [$user ? 'nullable' : 'required', 'string', 'min:8'],
            'phone' => ['nullable', 'string', 'max:255'],
            'position' => ['nullable', 'string', 'max:255'],
            'department' => ['nullable', 'string', 'max:255'],
            'role' => ['nullable', Rule::in(['admin', 'super admin', 'hrd', 'supervisor', 'staff'])],
            'shift_id' => ['nullable', Rule::exists('shifts', 'id')->where('company_id', $request->user()->company_id)],
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validatedUser($request);
        $data['role'] = $data['role'] ?? 'staff';
        $data['password'] = Hash::make($data['password']);
        User::create($data);

        return redirect()->route('users.index')->with('success', 'Karyawan berhasil ditambahkan.');
    }

    public function edit(User $user)
    {
        $shifts = Shift::orderBy('name')->get();

        return view('pages.users.edit', compact('user', 'shifts'));
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
        $user->update($data);

        return redirect()->route('users.index')->with('success', 'Data karyawan berhasil diperbarui.');
    }

    public function destroy(User $user)
    {
        $user->delete();

        return redirect()->route('users.index')->with('success', 'Karyawan berhasil dihapus.');
    }
}
