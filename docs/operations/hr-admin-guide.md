# Panduan Operasional HR & Administrator (Dashboard Web)

Tanggal Dokumen: 4 November 2026, Asia/Jakarta.  
Status Dokumen: **Panduan Resmi Pengelola HR, Admin, dan Supervisor**.  
Acuan Spesifikasi: [`docs/hris-operational-guides-prd.md`](../hris-operational-guides-prd.md) Bagian 4.

---

## 1. Ikhtisar & Hierarki Otorisasi Peran

Dashboard Web Naltech Attendance dirancang berbasis multi-tenancy ketat (`company_id`). Setiap aksi operasional dibatasi oleh peran (*role*) dan cakupan struktur organisasi karyawan.

### Matriks Kewenangan Peran Pengguna

| Fitur / Halaman | Super Admin | Admin Perusahaan | HRD / HR Manager | Supervisor / Atasan Langsung |
|:---|:---:|:---:|:---:|:---:|
| **Konfigurasi Workspace & Kalender** | Penuh | Penuh | Hanya Lihat | Ditolak (403) |
| **Kelola Master Shift & Penugasan** | Penuh | Penuh | Penuh | Ditolak (403) |
| **Kelola Master Karyawan & Arsip** | Penuh | Penuh | Penuh | Ditolak (403) |
| **Kelola Unit Organisasi** | Penuh | Penuh | Penuh | Ditolak (403) |
| **Kelola Master Tipe & Saldo Cuti** | Penuh | Penuh | Penuh | Ditolak (403) |
| **Persetujuan (Approval) Pengajuan** | Sebagai Fallback | Sebagai Fallback | Peninjau Tahap HR | Bawahan Langsung Saja |
| **Koreksi Data Absensi** | Penuh | Penuh | Seluruh Karyawan | Bawahan Langsung Saja |
| **Rekapitulasi Kehadiran & Ekspor CSV** | Seluruh Perusahaan | Seluruh Perusahaan | Seluruh Perusahaan | Bawahan Langsung Saja |

> [!WARNING]
> **Batas Otorisasi Supervisor**:
> Supervisor **bukan** pengelola seluruh perusahaan. Supervisor hanya memiliki akses untuk melihat riwayat kehadiran, menyetujui pengajuan, dan mengajukan koreksi kehadiran khusus bagi **karyawan yang tercatat sebagai bawahan langsungnya**. Percobaan mengakses data di luar tim akan ditolak oleh server dengan respon HTTP 403 Forbidden.

---

## 2. Checklist Kesiapan Operasional UI

Sebelum perusahaan memulai pencatatan absensi harian dan pengajuan izin/cuti karyawan, pengelola HR/Admin wajib memastikan checklist kesiapan berikut telah terkonfigurasi melalui antarmuka Dashboard Web:

| No | Komponen Konfigurasi | Menu Dashboard | Hal yang Harus Dipastikan |
|:---:|:---|:---|:---|
| 1 | **Identitas & Zona Waktu** | `Pengaturan` → `Perusahaan` | Nama perusahaan, jam kerja kantor default, dan zona waktu IANA resmi (misal: `Asia/Jakarta`, `Asia/Makassar`, atau `Asia/Jayapura`) telah terisi tepat. |
| 2 | **Kalender Kerja** | `Pengaturan` → `Kalender Kerja` | Hari kerja mingguan aktif telah ditentukan dan hari libur nasional/perusahaan untuk periode berjalan telah ditambahkan. |
| 3 | **Master Shift Kerja** | `Jadwal Kerja` → `Shift Operasional` | Master shift operasional (jam masuk, jam pulang, dan batas toleransi) telah dibuat, termasuk shift malam lintas hari jika ada. |
| 4 | **Struktur Unit & Atasan** | `Karyawan` → `Unit Organisasi` | Unit organisasi aktif telah dibuat dan hierarki atasan langsung (*manager*) telah dipetakan tanpa hierarki melingkar (*circular*). |
| 5 | **Metode & Radius Absensi** | `Pengaturan` → `Perusahaan` | Titik koordinat kantor (latitude, longitude), radius geofencing (km/meter), dan metode absensi aktif (QR/Lokasi) telah tervalidasi. |
| 6 | **Master Cuti & Alokasi Saldo** | `Pengajuan` → `Jenis Cuti` & `Buku Besar Saldo` | Jenis izin/cuti aktif telah dibuat, aturan pemotongan saldo telah ditetapkan, dan kuota awal tahunan telah dialokasikan ke karyawan. |
| 7 | **Alur Persetujuan (Workflow)** | `Pengaturan` → `Alur Persetujuan` | Peninjau tahap 1 (Atasan Langsung) dan tahap 2 (HR Manager) telah aktif dan terhubung ke akun yang sah. |

