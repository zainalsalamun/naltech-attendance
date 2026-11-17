# Milestone H5 — Jejak Audit Koreksi Absensi & Rekap Periode Kehadiran

Tanggal: 21 Oktober 2026, Asia/Jakarta.  
Status: **Selesai dan teruji penuh pada database pengujian terisolasi (135 tes backend, 1.748 assertions — 100% lulus)**.  
Acuan: `agent.md`, `design.md`, `skills.md`, [Timeline HRIS](hris-core-timeline.md), [Laporan Pengujian H4/P0–P5](hris-testing-execution-report.md).

---

## 1. Latar Belakang dan Tujuan

Sebagai kelanjutan dari fondasi jadwal efektif (H2), approval bertahap (H3), dan cuti rentang tanggal/saldo (H4), Milestone H5 melengkapi sistem operasional kehadiran dengan dua pilar utama yang sangat krusial bagi HRIS komersial:
1. **Jejak Audit Koreksi Kehadiran (Audit Trail)**: Setiap perubahan manual pada data kehadiran (jam masuk, jam pulang, status on-time/late, koordinat GPS) oleh HR/Admin wajib mencatat siapa aktor pengubah, kapan perubahan dilakukan, alasan koreksi, serta snapshot komparasi nilai sebelum (*before*) dan sesudah (*after*). Data historis tidak boleh diubah diam-diam tanpa jejak.
2. **Rekapitulasi Periode Kehadiran (Periodic Attendance Recap)**: Manajemen dan HR memerlukan laporan agregat kehadiran per karyawan dalam rentang tanggal tertentu (misalnya tanggal 1 s/d 25 untuk *payroll cut-off*), yang menghitung:
   - Hari kerja terjadwal berdasarkan kalender perusahaan.
   - Kehadiran tepat waktu (*on-time*), terlambat (*late*), dan perlu ditinjau (*review*).
   - Total hari hadir efektif dan total akumulasi jam kerja.
   - Hari cuti/izin resmi yang disetujui dalam periode tersebut.
   - Hari alpa / belum hadir (hari kerja terjadwal dikurangi hadir dan cuti disetujui).
   - Ekspor CSV streaming yang aman dari formula injection dan ramah memori.

---

## 2. Rincian Implementasi Backend & UI

### 2.1 Model & Relasi Audit Koreksi
- **Model `App\Models\AttendanceCorrectionEvent`**:
  - File: `apps/backend/app/Models/AttendanceCorrectionEvent.php`.
  - Berelasi dengan `Attendance` (`belongsTo(Attendance::class)`) dan `User` (`belongsTo(User::class, 'actor_id')`).
  - Casting kolom `snapshot` ke tipe `array` untuk menyimpan struktur JSON:
    ```json
    {
      "before": { "time_in": "...", "time_out": "...", "status": "..." },
      "after":  { "time_in": "...", "time_out": "...", "status": "..." }
    }
    ```
- **Pembaruan Model `App\Models\Attendance`**:
  - File: `apps/backend/app/Models/Attendance.php`.
  - Ditambahkan relasi `correctionEvents()` (`hasMany(AttendanceCorrectionEvent::class)`).
  - Melengkapi atribut `$fillable` untuk mendukung mass-assignment jadwal dan audit: `checked_in_at`, `checked_out_at`, `attendance_timezone`, `scheduled_start_at`, `scheduled_end_at`, `effective_rule_snapshot`, dan `work_schedule_id`.

### 2.2 Pencatatan Audit pada `AttendanceController`
- File: `apps/backend/app/Http/Controllers/AttendanceController.php`.
- Pada metode `update`:
  - Mengambil data asli sebelum diubah (`$attendance->getOriginal()`).
  - Membandingkan perubahan pada `time_in`, `time_out`, `status`, `latlon_in`, dan `latlon_out`.
  - Jika terdapat perbedaan nilai, secara otomatis membuat rekaman `AttendanceCorrectionEvent` dengan `actor_id = auth()->id()`, alasan penyesuaian (dari input formulir atau ringkasan otomatis perubahan), dan snapshot data sebelum/sesudah.
