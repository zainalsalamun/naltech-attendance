@extends('layouts.workspace')
@section('title', 'Rekap Kehadiran')
@section('content')
<div class="page-heading">
    <div>
        <p class="breadcrumb"><a href="{{ route('home') }}">Workspace</a> / <a href="{{ route('attendances.index') }}">Kehadiran</a> / Rekap</p>
        <h1>Rekapitulasi Kehadiran Karyawan</h1>
        <p class="muted">Ringkasan berbasis kalender kerja perusahaan dan tanggal aktif kepegawaian. Maksimal 90 hari inklusif. Zona waktu: <strong>{{ $timezone }}</strong>.</p>
    </div>
    <a class="secondary" href="{{ route('attendance-recap.export-csv', $filters) }}">
        <i class="ion-ios-download-outline" aria-hidden="true"></i> Ekspor CSV Rekap
    </a>
</div>

@include('components.workspace-feedback')

<div class="metrics-grid" style="margin-bottom:24px">
    <div class="metric-card">
        <span class="muted">Karyawan Terdata</span>
        <strong>{{ $summary['total_employees'] }} orang</strong>
        <small class="muted">Termasuk karyawan nonaktif/arsip pada periode terkait</small>
    </div>
    <div class="metric-card">
        <span class="muted">Hari Kerja Kalender</span>
        <strong>{{ $summary['scheduled_working_days'] }} hari</strong>
        <small class="muted">{{ $startDate }} s/d {{ $endDate }}</small>
    </div>
    <div class="metric-card">
        <span class="muted">Tingkat Kehadiran Terjadwal</span>
        <strong style="color:var(--good)">{{ $summary['attendance_rate'] }}%</strong>
        <small class="muted">{{ $summary['scheduled_present'] }} hadir / {{ $summary['scheduled_capacity'] }} hari kerja terjadwal</small>
    </div>
    <div class="metric-card">
        <span class="muted">Tepat Waktu / Terlambat</span>
        <strong>{{ $summary['total_ontime'] }} / {{ $summary['total_late'] }}</strong>
        <small class="muted">Berdasarkan jadwal efektif</small>
    </div>
    <div class="metric-card">
        <span class="muted">Cuti / Izin Efektif</span>
        <strong>{{ $summary['total_leave'] }} hari</strong>
        <small class="muted">Termasuk permohonan pembatalan berjalan</small>
    </div>
    <div class="metric-card">
        <span class="muted">Alpa (Hari Lampau Selesai)</span>
        <strong style="{{ $summary['total_absent'] > 0 ? 'color:var(--error)' : 'color:var(--muted)' }}">{{ $summary['total_absent'] }} hari</strong>
        <small class="muted">Hari kerja lampau tanpa kehadiran/izin</small>
    </div>
</div>