---

## 3. Prosedur Operasional HR & Administrator

---

### Prosedur 1: Akses Sistem & Navigasi Workspace
- **Tujuan**: Masuk ke dalam Dashboard Web secara aman dan memahami navigasi kerja.
- **Peran & Cakupan**: Super Admin, Admin, HRD, dan Supervisor.
- **Prasyarat**: Memiliki email dan password resmi perusahaan yang terdaftar aktif.
- **Menu & Langkah Berurutan**:
  1. Buka URL login Dashboard (misal: `http://domain-perusahaan/login`).
  2. Masukkan **Alamat Email** dan **Kata Sandi**.
  3. Klik tombol **Masuk**.
  4. Setelah berhasil masuk, Anda akan diarahkan ke halaman **Ringkasan**.
  5. Navigasi utama terletak di sidebar kiri yang terbagi menjadi 6 grup accordion:
     - **Ringkasan**: Statistik kehadiran hari ini, pengajuan menunggu, dan status kantor.
     - **Karyawan**: Data Karyawan, Unit Organisasi, dan Verifikasi Pensiun.
     - **Kehadiran**: Data Kehadiran, Koreksi Kehadiran, Rekap Kehadiran, QR Absensi, dan Pendaftaran Wajah.
     - **Jadwal Kerja**: Jadwal Efektif, Shift Operasional, dan Kalender Kerja.
     - **Pengajuan**: Persetujuan Terpadu, Izin & Cuti, Lembur, Reimbursement, Jenis Cuti, dan Buku Besar Saldo.
     - **Pengaturan**: Konfigurasi Perusahaan dan Alur Persetujuan.
  6. Untuk mengganti tema tampilan, klik tombol **Light** atau **Dark** pada bagian kanan atas header.
  7. Untuk keluar dari sistem, klik avatar profil di pojok kanan atas atau klik tombol **Keluar** pada footer sidebar.
- **Pesan Gagal & Pemulihan**:
  - *"Kredensial yang diberikan tidak cocok dengan data kami"*: Periksa kembali pengetikan email dan sandi Anda.
  - *"Akun Anda sedang dinonaktifkan atau diarsipkan"*: Hubungi Super Admin perusahaan untuk mengaktifkan kembali akun Anda.

---

### Prosedur 2: Manajemen Karyawan & Siklus Hidup (Lifecycle)
- **Tujuan**: Mendaftarkan karyawan baru, memperbarui profil, mengubah status kepegawaian, mengarsipkan karyawan, dan mereaktivasi akun terarsip.
- **Peran & Cakupan**: Super Admin, Admin Perusahaan, dan HRD.
- **Prasyarat**: Unit organisasi dan atasan langsung telah terdaftar sebelumnya.

#### A. Mendaftarkan Karyawan Baru
1. Buka menu **Karyawan** → **Data Karyawan**.
2. Klik tombol **Tambah Karyawan** di sudut kanan atas.
3. Isi data formulir:
   - **Nama Lengkap**: Nama resmi karyawan (wajib).
   - **Email Perusahaan**: Email aktif unik untuk login (wajib).
   - **Nomor Telepon**: Nomor kontak aktif (opsional).
   - **Nomor Induk Karyawan (NIK)**: Unik per perusahaan (opsional).
   - **Peran (Role)**: Pilih `Staf`, `Supervisor`, `HRD`, atau `Admin`.
   - **Unit Organisasi**: Pilih unit kerja aktif yang sesuai.
   - **Atasan Langsung**: Pilih manajer aktif dari daftar dropdown.
   - **Tanggal Masuk**: Tanggal resmi mulai bekerja.
   - **Kata Sandi Awal**: Masukkan kata sandi minimum 8 karakter.
4. Klik tombol **Simpan Karyawan**.