- Pada metode `edit`:
  - Mengisolasi absensi per `company_id`.
  - Melakukan eager loading `correctionEvents.actor` agar riwayat audit tampil langsung pada halaman formulir edit.

### 2.3 Antarmuka Koreksi & Audit
1. **Tabel Riwayat pada Halaman Edit Absensi**:
   - File: `apps/backend/resources/views/pages/absensi/edit.blade.php`.
   - Menampilkan card panel "Riwayat Audit Koreksi Kehadiran" di bawah formulir edit, berisi waktu penyesuaian, nama pengelola yang mengubah, alasan koreksi, serta tag perbandingan nilai awal vs nilai baru.
2. **Halaman Khusus Daftar Audit Koreksi (`/attendance-corrections`)**:
   - Controller: `apps/backend/app/Http/Controllers/AttendanceCorrectionController.php`.
   - View: `apps/backend/resources/views/pages/attendance_corrections/index.blade.php`.
   - Otorisasi: Khusus pengelola perusahaan (`admin`, `super admin`, `hrd`, `supervisor`).
   - Fitur pencarian: Filter nama karyawan, tanggal mulai, dan tanggal akhir penyesuaian.
   - Paginasi dengan state pencarian tetap terjaga.
   - Status empty-state informatif jika belum ada catatan koreksi.

### 2.4 Rekapitulasi Periode Kehadiran & Ekspor CSV
- Controller: `apps/backend/app/Http/Controllers/AttendanceRecapController.php`.
- View: `apps/backend/resources/views/pages/attendance_recap/index.blade.php`.
- **Logika Perhitungan Bisnis**:
  1. **Rentang Tanggal**: Menerima `start_date` dan `end_date` (default tanggal 1 s/d hari ini pada bulan berjalan). Validasi rentang maksimal 90 hari untuk mencegah exhaust memori.
  2. **Hari Kerja Terjadwal**: Menginspeksi `work_calendars` perusahaan pada setiap tanggal dalam rentang; jika hari libur atau non-kerja, hari tersebut tidak dihitung sebagai kewajiban kerja.
  3. **Agregasi Kehadiran**: Menghitung jumlah hari hadir unik, jumlah status tepat waktu (`ontime`), terlambat (`late`), dan perlu tinjau (`review`).
  4. **Perhitungan Jam Kerja**: Menghitung selisih jam kerja efektif antara `checked_in_at`/`time_in` dan `checked_out_at`/`time_out`.
  5. **Cuti / Izin Disetujui**: Mengagregasi pengajuan izin bertipe `approved` yang beririsan dengan periode rekapitulasi.
  6. **Alpa / Belum Hadir**: Menghitung `max(0, scheduled_days - present_days - leave_days)`.
- **Ekspor CSV Streaming (`/attendance-recap/export-csv`)**:
  - Menggunakan `StreamedResponse` untuk efisiensi memori tanpa buffering file utuh di server.
  - Dilengkapi UTF-8 BOM (`\xEF\xBB\xBF`) agar langsung terbaca rapi pada Microsoft Excel.
  - Sanitasi pencegahan formula injection pada kolom teks (menambahkan kutip tunggal jika diawali `=`, `+`, `-`, atau `@`).

### 2.5 Integrasi Navigasi Sidebar Workspace
- File: `apps/backend/resources/views/layouts/workspace.blade.php`.
- Menu baru dimasukkan di bawah accordion **Kehadiran** (`group-kehadiran`):
  - Data kehadiran (`/attendances`)
  - Koreksi kehadiran (`/attendance-corrections`)
  - Rekap kehadiran (`/attendance-recap`)
  - QR absensi (`/qr_absens`)
  - Verifikasi wajah (`/verifikasi`)
- Tetap mematuhi aturan navigasi: accordion otomatis terbuka jika URL aktif berada di bawah menu tersebut, state tersimpan per user/company di `localStorage`, dan link aktif ditandai `aria-current="page"`.

---

## 3. Hasil Pengujian Backend

Seluruh pengujian dijalankan pada database terisolasi SQLite in-memory (`apps/backend/phpunit.xml`). Tidak ada migrasi atau modifikasi yang dilakukan pada database utama MySQL.

