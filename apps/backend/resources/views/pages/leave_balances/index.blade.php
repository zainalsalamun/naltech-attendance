@extends('layouts.workspace')
@section('title', 'Saldo & Alokasi Cuti')
@section('content')
<div class="page-heading">
    <div>
        <p class="breadcrumb"><a href="{{ route('home') }}">Workspace</a> / Saldo cuti / Alokasi</p>
        <h1>Saldo & Alokasi Cuti</h1>
        <p class="muted">Kelola kuota tahunan, pantau saldo cadangan dan terpakai, serta berikan alokasi cuti karyawan.</p>
    </div>
    <div style="display:flex;gap:8px;flex-wrap:wrap">
        <a class="secondary" href="{{ route('leave-balances.ledger', ['year' => $selectedYear]) }}"><i class="ion-ios-list-outline" aria-hidden="true"></i> Buku besar transaksi</a>
        <a class="secondary" href="{{ route('leave-types.index') }}"><i class="ion-ios-filing-outline" aria-hidden="true"></i> Master jenis cuti</a>
    </div>
</div>

@include('components.workspace-feedback')

<section class="panel" style="margin-bottom:24px">
    <div class="metrics">
        <div class="metric">
            <div class="metric-heading">
                <span>Karyawan teralokasi</span>
                <span class="metric-icon"><i class="ion-ios-people-outline" aria-hidden="true"></i></span>
            </div>
            <div class="metric-value">{{ $summary['total_employees_allocated'] }} <small>orang</small></div>
            <p class="muted">Tahun periode {{ $selectedYear }}</p>
        </div>
        <div class="metric">
            <div class="metric-heading">
                <span>Total dialokasikan</span>
                <span class="metric-icon"><i class="ion-ios-calendar-outline" aria-hidden="true"></i></span>
            </div>
            <div class="metric-value">{{ $summary['total_allocated_days'] }} <small>hari</small></div>
            <p class="muted">Kuota yang diberikan HR</p>
        </div>
        <div class="metric">
            <div class="metric-heading">
                <span>Sedang dicadangkan</span>
                <span class="metric-icon"><i class="ion-ios-clock-outline" aria-hidden="true"></i></span>
            </div>
            <div class="metric-value amber">{{ $summary['total_reserved_days'] }} <small>hari</small></div>
            <p class="muted">Menunggu proses persetujuan</p>
        </div>
        <div class="metric">
            <div class="metric-heading">
                <span>Sisa saldo tersedia</span>
                <span class="metric-icon"><i class="ion-ios-pie-outline" aria-hidden="true"></i></span>
            </div>
            <div class="metric-value">{{ $summary['total_remaining_days'] }} <small>hari</small></div>
            <p class="muted">{{ $summary['total_used_days'] }} hari telah disetujui final</p>
        </div>
    </div>
</section>