#### B. Mengubah Data & Menangani Data Organisasi Nonaktif
1. Pada tabel data karyawan, cari baris karyawan yang diinginkan lalu klik tautan **Edit**.
2. **Retensi Unit/Atasan Nonaktif**: Jika karyawan terhubung dengan unit organisasi atau manajer yang telah dinonaktifkan di masa lampau, antarmuka akan tetap menampilkan nilai historis tersebut dengan label jelas `(nonaktif)`. Anda tidak dipaksa mengubahnya kecuali ingin memindahkan karyawan ke unit aktif baru.
3. Ubah data yang diperlukan. Jika data tidak ada yang diubah sama sekali lalu Anda menekan simpan, sistem secara otomatis mendeteksi **No-Op** (tidak ada mutasi data atau event log fiktif).
4. Klik tombol **Perbarui Karyawan**.

#### C. Mengarsipkan Karyawan (Penghentian Kerja)
> [!IMPORTANT]
> **Arsip Bukan Hapus Permanen**:
> Naltech Attendance menerapkan prinsip pelestarian jejak audit (*data retention*). Mengarsipkan karyawan **tidak menghapus** rekam jejak absensi, pengajuan cuti, atau riwayat keputusan yang pernah dilakukan karyawan tersebut.
1. Pada tabel **Data Karyawan**, klik tombol merah **Arsipkan** pada baris karyawan target (tombol tidak muncul pada diri sendiri atau karyawan yang sudah diarsipkan).
2. Dialog modal konfirmasi `<dialog>` akan terbuka di tengah layar dan menggelapkan latar belakang (*modal backdrop*).
3. Periksa ringkasan dampak:
   - Status awal: `Aktif` / `Nonaktif` → Status tujuan: `Diarsipkan`.
   - Akses login web dan API mobile karyawan akan dicabut seketika.
4. Masukkan **Alasan arsip (wajib)** pada kotak teks yang tersedia.
5. (Opsional) Masukkan **Tanggal keluar**.
6. Klik tombol merah **Arsipkan akun**.

#### D. Penanganan Konflik Versi Numerik (`version`) saat Edit / Arsip
- **Gejala**: Dua administrator membuka data karyawan yang sama secara bersamaan. Admin A menyimpan perubahan lebih dahulu. Saat Admin B menekan tombol simpan, formulir Admin B ditolak oleh server dengan pesan HTTP 409 Conflict.
- **Hasil & Pemulihan di Antarmuka**:
  1. Halaman kembali menampilkan formulir dengan **Notice Semantik Amber**: *"Data karyawan telah diperbarui oleh pengguna lain (konflik versi). Muat ulang halaman untuk meninjau perubahan terbaru."*
  2. Seluruh isian yang baru saja Anda ketikkan (nama, telepon, alasan perubahan/arsip) **tidak hilang** karena dipulihkan otomatis oleh sistem.
  3. Pada tindakan arsip, dialog modal arsip untuk karyawan sasaran akan dibuka kembali secara otomatis menggunakan `showModal()` dengan alasan yang tetap terjaga.
  4. Periksa data terbaru yang disimpan oleh rekan Anda, sesuaikan input bila perlu, lalu klik simpan kembali.

#### E. Mereaktivasi Karyawan Terarsip
1. Buka filter status pada daftar karyawan, pilih status **Diarsipkan**.
2. Klik tombol **Edit** pada karyawan terarsip.
3. Ubah status kepegawaian kembali menjadi **Aktif**.
4. Masukkan **Alasan perubahan (wajib)** mengapa karyawan tersebut diaktifkan kembali.
5. Simpan formulir. Karyawan kini aktif kembali dengan sesi keamanan baru (*epoch baru*), sehingga token/sesi lama yang pernah dicabut tidak hidup kembali.

---

### Prosedur 3: Struktur Organisasi & Hubungan Atasan
- **Tujuan**: Mengatur hierarki divisi/unit kerja dan menentukan garis pelaporan atasan langsung (*manager*).
- **Peran & Cakupan**: Super Admin dan Admin Perusahaan.
- **Menu**: `Karyawan` → `Unit Organisasi`.
- **Langkah Kerja**:
  1. Klik **Tambah Unit** untuk membuat divisi baru (masukkan kode unik dan nama unit).
  2. Unit yang tidak lagi beroperasi dapat dinonaktifkan (`is_active = false`). Menonaktifkan unit tidak merusak data profil karyawan yang pernah ditempatkan di sana.
  3. **Penetapan Atasan Langsung**: Dilakukan pada formulir edit karyawan.
