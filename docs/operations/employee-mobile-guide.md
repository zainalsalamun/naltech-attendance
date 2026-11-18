# Panduan Operasional Karyawan (Aplikasi Mobile)

Tanggal Dokumen: 4 November 2026, Asia/Jakarta.  
Status Dokumen: **Panduan Resmi Pengguna Aplikasi Mobile Flutter**.  
Acuan Spesifikasi: [`docs/hris-operational-guides-prd.md`](../hris-operational-guides-prd.md) Bagian 5.

---

## 1. Akses Akun, Sesi, dan Keamanan Kredensial

### 1.1 Masuk Aplikasi (Login)
- **Tujuan**: Mengakses akun kerja pribadi pada aplikasi mobile Naltech Attendance.
- **Prasyarat**: Karyawan berstatus aktif, memiliki alamat email resmi yang terdaftar oleh HR, dan kata sandi yang telah ditetapkan.
- **Langkah-langkah**:
  1. Buka aplikasi **Naltech Attendance** pada perangkat Android atau iOS Anda.
  2. Masukkan **Alamat Email** terdaftar.
  3. Masukkan **Kata Sandi**.
  4. Tekan tombol **Masuk**.
  5. Setelah verifikasi server berhasil, aplikasi akan menampilkan halaman **Beranda** beserta ringkasan jadwal dan riwayat kehadiran Anda.

> [!CAUTION]
> **Kebijakan Keamanan Kredensial**:
> Jangan pernah membagikan email atau kata sandi Anda kepada rekan kerja atau pihak mana pun. Tim HR dan Administrator sistem **tidak pernah** meminta kata sandi Anda untuk alasan apa pun. Jangan mencatat kata sandi di tempat umum atau menyimpannya pada catatan tidak terenkripsi.

### 1.2 Keluar Akun (Logout)
- Buka tab **Profil** di pojok kanan bawah navigasi.
- Gulir ke bagian bawah dan tekan tombol **Keluar**.
- Konfirmasi dialog keluar akun. Token sesi lokal akan dibersihkan secara aman dari perangkat Anda.

### 1.3 Penanganan Akun Nonaktif atau Sesi Kedaluwarsa
- **Sesi Kedaluwarsa (401 Unauthorized)**: Jika Anda melihat pesan *"Sesi telah berakhir. Silakan login kembali"*, token otentikasi telah habis masa berlakunya atau sesi Anda telah diperbarui oleh server (misal setelah perubahan data akun oleh HR). Silakan lakukan login ulang dengan kredensial Anda.
- **Akun Nonaktif / Diarsipkan**: Jika muncul pesan bahwa akun Anda tidak aktif atau dinonaktifkan, akun Anda telah dinonaktifkan atau diarsipkan di sistem pusat oleh HR. Segera hubungi bagian HRD melalui saluran komunikasi resmi perusahaan (email HR atau tiket bantuan internal) dengan menyebutkan nama lengkap dan NIK/Nomor Induk Karyawan Anda.

---

## 2. Memahami Kartu Jadwal Kerja di Halaman Beranda

Halaman **Beranda** menampilkan kartu status jadwal kerja hari ini yang disinkronkan secara langsung dari server.

### 2.1 Elemen Kartu Jadwal
1. **Nama Shift**: Menampilkan nama penugasan shift efektif hari ini (misal: *Shift Reguler Pagi*, *Shift Operasional Siang*, *Shift Malam Gudang*).
2. **Jam Kerja Resmi**: Menampilkan jam masuk dan jam pulang yang ditetapkan oleh perusahaan.
3. **Zona Waktu Perusahaan**: Jam ditampilkan dengan label zona waktu resmi kantor yang dikonfigurasi perusahaan (misal: `WIB`, `WITA`, `WIT`, atau format UTC transparan jika zona belum dipetakan).
4. **Lokasi Kantor**: Menampilkan nama kantor atau titik lokasi penugasan absensi Anda.
5. **Status Kehadiran Hari Ini**:
   - `Belum Masuk`: Anda belum mencatat check-in untuk jadwal kerja hari ini.
   - `Sudah Masuk (JamMasuk)`: Anda telah check-in dan jam tercatat di server.
   - `Selesai (JamMasuk - JamPulang)`: Sesi kerja hari ini telah lengkap (check-in dan checkout).

