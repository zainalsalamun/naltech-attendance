# Panduan Pengemasan Komersial & Penerimaan Pembeli (Milestone H6)

Tanggal: 21 Oktober 2026, Asia/Jakarta.  
Status: **Pengemasan tersedia; kesiapan produksi mengikuti hasil verifikasi dan konfigurasi pembeli.**  
Acuan: `agent.md`, `design.md`, `skills.md`, [Timeline HRIS](hris-core-timeline.md), [Laporan Pengujian H5](hris-h5-attendance-corrections-and-recap.md).

---

## 1. Ringkasan Produk

**Naltech Attendance** adalah solusi Human Resource Information System (HRIS) dan absensi presensi modern berbasis monorepo:
- **Backend & Web Dashboard**: Laravel 11 (PHP 8.2+), Blade Workspace UI, Tailwind CSS, Vite, SQLite/MySQL.
- **Mobile Application**: Flutter 3+ (Dart 3+), BLoC State Management, Geolocation Geofencing, Dynamic QR Scanner, Clean Architecture.

Produk ini dikemas secara profesional agar layak dipindahtangankan, dijual sebagai source code, digunakan langsung untuk operasional perusahaan, maupun dijadikan materi pembelajaran arsitektur software enterprise.

---

## 2. Matriks Fitur Lengkap

### 2.1 Web Dashboard (HR & Manajemen)

| Modul | Fitur & Kemampuan | Kesiapan |
|---|---|---|
| **Multi-Tenant Scoping** | Isolasi data ketat per perusahaan (`company_id`). Pengaturan titik kantor, koordinat GPS, radius presensi, jam kerja, dan zona waktu. | Tersedia; lihat cakupan dan batas pengujian |
| **Karyawan & Lifecycle** | Format NIK/nomor induk unik per perusahaan. Pengelolaan status aktif, nonaktif, dan arsip. Pembatasan akses sesi/API seketika bagi karyawan nonaktif. | Tersedia; lihat cakupan dan batas pengujian |
| **Struktur Organisasi** | Master Unit Organisasi multi-departemen, hirarki hubungan Atasan Langsung (*Manager*), dan validasi anti-circular reporting. | Tersedia; lihat cakupan dan batas pengujian |
| **Jadwal & Shift Kerja** | Master shift (Pagi, Siang, Malam/Lintas Tengah Malam). Roster jadwal efektif per karyawan per tanggal. Penugasan jadwal fleksibel. | Tersedia; lihat cakupan dan batas pengujian |
| **Kalender Kerja** | Pengaturan hari kerja mingguan dinamis dan hari libur nasional. Perhitungan hari kerja otomatis pada absensi dan cuti. | Tersedia; lihat cakupan dan batas pengujian |
| **Monitoring Kehadiran** | Riwayat presensi real-time dengan status tepat waktu (*on-time*), terlambat (*late*), dan perlu tinjau (*review*). | Tersedia; lihat cakupan dan batas pengujian |
| **Audit Koreksi Absensi** | Penyesuaian manual data absensi oleh HR mencatat aktor pengubah, alasan penyesuaian, dan perbandingan snapshot *before* vs *after*. | Tersedia; lihat cakupan dan batas pengujian |
| **Rekapitulasi Kehadiran** | Laporan kehadiran periode (payroll cut-off) dengan perhitungan hari kerja terjadwal, hari hadir, alpa, cuti disetujui, dan akumulasi jam kerja. | Tersedia; lihat cakupan dan batas pengujian |
| **Ekspor Laporan CSV** | Ekspor streaming ramah memori dengan proteksi *formula injection* dan dukungan UTF-8 BOM untuk Microsoft Excel. | Tersedia; lihat cakupan dan batas pengujian |
| **Cuti Rentang Tanggal** | Pengajuan cuti rentang tanggal (`start_date` s/d `end_date`), perhitungan hari kerja dari kalender, pencegahan overlap, dan penolakan lintas tahun kalender. | Tersedia; lihat cakupan dan batas pengujian |
| **Buku Besar Saldo Cuti** | Master jenis cuti, alokasi saldo tahunan, mutasi cadangan (*reserved*), pemakaian (*used*), pengembalian saat ditolak/batal, dan permohonan pembatalan cuti disetujui. | Tersedia; lihat cakupan dan batas pengujian |
| **Alur Persetujuan (Workflow)** | Persetujuan bertahap multi-step untuk Cuti, Lembur, dan Reimbursement (Atasan Langsung $\rightarrow$ HR/Finance). | Tersedia; lihat cakupan dan batas pengujian |
| **Workspace Navigation** | Sidebar 6 menu utama berstruktur rapi, toggle minimize desktop, drawer mobile responsif, dan tema adaptif (*light/dark*). | Tersedia; lihat cakupan dan batas pengujian |