- **Aturan Integritas Graf & Batasan Server**:
  - **Larangan Self-Manager**: Karyawan dilarang memilih dirinya sendiri sebagai atasan.
  - **Larangan Hubungan Melingkar (*Circular Hierarchy*)**: Jika Karyawan A adalah atasan Karyawan B, maka Karyawan B tidak dapat dijadikan atasan Karyawan A.
  - **Larangan Lintas Perusahaan**: Atasan wajib berasal dari perusahaan yang sama.
  - **Dampak Pemindahan Atasan terhadap Approval**: Pemindahan atasan langsung **tidak otomatis memindahkan snapshot alur persetujuan pada pengajuan yang sedang berjalan**. Pengajuan yang sudah diajukan sebelumnya tetap mempertahankan peninjau yang ditunjuk saat pengajuan dibuat.

---

### Prosedur 4: Kalender Kerja & Zona Waktu Perusahaan
- **Tujuan**: Menetapkan jam operasional, hari kerja mingguan, dan hari libur resmi perusahaan sebagai acuan perhitungan cuti dan rekap kehadiran.
- **Peran & Cakupan**: Super Admin dan Admin Perusahaan.
- **Menu**: `Pengaturan` → `Kalender Kerja`.
- **Langkah Kerja**:
  1. **Zona Waktu Perusahaan**: Diatur pada menu `Pengaturan Perusahaan`. Pastikan zona waktu IANA resmi (misal: `Asia/Jakarta`, `Asia/Makassar`, `Asia/Jayapura`) telah sesuai.
  2. **Hari Kerja Mingguan**: Centang hari operasional kerja reguler (misal: Senin sampai Jumat aktif, Sabtu dan Minggu libur).
  3. **Pengecualian Libur / Libur Pengganti**:
     - Masukkan tanggal libur (misal: `2026-10-22`).
     - Tuliskan nama perayaan/libur (misal: *"Cuti Bersama Nasional"*).
     - Pilih tipe: `Libur Nasional` atau `Libur Khusus Perusahaan`.
     - Klik **Simpan Hari Libur**.
- **Urutan Prioritas Evaluasi Server**:
  - Hari Libur Nasional/Perusahaan dievaluasi **sebelum** hari cuti kerja. Jika seorang karyawan mengajukan cuti pada hari yang bertepatan dengan hari libur nasional, hari tersebut tetap dicatat sebagai hari libur (`holiday`) dan **tidak memotong kuota saldo cuti** karyawan.
- **Batas Perubahan Kalender**: Perubahan kalender di masa depan berlaku untuk tanggal yang ditentukan; perubahan kalender tidak mengubah rekonsiliasi absensi lampau yang telah terkunci auditnya.

---

### Prosedur 5: Master Shift & Penugasan Shift Efektif
- **Tujuan**: Menetapkan jam masuk/pulang kerja dan menugaskan shift ke karyawan dengan tanggal mulai berlaku tertentu.
- **Peran & Cakupan**: Super Admin, Admin Perusahaan, dan HRD.
- **Menu**: `Jadwal Kerja` → `Shift Operasional` dan `Jadwal Kerja` → `Jadwal Efektif`.

#### A. Membuat Master Shift Normal & Shift Malam
1. Buka `Jadwal Kerja` → `Shift Operasional` → klik **Tambah Shift**.
2. Masukkan **Nama Shift** (misal: `Shift Pagi`, `Shift Reguler`, `Shift Malam`).
3. Tentukan **Jam Masuk** (`08:00`) dan **Jam Pulang** (`17:00`).
4. **Shift Malam (Lintas Hari / Cross-Midnight)**:
   - Jika jam masuk `22:00` dan jam pulang `06:00` (hari berikutnya), sistem otomatis mengenalinya sebagai shift lintas hari.
   - Server menjamin tanggal kerja (*work date*) absensi terkunci pada tanggal jadwal shift dimulai, bukan tanggal kalender saat check-out pagi harinya.