> [!IMPORTANT]
> **Status "Tanpa Shift" Bukan Berarti Bebas Aturan**:
> Jika kartu jadwal menampilkan status *"Tanpa Shift"*, ini berarti bagian HR belum menetapkan penugasan shift efektif atau pola kerja spesifik untuk Anda pada tanggal ini. Kondisi ini **bukan** berarti hari bebas tanpa kehadiran kerja. Konfirmasikan kepada supervisor atau HRD mengenai jam kerja operasional yang berlaku sebelum melakukan absensi.

---

## 3. Prosedur Check-in dan Checkout Kehadiran

Aplikasi mendukung metode absensi mandiri menggunakan **QR Code** dan **Radius Lokasi GPS** yang telah diaktifkan oleh perusahaan.

> [!NOTE]
> **Metode Pengenalan Wajah (Face Recognition)**:
> Sesuai kebijakan tata kelola sistem, fitur verifikasi wajah biometrik saat ini **berstatus ditunda (deferred)** untuk implementasi produksi hingga pengujian verifikasi model memenuhi standar keandalan. Metode absensi yang berlaku adalah verifikasi QR Code terenkripsi dan batas radius GPS resmi kantor.

### 3.1 Izin Perangkat yang Diperlukan
Saat pertama kali melakukan absensi, sistem operasi perangkat Anda akan meminta izin akses berikut:
- **Kamera**: Diperlukan untuk memindai QR Code di layar monitor kantor atau lokasi fisik yang ditentukan.
- **Lokasi (GPS)**: Diperlukan untuk memastikan perangkat Anda berada di dalam radius resmi kantor yang telah ditetapkan perusahaan. Pilih opsi *"Saat Aplikasi Digunakan"* dan pastikan GPS perangkat aktif dalam mode akurasi tinggi.

### 3.2 Langkah Check-in
1. Pada halaman **Beranda**, tekan tombol **Absen Masuk** (atau tombol ikon QR/Kamera).
2. Pastikan Anda telah berada di lokasi kantor resmi.
3. Arahkan kamera ponsel ke QR Code absensi resmi perusahaan yang aktif pada hari tersebut.
4. Sistem akan memvalidasi koordinat GPS Anda dan kode QR ke server secara real-time.
5. Saat berhasil, muncul notifikasi centang hijau *"Absen Masuk Berhasil"* dan jam masuk tercatat pada kartu Beranda.

### 3.3 Langkah Checkout
1. Saat jam kerja Anda berakhir, buka aplikasi dan tekan tombol **Absen Pulang**.
2. Arahkan kamera ke QR Code absensi pulang resmi kantor.
3. Tunggu hingga verifikasi server selesai. Status kartu Beranda akan berubah menjadi *"Selesai"*.

### 3.4 Penolakan Validasi dan Solusinya
- **"Anda berada di luar radius kantor"**:
  - Jarak GPS Anda melebihi batas radius geofencing yang ditetapkan perusahaan (misal toleransi radius 100 meter dari titik koordinat kantor).
  - *Solusi*: Dekati area lobi/kantor utama, pastikan GPS perangkat tidak dalam mode hemat daya, dan matikan aplikasi pemalsu lokasi (*mock location/fake GPS*) karena server akan menolaknya.
- **"QR Code tidak valid atau telah kedaluwarsa"**:
  - Kode QR yang dipindai bukan kode resmi hari ini atau berasal dari kantor lain.
  - *Solusi*: Minta petugas kantor atau atasan untuk menampilkan QR Code absensi harian yang aktif.

---

## 4. Ketentuan Khusus Shift Malam (Lintas Hari / Cross-Midnight)

Bagi karyawan dengan jadwal operasional malam (contoh: jadwal kerja pukul **22:00 WIB hingga 06:00 WIB esok hari**), pemahaman mengenai tanggal kerja server sangat penting:

### 4.1 Tanggal Kerja Server (`attendance_date`) vs Tanggal Kalender QR (`qr_date`)
- **`attendance_date` (Tanggal Kerja)**: Tanggal penugasan shift dimulai (misal: shift malam tanggal 20 Oktober pukul 22:00 WIB).
- **`qr_date` (Tanggal Kalender)**: Tanggal saat pemindaian QR berlangsung secara fisik.
- **Check-in Setelah Tengah Malam**: Jika Anda baru tiba dan melakukan check-in pada pukul **00:15 WIB** (secara kalender sudah masuk tanggal 21 Oktober), server sistem secara otomatis akan mengaitkan sesi absensi Anda ke shift malam tanggal **20 Oktober** berdasarkan toleransi penugasan shift efektif Anda.
- **Checkout Pagi Hari**: Saat Anda melakukan checkout pukul **06:00 WIB** pada tanggal 21 Oktober, checkout tersebut menutup sesi kerja shift tanggal 20 Oktober.

