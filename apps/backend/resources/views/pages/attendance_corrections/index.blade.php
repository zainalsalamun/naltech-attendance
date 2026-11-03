@extends('layouts.workspace')
@section('title', 'Koreksi Kehadiran')
@section('content')
<div class="page-heading">
    <div>
        <p class="breadcrumb"><a href="{{ route('home') }}">Workspace</a> / <a href="{{ route('attendances.index') }}">Kehadiran</a> / Koreksi</p>
        <h1>Jejak audit koreksi kehadiran</h1>
        <p class="muted">Pantau seluruh riwayat penyesuaian catatan jam masuk dan pulang karyawan oleh pengelola.</p>
    </div>
</div>

@include('components.workspace-feedback')

<section class="panel">
    <div class="panel-heading">
        <div>
            <h2>Riwayat penyesuaian absensi</h2>
            <p class="muted">{{ $corrections->total() }} catatan koreksi · Perusahaan Anda</p>
        </div>
    </div>

    <form method="get" action="{{ route('attendance-corrections.index') }}" class="record-filters">
        <div class="field">
            <label for="name">Nama karyawan</label>
            <input id="name" type="search" name="name" value="{{ $name }}" maxlength="100" placeholder="Cari nama karyawan">
        </div>
        <div class="field">
            <label for="date">Tanggal absensi</label>
            <input id="date" type="date" name="date" value="{{ $date }}">
        </div>
        <button type="submit" class="primary">Terapkan</button>
        <a class="secondary" href="{{ route('attendance-corrections.index') }}">Reset filter</a>
    </form>

    <div class="table-scroll" tabindex="0" role="region" aria-label="Tabel jejak audit koreksi kehadiran">
        <table>
            <caption class="sr-only">Jejak audit koreksi kehadiran</caption>
            <thead>
                <tr>
                    <th scope="col">Waktu Koreksi</th>
                    <th scope="col">Karyawan</th>
                    <th scope="col">Tanggal Absensi</th>
                    <th scope="col">Pengoreksi</th>
                    <th scope="col">Alasan Koreksi</th>
                    <th scope="col">Perubahan (Sebelum → Sesudah)</th>
                    <th scope="col">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse($corrections as $event)
                    @php
                        $user = $event->attendance?->user;
                        $snapshot = $event->snapshot ?? [];
                        $before = $snapshot['before'] ?? [];
                        $after = $snapshot['after'] ?? [];
                    @endphp
                    <tr>
                        <td class="time">
                            {{ $event->created_at ? $event->created_at->format('Y-m-d H:i') : '—' }}
                        </td>
                        <td>
                            @if($user)
                                <strong>{{ $user->name }}</strong>
                            @else
                                <span class="muted">—</span>
                            @endif
                        </td>
                        <td class="time">{{ $event->attendance?->date ?? '—' }}</td>
                        <td>
                            <span class="badge status-ontime">{{ $event->actor?->name ?? 'Pengelola' }}</span>
                        </td>
                        <td class="permission-reason" style="max-width:240px">
                            {{ $event->reason }}
                        </td>
                        <td>
                            <div style="font-size:12px;display:flex;flex-direction:column;gap:4px">
                                <div>
                                    <span class="muted">Masuk:</span> 
                                    <code>{{ $before['time_in'] ?? '—' }}</code> → <strong><code>{{ $after['time_in'] ?? '—' }}</code></strong>
                                </div>
                                <div>
                                    <span class="muted">Pulang:</span> 
                                    <code>{{ $before['time_out'] ?? '—' }}</code> → <strong><code>{{ $after['time_out'] ?? '—' }}</code></strong>
                                </div>
                            </div>
                        </td>
                        <td>
                            @if($event->attendance)
                                <a class="secondary" href="{{ route('attendances.edit', $event->attendance) }}" aria-label="Buka detail absensi {{ $user?->name }}">
                                    Detail
                                </a>
                            @else
                                <span class="muted">—</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="empty-state">
                            <strong>Tidak ada riwayat koreksi kehadiran</strong>
                            <p>Belum ada catatan absensi yang disesuaikan atau coba kriteria pencarian lain.</p>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @include('components.workspace-pagination', ['paginator' => $corrections])
</section>
@endsection
