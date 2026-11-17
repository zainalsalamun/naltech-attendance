# H1 — Lifecycle karyawan

Diperiksa 21 Oktober 2026. Implementasi backend/web tersedia; **migrasi database utama belum dijalankan**. Face tetap tidak tersedia dan evaluasi liveness ditunda.

## Perubahan

- Nomor karyawan wajib untuk akun baru di web, kapital, unik dalam perusahaan termasuk arsip. Data legacy boleh tetap kosong; tidak dibuatkan nomor atau tanggal rekaan.
- Tanggal bergabung/keluar opsional, tanggal keluar tidak boleh sebelum bergabung. Akun aktif harus mengosongkan tanggal keluar. Tanggal bersifat informasi, bukan penjadwal status otomatis.
- Status aktif/nonaktif dan arsip. Jalur `DELETE /users/{id}` sekarang mengarsipkan dengan alasan wajib; tidak menghapus karyawan atau absensi, izin, lembur dan reimbursement. Aktifkan kembali melalui Edit, pilih Aktif, kosongkan tanggal keluar dan isi alasan.
- Filter aktif/nonaktif/arsip/semua, pencarian nama/nomor, serta 10 event lifecycle terakhir pada halaman Edit. Event menyimpan aktor, waktu, alasan dan snapshot field lifecycle/role, tanpa kata sandi atau token. Ini belum merupakan audit seluruh perubahan HR.
- Transisi membutuhkan alasan, menolak penonaktifan diri, dan mempertahankan minimal satu admin aktif dalam perusahaan. HR/supervisor hanya mengelola staf; isolasi perusahaan dan role tetap ditegakkan di server.
- Nonaktif menolak login web/API, sesi yang sudah ada dan absensi. Token API, remember token dan token notifikasi dicabut. Versi autentikasi (`auth_epoch`) memastikan sesi file/database lama tidak hidup kembali sesudah reaktivasi; pengguna harus login baru. Sesi browser lama tidak mencabut token API baru yang sah.
- Perubahan password/role melalui pengelolaan karyawan juga mencabut akses lama. API login dan absensi memeriksa status di dalam transaksi dengan penguncian baris; middleware memeriksa akun terbaru pada permintaan terautentikasi. Bukan jaminan bebas race untuk seluruh mutasi bisnis lain.
- Ringkasan Home **hari ini** hanya menghitung akun aktif. Rekap tanggal lampau masih memakai roster legacy; penugasan/jadwal dan snapshot historis adalah lingkup H2/H5.

## Penerapan dan kompatibilitas

Schema baru: enam field lifecycle (`employee_number`, `employment_status`, `joined_on`, `left_on`, `deactivated_at`, `archived_at`), `auth_epoch`, dan tabel `employee_lifecycle_events`.

Operator perlu backup dan menerapkan migrasi sesuai prosedur lingkungan. File baru: `apps/backend/database/migrations/2026_10_21_000001_add_employee_lifecycle.php`. Belum diterapkan pada database utama. Akun legacy default aktif, nomor/tanggal null, tanpa event riwayat buatan. Sebelum schema tersedia, modul `/users` mengembalikan 503 sebelum model binding; autentikasi legacy tetap kompatibel. Rollback lifecycle dahulu sebelum migrasi membership yang menghapus `company_id` karena indeks nomor bergantung padanya.

Endpoint login tetap memakai kontrak sebelumnya; akun nonaktif mendapat 401. Tidak ada perubahan Flutter dalam irisan ini dan native belum diuji ulang. Pemindahan lampiran legacy H0 masih menjadi langkah operator terpisah, lihat [H0](hris-h0-implementation.md).

Arsip mempertahankan seluruh riwayat dan tidak otomatis menghapus foto/enrollment wajah. Retensi/penghapusan permanen memerlukan prosedur terpisah; struktur FK lama bukan pengganti kebijakan retensi. Tanggal belum menjadi ledger periode rehire. Master unit dan hubungan atasan terstruktur kemudian ditambahkan dalam [irisan organisasi H1](hris-h1-organization.md); laporan tersebut memuat pemeriksaan terbaru.

## Bukti pemeriksaan

- Seluruh backend: **91 tes lulus, 1.118 assertions**, SQLite memory terisolasi.
- Delapan tes lifecycle: **77 assertions**, mencakup pelestarian empat jenis riwayat, pencabutan akses, sesi lama setelah reaktivasi, nomor unik/arsip/perusahaan, role/admin terakhir, tanggal/alasan, migrasi legacy dan 503 schema belum tersedia.
- Browser Chrome headless: **9 checks lulus**, database/storage SQLite sementara. Form tambah, normalisasi nomor, pembatalan arsip, arsip, filter/pencarian, riwayat dan reaktivasi berhasil. Light/dark pada lebar 1440 dan 390 diperiksa, tanpa overflow halaman; tabel memiliki scroll sendiri.
- [Laporan browser](employee-lifecycle-browser-qa.json), [screenshot desktop](screenshots/employee-lifecycle/list-light-desktop.png), [form mobile](screenshots/employee-lifecycle/create-light-mobile.png), [dialog arsip](screenshots/employee-lifecycle/archive-dialog-dark.png).
- Pint pada file PHP terkait; kompilasi Blade berhasil dan cache dibersihkan. Pint mengeluarkan deprecation dependency PHP 8.4, tanpa kegagalan formatter.

Tidak ada deployment, migrasi database utama atau pengujian engine produksi pada tahap ini. Review web mendahului perubahan mobile.
