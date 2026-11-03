@extends('layouts.workspace')
@section('title', 'Detail Rekap: ' . $user->name)
@section('content')
<div class="page-heading">
    <div>
        <p class="breadcrumb"><a href="{{ route('home') }}">Workspace</a> / <a href="{{ route('attendances.index') }}">Kehadiran</a> / <a href="{{ $returnUrl }}">Rekap</a> / Detail</p>
        <h1>Detail Rekap Kehadiran: {{ $user->name }}</h1>
        <p class="muted">Penelusuran harian dari {{ $startDate }} sampai {{ $endDate }} · Zona waktu: <strong>{{ $timezone }}</strong></p>
    </div>
    <a class="secondary" href="{{ $returnUrl }}">
        <i class="ion-ios-arrow-thin-left" aria-hidden="true"></i> Kembali ke Rekap
    </a>
</div>

@include('components.workspace-feedback')

<div class="panel" style="margin-bottom:20px;padding:16px 20px">
    <div style="display:flex;flex-wrap:wrap;gap:24px;align-items:center">
        <div>
            <span class="muted" style="font-size:12px;display:block">Karyawan</span>
            <strong>{{ $user->name }}</strong> ({{ ucfirst($user->role) }})
        </div>
        <div>
            <span class="muted" style="font-size:12px;display:block">Unit Organisasi (Saat Ini)</span>
            <strong>{{ $user->organizationUnit?->name ?: ($user->department ?: 'Belum diatur') }}</strong>
        </div>
        <div>
            <span class="muted" style="font-size:12px;display:block">Atasan Langsung</span>
            <strong>{{ $user->manager?->name ?: 'Tidak ada atasan langsung' }}</strong>
        </div>
        <div>
            <span class="muted" style="font-size:12px;display:block">Periode Ditampilkan</span>
            <strong>{{ $startDate }} s/d {{ $endDate }}</strong>
        </div>
    </div>
</div>

<section class="panel">
    <div class="panel-heading">
        <div>
            <h2>Jadwal & Riwayat Harian</h2>
            <p class="muted">Perbandingan jadwal kalender, catatan absensi, dan izin/cuti resmi per tanggal.</p>
        </div>
    </div>

    <div class="table-scroll" tabindex="0" role="region" aria-label="Tabel detail rekap harian karyawan">
        <table>
            <caption class="sr-only">Tabel detail rekap harian karyawan</caption>
            <thead>
                <tr>
                    <th scope="col">Tanggal</th>
                    <th scope="col">Kalender</th>
                    <th scope="col">Jadwal Shift</th>
                    <th scope="col">Catatan Absensi</th>
                    <th scope="col">Cuti / Izin Efektif</th>
                    <th scope="col">Jam Kerja</th>
                    <th scope="col">Status Harian</th>
                </tr>
            </thead>
            <tbody>
                @foreach($days as $item)
                    @php
                        $dt = \Carbon\Carbon::parse($item['date']);
                    @endphp
                    <tr>
                        <td>
                            <strong>{{ $dt->locale('id')->translatedFormat('D, d M Y') }}</strong>
                            @if($item['date'] === $today)
                                <span class="badge" style="margin-left:4px;font-size:10px;background:var(--accent);color:#fff">Hari Ini</span>
                            @endif
                        </td>
                        <td>
                            <small class="muted">{{ $item['calendar']['label'] ?? '—' }}</small>
                        </td>
                        <td>
                            <small>{{ $item['schedule_info'] }}</small>
                        </td>
                        <td>
                            @if($item['attendance'])
                                <div>
                                    <code>{{ substr($item['attendance']->time_in, 0, 5) }}</code> – 
                                    <code>{{ $item['attendance']->time_out ? substr($item['attendance']->time_out, 0, 5) : '—' }}</code>
                                    <br>
                                    <small class="muted">
                                        <a href="{{ route('attendances.edit', $item['attendance']) }}">Koreksi absensi</a>
                                    </small>
                                </div>
                            @else
                                <span class="muted">—</span>
                            @endif
                        </td>
                        <td>
                            @if($item['permission'])
                                <div>
                                    <strong>{{ $item['permission']->title ?? 'Izin / Cuti' }}</strong>
                                    <br>
                                    <small class="muted">
                                        {{ $item['permission']->status === 'cancellation_requested' ? 'Menunggu pembatalan' : 'Disetujui' }}
                                        · <a href="{{ route('permissions.show', $item['permission']) }}">Detail</a>
                                    </small>
                                </div>
                            @else
                                <span class="muted">—</span>
                            @endif
                        </td>
                        <td class="time">
                            {{ $item['work_hours'] !== null ? $item['work_hours'] . ' jam' : '—' }}
                        </td>
                        <td>
                            <span class="badge {{ $item['badge_class'] }}">
                                {{ $item['status_label'] }}
                            </span>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</section>
@endsection
