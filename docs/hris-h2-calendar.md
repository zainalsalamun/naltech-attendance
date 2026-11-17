# H2 tahap 1 — Zona waktu dan kalender perusahaan

Diperiksa 21 Oktober 2026. Implementasi backend/web tersedia; **migrasi database utama belum dijalankan**. Ini tahap pertama H2, bukan penyelesaian seluruh penjadwalan HRIS. Face tetap tidak tersedia. Flutter tidak diubah; native belum diuji ulang.

## Perubahan yang tersedia

Menu **Kalender kerja** memuat zona waktu IANA, aturan mingguan dengan tanggal mulai berlaku, pengecualian satu tanggal (libur/hari kerja pengganti), kalender bulanan serta daftar aturan. Admin/super admin/HRD dapat menulis, supervisor membaca, staf tidak mengakses konfigurasi. Semua query dan mutasi memakai perusahaan akun aktif; ID perusahaan dari payload tidak dipakai.

Tidak mengasumsikan Senin–Jumat atau mengimpor hari libur nasional. HR memilih hari kerja dan mengonfirmasi pilihan, termasuk jika sengaja tidak memilih hari apa pun. Hari tanpa aturan efektif/pengecualian tetap **Belum diatur**. Pengecualian mengalahkan aturan mingguan. Aturan dengan tanggal efektif paling baru yang tidak melebihi tanggal rekap dipakai. Kode membaca aturan/pengecualian bulanan secara batch, bukan query per hari.

Aturan ditambahkan sebagai versi baru; tanggal mulai yang sama ditolak. Pengecualian dapat disimpan ulang untuk tanggal sama. Form menolak tanggal lampau menurut zona perusahaan. Tidak tersedia impor/backdate histori, hapus aturan atau penjadwalan lintas unit dalam irisan ini. Perubahan hari ini masih dapat mengubah klasifikasi kalender hari ini; snapshot kejadian belum tersedia. Aktor dan waktu tersimpan pada aturan/pengecualian, tetapi penggantian pengecualian belum memiliki ledger audit sebelum/sesudah (H3).

## Integrasi absensi dan Home

- Zona perusahaan nullable untuk legacy, mengikuti `app.timezone` sampai dikonfirmasi; UI menandainya. Tidak mengubah timezone PHP/global agar perusahaan lain tidak terpengaruh.
- Tanggal dan jam check-in/checkout, status `/api/is-checkin`, validasi QR absensi dan `/api/check-qr`, serta tanggal default/waktu muat Home memakai zona perusahaan.
- Check-in/checkout mengunci perusahaan sebelum karyawan, konsisten dengan penulisan konfigurasi. Pengubahan zona ke nilai lain ditolak jika sudah ada absensi; konfirmasi nilai yang sedang berlaku tetap diizinkan. Karena catatan lama belum menyimpan timezone, konversi riwayat perlu prosedur migrasi terpisah.
- Home memberi status Hari libur pada anggota tanpa absensi pada hari libur, tidak menghitung mereka sebagai Belum check-in. Catatan masuk yang benar-benar ada tetap tampil. Tidak melarang absensi di hari libur. Jika ada izin disetujui pada hari libur tetapi tanpa absensi, kategori yang tampil adalah Hari libur.
- Kalender tidak berarti semua anggota memiliki shift. Denominator masih anggota, waktu telat masih memakai shift saat ini, dan tanggal lampau memakai roster legacy. Belum check-in **bukan perhitungan alpa**; tidak ada kebijakan toleransi/penutupan periode.

## Penerapan

File schema: `apps/backend/database/migrations/2026_10_21_000003_add_work_calendars.php`. Menambah `companies.timezone` nullable serta tabel `work_calendar_rules` dan `work_calendar_days` dengan indeks perusahaan/tanggal dan FK. Tidak membuat aturan/hari libur atau mengonversi absensi lama. Modul kalender memberi 503 jika schema belum lengkap; Home dan absensi tetap kompatibel memakai fallback zona aplikasi sebelum migrasi.

Database utama belum dimigrasikan. Backup dan penerapan migrasi pending mengikuti prosedur operator. Rollback kalender sebelum migrasi organisasi/lifecycle/membership yang lebih lama. Migrasi lampiran H0 dan H1 utama yang belum diterapkan tetap langkah terpisah. Tidak ada deployment atau push.

## Validasi

- **99 tes backend, 1.222 assertions lulus** di SQLite memory terisolasi. Empat tes kalender mencakup aturan efektif, prioritas pengecualian, hari kosong, scope/role, penolakan tanggal lampau, schema belum siap, tanggal Asia/Jayapura melintasi UTC, QR, larangan perubahan zona dengan histori dan rekap libur.
- **15 checks browser Chrome headless lulus** memakai SQLite/storage sementara: konfigurasi zona, aturan mingguan, pengecualian libur/hari kerja pengganti dan Home; light/dark desktop 1440 dan layar sempit 390 tanpa overflow halaman.
- [Laporan browser](work-calendar-browser-qa.json), [konfigurasi desktop](screenshots/work-calendar/configuration-dark-desktop.png), [mobile](screenshots/work-calendar/configuration-light-mobile.png), [pilihan hari mobile](screenshots/work-calendar/weekdays-dark-mobile.png), [rekap hari libur](screenshots/work-calendar/holiday-dashboard-light-desktop.png).
- Pint dan kompilasi Blade diperiksa; cache view dibersihkan. Tidak menambah dependency. Belum menguji database produksi, konkurensi pada engine produksi atau tampilan native mobile.

## Berikutnya

H2 tahap 2: penugasan shift efektif per tanggal, snapshot jadwal/zona pada kejadian baru, checkout lintas tengah malam dan penyelarasan status/rekap. Catatan legacy tidak otomatis diberi snapshot rekaan. Sesudah fondasi itu, lanjut scope tim/approval H3 dan ledger cuti H4.
