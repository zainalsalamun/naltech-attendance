# PRD — Penyempurnaan Alur Penugasan Shift Web & Verifikasi Konkurensi H2

Tanggal: 30 Oktober 2026, Asia/Jakarta.  
Status: **Rencana pengerjaan dan verifikasi.**  
Prioritas: Dashboard web. Mobile Flutter tetap dalam backlog. Commit, push, deployment, dan pengemasan komersial tetap ditunda.

---

## 1. Konteks dan Penyesuaian Pengguna

Dokumen ini disusun menyelaraskan catatan audit H2 sebelumnya dengan arahan pengguna:
1. **Verifikasi Regresi `expected_id`**: `expected_id` telah divalidasi secara wajib (`required|integer|min:0`) pada controller. Penanganan ini merupakan pola yang tepat untuk tabel riwayat append-only (`employee_shift_assignments`), sehingga temuan ini diverifikasi penuh sebagai tes regresi proteksi konkurensi, bukan fitur yang belum dibuat.
2. **Ambang Peringatan Jeda Antar-Shift**: Peringatan jeda istirahat antar-shift harus berbasis ambang kebijakan perusahaan (apabila dikonfigurasi), bukan menetapkan 12 jam sebagai aturan universal yang kaku.
3. **Batasan Ruang Lingkup (Scope Boundary)**: Rotasi otomatis, penugasan massal per unit organisasi, dan split shift ditegaskan tetap berada dalam **backlog**, tidak diperbesar pada irisan pengerjaan ini.
4. **Pembuktian Konkurensi MySQL Spesifik H2**: Pengujian balapan konkurensi multi-proses pada MySQL 9.3 InnoDB harus secara eksplisit menamai suite terarah `EffectiveShiftConcurrencyMySqlTest` dengan skenario penugasan shift nyata, memisahkan secara tegas dari bukti konkurensi lifecycle H1.
5. **Status Review Visual H5 & H1**: Review visual H5 dan H1 oleh agen tetap berstatus terbuka menunggu peninjauan visual final oleh pengguna. Pengamatan visual agen atas soliditas kartu, hirarki warna, dan tata letak tidak mengklaim rasio kontras laboratorium atau ukuran target sentuh tanpa instrumen ukur tersendiri.

---

## 2. Lingkup Penyempurnaan Alur Penugasan Shift Web

### A. Modal Pratinjau Penugasan Jadwal (`<dialog id="shift-assignment-preview-dialog">`)
- Pada halaman `/effective-shifts` (`pages/effective-shifts/index.blade.php`), tombol submit diubah menjadi tombol pemicu pratinjau `Tinjau & Simpan Jadwal`.
- Dialog modal bergaya Workspace dengan latar belakang solid (`var(--panel)`), kontras teks tinggi (`var(--ink)`), dan bayangan tegas (`var(--shadow-lg)`).
- Menampilkan:
  1. **Ringkasan Karyawan & Zona Waktu**: Nama karyawan yang dipilih dan zona waktu perusahaan.
  2. **Tabel Perbandingan Sebelum (Before) vs Sesudah (After)**:
     - Nama Shift (Sebelum vs Sesudah).
     - Jam Masuk & Jam Pulang (Sebelum vs Sesudah).
     - Tanggal Mulai Berlaku (`effective_from`).
  3. **Penjelasan Dinamis Dampak pada Sesi Berjalan & Tanggal Berlaku**:
     - *Jika `effective_from === today`*: Menjelaskan bahwa jadwal baru berlaku untuk catatan kehadiran hari ini yang belum check-in; jika karyawan sudah memiliki absensi pada hari ini, mutasi akan ditolak oleh server guna menjaga konsistensi data.
     - *Jika `effective_from > today`*: Menjelaskan bahwa jadwal baru aktif mulai tanggal target; sesi kehadiran hari ini dan riwayat absensi lampau tidak berubah.
     - *Jika shift adalah shift lintas hari (`time_out < time_in`)*: Memberikan catatan kontekstual bahwa jam pulang berada pada hari berikutnya, dan tanggal kerja mengikuti jadwal yang ditentukan server serta dapat berbeda dari tanggal kalender saat check-in.
     - *Jika penugasan adalah "Tanpa shift"*: Menjelaskan bahwa karyawan tidak memiliki shift terjadwal mulai tanggal target.
  4. **Tampilan Alasan Perubahan**: Menampilkan alasan penugasan yang wajib diisi oleh HR/admin.
  5. **Jaminan Pelestarian Riwayat**: Catatan bahwa jadwal baru disimpan sebagai revisi baru secara atomik tanpa menghapus penempatan masa lalu.
