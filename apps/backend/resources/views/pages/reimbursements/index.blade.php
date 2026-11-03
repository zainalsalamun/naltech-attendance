@extends('layouts.workspace')
@section('title', 'Reimbursement')
@section('content')
<div class="page-heading">
    <div>
        <p class="breadcrumb"><a href="{{ route('home') }}">Workspace</a> / Reimbursement</p>
        <h1>Pengajuan reimbursement</h1>
        <p class="muted">Kelola penggantian biaya, bukti pengeluaran, dan persetujuan karyawan.</p>
    </div>
    <a class="primary" href="{{ route('reimbursements.create') }}"><i class="ion-ios-plus-empty" aria-hidden="true"></i> Tambah reimbursement</a>
</div>

@include('components.workspace-feedback')

<section class="panel">
    <div class="panel-heading">
        <div>
            <h2>Daftar pengajuan reimbursement</h2>
            <p class="muted">{{ $reimbursements->total() }} pengajuan sesuai kriteria pencarian</p>
        </div>
    </div>

    <form class="record-filters" method="get" action="{{ route('reimbursements.index') }}">
        <div class="field">
            <label for="name">Nama karyawan</label>
            <input id="name" name="name" type="search" value="{{ $filters['name'] }}" maxlength="100" placeholder="Cari nama karyawan">
        </div>
        <div class="field">
            <label for="date">Tanggal reimbursement</label>
            <input id="date" name="date" type="date" value="{{ $filters['date'] }}">
        </div>
        <input type="hidden" name="user_id" value="{{ $filters['user_id'] }}">
        <div class="field">
            <label for="action_context">Konteks tindakan</label>
            <select id="action_context" name="action_context">
                <option value="all" @selected(($filters['action_context'] ?? 'all') === 'all')>Semua yang dapat saya lihat</option>
                <option value="actionable" @selected(($filters['action_context'] ?? '') === 'actionable')>Perlu tindakan saya</option>
                <option value="waiting_others" @selected(($filters['action_context'] ?? '') === 'waiting_others')>Menunggu peninjau lain</option>
            </select>
        </div>
        <div class="field">
            <label for="status">Status reimbursement</label>
            <select id="status" name="status">
                @foreach(['all' => 'Semua status', 'pending' => 'Menunggu persetujuan', 'approved' => 'Disetujui', 'rejected' => 'Ditolak', 'cancelled' => 'Dibatalkan'] as $value => $label)
                    <option value="{{ $value }}" @selected($filters['status'] === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <button class="primary" type="submit">Terapkan</button>
        <a class="secondary" href="{{ route('reimbursements.index') }}">Reset</a>
    </form>

    <div class="table-scroll" tabindex="0" role="region" aria-label="Pengajuan reimbursement perusahaan — tabel dapat digeser horizontal">
        <table>
            <caption class="sr-only">Pengajuan reimbursement perusahaan</caption>
            <thead>
                <tr>
                    <th scope="col">Karyawan</th>
                    <th scope="col">Tanggal</th>
                    <th scope="col">Nominal</th>
                    <th scope="col">Status & Tahap</th>
                    <th scope="col">Catatan</th>
                    <th scope="col">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse($reimbursements as $reimbursement)
                    @php
                        $viewer = auth()->user();
                        $approvalService = app(\App\Services\SubmissionApproval::class);
                        $canDecide = $approvalService->canDecide($viewer, $reimbursement);
                        $activeReviewer = !empty($reimbursement->approval_steps)
                            ? app(\App\Services\ApprovalWorkflow::class)->reviewer($reimbursement)
                            : null;
                        $statusBadgeClass = match($reimbursement->status) {
                            'approved' => 'status-ontime',
                            'pending' => 'amber',
                            'rejected', 'cancelled' => 'error',
                            default => 'muted',
                        };
                    @endphp
                    <tr>
                        <td>
                            <div class="person">
                                <span class="initials" aria-hidden="true">{{ mb_strtoupper(mb_substr($reimbursement->user->name, 0, 2)) }}</span>
                                <div>
                                    <strong>{{ $reimbursement->user->name }}</strong>
                                    <small>{{ $reimbursement->user->department ?: 'Departemen belum diatur' }}</small>
                                </div>
                            </div>
                        </td>
                        <td class="time">{{ $reimbursement->date }}</td>
                        <td class="time"><strong>Rp {{ number_format($reimbursement->amount, 2, ',', '.') }}</strong></td>
                        <td>
                            <div>
                                <span class="badge {{ $statusBadgeClass }}">
                                    {{ ['pending' => 'Menunggu persetujuan', 'approved' => 'Disetujui', 'rejected' => 'Ditolak', 'cancelled' => 'Dibatalkan'][$reimbursement->status] ?? 'Perlu ditinjau' }}
                                </span>
                            </div>
                            @if($reimbursement->status === 'pending')
                                @if($reimbursement->approval_progress)
                                    <small class="muted" style="display:block;margin-top:3px">
                                        Tahap {{ $reimbursement->approval_progress['current'] }} dari {{ $reimbursement->approval_progress['total'] }}: {{ $reimbursement->approval_progress['label'] }}
                                    </small>
                                @endif
                                @if($activeReviewer)
                                    <small class="muted" style="display:block;margin-top:2px">
                                        Peninjau: <strong>{{ $activeReviewer->name }}</strong>
                                    </small>
                                @elseif(!empty($reimbursement->approval_steps))
                                    <small class="error" style="display:block;margin-top:2px">
                                        Peninjau belum tersedia
                                    </small>
                                @endif
                            @endif
                        </td>
                        <td class="permission-reason">{{ \Illuminate\Support\Str::limit($reimbursement->description, 80) ?: 'Tanpa catatan' }}</td>
                        <td>
                            <div class="record-actions">
                                @if($canDecide)
                                    <a class="primary" href="{{ route('reimbursements.show', $reimbursement) }}" aria-label="Tinjau pengajuan reimbursement {{ $reimbursement->user->name }}">Tinjau pengajuan</a>
                                @else
                                    <a class="secondary" href="{{ route('reimbursements.show', $reimbursement) }}" aria-label="Detail reimbursement {{ $reimbursement->user->name }}">Detail</a>
                                @endif
                                @if($reimbursement->status === 'pending' && (auth()->id() === $reimbursement->user_id || in_array(auth()->user()?->role, ['admin', 'super admin', 'hrd'], true)))
                                    <a class="secondary" href="{{ route('reimbursements.edit', $reimbursement) }}" aria-label="Edit reimbursement {{ $reimbursement->user->name }}">Edit</a>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="empty-state">
                            <strong>Tidak ada pengajuan reimbursement</strong>
                            <p>
                                @if(($filters['action_context'] ?? 'all') !== 'all' || ($filters['status'] ?? 'all') !== 'all' || !empty($filters['name']) || !empty($filters['date']))
                                    Tidak ada pengajuan reimbursement yang sesuai dengan kombinasi filter saat ini.
                                @else
                                    Belum ada data pengajuan reimbursement.
                                @endif
                            </p>
                            @if(($filters['action_context'] ?? 'all') !== 'all' || ($filters['status'] ?? 'all') !== 'all' || !empty($filters['name']) || !empty($filters['date']))
                                <a class="secondary" href="{{ route('reimbursements.index') }}" style="margin-top:8px">Reset filter</a>
                            @endif
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @include('components.workspace-pagination', ['paginator' => $reimbursements, 'unit' => 'pengajuan'])
</section>
@endsection
