@extends('layouts.workspace')
@section('title', 'Cuti dan Izin')
@section('content')
<div class="page-heading">
    <div>
        <p class="breadcrumb"><a href="{{ route('home') }}">Workspace</a> / Cuti dan izin</p>
        <h1>Pengajuan Cuti & Izin</h1>
        <p class="muted">Tinjau alasan, bukti pendukung, kalkulasi hari kerja, dan persetujuan bertingkat.</p>
    </div>
    <div style="display:flex;gap:8px;flex-wrap:wrap">
        @if(!empty($manager) || in_array(auth()->user()?->role, ['admin', 'super admin', 'hrd', 'supervisor'], true))
            <a class="secondary" href="{{ route('leave-balances.index') }}"><i class="ion-ios-pie-outline" aria-hidden="true"></i> Saldo cuti</a>
            <a class="secondary" href="{{ route('leave-balances.ledger') }}"><i class="ion-ios-list-outline" aria-hidden="true"></i> Buku besar saldo</a>
            <a class="secondary" href="{{ route('leave-types.index') }}"><i class="ion-ios-filing-outline" aria-hidden="true"></i> Jenis cuti</a>
        @endif
        <a class="primary" href="{{ route('permissions.create') }}"><i class="ion-ios-plus-empty" aria-hidden="true"></i> Ajukan cuti / izin</a>
    </div>
</div>

@include('components.workspace-feedback')