<div class="dashboard-grid">
    <section class="panel">
        <div class="panel-heading">
            <div>
                <h2>Daftar saldo karyawan</h2>
                <p class="muted">{{ $balances->total() }} catatan saldo untuk tahun {{ $selectedYear }}</p>
            </div>
        </div>

        <form method="get" action="{{ route('leave-balances.index') }}" class="record-filters">
            <div class="field" style="max-width:110px">
                <label for="filter-year">Tahun</label>
                <input id="filter-year" type="number" name="year" value="{{ $selectedYear }}" min="2020" max="2050">
            </div>
            <div class="field">
                <label for="filter-name">Nama karyawan</label>
                <input id="filter-name" type="search" name="name" value="{{ $nameFilter }}" placeholder="Cari nama karyawan">
            </div>
            <div class="field">
                <label for="filter-type">Jenis cuti</label>
                <select id="filter-type" name="leave_type_id">
                    <option value="">Semua jenis pemotong saldo</option>
                    @foreach($leaveTypes as $type)
                        <option value="{{ $type->id }}" @selected($typeFilter === $type->id)>{{ $type->code }} — {{ $type->name }}</option>
                    @endforeach
                </select>
            </div>
            <button class="primary" type="submit">Terapkan</button>
            <a class="secondary" href="{{ route('leave-balances.index') }}">Reset</a>
        </form>

        <div class="table-scroll" tabindex="0" role="region" aria-label="Tabel saldo cuti karyawan">
            <table>
                <thead>
                    <tr>
                        <th scope="col">Karyawan</th>
                        <th scope="col">Jenis cuti</th>
                        <th scope="col">Periode</th>
                        <th scope="col" style="text-align:right">Alokasi</th>
                        <th scope="col" style="text-align:right">Cadangan</th>
                        <th scope="col" style="text-align:right">Terpakai</th>
                        <th scope="col" style="text-align:right">Sisa saldo</th>
                        <th scope="col">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($balances as $row)
                        <tr>
                            <td>
                                <div class="person">
                                    <span class="initials" aria-hidden="true">{{ mb_strtoupper(mb_substr($row->user->name, 0, 2)) }}</span>
                                    <div>
                                        <strong>{{ $row->user->name }}</strong>
                                        <small>{{ $row->user->department ?: 'Departemen belum diatur' }}</small>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <strong>{{ $row->leaveType->code }}</strong>
                                <small style="display:block;color:var(--muted)">{{ $row->leaveType->name }}</small>
                            </td>
                            <td>Tahun {{ $row->period_year }}</td>
                            <td style="text-align:right;font-weight:500">{{ $row->allocated_days }} hr</td>
                            <td style="text-align:right;color:var(--amber)">{{ $row->reserved_days }} hr</td>
                            <td style="text-align:right;color:var(--muted)">{{ $row->used_days }} hr</td>
                            <td style="text-align:right">
                                <strong style="color:var(--good);font-size:13px">{{ $row->remaining_days }} hari</strong>
                            </td>
                            <td>
                                <a class="secondary" href="{{ route('leave-balances.ledger', ['year' => $row->period_year, 'user_id' => $row->user_id]) }}" title="Lihat riwayat transaksi saldo">Riwayat</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="empty-state">
                                <strong>Belum ada alokasi saldo cuti</strong>
                                <p>Gunakan formulir di samping untuk memberikan alokasi kuota cuti tahunan kepada karyawan.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @include('components.workspace-pagination', ['paginator' => $balances, 'unit' => 'karyawan'])
    </section>

    <div class="dashboard-rail">
        <section class="panel" style="padding:20px 18px">
            <h2><i class="ion-ios-personadd-outline" aria-hidden="true"></i> Alokasi per Karyawan</h2>
            <p class="muted" style="margin:5px 0 15px">Atur atau sesuaikan saldo cuti individu. Setiap perubahan dicatat ke buku besar audit.</p>

            <form method="post" action="{{ route('leave-balances.store') }}">
                @csrf
                <div class="field" style="margin-bottom:12px">
                    <label for="alloc-user">Pilih Karyawan</label>
                    <select id="alloc-user" name="user_id" required>
                        <option value="">-- Pilih karyawan aktif --</option>
                        @foreach($employees as $emp)
                            <option value="{{ $emp->id }}">{{ $emp->name }} ({{ $emp->department ?: 'Tanpa departemen' }})</option>
                        @endforeach
                    </select>
                </div>

                <div class="field" style="margin-bottom:12px">
                    <label for="alloc-type">Jenis Cuti</label>
                    <select id="alloc-type" name="leave_type_id" required>
                        <option value="">-- Pilih jenis pemotong saldo --</option>
                        @foreach($leaveTypes as $type)
                            <option value="{{ $type->id }}">{{ $type->code }} — {{ $type->name }} (Kuota acuan: {{ $type->annual_quota_days ?? 0 }} hari)</option>
                        @endforeach
                    </select>
                </div>

                <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:12px">
                    <div class="field">
                        <label for="alloc-year">Tahun Periode</label>
                        <input id="alloc-year" type="number" name="period_year" value="{{ $selectedYear }}" required min="2020" max="2050">
                    </div>
                    <div class="field">
                        <label for="alloc-days">Total Alokasi (Hari)</label>
                        <input id="alloc-days" type="number" name="allocated_days" min="0" max="366" value="12" required>
                    </div>
                </div>

                <div class="field" style="margin-bottom:15px">
                    <label for="alloc-reason">Alasan / Catatan Penyesuaian (Wajib)</label>
                    <textarea id="alloc-reason" name="reason" required maxlength="1000" rows="3" placeholder="Contoh: Alokasi cuti tahunan reguler periode 2026"></textarea>
                </div>

                <button class="primary" type="submit" style="width:100%">Simpan Alokasi</button>
            </form>
        </section>

        <section class="panel" style="padding:20px 18px">
            <h2><i class="ion-ios-people-outline" aria-hidden="true"></i> Alokasi Massal</h2>
            <p class="muted" style="margin:5px 0 15px">Berikan alokasi kuota yang sama ke seluruh karyawan aktif perusahaan sekaligus.</p>

            <form method="post" action="{{ route('leave-balances.batch') }}" onsubmit="return confirm('Apakah Anda yakin ingin menerapkan kuota ini ke seluruh karyawan aktif?');">
                @csrf
                <div class="field" style="margin-bottom:12px">
                    <label for="batch-type">Jenis Cuti</label>
                    <select id="batch-type" name="leave_type_id" required>
                        <option value="">-- Pilih jenis pemotong saldo --</option>
                        @foreach($leaveTypes as $type)
                            <option value="{{ $type->id }}">{{ $type->code }} — {{ $type->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:12px">
                    <div class="field">
                        <label for="batch-year">Tahun Periode</label>
                        <input id="batch-year" type="number" name="period_year" value="{{ $selectedYear }}" required min="2020" max="2050">
                    </div>
                    <div class="field">
                        <label for="batch-days">Kuota (Hari)</label>
                        <input id="batch-days" type="number" name="allocated_days" min="0" max="366" value="12" required>
                    </div>
                </div>

                <div class="field" style="margin-bottom:15px">
                    <label for="batch-reason">Alasan Alokasi Massal (Wajib)</label>
                    <textarea id="batch-reason" name="reason" required maxlength="1000" rows="2" placeholder="Contoh: Alokasi tahunan seluruh staf periode 2026"></textarea>
                </div>

                <button class="secondary" type="submit" style="width:100%">Terapkan ke Semua Staf</button>
            </form>
        </section>
    </div>
</div>
@endsection