### 2.2 Mobile Application (Karyawan)

| Modul | Fitur & Kemampuan | Kesiapan |
|---|---|---|
| **Autentikasi & Sesi** | Login karyawan, penyimpanan token aman, manajemen logout, dan refresh sesi. | Tersedia; lihat cakupan dan batas pengujian |
| **Presensi Geofencing** | Validasi koordinat GPS terhadap radius kantor, deteksi jam masuk/keluar, dan anti-duplikasi absensi harian. | Tersedia; lihat cakupan dan batas pengujian |
| **Presensi QR Code** | Pemindaian kode QR dinamis kantor dengan verifikasi token server. | Tersedia; lihat cakupan dan batas pengujian |
| **Deteksi Shift & Jadwal** | Menampilkan informasi shift efektif hari ini (termasuk jadwal malam lintas hari) pada kartu Beranda. | Tersedia; lihat cakupan dan batas pengujian |
| **Pengajuan Cuti / Izin** | Pemilihan jenis cuti aktif, kalkulasi hari kerja real-time, tinjauan sisa kuota saldo, dan upload lampiran surat dokter. | Tersedia; lihat cakupan dan batas pengujian |
| **Pengajuan Lembur** | Form pengajuan lembur dengan tanggal, durasi menit/jam, dan deskripsi tugas. | Tersedia; lihat cakupan dan batas pengujian |
| **Pengajuan Reimbursement**| Form klaim biaya dinas/medis dengan nominal dan upload foto struk kuitansi privat. | Tersedia; lihat cakupan dan batas pengujian |
| **Tracking Status & Riwayat**| Monitoring tahap approval, catatan peninjau, alasan penolakan, riwayat absensi, dan pembatalan mandiri. | Tersedia; lihat cakupan dan batas pengujian |

---

## 3. Panduan Instalasi & Deployment Pembeli

### 3.1 Prasyarat Sistem
- **PHP**: Versi 8.2 atau lebih baru dengan ekstensi `pdo`, `pdo_mysql` / `pdo_sqlite`, `mbstring`, `openssl`, `curl`, `gd`, `zip`.
- **Database**: MySQL 8.0+ / MariaDB 10.4+ (atau SQLite 3.35+ untuk keperluan evaluasi/pengujian).
- **Composer**: Versi 2.5+.
- **Node.js**: Versi 18+ dan NPM (bila ingin meng-compile ulang aset web).
- **Flutter SDK**: Versi 3.19+ (Channel stable).

---

### 3.2 Langkah Instalasi Backend Laravel

1. **Masuk ke Direktori Backend**:
   ```bash
   cd apps/backend
   composer install
   ```

2. **Siapkan Konfigurasi Environment**:
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```

3. **Konfigurasikan Basis Data pada `.env`**:
   Isi juga `SETUP_ADMIN_PASSWORD` dengan kata sandi unik minimal 12 karakter sebelum menjalankan seeder. Nilai tidak memiliki default dan tidak dicetak oleh seeder. Setup berhenti sebelum membuat perusahaan/akun jika nilai belum valid. Pengulangan setup tidak mengganti password akun yang sudah ada.
   Buka file `.env` dan tentukan driver database:
   - **Mode Cepat (SQLite)**:
     ```env
     DB_CONNECTION=sqlite
     ```
     Buat file basis datanya jika belum ada: `touch database/database.sqlite`.
   - **Mode Produksi (MySQL)**:
     ```env
     DB_CONNECTION=mysql
     DB_HOST=127.0.0.1
     DB_PORT=3306
     DB_DATABASE=naltech_attendance
     DB_USERNAME=naltech_user
     DB_PASSWORD=password_aman_anda
     ```

4. **Jalankan Migrasi Database**:
   ```bash
   php artisan migrate
   ```

5. **Pilih Opsi Seeding Sesuai Kebutuhan**:
   - **Opsi A: Setup Bersih Produksi (*Clean Initial Setup*)**  
     Membuat profil perusahaan default, akun Super Admin, master shift, unit organisasi, kalender kerja, master cuti, dan alur approval tanpa data transaksi fiktif.
     ```bash
     php artisan db:seed
     # atau
     php artisan db:seed --class=InitialSetupSeeder
     ```
   - **Opsi B: Setup Lengkap Data Demo & Pembelajaran (*Demo Showcase*)**  
     Menyiapkan hierarki karyawan lengkap (Direktur, HR Manager, Supervisor, Staff), alokasi kuota saldo cuti 2026, catatan absensi, riwayat jejak audit koreksi, serta contoh pengajuan cuti, lembur, dan reimbursement.
     ```bash
     php artisan db:seed --class=DemoDataSeeder
     ```

6. **Tautkan Direktori Penyimpanan & Jalankan Server**:
   ```bash
   php artisan storage:link
   php artisan serve --port=8000
   ```
   Web Dashboard kini dapat diakses di: `http://localhost:8000`