<section class="panel">
    <div class="panel-heading">
        <div>
            <h2>Tabel Rekapitulasi Periode</h2>
            <p class="muted">Periode {{ $startDate }} sampai {{ $endDate }} · Filter unit mencerminkan unit organisasi saat ini</p>
        </div>
    </div>

    <form method="get" action="{{ route('attendance-recap.index') }}" class="record-filters" style="display:flex;flex-wrap:wrap;gap:12px;align-items:flex-end">
        <div class="field">
            <label for="start_date">Dari tanggal</label>
            <input id="start_date" type="date" name="start_date" value="{{ $startDate }}" required>
        </div>
        <div class="field">
            <label for="end_date">Sampai tanggal</label>
            <input id="end_date" type="date" name="end_date" value="{{ $endDate }}" required>
        </div>
        <div class="field">
            <label for="name">Nama karyawan</label>
            <input id="name" type="search" name="name" value="{{ $nameFilter }}" maxlength="100" placeholder="Cari nama karyawan">
        </div>
        @if($organizationUnits->isNotEmpty())
            <div class="field">
                <label for="organization_unit_id">Unit Organisasi (Saat Ini)</label>
                <select id="organization_unit_id" name="organization_unit_id">
                    <option value="">Semua Unit</option>
                    @foreach($organizationUnits as $unit)
                        <option value="{{ $unit->id }}" @selected(($filters['organization_unit_id'] ?? null) == $unit->id)>{{ $unit->name }}</option>
                    @endforeach
                </select>
            </div>
        @endif
        @if(!empty($filters['user_id']))
            <input type="hidden" name="user_id" value="{{ $filters['user_id'] }}">
        @endif
        <div style="display:flex;gap:8px">
            <button type="submit" class="primary">Terapkan</button>
            <a class="secondary" href="{{ route('attendance-recap.index') }}">Reset</a>
        </div>
    </form>

    <div class="table-scroll" tabindex="0" role="region" aria-label="Tabel rekapitulasi kehadiran karyawan periode">
        <table>
            <caption class="sr-only">Tabel rekapitulasi kehadiran karyawan periode</caption>
            <thead>
                <tr>
                    <th scope="col">Karyawan</th>
                    <th scope="col">Unit Saat Ini</th>
                    <th scope="col">Hari Terjadwal</th>
                    <th scope="col">Tepat Waktu</th>
                    <th scope="col">Terlambat</th>
                    <th scope="col">Perlu Tinjau</th>
                    <th scope="col">Total Hadir</th>
                    <th scope="col">Cuti / Izin</th>
                    <th scope="col">Alpa</th>
                    <th scope="col">Total Jam Kerja</th>
                    <th scope="col">Tindakan</th>
                </tr>
            </thead>
            <tbody>
                @forelse($recap as $row)
                    @php($user = $row['user'])
                    <tr>
                        <td>
                            <div class="person">
                                <span class="initials" aria-hidden="true">{{ mb_strtoupper(mb_substr($user->name, 0, 2)) }}</span>
                                <div>
                                    <strong>{{ $user->name }}</strong>
                                    <small class="muted">{{ ucfirst($user->role) }}</small>
                                </div>
                            </div>
                        </td>
                        <td>
                            <small>{{ $user->organizationUnit?->name ?: ($user->department ?: 'Belum diatur') }}</small>
                        </td>
                        <td class="time">{{ $row['scheduled_days'] }} hari</td>
                        <td class="time" style="color:var(--good)">{{ $row['ontime_days'] }}</td>
                        <td class="time" style="color:var(--amber)">{{ $row['late_days'] }}</td>
                        <td class="time" style="color:var(--muted)">{{ $row['review_days'] }}</td>
                        <td class="time"><strong>{{ $row['present_days'] }} hari</strong></td>
                        <td class="time">{{ $row['leave_days'] }} hari</td>
                        <td class="time" style="{{ $row['absent_days'] > 0 ? 'color:var(--error);font-weight:600' : 'color:var(--muted)' }}">
                            {{ $row['absent_days'] }} hari
                            @if(!empty($row['unconfirmed_days']))
                                <br><small class="muted">({{ $row['unconfirmed_days'] }} belum hadir hari ini)</small>
                            @endif
                        </td>
                        <td class="time">
                            <strong>{{ number_format($row['total_work_hours'], 1, ',', '.') }} jam</strong>
                        </td>
                        <td>
                            <a class="secondary" style="font-size:12px;padding:4px 8px" href="{{ route('attendance-recap.show', array_merge(['user' => $user->id], array_filter([
                                'start_date' => $startDate,
                                'end_date' => $endDate,
                                'name' => $nameFilter ?: null,
                                'organization_unit_id' => $filters['organization_unit_id'] ?? null,
                                'page' => request()->input('page') ?: null,
                            ]))) }}">
                                Detail Harian <i class="ion-ios-arrow-thin-right" aria-hidden="true"></i>
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="11" class="empty-state">
                            <i class="ion-ios-search" aria-hidden="true"></i>
                            <strong>Tidak ada data karyawan sesuai filter</strong>
                            <p>Coba sesuaikan rentang tanggal atau kata kunci pencarian.</p>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @include('components.workspace-pagination', ['paginator' => $paginator, 'unit' => 'karyawan'])
</section>
@endsection