- **Aksi Dialog**:
  - Tombol *Kembali mengedit*: Menutup dialog tanpa mutasi data dan mengembalikan fokus ke tombol pemicu dengan mempertahankan seluruh isian form.
  - Tombol *Konfirmasi simpan jadwal*: Mensubmit form asli dengan proteksi pencegahan klik ganda (*double-submit lock* via atribut `disabled`).

### B. Penanganan Konflik Konkurensi yang Mempertahankan Input
- Pada `EffectiveShiftController::store`:
  - Jika request bukan JSON dan terjadi `HttpException` status 409 (misalnya karena `expected_id` tidak sesuai akibat ada admin lain yang memperbarui jadwal karyawan tersebut lebih dulu):
    - Controller menangkap pengecualian dan melakukan redirect: `back()->withInput()->with('conflict', $e->getMessage())`.
  - Jika request berupa JSON (misalnya API atau integration test):
    - Pengecualian 409 dilempar kembali sehingga klien API mendapatkan respons HTTP 409 Conflict.
- Pada view `pages/effective-shifts/index.blade.php`:
  - Pesan flash `conflict` ditampilkan dalam kotak pemberitahuan semantik (`<div class="notice amber" role="alert">`).
  - Seluruh input form (`shift_id`, `effective_from`, `reason`) dipulihkan otomatis melalui helper `old(...)`, sehingga HR tidak kehilangan alasan atau tanggal yang sudah diketik ketika terjadi konflik.
  - Nilai `expected_id` pada form ter-refresh mengikuti ID assignment terbaru dari database.

---

## 3. Rencana Pengujian dan Bukti

### 1. Suite Feature Backend (`EffectiveShiftTest.php`)
- **Stale `expected_id` via JSON**: Memastikan request API dengan `expected_id` usang ditolak dengan HTTP 409 Conflict.
- **Stale `expected_id` via Web Form**: Memastikan submit form biasa dengan `expected_id` usang dialihkan kembali (`assertRedirect`), membawa pesan `conflict` pada sesi (`assertSessionHas('conflict')`), mempertahankan nilai `old('reason')`, `old('effective_from')`, dan `old('shift_id')`, serta tidak menambah baris baru di `employee_shift_assignments` (0 mutasi).
- **Elemen UI Pratinjau**: Memastikan view `pages/effective-shifts/index` merender `<dialog id="shift-assignment-preview-dialog">`, tombol pemicu, tabel before/after, kontainer dampak sesi, dan tombol kembali/konfirmasi.

### 2. Suite Konkurensi Multi-Proses MySQL 9.3 InnoDB (`EffectiveShiftConcurrencyMySqlTest.php`)
- Dijalankan pada basis data QA disposable terisolasi (port 3307).
- Operasi worker baru: `assign_effective_shift` pada `tools/mysql-qa/worker.php`.
- **Skenario Balapan**:
  - Dua aktor HR berbeda mencoba menugaskan shift baru secara simultan untuk karyawan yang sama dengan `expected_id` yang sama.
  - Eksekusi dipicu serentak melewati gerbang barrier microtime.
  - **Hasil yang Diharapkan**:
    - Tepat 1 proses menghasilkan status HTTP 200 (berhasil tersimpan).
    - Tepat 1 proses menghasilkan status HTTP 409 (ditolak karena konflik konkurensi).
    - Tabel `employee_shift_assignments` bertambah tepat 1 baris baru.
    - Tidak ada data tertimpa secara diam-diam (*no lost update*).
- Setelah pengujian selesai, database dan user disposable dibersihkan bersih via `cleanup.php`.

---

## 4. Batasan & Syarat
- Database MySQL utama pengguna **tidak boleh dimutasi**.
- Berkas `.env` utama dan Firebase **tidak boleh diubah**.
- Perintah `git commit`, `git push`, dan *deployment* **tetap ditunda** menunggu arahan pengguna.