### 3.1 Suite Baru: `AttendanceCorrectionRecapTest`
- File: `apps/backend/tests/Feature/AttendanceCorrectionRecapTest.php`.
- Hasil: **3 tests, 32 assertions — LULUS (100%)**.
  1. `test_updating_attendance_creates_correction_audit_event_and_renders_in_edit_page`:
     - Memverifikasi saat pengelola mengubah absensi, rekaman `attendance_correction_events` terbuat otomatis dengan snapshot nilai lama dan baru.
     - Memverifikasi tabel riwayat audit tampil pada halaman edit.
  2. `test_attendance_corrections_index_is_scoped_by_company_and_role`:
     - Memverifikasi daftar audit hanya dapat diakses oleh admin/hrd/supervisor.
     - Memverifikasi isolasi multi-tenant antar perusahaan.
  3. `test_attendance_recap_and_csv_export_calculates_working_days_and_metrics`:
     - Memverifikasi perhitungan hari kerja kalender, tepat waktu, alpa, dan cuti disetujui.
     - Memverifikasi respon streaming CSV ekspor dengan header `text/csv`, format nilai, dan kalkulasi jam kerja.

### 3.2 Verifikasi Regresi Menyeluruh
- Perintah: `rtk proxy ./apps/backend/vendor/bin/phpunit -c apps/backend/phpunit.xml`
- Hasil akhir:
  ```text
  PHPUnit 11.4.0 by Sebastian Bergmann and contributors.

  Runtime:       PHP 8.4.6
  Configuration: /Users/macbookpro/development/fic/absensi/naltech-attendance/apps/backend/phpunit.xml

  ...............................................................  63 / 135 ( 46%)
  ............................................................... 126 / 135 ( 93%)
  .........                                                       135 / 135 (100%)

  Time: 00:14.588, Memory: 77.52 MB

  OK (135 tests, 1748 assertions)
  ```
- Tidak ada regresi pada modul autentikasi, lifecycle karyawan, kalender kerja, shift efektif, approval cuti bertahap, alokasi saldo, maupun ledger.

---

## 4. Status Integrasi Mobile & Keamanan

- **Mobile API Contract**: Modul koreksi dan rekapitulasi adalah fitur khusus web pengelola (HR/Admin). Endpoint absensi mobile (`api/checkin`, `api/checkout`, `api/history`) tetap kompatibel tanpa perubahan pemutus (*breaking changes*).
- **Face Enrollment**: Tetap dinonaktifkan sesuai batasan arsitektur produk.
- **Integritas Database Utama**: Database utama MySQL tidak disentuh selama pengujian H5.

---

## 5. Ringkasan Status Milestone

| Milestone | Ruang Lingkup | Status | Hasil Uji |
|---|---|---|---|
| **H0** | Akses API, ekspor, lampiran privat, route scoping | Selesai | 74+ tes lulus |
| **H1** | Lifecycle karyawan, nomor induk unik, organisasi & atasan | Selesai | 95 tes lulus |
| **H2** | Kalender kerja, zona waktu, shift efektif & checkout malam | Selesai | 123 tes lulus |
| **H3** | Alur persetujuan bertahap (Izin, Lembur, Reimbursement) | Selesai | 111 tes lulus |
| **H4** | Cuti rentang tanggal, evaluasi kalender, alokasi & buku besar saldo | Selesai | 132 tes lulus |
| **H5** | **Jejak audit koreksi absensi & rekapitulasi periode kehadiran** | **Selesai** | **135 tes, 1.748 assertions lulus (100%)** |
| **H6** | Pengemasan source code, panduan instalasi, seed/demo terpisah | Berikutnya | Menunggu arahan pengguna |

## Pembaruan review — 21 Oktober 2026

[Laporan terbaru](hris-review-fixes-and-mysql-testing.md) memperbaiki roster historis, pembilang/penyebut kehadiran dan validasi 90 hari pada halaman/CSV. Status saat ini implementasi tersedia dengan batas yang dinyatakan; bukan klaim rekap payroll final. Ekspor menulis baris melalui StreamedResponse setelah perhitungan data; belum benchmark memori skala besar atau klaim BOM yang diverifikasi di irisan ini.
