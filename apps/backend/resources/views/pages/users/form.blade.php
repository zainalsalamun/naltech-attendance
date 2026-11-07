<div class="page-heading">
    <div>
        <p class="breadcrumb"><a href="{{ route('home') }}">Workspace</a> / <a href="{{ route('users.index') }}">Karyawan</a> / {{ $editing ? 'Edit' : 'Tambah' }}</p>
        <h1>{{ $editing ? 'Edit karyawan' : 'Tambah karyawan' }}</h1>
        <p class="muted">{{ $editing ? 'Perbarui informasi akun dan jadwal kerja.' : 'Daftarkan akun karyawan di perusahaan Anda.' }}</p>
    </div>
</div>

@include('components.workspace-feedback')

<section class="panel record-form-panel">
    <div class="panel-heading">
        <h2>Informasi karyawan</h2>
    </div>
    <form id="employee-form" class="record-form" method="post" action="{{ $editing ? route('users.update', $user) : route('users.store') }}">
        @csrf
        @if($editing)
            @method('PUT')
            <input type="hidden" name="version" value="{{ old('version', $user->version ?? 0) }}">
        @endif

        <div class="form-grid">
            <div class="field">
                <label for="organization_unit_id">Unit organisasi</label>
                <select id="organization_unit_id" name="organization_unit_id" @error('organization_unit_id') aria-invalid="true" aria-describedby="organization_unit_id-error" @enderror>
                    <option value="">Belum dipetakan</option>
                    @foreach($units as $unit)
                        <option value="{{ $unit->id }}" @selected((string)old('organization_unit_id', $editing ? $user->organization_unit_id : '') === (string)$unit->id)>
                            {{ $unit->code }} · {{ $unit->name }}{{ $unit->is_active ? '' : ' (nonaktif)' }}
                        </option>
                    @endforeach
                </select>
                <span class="muted">Pilih unit yang cocok dengan departemen lama. Teks lama tidak diubah otomatis. <a href="{{ route('units.index') }}">Lihat unit</a></span>
                @error('organization_unit_id')<span class="error" id="organization_unit_id-error">{{ $message }}</span>@enderror
            </div>

            <div class="field">
                <label for="manager_id">Atasan langsung</label>
                <select id="manager_id" name="manager_id" @error('manager_id') aria-invalid="true" aria-describedby="manager_id-error" @enderror>
                    <option value="">Belum diatur</option>
                    @foreach($managers as $manager)
                        <option value="{{ $manager->id }}" @selected((string)old('manager_id', $editing ? $user->manager_id : '') === (string)$manager->id)>
                            {{ $manager->name }}{{ $manager->isEmploymentActive() ? '' : ' (nonaktif)' }}
                        </option>
                    @endforeach
                </select>
                <span class="muted">Penempatan atasan belum mengubah hak approval.</span>
                @error('manager_id')<span class="error" id="manager_id-error">{{ $message }}</span>@enderror
            </div>

            <div class="field">
                <label for="employee_number">Nomor karyawan{{ $editing ? '' : ' (wajib)' }}</label>
                <input id="employee_number" name="employee_number" maxlength="50" value="{{ old('employee_number', $editing ? $user->employee_number : '') }}" @if(!$editing) required @endif @error('employee_number') aria-invalid="true" aria-describedby="employee-number-error" @enderror>
                <span class="muted">Unik dalam perusahaan. Data lama boleh belum diisi; huruf disimpan kapital.</span>
                @error('employee_number')<span class="error" id="employee-number-error">{{ $message }}</span>@enderror
            </div>

            @foreach(['joined_on' => 'Tanggal bergabung', 'left_on' => 'Tanggal keluar'] as $field => $label)
                <div class="field">
                    <label for="{{ $field }}">{{ $label }}</label>
                    <input id="{{ $field }}" name="{{ $field }}" type="date" value="{{ old($field, $editing ? $user->$field?->format('Y-m-d') : '') }}" @error($field) aria-invalid="true" aria-describedby="{{ $field }}-error" @enderror>
                    @error($field)<span class="error" id="{{ $field }}-error">{{ $message }}</span>@enderror
                </div>
            @endforeach

            <div class="field">
                <label for="employment_status">Status kepegawaian</label>
                <select id="employment_status" name="employment_status" required @error('employment_status') aria-invalid="true" aria-describedby="employment-status-error" @enderror>
                    @foreach(['active' => 'Aktif', 'inactive' => 'Nonaktif'] as $value => $label)
                        <option value="{{ $value }}" @selected(old('employment_status', $editing ? $user->employment_status : 'active') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                <span class="muted">Perubahan berlaku segera. Tanggal bukan jadwal aktivasi otomatis. Kosongkan tanggal keluar saat mengaktifkan kembali. Untuk mengarsipkan dan menonaktifkan akses, gunakan tombol Arsipkan pada daftar karyawan.</span>
                @error('employment_status')<span class="error" id="employment-status-error">{{ $message }}</span>@enderror
            </div>

            <div class="field">
                <label for="lifecycle_reason">Alasan perubahan status</label>
                <textarea id="lifecycle_reason" name="lifecycle_reason" rows="3" maxlength="500" @error('lifecycle_reason') aria-invalid="true" aria-describedby="lifecycle-reason-error" @enderror>{{ old('lifecycle_reason') }}</textarea>
                <span class="muted">Wajib saat menonaktifkan atau mengaktifkan kembali; maksimal 500 karakter.</span>
                @error('lifecycle_reason')<span class="error" id="lifecycle-reason-error">{{ $message }}</span>@enderror
            </div>

            @foreach(['name' => ['Nama lengkap', 'text', true], 'email' => ['Email', 'email', true], 'phone' => ['Nomor telepon', 'tel', false], 'position' => ['Jabatan', 'text', false], 'department' => ['Departemen (teks lama)', 'text', false]] as $field => [$label, $type, $required])
                <div class="field">
                    <label for="{{ $field }}">{{ $label }}{{ $required ? ' (wajib)' : '' }}</label>
                    <input id="{{ $field }}" name="{{ $field }}" type="{{ $type }}" maxlength="255" value="{{ old($field, $editing ? $user->$field : '') }}" @if($required) required @endif @error($field) aria-invalid="true" aria-describedby="{{ $field }}-error" @enderror>
                    @error($field)<span class="error" id="{{ $field }}-error">{{ $message }}</span>@enderror
                </div>
            @endforeach

            <div class="field">
                <label for="password">Kata sandi{{ $editing ? '' : ' (wajib)' }}</label>
                <input id="password" name="password" type="password" autocomplete="new-password" minlength="8" @if(!$editing) required @endif aria-describedby="password-help @error('password') password-error @enderror" @error('password') aria-invalid="true" @enderror>
                <span id="password-help" class="muted">{{ $editing ? 'Kosongkan untuk mempertahankan kata sandi. Minimal 8 karakter jika diganti.' : 'Minimal 8 karakter.' }}</span>
                @error('password')<span class="error" id="password-error">{{ $message }}</span>@enderror
            </div>

            <div class="field">
                <label for="shift_id">Shift / jam kerja</label>
                <select id="shift_id" name="shift_id" @disabled($hasEffectiveShift ?? false) @error('shift_id') aria-invalid="true" aria-describedby="shift-error" @enderror>
                    <option value="">Tanpa shift</option>
                    @foreach($shifts as $shift)
                        <option value="{{ $shift->id }}" @selected((string)old('shift_id', $editing ? $user->shift_id : '') === (string)$shift->id)>
                            {{ $shift->name }} ({{ substr($shift->time_in, 0, 5) }}–{{ substr($shift->time_out, 0, 5) }})
                        </option>
                    @endforeach
                </select>
                @if($hasEffectiveShift ?? false)
                    <input type="hidden" name="shift_id" value="{{ $user->shift_id }}">
                    <span class="muted">Penempatan lama dikunci. <a href="{{ route('effective-shifts.index', ['user_id' => $user->id]) }}">Kelola jadwal efektif</a> untuk perubahan berdasarkan tanggal.</span>
                @endif
                @if($shifts->isEmpty())
                    <span class="muted">Belum ada shift perusahaan. <a href="{{ route('shifts.index') }}">Kelola shift</a></span>
                @endif
                @error('shift_id')<span class="error" id="shift-error">{{ $message }}</span>@enderror
            </div>

            <div class="field">
                <label for="role">Peran akun</label>
                <select id="role" name="role" required @error('role') aria-invalid="true" aria-describedby="role-error" @enderror>
                    @foreach((in_array(auth()->user()->role, ['admin', 'super admin'], true) ? ['staff' => 'Staf', 'supervisor' => 'Supervisor', 'hrd' => 'HRD', 'admin' => 'Admin', 'super admin' => 'Super Admin'] : ['staff' => 'Staf']) as $value => $label)
                        <option value="{{ $value }}" @selected(old('role', $editing ? $user->role : 'staff') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                @error('role')<span class="error" id="role-error">{{ $message }}</span>@enderror
            </div>
        </div>

        <div class="dialog-actions">
            <a class="secondary" href="{{ route('users.index') }}">Batal</a>
            <button id="btn-submit-employee" class="primary" type="button">{{ $editing ? 'Simpan perubahan' : 'Tambah karyawan' }}</button>
        </div>
    </form>
</section>

@if($editing)
{{-- Modal Pratinjau Transisi Status Lifecycle --}}
<dialog id="lifecycle-preview-dialog" class="attendance-dialog" aria-labelledby="lifecycle-preview-title" style="max-width:540px;width:92%;padding:20px;border-radius:8px;border:1px solid var(--line);background:var(--panel);color:var(--ink);box-shadow:var(--shadow-lg);">
    <div class="dialog-heading" style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;border-bottom:1px solid var(--line);padding-bottom:12px">
        <h2 id="lifecycle-preview-title" style="margin:0;font-size:18px;">Pratinjau Perubahan Status</h2>
        <button type="button" id="lifecycle-dialog-close" class="secondary" style="padding:4px 8px;font-size:12px;">✕ Tutup</button>
    </div>
    <div class="dialog-content" style="font-size:13px;display:flex;flex-direction:column;gap:12px">
        <div style="background:var(--bg);padding:10px 12px;border-radius:6px;border:1px solid var(--line)">
            <div><strong>Karyawan:</strong> <span id="preview-user-name">{{ $user->name }}</span></div>
            <div><strong>Nomor Karyawan:</strong> <code>{{ $user->employee_number ?: 'Belum diatur' }}</code></div>
            <div><strong>Perubahan Status:</strong> <span id="preview-initial-status">{{ $user->archived_at ? 'Diarsipkan' : ($user->employment_status === 'active' ? 'Aktif' : 'Nonaktif') }}</span> → <strong><span id="preview-target-status">—</span></strong></div>
        </div>

        <div>
            <div style="font-size:11px;font-weight:600;text-transform:uppercase;color:var(--muted);margin-bottom:4px">Alasan Perubahan Status</div>
            <div id="preview-lifecycle-reason" style="background:var(--bg);padding:8px 10px;border-radius:4px;border:1px solid var(--line);white-space:pre-wrap;color:var(--ink);font-size:13px"></div>
        </div>

        <div style="background:var(--bg);padding:10px 12px;border-radius:6px;border:1px solid var(--line);border-left:3px solid var(--amber)">
            <div style="font-weight:600;margin-bottom:4px">Dampak Akses & Sesi</div>
            <div id="preview-access-impact" class="muted"></div>
        </div>

        <p class="muted" style="margin:4px 0 0;font-size:12px">
            <strong>Pelestarian Riwayat:</strong> Riwayat absensi, pengajuan cuti/lembur/reimbursement, serta riwayat audit tetap tersimpan dan tidak dihapus. Pengaktifan kembali tersedia melalui menu Edit.
        </p>
    </div>
    <div class="dialog-actions" style="margin-top:18px;display:flex;justify-content:flex-end;gap:8px">
        <button type="button" id="lifecycle-dialog-back" class="secondary">Kembali mengedit</button>
        <button type="button" id="lifecycle-dialog-confirm" class="primary">Konfirmasi perubahan</button>
    </div>
</dialog>

<script>
(function() {
    var form = document.getElementById('employee-form');
    var btnSubmit = document.getElementById('btn-submit-employee');
    var dialog = document.getElementById('lifecycle-preview-dialog');
    var btnClose = document.getElementById('lifecycle-dialog-close');
    var btnBack = document.getElementById('lifecycle-dialog-back');
    var btnConfirm = document.getElementById('lifecycle-dialog-confirm');

    var statusSelect = document.getElementById('employment_status');
    var reasonInput = document.getElementById('lifecycle_reason');
    var initialStatus = '{{ $user->employment_status ?? "active" }}';
    var isArchived = {{ $user->archived_at ? 'true' : 'false' }};

    var previewTargetStatus = document.getElementById('preview-target-status');
    var previewReason = document.getElementById('preview-lifecycle-reason');
    var previewImpact = document.getElementById('preview-access-impact');

    var lastFocused = null;
    var isSubmitting = false;

    function openDialog(targetStatus, reason) {
        previewTargetStatus.textContent = targetStatus === 'active' ? 'Aktif' : 'Nonaktif';
        previewReason.textContent = reason;

        if (targetStatus === 'inactive') {
            previewImpact.textContent = 'Akses login web dan API dicabut segera. Seluruh sesi aktif dan bearer token dibatalkan.';
        } else {
            previewImpact.textContent = 'Akun diaktifkan kembali. Pengguna harus login baru dengan kredensial aktif; token/sesi lama tidak dihidupkan kembali.';
        }

        lastFocused = document.activeElement;
        if (typeof dialog.showModal === 'function') {
            dialog.showModal();
        } else {
            dialog.setAttribute('open', '');
        }
        btnBack.focus();
    }

    function closeDialog() {
        if (typeof dialog.close === 'function') {
            dialog.close();
        } else {
            dialog.removeAttribute('open');
        }
        isSubmitting = false;
        if (btnConfirm) {
            btnConfirm.disabled = false;
            btnConfirm.textContent = 'Konfirmasi perubahan';
        }
        if (lastFocused && typeof lastFocused.focus === 'function') {
            lastFocused.focus();
        }
    }

    if (btnSubmit) {
        btnSubmit.addEventListener('click', function(e) {
            var targetStatus = statusSelect ? statusSelect.value : initialStatus;
            var isTransition = (targetStatus !== initialStatus) || (isArchived && targetStatus === 'active');

            if (isTransition) {
                var reason = (reasonInput.value || '').trim();
                if (!reason) {
                    alert('Alasan perubahan status wajib diisi sebelum menyimpan.');
                    reasonInput.focus();
                    return;
                }
                openDialog(targetStatus, reason);
            } else {
                if (isSubmitting) return;
                isSubmitting = true;
                btnSubmit.disabled = true;
                btnSubmit.textContent = 'Menyimpan...';
                form.submit();
            }
        });
    }

    if (btnClose) btnClose.addEventListener('click', closeDialog);
    if (btnBack) btnBack.addEventListener('click', closeDialog);

    if (dialog) {
        dialog.addEventListener('cancel', function() {
            setTimeout(function() {
                if (lastFocused && typeof lastFocused.focus === 'function') {
                    lastFocused.focus();
                }
            }, 0);
        });
    }

    if (btnConfirm) {
        btnConfirm.addEventListener('click', function() {
            if (isSubmitting) return;
            isSubmitting = true;
            btnConfirm.disabled = true;
            btnConfirm.textContent = 'Memproses...';
            form.submit();
        });
    }
})();
</script>
@else
<script>
(function() {
    var form = document.getElementById('employee-form');
    var btnSubmit = document.getElementById('btn-submit-employee');
    var isSubmitting = false;

    if (btnSubmit) {
        btnSubmit.addEventListener('click', function() {
            if (isSubmitting) return;
            isSubmitting = true;
            btnSubmit.disabled = true;
            btnSubmit.textContent = 'Menyimpan...';
            form.submit();
        });
    }
})();
</script>
@endif
