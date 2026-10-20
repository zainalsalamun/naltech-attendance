<?php

namespace App\Http\Controllers;

use App\Mail\ApprovedPermissionConfirmation;
use App\Models\Permission;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification;
use Resend\Laravel\Facades\Resend;

class PermissionController extends Controller
{
    //index
    public function index(Request $request)
    {
        $filters = $request->validate([
            'name' => ['nullable', 'string', 'max:100'],
            'date' => ['nullable', 'date_format:Y-m-d'],
            'status' => ['nullable', \Illuminate\Validation\Rule::in(['all', 'approved', 'unapproved'])],
        ]);
        $filters = ['name' => trim($filters['name'] ?? ''), 'date' => $filters['date'] ?? '', 'status' => $filters['status'] ?? 'all'];
        $permissions = Permission::with('user')
            ->when($filters['name'] !== '', fn ($query) => $query->whereHas('user', fn ($users) => $users->where('name', 'like', '%'.$filters['name'].'%')))
            ->when($filters['date'] !== '', fn ($query) => $query->where('date_permission', $filters['date']))
            ->when($filters['status'] !== 'all', fn ($query) => $query->where('is_approved', $filters['status'] === 'approved'))
            ->orderByDesc('id')->paginate(10)->withQueryString();

        return view('pages.permission.index', compact('permissions', 'filters'));
    }

    //view
    public function show($id)
    {
        $permission = Permission::with('user')->findOrFail($id);

        return view('pages.permission.show', compact('permission'));
    }

    //edit
    public function edit($id)
    {
        $permission = Permission::with('user')->findOrFail($id);

        return view('pages.permission.edit', compact('permission'));
    }

    //update
    public function update(Request $request, $id)
    {
        $permission = Permission::with('user')->findOrFail($id);
        $validated = $request->validate(['is_approved' => ['required', 'boolean']]);
        $wasApproved = (bool) $permission->is_approved;
        $permission->is_approved = $validated['is_approved'];
        $permission->save();
        if (! $wasApproved && $permission->is_approved) {
            try {
                $user = $permission->user;
                $date = Carbon::parse($permission->date_permission)->locale('id')->translatedFormat('d F Y');
                Resend::emails()->send([
                    'from' => 'onboarding@resend.dev',
                    'to' => $user->email,
                    'subject' => 'Approved Permission - '.$user->name,
                    'html' => (new ApprovedPermissionConfirmation($user, $date, $permission->reason))->render(),
                ]);
            } catch (\Throwable $exception) {
                // The decision is already saved. Never expose provider errors or credentials.
                return redirect()->route('permissions.index')->with('warning',
                    'Persetujuan tersimpan, tetapi email pemberitahuan belum terkirim. Hubungi karyawan melalui saluran kerja Anda.');
            }
        }

        return redirect()->route('permissions.index')->with('success', 'Status izin berhasil diperbarui.');
    }

    public function sendNotificationToUser($userId, $message)
    {
        // Dapatkan FCM token user dari tabel 'users'

        $user = User::find($userId);
        $token = $user->fcm_token;

        // Kirim notifikasi ke perangkat Android
        $messaging = app('firebase.messaging');
        $notification = Notification::create('Status Izin', $message);

        $message = CloudMessage::withTarget('token', $token)
            ->withNotification($notification);

        $messaging->send($message);
    }
}