> [!CAUTION]
> **Jam Perangkat Bukan Sumber Keputusan**:
> Jam, menit, dan tanggal yang digunakan untuk validasi absensi sepenuhnya ditentukan oleh **server waktu pusat**. Mengubah jam atau zona waktu manual pada pengaturan smartphone Anda tidak akan mempengaruhi pencatatan waktu server dan justru dapat menyebabkan kegagalan validasi token keamanan.

---

## 5. Penanganan Sesi Terbuka / Terkunci Melewati Batas 24 Jam

Jika Anda melakukan check-in tetapi lupa melakukan checkout hingga melewati batas 24 jam kalender:
- **Kondisi Sistem**: Server secara otomatis mengunci sesi absensi yang menggantung lebih dari 24 jam demi integritas data rekapitulasi.
- **Gejala di Aplikasi**: Tombol checkout tidak lagi merespons normal atau muncul pesan bahwa sesi kehadiran sebelumnya terkunci dan memerlukan koreksi pengelola.
- **Tindakan yang Benar**:
  1. **Jangan** mencoba memanipulasi jam/tanggal smartphone Anda.
  2. **Jangan** menghapus data aplikasi atau melakukan uninstal dengan harapan mereset status server.
  3. Hubungi atasan langsung atau bagian HRD untuk mengajukan permohonan **Koreksi Absensi** melalui Dashboard Web HR, dengan melampirkan bukti fisik jam kerja sebenarnya.

---

## 6. Prosedur Pengajuan Izin dan Cuti (Rentang Tanggal)

Karyawan dapat mengajukan permohonan izin atau cuti berbayar melalui modul pengajuan mobile.

### 6.1 Langkah Pengajuan Cuti / Izin
1. Buka tab **Pengajuan** di navigasi bawah, lalu pilih jenis pengajuan **Izin / Cuti** (atau tekan tombol `+` dan pilih `Izin/Cuti`).
2. **Pilih Jenis Izin/Cuti**: Pilih tipe izin dari daftar (misal: *Cuti Tahunan*, *Cuti Melahirkan*, *Izin Sakit*).
3. **Pilih Rentang Tanggal**:
   - Tentukan **Tanggal Mulai**.
   - Tentukan **Tanggal Berakhir**.
4. **Periksa Preview Hari Kerja & Saldo**:
   - Aplikasi akan otomatis menghitung jumlah **hari kerja efektif** dengan mengecualikan hari libur mingguan dan libur nasional resmi perusahaan.
   - Jika tipe cuti bersaldo (seperti Cuti Tahunan), layar akan menampilkan sisa saldo Anda saat ini dan proyeksi saldo setelah pengajuan disetujui.
5. **Isi Alasan Pengajuan**: Tuliskan alasan keperluan izin/cuti secara jelas dan jujur.
6. **Unggah Lampiran (Jika Diperlukan)**: Untuk izin sakit atau cuti khusus, lampirkan bukti pendukung (surat dokter) format JPG/PNG (maksimal 2.048 KiB).
7. Tekan tombol **Kirim pengajuan**.

---

## 7. Prosedur Pengajuan Lembur (Overtime)

- **Tujuan**: Mengajukan pencatatan jam kerja lembur yang ditugaskan oleh atasan.
- **Langkah-langkah**:
  1. Buka tab **Pengajuan** → tekan tombol tambah `+` → pilih **Lembur**.
  2. Pilih **Tanggal Pelaksanaan Lembur**.
  3. Masukkan **Durasi Lembur (Menit Bulat)**:
     - Masukkan durasi dalam satuan menit penuh (contoh: 2 jam diisi dengan angka `120`).
     - Batas teknis input aplikasi adalah antara **1 hingga 1.440 menit** (maksimal 24 jam dalam satu pengajuan).
  4. Masukkan **Deskripsi Tugas Lembur**: Jelaskan uraian pekerjaan atau proyek yang diselesaikan selama waktu lembur.
  5. Tekan tombol **Kirim pengajuan**.

