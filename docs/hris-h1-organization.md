# H1 — Unit organisasi dan atasan langsung

Diperiksa 21 Oktober 2026. Implementasi backend/web tersedia; **migrasi database utama belum dijalankan**. Melanjutkan [lifecycle karyawan](hris-h1-implementation.md). Mobile menunggu review web, Face tetap tidak tersedia.

## Perilaku yang tersedia

- Menu **Unit organisasi**: daftar, cari kode/nama, filter aktif/nonaktif/semua, tambah dan edit. Kode kapital, unik dalam perusahaan termasuk unit nonaktif. Tidak tersedia jalur hapus; status dapat diaktifkan kembali melalui Edit.
- Admin/super admin/HRD mengelola unit. Supervisor dapat membaca; staf tidak mendapat akses modul. Seluruh binding, pencarian dan jumlah karyawan dibatasi perusahaan aktif.
- Form karyawan memuat unit terstruktur dan atasan langsung. Hubungan baru hanya menerima unit/atasan aktif di perusahaan yang sama. Atasan tidak boleh diri sendiri; penelusuran seluruh rantai menolak lingkaran, termasuk lingkaran tidak langsung.
- Menonaktifkan unit atau akun atasan tidak menghapus hubungan yang sudah ada. Hubungan tersebut diberi label nonaktif dan dapat dipertahankan, diganti atau dikosongkan. Unit/atasan nonaktif tidak muncul untuk penempatan baru. Atasan tidak harus memiliki role supervisor: hubungan organisasi berbeda dari hak akses.
- Mutasi unit dan hubungan menggunakan penguncian perusahaan yang sama dalam transaksi. Perubahan paralel yang lewat jalur ini diserialkan agar validasi graf tidak memakai struktur sebelum perubahan lain selesai. Uji konkurensi engine produksi belum dilakukan.
- `department` tetap teks legacy yang dapat disunting. Tidak ada pencocokan otomatis berdasar nama, backfill unit atau asumsi atasan. HR memilih unit pada form sambil melihat teks departemen lama; kolom daftar menampilkan keduanya.
- Snapshot event lifecycle karyawan kini juga menyimpan `organization_unit_id` dan `manager_id` sebelum/sesudah. Riwayat penggantian nama/status master unit belum berupa audit event tersendiri; ini bukan approval/audit bersama H3.

Hubungan ini belum mengubah siapa yang boleh menyetujui izin/lembur/reimbursement. Penggunaan atasan untuk scope tim dan alur approval adalah pekerjaan H3. Tidak ada hierarki antarunit, ledger periode penempatan atau penjadwalan efektif otomatis pada irisan ini.

## Schema dan penerapan

Migrasi baru `apps/backend/database/migrations/2026_10_21_000002_add_employee_organization.php` membuat `organization_units` dan menambah `users.organization_unit_id` / `users.manager_id` nullable dengan FK restrict. Existing karyawan tetap tanpa unit/atasan; teks departemen tidak berubah. Code tidak menambahkan field tersebut ke mass assignment profil/API.

Database utama belum dimigrasikan. Operator perlu backup dan menerapkan migrasi pending sesuai prosedur lingkungan. Urutan rollback: organisasi terlebih dahulu, lifecycle, lalu membership; tabel unit/relasi bergantung pada perusahaan dan users. Halaman `/users` dan `/units` memberi 503 sebelum binding jika schema kepegawaian belum lengkap. Login dan modul lama tetap dapat digunakan sebelum penerapan. Pemindahan lampiran legacy H0 adalah langkah operator terpisah.

## Pemeriksaan aktual

- **95 tes backend, 1.170 assertions lulus**, database SQLite memory terisolasi. Empat tes organisasi menyumbang 52 assertions: scope/role, kode unik, pemetaan, pelestarian legacy, unit/atasan nonaktif, self/cycle/lintas perusahaan, event dan schema belum tersedia.
- **20 checks browser Chrome headless lulus**, database/storage sementara. State kosong, tambah unit, penempatan, nonaktif dan filter diperiksa; form/list light/dark pada lebar 1440 dan 390 tanpa overflow halaman. Tabel tetap dapat digeser.
- Screenshot diperiksa: [daftar desktop](screenshots/employee-organization/unit-list-light-desktop.png), [form unit mobile dark](screenshots/employee-organization/unit-form-dark-mobile.png), [pemetaan karyawan](screenshots/employee-organization/employee-mapping-light-mobile.png). [Laporan browser](employee-organization-browser-qa.json).
- Pint berhasil, kompilasi Blade berhasil dan cache dibersihkan. Tidak menambah dependency/aset build baru.

Belum ada deployment, pengujian native mobile baru atau uji database produksi. H1 master dasar kini tersedia dalam kode; review web dan penerapan schema tetap terpisah. Tahap pengembangan berikutnya adalah H2: kalender, zona waktu perusahaan dan penugasan shift efektif, dengan kebijakan dikonfigurasi eksplisit.