#### B. Menugaskan Shift Efektif ke Karyawan
1. Buka `Jadwal Kerja` → `Jadwal Efektif`.
2. Pilih karyawan yang akan ditugaskan jadwalnya.
3. Pilih shift kerja baru dari dropdown.
4. Tentukan **Tanggal Mulai Berlaku (`effective_from`)**:
   - Anda dapat memilih tanggal hari ini atau tanggal di masa mendatang. Penugasan jadwal mendatang tidak akan merusak jadwal atau sesi absensi berjalan hari ini.
5. Masukkan **Alasan perubahan jadwal (wajib)**.
6. Klik tombol **Tinjau & Simpan Jadwal**.
7. **Modal Pratinjau Jadwal (`#shift-assignment-preview-dialog`)**:
   - Dialog akan menampilkan tabel perbandingan **Sebelum** vs **Sesudah**.
   - **Baseline Resolver Tanggal Target**: Jika Anda menugaskan shift untuk tanggal masa depan (misal: 5 November), kolom "Sebelum" akan menampilkan jadwal yang aktif pada tanggal 5 November tersebut (bukan sekadar jadwal hari ini).
   - Pada shift malam, dialog menampilkan catatan peringatan: *"Jam pulang berada pada hari berikutnya. Tanggal kerja mengikuti jadwal yang ditentukan server dan dapat berbeda dari tanggal kalender saat check-in."*
8. Jika data telah sesuai, klik tombol **Konfirmasi simpan jadwal**. Jika ingin memperbaiki, klik **Kembali mengedit** (dialog tertutup tanpa ada data yang termutasi).

> [!NOTE]
> **Status "Tanpa Shift" Bukan Hari Libur**:
> Jika seorang karyawan belum memiliki shift atau ditugaskan "Tanpa Shift", statusnya tercatat sebagai *Tanpa shift*, bukan hari libur mingguan perusahaan.

---

### Prosedur 6: Metode Absensi & Perimeter Lokasi Kantor
- **Tujuan**: Menentukan bagaimana karyawan mencatat kehadiran di lokasi kantor yang sah.
- **Peran & Cakupan**: Super Admin dan Admin Perusahaan.
- **Menu**: `Pengaturan` → `Perusahaan` dan `Kehadiran` → `QR Absensi`.
- **Langkah Kerja**:
  1. **Perimeter Lokasi Kantor (Geofencing)**:
     - Tentukan titik pusat kantor dengan memasukkan nilai **Latitude** dan **Longitude**.
     - Tentukan **Radius Toleransi** dalam kilometer atau meter (misal: `0.05` km = 50 meter).
  2. **Metode QR Absensi Harian**:
     - Jika metode QR diaktifkan, buka menu `Kehadiran` → `QR Absensi`.
     - Layar akan menampilkan kode QR dinamis harian yang berlaku untuk tanggal hari ini di zona waktu perusahaan.
     - Karyawan memindai QR ini melalui aplikasi mobile di dalam radius kantor.
- **Batas Kemampuan Teknis**:
  - Sistem tidak menyediakan bypass radius lokasi tanpa izin resmi.
  - **Biometrik Wajah**: Fitur absensi pengenalan wajah mandiri di HP **tetap dinonaktifkan** untuk rilis produksi ini karena masih dalam tahap evaluasi laboratorium (PoC).

---

### Prosedur 7: Manajemen Jenis Cuti & Buku Besar Saldo
- **Tujuan**: Mengatur kuota cuti tahunan, mencatat alokasi saldo, dan memantau pemakaian saldo cuti karyawan secara akurat.
- **Peran & Cakupan**: Super Admin, Admin Perusahaan, dan HRD.
- **Menu**: `Pengajuan` → `Jenis Cuti` dan `Pengajuan` → `Buku Besar Saldo`.

#### A. Jenis Cuti Bersaldo vs Non-Saldo
1. Buka menu `Pengajuan` → `Jenis Cuti`.
2. Setiap tipe cuti memiliki opsi pemotongan saldo (`deducts_balance`):
   - **Bersaldo (Deduct Balance = Ya)**: Cuti Tahunan, Cuti Bersama. Memotong kuota saldo karyawan.
   - **Non-Saldo (Deduct Balance = Tidak)**: Cuti Melahirkan, Cuti Sakit Resmi, Izin Khusus Duka Cita. Tidak memotong kuota tahunan namun tetap mewajibkan bukti pendukung.