> [!WARNING]
> **Batas Teknis vs Kebijakan Perusahaan**:
> Batas teknis formulir (1–1.440 menit) adalah batasan sistem agar input data valid. Hal ini **bukan** berarti setiap durasi yang Anda masukkan otomatis disetujui oleh atasan. Setiap pengajuan lembur tetap wajib mengikuti ketentuan regulasi ketenagakerjaan dan kebijakan batas lembur harian/mingguan perusahaan Anda.

---

## 8. Prosedur Pengajuan Reimbursement (Klaim Biaya)

- **Tujuan**: Mengajukan permohonan penggantian biaya operasional kantor yang telah Anda bayarkan terlebih dahulu.
- **Langkah-langkah**:
  1. Buka tab **Pengajuan** → pilih **Reimbursement**.
  2. Pilih **Tanggal Transaksi/Kuitansi**.
  3. Masukkan **Nominal Pengajuan (Rupiah)**: Ketikkan nominal biaya sesuai kuitansi fisik (contoh: `150.000`).
  4. Masukkan **Keterangan Keperluan**: Tuliskan rincian pengeluaran (contoh: *"Bahan bakar kendaraan dinas operasional ke gudang cabang"*).
  5. Tekan tombol **Pilih lampiran** untuk melampirkan foto kuitansi/bukti pembayaran asli.
  6. Tekan tombol **Kirim pengajuan**.

### Aturan Ketat Lampiran Bukti Reimbursement
- **Format yang Diterima**: File foto dengan format **JPG**, **JPEG**, atau **PNG**. Format PDF atau file teks tidak didukung untuk modul mobile.
- **Batas Ukuran Maksimal**: Tepat **2.048 KiB (2.097.152 byte)** atau sekitar 2 MB.
- **Pemberitahuan Langsung & Preservasi**: Jika Anda mencoba memilih file di atas 2.048 KiB, aplikasi langsung membatalkan pilihan tersebut dan menampilkan pesan:
  > *"Ukuran file melebihi 2.048 KiB (2.097.152 byte). Gunakan file maksimal 2 MB."*
- Jika Anda sebelumnya telah memilih lampiran valid dan pemilihan kedua ditolak karena ukuran melebihi batas, **lampiran valid sebelumnya tetap tersimpan dan tidak terhapus**.

---

## 9. Riwayat Pengajuan, Paginasi, dan Detail Tahap Keputusan

Karyawan dapat memantau status pengajuan izin, lembur, dan reimbursement melalui tab daftar riwayat.

### 9.1 Filter dan Paginasi
- Daftar pengajuan dibagi menjadi tab: **Semua**, **Menunggu (Pending)**, **Disetujui (Approved)**, dan **Ditolak (Rejected)**.
- Untuk memperbarui data status terbaru, tarik layar ke bawah (**Pull-to-Refresh**).
- Jika daftar memiliki banyak data, gulir ke bawah dan tekan tombol **Muat Lebih Banyak** untuk menampilkan riwayat lampau.

### 9.2 Halaman Detail Pengajuan
Halaman detail menyajikan 3 komponen terpisah yang mudah dibedakan:
1. **Kartu Catatan Pemohon**: Menampilkan tanggal, deskripsi, durasi/nominal, dan alasan awal yang Anda tuliskan saat mengajukan.
2. **Kartu Keputusan Terakhir**: Menampilkan nama peninjau akhir, status final (*Disetujui* / *Ditolak*), tanggal dan jam keputusan dengan label zona waktu resmi perusahaan (misal: `14:30 WIB`), serta catatan alasan peninjau (*decision_reason*).
3. **Kartu Riwayat Tahap Persetujuan**: Menampilkan alur berjenjang (misal: Tahap 1 oleh Supervisor, Tahap 2 oleh HR Manager), lengkap dengan status tiap tahap, nama peninjau, dan catatan pada masing-masing tahapan.

---

## 10. Kebijakan Pembatalan Pengajuan (Cancellation)

Aplikasi memberikan fleksibilitas pembatalan mandiri sesuai dengan status pengajuan:

### 10.1 Pembatalan Pengajuan Berstatus "Menunggu" (Pending)
- Berlaku untuk seluruh modul (**Izin/Cuti, Lembur, dan Reimbursement**).
- Selama pengajuan belum diproses oleh atasan/HR, Anda dapat membuka detail pengajuan dan menekan tombol **Batalkan Pengajuan**.
- Pengajuan akan langsung dibatalkan, dan jika itu pengajuan cuti tahunan, kuota saldo yang dicadangkan akan dikembalikan ke saldo aktif Anda.

