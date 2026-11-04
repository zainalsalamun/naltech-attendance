@extends('layouts.workspace')
@section('title', 'Buku Besar Transaksi Saldo Cuti')
@section('content')
<div class="page-heading">
    <div>
        <p class="breadcrumb"><a href="{{ route('home') }}">Workspace</a> / <a href="{{ route('leave-balances.index') }}">Saldo cuti</a> / Buku besar</p>
        <h1>Buku Besar Transaksi Saldo Cuti</h1>
        <p class="muted">Catatan jejak audit lengkap setiap alokasi, pencadangan, pemakaian, dan pengembalian saldo.</p>
    </div>
    <div style="display:flex;gap:8px">
        <a class="secondary" href="{{ route('leave-balances.index', ['year' => $selectedYear]) }}"><i class="ion-ios-arrow-back" aria-hidden="true"></i> Kembali ke daftar saldo</a>
    </div>
</div>

@include('components.workspace-feedback')

<section class="panel">
    <div class="panel-heading">
        <div>
            <h2>Riwayat transaksi saldo</h2>
            <p class="muted">{{ $ledgers->total() }} entri mutasi saldo pada tahun {{ $selectedYear }}</p>
        </div>
    </div>

    <form method="get" action="{{ route('leave-balances.ledger') }}" class="record-filters">
        <div class="field" style="max-width:110px">
            <label for="filter-year">Tahun</label>
            <input id="filter-year" type="number" name="year" value="{{ $selectedYear }}" min="2020" max="2050">
        </div>
        <div class="field">
            <label for="filter-user">Karyawan</label>
            <select id="filter-user" name="user_id">
                <option value="">Semua karyawan</option>
                @foreach($employees as $emp)
                    <option value="{{ $emp->id }}" @selected($userId === $emp->id)>{{ $emp->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="field">
            <label for="filter-type">Jenis transaksi</label>
            <select id="filter-type" name="transaction_type">
                <option value="">Semua transaksi</option>
                @foreach([
                    'allocation' => 'Alokasi saldo',
                    'adjustment' => 'Penyesuaian kuota',
                    'reserve' => 'Cadangan pengajuan',
                    'use' => 'Pemakaian cuti',
                    'release_reserved' => 'Pengembalian cadangan',
                    'restore_used' => 'Pengembalian saldo terpakai',
                ] as $val => $lbl)
                    <option value="{{ $val }}" @selected($transactionType === $val)>{{ $lbl }}</option>
                @endforeach
            </select>
        </div>
        <button class="primary" type="submit">Terapkan</button>
        <a class="secondary" href="{{ route('leave-balances.ledger') }}">Reset</a>
    </form>

    <div class="table-scroll" tabindex="0" role="region" aria-label="Tabel buku besar saldo cuti">
        <table>
            <thead>
                <tr>
                    <th scope="col">Waktu & Aktor</th>
                    <th scope="col">Karyawan</th>
                    <th scope="col">Jenis cuti</th>
                    <th scope="col">Transaksi</th>
                    <th scope="col" style="text-align:right">Perubahan</th>
                    <th scope="col" style="text-align:right">Saldo sesudah</th>
                    <th scope="col">Referensi</th>
                    <th scope="col">Alasan & Jejak</th>
                </tr>
            </thead>
            <tbody>
                @forelse($ledgers as $ledger)
                    @php
                        $colorClass = match($ledger->transaction_type) {
                            'allocation', 'restore_used' => 'var(--good)',
                            'reserve' => 'var(--amber)',
                            'use' => 'var(--ink)',
                            'release_reserved' => 'var(--accent)',
                            default => 'var(--muted)',
                        };
                        $sign = $ledger->amount_days > 0 ? '+' : '';
                    @endphp
                    <tr>
                        <td class="time">
                            <div>{{ $ledger->created_at->format('Y-m-d H:i') }}</div>
                            <small class="muted">Oleh: {{ $ledger->actor?->name ?? 'Sistem' }}</small>
                        </td>
                        <td>
                            <div class="person">
                                <span class="initials" aria-hidden="true">{{ mb_strtoupper(mb_substr($ledger->user->name, 0, 2)) }}</span>
                                <div>
                                    <strong>{{ $ledger->user->name }}</strong>
                                    <small>{{ $ledger->user->department ?: 'Departemen belum diatur' }}</small>
                                </div>
                            </div>
                        </td>
                        <td>
                            <strong>{{ $ledger->leaveBalance->leaveType->code ?? '-' }}</strong>
                            <small style="display:block;color:var(--muted)">{{ $ledger->leaveBalance->leaveType->name ?? '-' }}</small>
                        </td>
                        <td>
                            <span class="badge" style="color:{{ $colorClass }};font-weight:500">
                                {{ $ledger->transaction_label }}
                            </span>
                        </td>
                        <td style="text-align:right;font-weight:600;color:{{ $colorClass }}">
                            {{ $sign }}{{ $ledger->amount_days }} hari
                        </td>
                        <td style="text-align:right">
                            <strong>{{ $ledger->current_remaining }} hr</strong>
                            <small class="muted" style="display:block">
                                (Alokasi: {{ $ledger->current_allocated }} | Cdg: {{ $ledger->current_reserved }} | Pk: {{ $ledger->current_used }})
                            </small>
                        </td>
                        <td>
                            @if($ledger->permission_id)
                                <a class="text-link" href="{{ route('permissions.show', $ledger->permission_id) }}">
                                    Pengajuan #{{ $ledger->permission_id }}
                                </a>
                            @else
                                <span class="muted">Penyesuaian HR</span>
                            @endif
                        </td>
                        <td style="max-width:240px">
                            <div>{{ $ledger->reason }}</div>
                            <small class="muted">Sebelumnya: {{ $ledger->previous_remaining }} hari tersedia</small>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="empty-state">
                            <strong>Belum ada transaksi saldo</strong>
                            <p>Semua aktivitas mutasi kuota cuti akan tercatat secara permanen di sini.</p>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @include('components.workspace-pagination', ['paginator' => $ledgers, 'unit' => 'transaksi'])
</section>
@endsection
