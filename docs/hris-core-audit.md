# Audit fitur inti HRIS — 21 Oktober 2026

Kesimpulan: fondasi saat ini adalah **absensi dan administrasi karyawan dasar**.
Belum cukup untuk diposisikan sebagai HRIS lengkap yang sudah siap operasional.
Materi belajar dapat memakai modul yang ada dengan batasan yang jelas, sedangkan
paket operasional memerlukan perbaikan integritas/akses dan modul inti berikut.
Ini penilaian kode lokal dan pengujian terisolasi, bukan audit deployment pembeli.

Liveness sesi/video ditunda sesuai arahan pengguna. Face tetap tidak aktif;
pekerjaan HRIS ini tidak mengaktifkan atau melanjutkan eksperimen biometrik.

## Peta fitur yang ditemukan

| Area | Sudah tersedia | Masih perlu dilengkapi |
| --- | --- | --- |
| Karyawan | Akun, kontak, jabatan/departemen teks, role, perusahaan, shift; CRUD web dan profil sendiri di mobile | Nomor karyawan per perusahaan, tanggal bergabung/keluar, status hubungan kerja, atasan/unit terstruktur, riwayat perubahan, arsip/nonaktif |
| Absensi | Masuk/pulang waktu server, radius, manual/QR sesuai perusahaan, satu catatan per akun/tanggal, riwayat dan ekspor | Checkout lintas tanggal, kalender kerja, penugasan shift efektif per tanggal, snapshot historis, metode/kebijakan saat kejadian, koreksi dengan alasan/audit |
| Izin | Pengajuan satu tanggal dengan alasan/lampiran; HR dapat menyetujui; daftar/detail milik sendiri pada API | Jenis cuti, rentang tanggal, setengah hari bila dipilih, aturan hari kerja, saldo, reservasi/pemakaian/pengembalian saldo, pembatalan dan overlap |
| Approval | Izin boolean; lembur dan reimbursement pending/approved/rejected | Peninjau/waktu/alasan keputusan, transisi status terkontrol, larangan persetujuan sendiri, lingkup tim, tahapan, idempotensi dan audit event |
| Rekap | Home harian, filter nama/tanggal, tabel absensi dan CSV | Rekap periode per karyawan, jam kerja/telat/pulang awal/ketidakhadiran dengan kalender, penutupan periode dan hasil historis stabil |
| Lembur/reimbursement | Pengajuan dan pengelolaan dasar | Approval yang dapat dilacak, kontrol akses detail lembur, batas/validasi seragam API-web, penguncian pengajuan final dan status pembayaran terpisah bila diperlukan |

`department` dan `position` saat ini teks bebas, bukan master organisasi.
`permissions.is_approved=false` berarti belum disetujui; tidak dapat membedakan
menunggu dan ditolak. UI saat ini memang memakai label “Belum disetujui”.
Tarif lembur perusahaan dan pengajuan reimbursement belum merupakan payroll.

## Temuan yang didahulukan

Tabel ini adalah snapshot sebelum perbaikan. [Implementasi H0](hris-h0-implementation.md)
menangani H-01, H-03, H-08 serta upload/akses lampiran baru H-04. Perlindungan
file legacy H-04 memerlukan apply command pada lingkungan yang dipilih. H-02
ditangani pada jalur operasional oleh [lifecycle H1](hris-h1-implementation.md), diuji tanpa menghapus histori; migrasi utama belum diterapkan. [Master unit/atasan H1](hris-h1-organization.md) juga tersedia dan diuji; [approval H3 tahap 1](hris-h3-approval.md) menutup jalur self-approval/overwrite/hapus operasional dengan audit transisi; sisa H2–H5 tetap terbuka; tabel berikut tetap snapshot audit awal.

P0 berarti didahulukan sebelum perluasan/paket operasional; P1 berarti fondasi
fitur berikutnya. Prioritas ini bukan klaim seluruh masalah repo sudah ditemukan.