#### B. Mengalokasikan Saldo Cuti Karyawan
1. Buka menu `Pengajuan` → `Buku Besar Saldo`.
2. Pilih karyawan, tahun periode berjalan (misal: `2026`), dan jenis cuti bersaldo.
3. Masukkan jumlah hari alokasi (misal: `12` hari).
4. Masukkan alasan penyesuaian (misal: *"Alokasi hak cuti tahunan 2026"*).
5. Klik **Simpan Alokasi Saldo**.

#### C. Siklus Saldo Cuti pada Buku Besar
Sistem mencatat mutasi saldo secara ketat melalui buku besar:
1. **Saat Karyawan Mengajukan Cuti**: Saldo sejumlah hari kerja yang diajukan masuk status **Dipesan (Reserved)**. Kuota tersedia berkurang, namun saldo belum dianggap terpakai permanen.
2. **Saat Pengajuan Disetujui Final oleh HR**: Status saldo dipindahkan dari *Dipesan* menjadi **Terpakai (Used)**.
3. **Saat Pengajuan Ditolak atau Dibatalkan Mandiri**: Saldo yang tadinya *Dipesan* dikembalikan seutuhnya menjadi **Tersedia (Restored)**.
4. **Permohonan Pembatalan Cuti yang Sudah Disetujui (`cancellation_requested`)**:
   - Jika karyawan membatalkan cuti yang sudah disetujui sebelumnya, permohonan masuk ke antrean HR.
   - **Jika HR Menyetujui Pembatalan**: Saldo yang telah terpakai dikembalikan ke saldo tersedia melalui entri kredit buku besar.
   - **Jika HR Menolak Pembatalan**: Cuti tetap berlaku disetujui dan saldo tetap berstatus terpakai.

---

### Prosedur 8: Alur Persetujuan (Approval Workflow) Terpadu
- **Tujuan**: Meninjau, menyetujui, atau menolak permohonan izin/cuti, lembur, dan reimbursement dari karyawan.
- **Peran & Cakupan**: Supervisor (khusus bawahan langsung), HRD (peninjau akhir), dan Admin (fallback).
- **Menu**: `Pengajuan` → `Cuti / izin`, `Lembur`, atau `Reimbursement`.

#### A. Memfilter Antrean Keputusan
Pada halaman daftar pengajuan modul masing-masing, gunakan filter dropdown **Konteks tindakan**:
- **Perlu tindakan saya (`actionable`)**: Menampilkan pengajuan yang secara sah sedang menunggu keputusan dari akun Anda pada tahap aktif saat ini.
- **Menunggu peninjau lain (`waiting_others`)**: Menampilkan pengajuan tim Anda yang sedang berada pada meja peninjau tahap lain (misal: sudah disetujui atasan, kini menunggu HR).
- **Semua yang dapat saya lihat (`all`)**: Menampilkan seluruh riwayat pengajuan dalam cakupan otorisasi Anda.

#### B. Meninjau & Memberikan Keputusan
1. Klik tombol **Tinjau pengajuan** (atau **Tinjau pembatalan**) pada baris pengajuan yang ingin diproses.
2. Halaman detail pengajuan akan terbuka:
   - Periksa rincian: nama pemohon, tanggal/rentang, jam/nominal, alasan pemohon, dan foto lampiran (struk atau surat dokter).
   - Periksa kartu status approval yang menampilkan tahapan berjalan dan nama peninjau saat ini.
3. Pada kartu formulir keputusan di bagian bawah halaman:
   - Pilih opsi: **Setujui Pengajuan** atau **Tolak Pengajuan**.
   - Masukkan **Alasan Keputusan (Wajib)**: Tuliskan catatan evaluasi Anda.
4. Klik tombol **Tinjau keputusan**.
5. **Modal Pratinjau Keputusan (`#decision-review-dialog`)**:
   - Menampilkan ringkasan pemohon, keputusan yang dipilih, alasan yang Anda tulis, serta **Pratinjau Dampak Keputusan** (misal: pada cuti tahunan, menampilkan dampak saldo yang dilepas atau dicatat terpakai).
   - Pada modul reimbursement dan lembur, dialog bersih dari istilah saldo (*"Penolakan akan mengakhiri pengajuan."*).