### 10.2 Pembatalan Pengajuan Cuti Berstatus "Disetujui" (Approved)
- Jika pengajuan cuti Anda telah disetujui tetapi Anda berencana masuk kerja karena agenda batal, Anda dapat mengajukan **Permohonan Pembatalan Cuti**.
- Tekan tombol **Minta Pembatalan** pada halaman detail cuti.
- Status cuti akan berubah menjadi **Menunggu Pembatalan (`cancellation_requested`)**.
- **Aturan Penting**:
  - Selama permohonan pembatalan berstatus `cancellation_requested`, izin cuti **tetap dianggap sah dan berlaku** di sistem absensi sampai permohonan pembatalan tersebut disetujui secara resmi oleh HRD.
  - Jika HRD **menyetujui pembatalan**, status pengajuan berubah menjadi dibatalkan dan saldo cuti Anda dikembalikan secara otomatis.
  - Jika HRD **menolak pembatalan**, status pengajuan **kembali menjadi Disetujui (Approved)** seperti semula.

> [!WARNING]
> **Pengajuan Lembur & Reimbursement yang Telah Disetujui**:
> Karyawan **tidak dapat** membatalkan sendiri pengajuan lembur atau reimbursement yang sudah berstatus *Disetujui (Approved)* melalui aplikasi mobile. Jika terjadi kesalahan data pada pengajuan yang telah disetujui, hubungi atasan atau HRD untuk penyesuaian administratif.

---

## 11. Pengunduhan dan Penanganan Lampiran Privat

Foto kuitansi reimbursement dan surat dokter adalah dokumen privat yang dilindungi oleh sistem otentikasi ketat server.

### 11.1 Perlindungan Sesi dan Privasi Lampiran
- Lampiran tidak diakses melalui link publik bebas, melainkan melalui endpoint privat server dengan verifikasi token pengguna aktif.
- URL lampiran privat tidak dapat dibagikan kepada orang lain di luar perusahaan.
- **Isolasi Sesi**: Aplikasi menjamin bahwa saat Anda logout atau berganti akun, cache lampiran akun sebelumnya dibersihkan sehingga pengguna berikutnya tidak dapat melihat dokumen pribadi Anda.

### 11.2 Penanganan Kendala Pemuatan Lampiran
Saat membuka detail pengajuan dengan lampiran:
- Aplikasi akan menampilkan teks: *"Memuat lampiran privat…"*.
- **Jika Terjadi Gangguan Koneksi**: Aplikasi menampilkan pesan *"Gagal terhubung ke server. Periksa koneksi internet Anda"* beserta tombol **Muat ulang lampiran**.
- Menekan tombol **Muat ulang lampiran** hanya akan mengunduh ulang file gambar tanpa memuat ulang seluruh data pengajuan, sehingga menghemat kuota internet Anda.
- **Jika Muncul Pesan Akses Ditolak (403)**: Berarti akun yang sedang login tidak memiliki hak otorisasi untuk melihat lampiran tersebut.
- **Jika Muncul Pesan Sesi Berakhir (401)**: Silakan lakukan login ulang ke aplikasi.

---

## 12. Penanganan Masalah Saat Halaman Beranda Gagal Dimuat

Dalam kondisi jaringan seluler lemah atau server sedang mengalami pemeliharaan:
- Layar Beranda mungkin menampilkan pesan error koneksi atau indikator gagal memuat data.
- **Prinsip Utama**: Kondisi halaman Beranda gagal dimuat **bukan berarti Anda belum check-in** atau data absensi Anda hilang di server.
- **Langkah Tindakan**:
  1. Periksa kestabilan koneksi internet (WiFi atau paket data seluler).
  2. Tarik layar ke bawah (**Pull-to-Refresh**) untuk melakukan sinkronisasi ulang dengan server.
  3. Tunggu hingga data selesai dimuat.
  4. Jangan menekan tombol absensi berulang-ulang secara agresif ketika jaringan sedang terputus untuk menghindari pengiriman request ganda.
  5. Jika aplikasi menampilkan indikator sesi terputus setelah Anda berganti akun, tutup aplikasi dari daftar aplikasi aktif (*force close*), buka kembali, dan lakukan login bersih.
