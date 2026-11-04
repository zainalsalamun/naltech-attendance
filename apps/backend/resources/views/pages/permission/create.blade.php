@extends('layouts.workspace')
@section('title', 'Ajukan Cuti / Izin')
@section('content')
<div class="page-heading">
    <div>
        <p class="breadcrumb"><a href="{{ route('home') }}">Workspace</a> / <a href="{{ route('permissions.index') }}">Cuti dan izin</a> / Ajukan</p>
        <h1>Ajukan Cuti / Izin</h1>
        <p class="muted">Pilih jenis cuti, tentukan rentang tanggal, dan periksa rincian hari kerja serta saldo sebelum mengirim.</p>
    </div>
</div>

@include('components.workspace-feedback')

<div class="dashboard-grid">
    <section class="panel record-form-panel">
        <div class="panel-heading">
            <h2>Formulir Pengajuan Cuti / Izin</h2>
        </div>

        <form class="record-form" method="post" enctype="multipart/form-data" action="{{ route('permissions.store') }}" id="leave-form" data-calculate-url="{{ route('leave-balances.calculate') }}">
            @csrf
            
            <div class="field" style="margin-bottom:16px">
                <label for="leave_type_id">Jenis Cuti / Izin (Wajib)</label>
                <select id="leave_type_id" name="leave_type_id">
                    <option value="">Izin umum (bukan cuti)</option>
                    @foreach($types as $type)
                        @php
                            $bal = $userBalances->get($type->id);
                            $balText = $type->deducts_balance 
                                ? ($bal ? " · Sisa saldo: {$bal->remaining_days} hari" : " · Belum ada alokasi saldo {$year}")
                                : " · Tanpa saldo";
                        @endphp
                        <option value="{{ $type->id }}" 
                            data-deducts="{{ $type->deducts_balance ? '1' : '0' }}"
                            data-attachment="{{ $type->requires_attachment ? '1' : '0' }}"
                            data-remaining="{{ $bal ? $bal->remaining_days : 0 }}"
                            data-has-balance="{{ $bal ? '1' : '0' }}"
                            @selected((string)old('leave_type_id') === (string)$type->id)>
                            {{ $type->code }} — {{ $type->name }}{{ $type->requires_attachment ? ' (Lampiran wajib)' : '' }}{{ $balText }}
                        </option>
                    @endforeach
                </select>
                @error('leave_type_id')<span class="error">{{ $message }}</span>@enderror
            </div>

            <div class="form-grid" style="margin-bottom:16px">
                <div class="field">
                    <label for="start_date">Tanggal Mulai (Wajib)</label>
                    <input id="start_date" name="start_date" type="date" required value="{{ old('start_date', old('date')) }}">
                    @error('start_date')<span class="error">{{ $message }}</span>@enderror
                </div>
                <div class="field">
                    <label for="end_date">Tanggal Selesai (Wajib)</label>
                    <input id="end_date" name="end_date" type="date" required value="{{ old('end_date', old('date')) }}">
                    @error('end_date')<span class="error">{{ $message }}</span>@enderror
                </div>
            </div>

            <!-- Live Calculation & Balance Preview Box -->
            <div id="calculation-box" class="notice" aria-live="polite" style="display:none;margin-bottom:20px;border-left:4px solid var(--accent)">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px">
                    <strong style="color:var(--ink);font-size:13px"><i class="ion-ios-calculator-outline" aria-hidden="true"></i> Rincian Perhitungan Hari Cuti</strong>
                    <span id="calc-badge" class="badge"></span>
                </div>
                <button type="button" id="calc-retry" class="secondary" hidden>Hitung ulang</button>
                <div id="calc-summary" style="font-size:12px;margin-bottom:8px"></div>
                <div id="calc-breakdown" style="font-size:11px;color:var(--muted);max-height:140px;overflow-y:auto;border-top:1px solid var(--line);padding-top:8px"></div>
                <div id="calc-balance-impact" style="margin-top:10px;font-size:12px;padding-top:8px;border-top:1px dashed var(--line)"></div>
            </div>

            <div class="field" style="margin-bottom:16px">
                <label for="reason">Alasan Pengajuan (Wajib)</label>
                <textarea id="reason" name="reason" rows="4" maxlength="5000" required placeholder="Tuliskan alasan lengkap pengajuan cuti atau izin Anda...">{{ old('reason') }}</textarea>
                @error('reason')<span class="error">{{ $message }}</span>@enderror
            </div>

            <div class="field" style="margin-bottom:20px">
                <label for="image">Bukti Pendukung / Lampiran <span id="attachment-note" class="muted">(Opsional / Wajib sesuai jenis)</span></label>
                <input id="image" name="image" type="file" accept="image/jpeg,image/png">
                <span class="muted" style="display:block;margin-top:4px">Format JPG atau PNG maksimal 2 MB. Wajib diunggah bila jenis cuti mensyaratkan lampiran.</span>
                @error('image')<span class="error">{{ $message }}</span>@enderror
            </div>

            <div class="dialog-actions">
                <a class="secondary" href="{{ route('permissions.index') }}">Batal & Kembali</a>
                <button class="primary" type="submit" id="submit-btn" disabled>Tinjau pengajuan</button>
            </div>
        </form>
    </section>

    <div class="dashboard-rail">
        <section class="panel" style="padding:20px 18px">
            <h2><i class="ion-ios-pie-outline" aria-hidden="true"></i> Saldo Cuti Anda ({{ $year }})</h2>
            <p class="muted" style="margin:5px 0 15px">Tersedia dapat diajukan. Cadangan menunggu approval. Terpakai sudah disetujui. Panel ini untuk periode di atas; preview mengikuti tahun tanggal pilihan.</p>

            <dl style="margin:0;font-size:12px">
                @forelse($types->where('deducts_balance', true) as $t)
                    @php $ub = $userBalances->get($t->id); @endphp
                    <div style="border-top:1px solid var(--line);padding:10px 0;display:flex;justify-content:space-between;align-items:center">
                        <div>
                            <strong>{{ $t->name }}</strong>
                            <small class="muted" style="display:block">Kuota kebijakan: {{ $t->annual_quota_days ?? 0 }} hari</small>
                        </div>
                        <div style="text-align:right">
                            @if($ub)
                                <strong style="color:var(--good);font-size:13px">{{ $ub->remaining_days }} hari</strong>
                                <small class="muted" style="display:block">Tersedia · Cadangan: {{ $ub->reserved_days }} · Terpakai: {{ $ub->used_days }}</small>
                            @else
                                <span class="muted" style="font-size:11px">Belum dialokasikan</span>
                            @endif
                        </div>
                    </div>
                @empty
                    <p class="muted">Belum ada jenis cuti yang menggunakan saldo.</p>
                @endforelse
            </dl>
        </section>

        <section class="panel" style="padding:20px 18px">
            <h2><i class="ion-ios-information-outline" aria-hidden="true"></i> Aturan & Kebijakan</h2>
            <ul style="font-size:11px;color:var(--muted);padding-left:16px;margin:10px 0 0;line-height:1.6">
                <li>Rentang tanggal awal dan akhir bersifat <strong>inklusif</strong>.</li>
                <li>Hari libur sesuai kalender perusahaan <strong>dilewati</strong> dan tidak mengurangi saldo cuti.</li>
                <li>Shift malam dihitung berdasarkan tanggal kerja kalender asal (bukan 2 hari).</li>
                <li>Pengajuan bertumpuk pada tanggal yang sama tidak diizinkan.</li>
                <li>Pengajuan akan melewati alur persetujuan bertingkat yang berlaku di perusahaan.</li>
            </ul>
        </section>
    </div>
</div>

<dialog id="leave-review" class="attendance-dialog" aria-labelledby="leave-review-title">
    <div class="dialog-heading"><h2 id="leave-review-title">Tinjau pengajuan</h2><button type="button" id="review-close" class="secondary">Kembali mengedit</button></div>
    <div id="leave-review-content" class="dialog-content"></div>
    <p class="muted">Saldo belum berubah. Saat dikirim, hari cuti akan dicadangkan dan server memeriksa ulang kalender, saldo serta pengajuan bertumpuk.</p>
    <div class="dialog-actions"><button type="button" id="review-send" class="primary">Kirim pengajuan</button></div>
</dialog>
<script src="{{ asset('js/leave-request.js') }}?v={{ filemtime(public_path('js/leave-request.js')) }}" defer></script>
@endsection