6. Klik tombol **Konfirmasi keputusan** (tombol terlindung dari klik ganda).

#### C. Ketentuan Integritas Persetujuan
- **Larangan Self-Approval Mutlak**: Pengelola tidak dapat menyetujui pengajuannya sendiri, meskipun pengelola tersebut memegang peran Admin atau HRD.
- **Pengalihan ke Fallback Reviewer**: Jika atasan langsung seorang staf sedang dinonaktifkan atau diarsipkan, sistem secara otomatis mengalihkan kewenangan tindakan ke peninjau cadangan (*fallback*) yang sah di perusahaannya.
- **Penolakan Data Legacy `needs_review`**: Pengajuan lama dengan status rekonsiliasi diblokir dari keputusan tombol instan (HTTP 409 Conflict) untuk mencegah kesalahan mutasi saldo.

---

### Prosedur 9: Pengajuan Lembur & Reimbursement Web
- **Tujuan**: Mengelola pencatatan jam lembur dan klaim pengeluaran dana operasional kantor.
- **Peran & Cakupan**: Super Admin, Admin Perusahaan, HRD, dan Supervisor.
- **Menu**: `Pengajuan` → `Lembur` dan `Pengajuan` → `Reimbursement`.

#### A. Lembur Web
1. Buka menu `Pengajuan` → `Lembur`.
2. Klik **Tambah Lembur**.
3. Pilih karyawan aktif, tanggal pelaksanaan lembur, dan masukkan **Durasi Lembur (Menit)**.
   - **Batas Durasi 1–1.440 Menit**: Sistem membatasi durasi pengajuan antara 1 menit hingga maksimal 1.440 menit (24 jam = 1 hari kalender kerja). Angka ini merupakan batas validasi teknis satu lembar formulir pengajuan, bukan acuan batas legal kerja lembur perusahaan.
4. Tuliskan deskripsi pekerjaan lembur lalu klik simpan.
5. Tabel lembur menyajikan 20 baris per halaman dengan pengurutan tanggal dan ID secara konsisten.

#### B. Reimbursement Web
1. Buka menu `Pengajuan` → `Reimbursement`.
2. Klik **Tambah Reimbursement**.
3. Pilih karyawan aktif, tanggal transaksi, nominal Rupiah, deskripsi kebutuhan, dan unggah foto bukti nota/struk.
4. **Pemilik Terkunci Read-Only**: Saat formulir edit reimbursement dibuka, kolom pemilik karyawan dikunci secara permanen (*readonly*) guna mencegah pemindahan kepemilikan klaim dana secara tidak sah.
5. **Privasi Bukti**: File nota yang diunggah disimpan pada penyimpanan privat (*private storage*) dan hanya dapat diunduh oleh pemohon serta peninjau berwenang.

> [!WARNING]
> **Disclaimer Status Approved**:
> Status *Disetujui (Approved)* pada pengajuan lembur maupun reimbursement merupakan **persetujuan administratif SDM/operasional**, bukan bukti bahwa dana telah ditransfer atau dibayarkan melalui modul penggajian (*payroll*).

---

### Prosedur 10: Koreksi Kehadiran & Jejak Audit
- **Tujuan**: Memperbaiki kekeliruan catatan jam masuk/pulang karyawan dengan pencatatan jejak audit yang transparan.
- **Peran & Cakupan**: Super Admin, Admin Perusahaan, HRD (seluruh karyawan), dan Supervisor (khusus bawahan langsung).
- **Menu**: `Kehadiran` → `Data Kehadiran` → klik tombol **Koreksi** pada baris target.

#### Langkah Melakukan Koreksi Jam Absensi:
1. Pada formulir koreksi, periksa data awal kehadiran:
   - **Tanggal Kerja Asal**: Tetap mengikuti tanggal dimulainya jadwal shift karyawan.
   - **Shift Malam**: Jika karyawan bekerja shift malam, formulir menyediakan teks panduan: *"Isi tanggal pulang sesuai kejadian sebenarnya; pada shift malam biasanya hari berikutnya."*
2. Sesuaikan jam masuk, jam pulang, tanggal, atau koordinat yang keliru.
3. Saat Anda mengubah kolom, sistem secara dinamis menampilkan indikator **Badge Diff**:
   - Kolom yang nilainya diubah diberi badge hijau **"Diubah"**.
   - Kolom yang nilainya tetap diberi badge abu-abu **"Tidak berubah"**.
