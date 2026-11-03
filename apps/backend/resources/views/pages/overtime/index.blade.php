@extends('layouts.workspace')
@section('title', 'Lembur')
@section('content')
<div class="page-heading">
    <div>
        <p class="breadcrumb"><a href="{{ route('home') }}">Workspace</a> / Lembur</p>
        <h1>Pengajuan lembur</h1>
        <p class="muted">Kelola durasi pekerjaan tambahan dan persetujuan karyawan.</p>
    </div>
    <a class="primary" href="{{ route('overtimes.create') }}"><i class="ion-ios-plus-empty" aria-hidden="true"></i> Tambah lembur</a>
</div>

@include('components.workspace-feedback')

<section class="panel">
    <div class="panel-heading">
        <div>
            <h2>Daftar pengajuan lembur</h2>
            <p class="muted">{{ $overtimes->total() }} pengajuan sesuai kriteria pencarian</p>
        </div>
    </div>

    <form class="record-filters" method="get" action="{{ route('overtimes.index') }}">
        <div class="field">
            <label for="name">Nama karyawan</label>
            <input id="name" name="name" type="search" value="{{ $filters['name'] }}" maxlength="100" placeholder="Cari nama karyawan">
        </div>
        <div class="field">
            <label for="date">Tanggal lembur</label>
            <input id="date" name="date" type="date" value="{{ $filters['date'] }}">
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
            <label for="status">Status lembur</label>
            <select id="status" name="status">
                @foreach(['all' => 'Semua status', 'pending' => 'Menunggu persetujuan', 'approved' => 'Disetujui', 'rejected' => 'Ditolak', 'cancelled' => 'Dibatalkan'] as $value => $label)
                    <option value="{{ $value }}" @selected($filters['status'] === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <button class="primary" type="submit">Terapkan</button>
        <a class="secondary" href="{{ route('overtimes.index') }}">Reset</a>
    </form>

    <div class="table-scroll" tabindex="0" role="region" aria-label="Pengajuan lembur perusahaan — tabel dapat digeser horizontal">
        <table>
            <caption class="sr-only">Pengajuan lembur perusahaan</caption>
            <thead>
                <tr>
                    <th scope="col">Karyawan</th>
                    <th scope="col">Tanggal</th>
                    <th scope="col">Durasi</th>
                    <th scope="col">Status & Tahap</th>
                    <th scope="col">Catatan</th>
                    <th scope="col">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse($overtimes as $overtime)
                    @php
                        $viewer = auth()->user();
                        $approvalService = app(\App\Services\SubmissionApproval::class);
                        $canDecide = $approvalService->canDecide($viewer, $overtime);
                        $activeReviewer = !empty($overtime->approval_steps)
                            ? app(\App\Services\ApprovalWorkflow::class)->reviewer($overtime)
                            : null;
                        $statusBadgeClass = match($overtime->status) {
                            'approved' => 'status-ontime',
                            'pending' => 'amber',
                            'rejected', 'cancelled' => 'error',
                            default => 'muted',
                        };
                    @endphp
                    <tr>
                        <td>
                            <div class="person">
                                <span class="initials" aria-hidden="true">{{ mb_strtoupper(mb_substr($overtime->user->name, 0, 2)) }}</span>
                                <div>
                                    <strong>{{ $overtime->user->name }}</strong>
                                    <small>{{ $overtime->user->department ?: 'Departemen belum diatur' }}</small>
                                </div>
                            </div>
                        </td>
                        <td class="time">{{ $overtime->date }}</td>
                        <td class="time"><strong>{{ $overtime->duration }} menit</strong></td>
                        <td>
                            <div>
                                <span class="badge {{ $statusBadgeClass }}">
                                    {{ ['pending' => 'Menunggu persetujuan', 'approved' => 'Disetujui', 'rejected' => 'Ditolak', 'cancelled' => 'Dibatalkan'][$overtime->status] ?? 'Perlu ditinjau' }}
                                </span>
                            </div>
                            @if($overtime->status === 'pending')
                                @if($overtime->approval_progress)
                                    <small class="muted" style="display:block;margin-top:3px">
                                        Tahap {{ $overtime->approval_progress['current'] }} dari {{ $overtime->approval_progress['total'] }}: {{ $overtime->approval_progress['label'] }}
                                    </small>
                                @endif
                                @if($activeReviewer)
                                    <small class="muted" style="display:block;margin-top:2px">
                                        Peninjau: <strong>{{ $activeReviewer->name }}</strong>
                                    </small>
                                @elseif(!empty($overtime->approval_steps))
                                    <small class="error" style="display:block;margin-top:2px">
                                        Peninjau belum tersedia
                                    </small>
                                @endif
                            @endif
                        </td>
                        <td class="permission-reason">
                            {{ \Illuminate\Support\Str::limit($overtime->description, 80) ?: 'Tanpa catatan' }}
                        </td>
                        <td>
                            <div class="record-actions">
                                @if($canDecide)
                                    <a class="primary" href="{{ route('overtimes.show', $overtime) }}" aria-label="Tinjau pengajuan lembur {{ $overtime->user->name }}">Tinjau pengajuan</a>
                                @else
                                    <a class="secondary" href="{{ route('overtimes.show', $overtime) }}" aria-label="Detail lembur {{ $overtime->user->name }}">Detail</a>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="empty-state">
                            <strong>Tidak ada pengajuan lembur</strong>
                            <p>
                                @if(($filters['action_context'] ?? 'all') !== 'all' || ($filters['status'] ?? 'all') !== 'all' || !empty($filters['name']) || !empty($filters['date']))
                                    Tidak ada pengajuan lembur yang sesuai dengan kombinasi filter saat ini.
                                @else
                                    Belum ada data pengajuan lembur.
                                @endif
                            </p>
                            @if(($filters['action_context'] ?? 'all') !== 'all' || ($filters['status'] ?? 'all') !== 'all' || !empty($filters['name']) || !empty($filters['date']))
                                <div style="margin-top:10px">
                                    <a class="secondary" href="{{ route('overtimes.index') }}">Reset semua filter</a>
                                </div>
                            @endif
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @include('components.workspace-pagination', ['paginator' => $overtimes, 'unit' => 'pengajuan'])
</section>
@endsection