<section class="panel">
    <div class="panel-heading">
        <div>
            <h2>Daftar pengajuan cuti dan izin</h2>
            <p class="muted">{{ $permissions->total() }} pengajuan sesuai kriteria pencarian</p>
        </div>
    </div>

    <form method="get" action="{{ route('permissions.index') }}" class="record-filters">
        <div class="field">
            <label for="name">Nama karyawan</label>
            <input id="name" type="search" name="name" value="{{ $filters['name'] }}" maxlength="100" placeholder="Cari nama karyawan">
        </div>
        <div class="field">
            <label for="date">Tanggal dalam rentang</label>
            <input id="date" type="date" name="date" value="{{ $filters['date'] }}">
        </div>
        <div class="field">
            <label for="action_context">Konteks tindakan</label>
            <select id="action_context" name="action_context">
                <option value="all" @selected(($filters['action_context'] ?? 'all') === 'all')>Semua yang dapat saya lihat</option>
                <option value="actionable" @selected(($filters['action_context'] ?? '') === 'actionable')>Perlu tindakan saya</option>
                <option value="waiting_others" @selected(($filters['action_context'] ?? '') === 'waiting_others')>Menunggu peninjau lain</option>
            </select>
        </div>
        <div class="field">
            <label for="status">Status pengajuan</label>
            <select id="status" name="status">
                @foreach([
                    'all' => 'Semua status',
                    'pending' => 'Menunggu persetujuan',
                    'approved' => 'Disetujui',
                    'rejected' => 'Ditolak',
                    'cancelled' => 'Dibatalkan',
                    'cancellation_requested' => 'Menunggu pembatalan',
                    'needs_review' => 'Perlu ditinjau',
                ] as $value => $label)
                    <option value="{{ $value }}" @selected($filters['status'] === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <button class="primary" type="submit">Terapkan</button>
        <a class="secondary" href="{{ route('permissions.index') }}">Reset</a>
    </form>

    <div class="table-scroll" tabindex="0" role="region" aria-label="Daftar pengajuan cuti perusahaan">
        <table>
            <thead>
                <tr>
                    <th scope="col">Karyawan</th>
                    <th scope="col">Rentang tanggal</th>
                    <th scope="col">Jenis cuti</th>
                    <th scope="col">Alasan</th>
                    <th scope="col">Status & Tahap</th>
                    <th scope="col">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse($permissions as $permission)
                    @php
                        $viewer = auth()->user();
                        $approvalService = app(\App\Services\SubmissionApproval::class);
                        $canDecide = $approvalService->canDecide($viewer, $permission);
                        $actionType = $approvalService->actionType($viewer, $permission);
                        $activeReviewer = !empty($permission->approval_steps)
                            ? app(\App\Services\ApprovalWorkflow::class)->reviewer($permission)
                            : null;
                        $statusBadgeClass = match($permission->status) {
                            'approved' => 'status-ontime',
                            'pending' => 'amber',
                            'rejected', 'cancelled' => 'error',
                            'cancellation_requested' => 'amber',
                            default => 'muted',
                        };
                    @endphp
                    <tr>
                        <td>
                            <div class="person">
                                <span class="initials" aria-hidden="true">{{ mb_strtoupper(mb_substr($permission->user->name, 0, 2)) }}</span>
                                <div>
                                    <strong>{{ $permission->user->name }}</strong>
                                    <small>{{ $permission->user->department ?: 'Departemen belum diatur' }}</small>
                                </div>
                            </div>
                        </td>
                        <td class="time">
                            <div><strong>{{ $permission->date_range_label }}</strong></div>
                            @if($permission->dates_breakdown)
                                <small class="muted">{{ count($permission->dates_breakdown) }} hari total kalender</small>
                            @endif
                        </td>
                        <td>
                            @if($permission->leave_policy_snapshot)
                                <strong>{{ $permission->leave_policy_snapshot['name'] }}</strong>
                                <small style="display:block;color:var(--muted)">
                                    {{ $permission->leave_policy_snapshot['deducts_balance'] ? 'Mengurangi saldo' : 'Tanpa potong saldo' }}
                                </small>
                            @else
                                <span class="muted">Izin umum</span>
                            @endif
                        </td>
                        <td class="permission-reason">
                            {{ \Illuminate\Support\Str::limit($permission->reason, 100) }}
                        </td>
                        <td>
                            <div>
                                <span class="badge {{ $statusBadgeClass }}" style="font-weight:500">
                                    {{ $permission->approval_label }}
                                </span>
                            </div>
                            @if($permission->status === 'pending')
                                @if($permission->approval_progress)
                                    <small class="muted" style="display:block;margin-top:3px">
                                        Tahap {{ $permission->approval_progress['current'] }} dari {{ $permission->approval_progress['total'] }}: {{ $permission->approval_progress['label'] }}
                                    </small>
                                @endif
                                @if($activeReviewer)
                                    <small class="muted" style="display:block;margin-top:2px">
                                        Peninjau: <strong>{{ $activeReviewer->name }}</strong>
                                    </small>
                                @elseif(!empty($permission->approval_steps))
                                    <small class="error" style="display:block;margin-top:2px">
                                        Peninjau belum tersedia
                                    </small>
                                @endif
                            @elseif($permission->status === 'cancellation_requested')
                                <small class="amber" style="display:block;margin-top:3px">
                                    Permintaan pembatalan menunggu pengelola
                                </small>
                            @endif
                        </td>
                        <td>
                            @if($canDecide)
                                @if($actionType === 'cancellation')
                                    <a class="primary" href="{{ route('permissions.show', $permission) }}" style="white-space:nowrap">Tinjau pembatalan</a>
                                @else
                                    <a class="primary" href="{{ route('permissions.show', $permission) }}" style="white-space:nowrap">Tinjau pengajuan</a>
                                @endif
                            @else
                                <a class="secondary" href="{{ route('permissions.show', $permission) }}">Detail</a>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="empty-state">
                            <strong>Tidak ada pengajuan izin</strong>
                            <p>
                                @if(($filters['action_context'] ?? 'all') !== 'all' || ($filters['status'] ?? 'all') !== 'all' || !empty($filters['name']) || !empty($filters['date']))
                                    Tidak ada pengajuan cuti atau izin yang sesuai dengan kombinasi filter saat ini.
                                @else
                                    Belum ada data pengajuan cuti atau izin.
                                @endif
                            </p>
                            @if(($filters['action_context'] ?? 'all') !== 'all' || ($filters['status'] ?? 'all') !== 'all' || !empty($filters['name']) || !empty($filters['date']))
                                <div style="margin-top:10px">
                                    <a class="secondary" href="{{ route('permissions.index') }}">Reset semua filter</a>
                                </div>
                            @endif
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @include('components.workspace-pagination', ['paginator' => $permissions, 'unit' => 'pengajuan'])
</section>
@endsection