4. Masukkan **Alasan Koreksi (Wajib)**: Tuliskan latar belakang penyesuaian (misal: *"Lupa check-out karena penugasan server di luar kantor"*). Alasan wajib diisi dan tidak boleh hanya spasi.
5. Klik tombol **Tinjau Koreksi**.
6. **Modal Pratinjau Koreksi (`#correction-preview-dialog`)**:
   - Menampilkan tabel perbandingan nilai lama (*Sebelum*) vs nilai baru (*Sesudah*).
   - Menampilkan teks: *"Perubahan dan alasan koreksi akan disimpan dalam riwayat. Pastikan seluruh detail telah sesuai sebelum konfirmasi."*
   - Jika ingin membatalkan, klik **Kembali mengedit** (dialog tertutup, form tidak tersimpan, 0 mutasi database).
7. Klik **Konfirmasi koreksi** untuk menyimpan.
8. **Penelusuran Jejak Audit**: Seluruh riwayat perubahan dapat ditinjau kapan saja melalui kartu riwayat di bawah formulir edit atau melalui menu mandiri `Kehadiran` → `Koreksi Kehadiran` (`/attendance-corrections`).

---

### Prosedur 11: Rekapitulasi Kehadiran, Drilldown & Ekspor Data
- **Tujuan**: Menghitung agregat kehadiran periode, menelusuri rincian per karyawan, dan mengekspor laporan ke format CSV.
- **Peran & Cakupan**: Super Admin, Admin Perusahaan, HRD (seluruh populasi), dan Supervisor (khusus tim bawahan).
- **Menu**: `Kehadiran` → `Rekap Kehadiran` (`/attendance-recap`).

#### A. Mengatur Filter Rekapitulasi
1. Tentukan **Dari Tanggal** dan **Sampai Tanggal**.
   - **Batas Maksimal 90 Hari**: Rentang tanggal dibatasi maksimal 90 hari kalender inklusif guna menjaga performa komputasi server.
2. (Opsional) Filter berdasarkan **Nama Karyawan** atau **Unit Organisasi**.
3. Klik tombol **Terapkan**.
4. **Pelestarian Populasi Historis**: Karyawan yang telah dinonaktifkan atau diarsipkan namun pernah aktif bekerja pada rentang tanggal tersebut tetap ditampilkan pada laporan rekap.

#### B. Menelusuri Rincian Harian (Drilldown Detail)
1. Pada tabel rekap, klik tombol **Rincian** pada baris karyawan tertentu.
2. Halaman detail (`/attendance-recap/{user}`) akan menampilkan kalender harian lengkap: tanggal, jenis hari, nama shift, jam masuk/pulang, menit keterlambatan, dan status kehadiran.
3. **Sinkronisasi Hari Libur**: Jika tanggal tertentu merupakan hari libur mingguan/nasional dan karyawan sedang dalam rentang cuti, status utama tercatat sebagai **Hari Libur (`holiday`)**, sehingga total hari cuti sinkron 1:1 antara rekap agregat dan rincian harian.
4. **Cuti Menunggu Pembatalan**: Hari cuti yang berstatus `cancellation_requested` tetap dihitung sebagai cuti efektif (1 hari kerja).
5. Untuk kembali ke rekapitulasi, klik tombol **Kembali ke Rekap Kehadiran**. Seluruh filter pencarian, rentang tanggal, unit, dan nomor halaman sebelumnya **tetap terpelihara utuh**.

#### C. Mengekspor Laporan ke Format CSV
1. Pada halaman rekapitulasi, klik tombol **Ekspor CSV Rekap**.
2. File CSV akan diunduh secara *streaming* per kelompok (*batch* 100 baris) dengan penggunaan memori server yang terkontrol.
3. File CSV diawali penanda UTF-8 BOM (`\xEF\xBB\xBF`) sehingga karakter aksen dan format bahasa Indonesia terbaca rapi di Microsoft Excel.
4. Seluruh sel teks dilindungi dari celah injeksi formula Excel (*CSV Formula Injection Protection*).
5. Laporan CSV mencakup seluruh karyawan yang cocok dengan filter aktif lintas halaman.