| ID | Prioritas | Temuan dan dampak | Bukti |
| --- | --- | --- | --- |
| H-01 | P0 | API detail lembur hanya memeriksa perusahaan, tidak pemilik/role. Staf biasa bisa membaca pengajuan rekan satu perusahaan dengan ID-nya | Reproduksi HTTP terisolasi mendapat 200 dan user_id pemilik lain; `Api/OvertimeController::show` |
| H-02 | P0 | Hapus karyawan memakai hard delete; FK cascade menghapus absensi, izin, lembur dan reimbursement terkait. Riwayat HR/keuangan hilang | Reproduksi DELETE karyawan menghapus catatan absensinya; migrasi empat tabel memakai onDelete cascade |
| H-03 | P0 | CSV kehadiran menulis nama/koordinat tanpa proteksi formula; CSV Verifikasi sudah memiliki proteksi berbeda | Reproduksi nama fixture `=1+1` keluar apa adanya pada kolom nama; `AttendanceController::exportCsv` dibanding `VerifikasiPensiunController::csvText` |
| H-04 | P0 | Lampiran izin/reimbursement berada pada disk public dan UI menautkan storage langsung. Tidak ada jalur download terotorisasi yang ditemukan untuk bukti ini | Pembacaan kode store/upload dan partial bukti. Jika storage disajikan publik, URL file tidak memeriksa akun/perusahaan; akses HTTP deployment belum diuji |
| H-05 | P1 | Approval bisnis bisa langsung mengubah nilai akhir; belum ada peninjau, alasan, event, state machine atau scope tim | Controller update izin/lembur/reimbursement dan schema; tes izin yang ada juga menerima admin menyetujui pengajuannya sendiri |
| H-06 | P1 | Shift malam dapat disimpan, tetapi checkout mencari catatan pada tanggal server hari ini saja | Reproduksi catatan tanggal 20 pukul 22.00, checkout tanggal server 21 pukul 06.00 mendapat 400 dan catatan belum ditutup |
| H-07 | P1 | Mengganti shift saat ini dapat mengubah interpretasi terlambat pada tanggal lampau; tanpa kalender tidak bisa menghitung alpa/hari kerja secara andal | Home menggabungkan users.shift_id saat ini; tidak ada snapshot/kalender kerja pada schema |
| H-08 | P1 | Kontrak/validasi API belum seragam dengan web: update shift memakai request all, beberapa apiResource mengiklankan method yang belum diimplementasikan | `Api/ShiftController`, `Api/OvertimeController`, `routes/api.php`; pemeriksaan statis, semua kombinasi route belum direproduksi |
| H-09 | P1 | Edit absensi web memeriksa required saja pada waktu/koordinat, tidak mencatat alasan, aktor atau nilai sebelum/sesudah | `AttendanceController::update`; belum diuji terhadap semua engine database |

Nomor ID bukan pengganti otorisasi. Isolasi perusahaan yang ada tetap berguna,
tetapi tidak otomatis membatasi akses antar karyawan dalam satu perusahaan.
Scope supervisor per tim belum dapat ditegakkan tanpa data atasan/unit.

## Bukti kode

- [User dan lifecycle akun](../apps/backend/app/Http/Controllers/UserController.php),
  [model User](../apps/backend/app/Models/User.php).
- [API izin](../apps/backend/app/Http/Controllers/Api/PermissionController.php),
  [approval izin](../apps/backend/app/Http/Controllers/PermissionController.php),
  [schema izin](../apps/backend/database/migrations/2024_04_24_135413_create_permissions_table.php).
- [Detail lembur API](../apps/backend/app/Http/Controllers/Api/OvertimeController.php),
  [approval lembur](../apps/backend/app/Http/Controllers/OvertimeController.php),
  [reimbursement](../apps/backend/app/Http/Controllers/ReimbursementController.php).
- [Pencatatan absensi](../apps/backend/app/Http/Controllers/Api/AttendanceController.php),
  [edit/ekspor](../apps/backend/app/Http/Controllers/AttendanceController.php),
  [perhitungan Home](../apps/backend/app/Http/Controllers/DashboardController.php).
- [Role pengelola](../apps/backend/app/Http/Middleware/EnsureAttendanceManager.php),
  [scope perusahaan](../apps/backend/app/Models/Concerns/BelongsToCompanyUser.php),
  [route API](../apps/backend/routes/api.php).

## Validasi audit

Baseline backend: **74 tes lulus, 962 assertions**. Empat probe observasi tambahan
dijalankan terpisah: **4 reproduksi terkonfirmasi, 10 assertions**. Probe sengaja
memeriksa perilaku saat ini untuk membuktikan gap, bukan menyatakan perilaku
tersebut aman. Tidak ditambahkan sebagai tes regresi yang mempertahankan bug.
Saat perbaikan dikerjakan, tulis tes untuk perilaku aman yang diinginkan.

Semua database pengujian memakai SQLite dalam memori, dengan konfigurasi koneksi
dipaksa pada proses tes. Waktu shift malam mengikuti tanggal efektif server yang
dibekukan, bukan asumsi zona waktu perusahaan. Konfigurasi aplikasi memiliki
APP_TIMEZONE global dengan default UTC; belum ada zona waktu per perusahaan.
Empat probe sementara dibersihkan sesudah audit. Tidak ada email/pesan nyata,
akses akun produksi, migrasi database utama, perubahan UI atau logika aplikasi
dari tahap audit ini. Pengujian browser/mobile baru tidak dijalankan karena
implementasinya tidak diubah.

Tes yang lulus membuktikan kontrak yang diuji, bukan kelengkapan HRIS atau bahwa
celah di atas telah diperbaiki. Temuan statis dibedakan dari reproduksi HTTP.

## Lingkup paket source code

Untuk paket awal, nyatakan fitur secara spesifik: absensi manual/QR, karyawan,
izin sederhana, lembur, reimbursement dan Workspace. Jangan menjanjikan cuti
dengan saldo, approval bertingkat, payroll lengkap atau Face operasional sebelum
tersedia dan diuji. Dokumentasi belajar perlu menjelaskan batas itu serta
contoh pengujian dan data demo tanpa rahasia.

Rekomendasi MVP HRIS berikutnya: master/lifecycle karyawan, kalender/jadwal,
approval terstruktur, cuti dengan ledger saldo, dan rekap periode. Payroll,
rekrutmen serta penilaian kinerja dapat menjadi modul lanjutan terpisah, tanpa
menunda perbaikan fondasi. Urutan dan kriteria selesai ada pada
[timeline HRIS inti](hris-core-timeline.md).