---

### 3.3 Langkah Instalasi Aplikasi Mobile Flutter

1. **Masuk ke Direktori Mobile**:
   ```bash
   cd apps/mobile
   flutter pub get
   ```

2. **Konfigurasikan Environment Firebase**:
   ```bash
   cp .env.example .env
   ```
   *Catatan: Masukkan ID proyek Firebase dan API key Anda sendiri pada file `.env` dan `android/app/google-services.json`.*

3. **Atur Alamat URL Backend API**:
   Buka file [`apps/mobile/lib/core/constants/variables.dart`](file:///Users/macbookpro/development/fic/absensi/naltech-attendance/apps/mobile/lib/core/constants/variables.dart):
   - Jika menggunakan Android Emulator: `http://10.0.2.2:8000`
   - Jika menggunakan iOS Simulator: `http://localhost:8000`
   - Jika menggunakan HP Fisik: gunakan alamat IP LAN komputer Anda (misal `http://192.168.1.100:8000`).

4. **Jalankan Aplikasi Mobile**:
   ```bash
   flutter run
   ```

---

## 4. Kredensial Akun Bawaan Demo

Bila Anda menjalankan `php artisan db:seed --class=DemoDataSeeder`, gunakan akun berikut untuk menjelajahi seluruh alur peran:

| Peran | Email | Kata Sandi | Jabatan & Divisi | Kewenangan Utama |
|---|---|---|---|---|
| **Super Admin** | `superadmin@naltech.test` | Nilai `SETUP_ADMIN_PASSWORD` saat setup | System Administrator (Tech) | Konfigurasi sistem, kalender, shift, alur persetujuan, isolasi tenant. |
| **Direktur** | `direktur@naltech.test` | `password` | Direktur Operasional (DIR) | Final approval eksekutif, tinjauan performa seluruh divisi. |
| **HR Manager** | `hr@naltech.test` | `password` | HR & GA Manager (HRD) | Manajemen karyawan, alokasi kuota cuti, koreksi absensi, rekapitulasi periode, ekspor CSV. |
| **Supervisor** | `supervisor@naltech.test` | `password` | Supervisor Operasional (OPS) | Atasan langsung staff, peninjau tahap pertama izin/lembur/reimbursement. |
| **Staff Operasional** | `staff@naltech.test` | `password` | Staff Teknisi (OPS) | Absensi mobile, pengajuan cuti dengan ledger, pengajuan lembur, monitoring status. |
| **Staff Finance** | `siti@naltech.test` | `password` | Staff Administrasi (FIN) | Akun staf dengan riwayat cuti resmi disetujui. |

---

## 5. Skenario Uji Penerimaan Pembeli (*Buyer Acceptance Checklist*)

Lakukan pengujian berikut untuk memverifikasi fungsi source code:

### Skenario 1: Verifikasi Setup Bersih & Dashboard Admin
1. Buka `http://localhost:8000` dan login dengan email admin setup dan kata sandi unik yang dikonfigurasikan melalui `SETUP_ADMIN_PASSWORD`.
2. Buka menu **Jadwal Kerja** $\rightarrow$ **Master Shift**: Verifikasi 4 shift standar tersedia.
3. Buka menu **Jadwal Kerja** $\rightarrow$ **Kalender Kerja**: Verifikasi kalender aktif (Senin–Jumat hari kerja).
4. Buka menu **Pengaturan** $\rightarrow$ **Jenis Cuti**: Verifikasi 5 jenis cuti (Cuti Tahunan dengan kuota 12).
5. Buka menu **Pengaturan** $\rightarrow$ **Alur Persetujuan**: Verifikasi alur 2-tahap (Atasan Langsung $\rightarrow$ HRD).

### Skenario 2: Siklus Pengajuan Cuti & Mutasi Saldo Ledger
1. Buka menu **Pengajuan** $\rightarrow$ **Cuti / Izin**, ajukan cuti untuk karyawan Andi Pratama (rentang 2 hari kerja).
2. Periksa saldo cuti: Saldo tersedia berkurang 2 hari dan berpindah ke status **dicadangkan** (*reserved*).
3. Login sebagai Supervisor (`supervisor@naltech.test`), buka detail pengajuan, klik **Setujui**. Status berpindah ke tahap 2 (menunggu HRD).
4. Login sebagai HR Manager (`hr@naltech.test`), klik **Setujui Final**. Saldo cadangan berpindah menjadi **terpakai** (*used*).
5. Periksa buku besar saldo ([`leave_balance_ledgers`]): Tercatat mutasi `used_from_reserved` dengan jejak waktu dan nama peninjau.

### Skenario 3: Absensi, Audit Koreksi & Rekapitulasi Periode
1. Buka menu **Kehadiran** $\rightarrow$ **Data Kehadiran**, pilih salah satu absensi, lalu klik **Edit**.
2. Ubah `time_in` dari 08:18 menjadi 08:00, masukkan alasan *"Penyesuaian mesin fingerprint"*, klik **Simpan Perubahan**.
3. Verifikasi: Pada bagian bawah formulir tampil tabel **Riwayat Audit Koreksi Kehadiran** dengan tanggal, nama pengelola yang mengoreksi, dan tag perbandingan nilai awal vs nilai baru.
4. Buka menu **Kehadiran** $\rightarrow$ **Rekap Kehadiran**:
   - Pilih rentang tanggal awal bulan s/d hari ini.
   - Verifikasi metrik kartu: Tingkat Kehadiran %, Hari Kerja Terjadwal, Tepat Waktu, Terlambat, Cuti Disetujui, dan Alpa.
   - Klik tombol **Ekspor CSV**: Verifikasi file CSV terunduh dengan rapi, format angka jam kerja sesuai, dan bebas formula injection.

---

## 6. Batasan Teknis & Rekomendasi Lanjutan

1. **Face Enrollment & Biometrik**:
   - Fitur pendaftaran wajah saat ini berstatus PoC / dinonaktifkan secara aman untuk absensi operasional. Pendaftaran wajah memerlukan peninjauan admin dan audit terpisah. Untuk produksi skala besar, disarankan mengintegrasikan face liveness vendor bersertifikasi (misal: AWS Rekognition atau Azure Face API).
2. **Geofencing & Anti Fake GPS**:
   - Validasi radius dilakukan di sisi server berdasarkan koordinat perangkat yang dikirimkan. Untuk aplikasi mobile produksi di Play Store/App Store, dapat ditambahkan package native detector mock-location bila diperlukan kepatuhan ketat anti-spoofing.
3. **Firebase Cloud Messaging**:
   - Pastikan mengunggah file `google-services.json` dan `GoogleService-Info.plist` yang terhubung dengan akun Google Firebase milik perusahaan Anda agar push-notification berfungsi di perangkat pengguna.

---

## 7. Kesimpulan

Source code **Naltech Attendance HRIS** menyediakan fondasi berikut; daftar ini bukan sertifikasi kesiapan produksi:
- Setup admin baru wajib menggunakan SETUP_ADMIN_PASSWORD unik minimal 12 karakter; akun demo berpassword contoh hanya untuk lingkungan demo terisolasi.
- Menggunakan arsitektur berlapis yang rapi dan mudah dipahami.
- Dilengkapi pengujian otomatis penuh (**135 tests, 1.748 assertions passing 100%**).
- Memiliki dokumentasi lengkap dan seeder terpisah untuk produksi maupun demonstrasi.

## Koreksi hasil review — 21 Oktober 2026

Label 100%/siap produksi pada versi awal dokumen terlalu luas. Suite backend SQLite tidak membuktikan konkurensi MySQL, instalasi pembeli end-to-end, GPS/kamera, iOS atau release. [Laporan perbaikan dan MySQL](hris-review-fixes-and-mysql-testing.md) menjadi acuan terbaru. Password contoh pada akun demo tidak boleh dipakai untuk operasional; akun lama yang pernah dibuat dengan password bawaan harus dirotasi operator melalui alur akun yang tersedia. Perbaikan seeder tidak mengubah kredensial database utama secara otomatis.
