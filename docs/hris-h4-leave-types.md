# H4 tahap 1 — Jenis dan kebijakan cuti perusahaan

Diperiksa 21 Oktober 2026. Master Dashboard dan integrasi pengajuan satu tanggal tersedia. Tidak menjalankan migrasi pada database utama. H2 tahap 2 serta pengujian wajah tetap ditunda.

## Ruang lingkup

Menu **Jenis cuti** menyediakan daftar, pencarian kode/nama, filter aktif/nonaktif, tambah, kelola dan riwayat perubahan. HR/admin/super admin perusahaan yang sama dapat menulis; supervisor hanya membaca daftar, staf tidak mengelola master. Tidak tersedia hard delete. Kode dinormalisasi ke huruf besar dan unik per perusahaan; nama/aturan tidak memakai default hukum atau kuota yang diasumsikan. Perusahaan dapat membuat sendiri jenis seperti tahunan, sakit, dan lainnya.

Setiap jenis memuat kode, nama, status, apakah mengurangi saldo, kuota dasar per tahun, kewajiban lampiran serta keterangan persyaratan. Aturan boolean dipilih eksplisit. Kuota bilangan bulat 0–366 wajib bila mengurangi saldo; tipe yang tidak mengurangi saldo menyimpan kuota null. Ini **kuota kebijakan, bukan saldo karyawan**. Belum ada alokasi/pengurangan saldo, tahun kebijakan, accrual, prorata, carry-over, kelayakan masa kerja atau validasi saldo tersedia.

Perubahan memerlukan alasan maksimal 1.000 karakter dan versi form yang sesuai. Transaksi mengunci perusahaan lalu aktor dan jenis, memeriksa ulang akun/role, menyimpan versi baru serta event before/after. Form usang ditolak 409; kode duplikat menghasilkan validasi. Riwayat UI menampilkan 30 perubahan terbaru dengan aktor, waktu, aturan saat disimpan dan alasan. Semua event tetap disimpan di database. Nama aktor mengikuti nama akun saat dibaca, bukan snapshot nama historis.

## Pengajuan dan approval

Dashboard Pengajuan izin memiliki aksi **Ajukan izin / cuti** untuk pengelola yang sudah berhak mengakses modul web. Pengajuan ini selalu milik akun yang masuk; tidak dapat mengganti pemilik lewat payload. Form memilih jenis aktif perusahaan, tanggal, alasan dan gambar JPG/PNG maksimal 2 MB. Bila jenis mensyaratkan lampiran, server memvalidasinya sebelum menulis berkas. Pemilihan jenis nonaktif, tidak ada atau milik perusahaan lain ditolak dengan validasi yang sama.

Server menyalin ID/kode/nama/versi/persyaratan/kuota/aturan lampiran ke `permissions.leave_policy_snapshot`. Snapshot tidak dapat dipalsukan melalui payload. Mengubah kode/nama/kuota, menonaktifkan jenis atau mengubah kewajiban lampiran setelah pengajuan tidak mengubah snapshot. Keputusan pengajuan berjalan tetap memakai aturan saat dikirim; penonaktifan master tidak membatalkan pengajuan lama.

Pengajuan cuti bertipe memakai **alur approval izin H3** yang sama; belum ada alur berbeda per jenis cuti. Tahap Atasan → HR, larangan self-approval, versi/event, penolakan dan pembatalan tetap berlaku. Persetujuan cuti tidak menghasilkan pemotongan saldo pada tahap ini. Event approval turut menyimpan field kebijakan dalam snapshot bisnis agar keputusan dapat ditelusuri.

Izin umum tetap boleh tanpa jenis dan tidak dianggap pengajuan cuti. Ini menjaga kompatibilitas klien lama. Data historis tidak diklasifikasikan otomatis dan tidak diberi kuota buatan. Jenis cuti perlu dipilih eksplisit; belum ada aturan wajib klasifikasi seluruh izin atau proses reklasifikasi izin umum menjadi cuti. Reviewer harus memeriksa pemilihan kategori ini. Tidak ada perubahan kebijakan akses bukti privat H0/H3.

## API dan mobile

- `GET /api/leave-types`: autentikasi, perusahaan aktif, hanya jenis aktif perusahaan akun, pagination 50 dengan current/last page. Field berasal dari policy yang tersedia, bukan saldo karyawan.
- `POST /api/api-permissions`: `leave_type_id` opsional; tanggal/alasan/lampiran tetap mengikuti kontrak sebelumnya. Server memvalidasi jenis dan membuat snapshot + alur approval. Payload status/boolean/owner/snapshot palsu tidak dipercaya.
- API list/detail izin mengembalikan jenis dan snapshot tersimpan. Status/approval_history tetap memakai H3.

Flutter belum ditambah pemilih jenis cuti pada tahap ini; aplikasi yang ada masih mengirim izin umum. API sudah tersedia untuk tahap mobile setelah review Dashboard. Tidak ada klaim uji HP, iOS atau APK release untuk fitur jenis cuti. URL mobile pengguna tidak diubah, dependency tidak ditambah.

## Penerapan dan batas

Migrasi `2026_10_21_000006_add_leave_types.php` menambah `leave_types`, `leave_type_events`, serta relasi nullable dan snapshot pada `permissions`. Tidak ada seed jenis atau pengisian data legacy otomatis. Foreign key restrictive mempertahankan keterkaitan riwayat. Route master, modul izin web dan API katalog menolak schema belum siap dengan 503. API create izin juga menolak melalui service jika schema belum lengkap. API list/detail lama tetap bisa membaca izin tanpa memalsukan kategori.

Backup, migrasi utama dan deployment tetap langkah operator. Pengujian memakai SQLite memory dan database/storage QA disposable; tidak menyentuh data utama. Rollback menghapus kebijakan dan snapshot sehingga bukan pemulihan proses bisnis penuh; memerlukan backup/rekonsiliasi sebelum downgrade. Uji konkurensi pada database produksi belum dilakukan.

Pengajuan masih untuk **satu tanggal**. Rentang tanggal, overlap, perhitungan hari sesuai jadwal efektif, ledger saldo dan pemotongan/pengembalian atomik menunggu tahap berikutnya; H2 tahap 2 tetap ditunda sesuai arahan pengguna. Belum ada pengaruh kuota terhadap payroll atau pembayaran. Tidak menambahkan pengiriman notifikasi eksternal.

## Bukti pemeriksaan

**117 tes backend lulus, 1.542 assertions**, menggunakan database terisolasi.

- Enam tes backend baru mencakup isolasi perusahaan/role, normalisasi kode dan duplikat, kuota wajib/0/batas, master kosong tanpa seed, audit/versi usang, lampiran privat wajib sebelum penulisan, penolakan jenis asing/nonaktif, payload snapshot palsu, snapshot setelah perubahan master, approval dua tahap dan izin umum legacy, pemilik form web, serta schema belum siap.
- Tes route lama diperbarui karena `/permissions/create` kini benar-benar tersedia. Tidak menghapus pemeriksaan route lain yang tetap tidak didukung.
- **25 pemeriksaan Chrome headless** pada fixture sintetis: state kosong, form master, audit, katalog API, validasi lampiran, upload gambar uji lewat form web, snapshot asli setelah master berubah, final approval melalui Dashboard, dan supervisor read-only. Light/dark desktop 1440 dan layar 390 tanpa overflow halaman. [Laporan browser](leave-policy-browser-qa.json).
- [Master kebijakan dark](screenshots/leave-policies/policy-dark-desktop.png), [detail cuti pada layar sempit](screenshots/leave-policies/submission-light-narrow.png). Screenshot adalah browser aktual pada data QA; gambar lampiran merupakan piksel sintetis, bukan foto pribadi.
- Format PHP terkait, kompilasi Blade beserta sintaks PHP hasil kompilasi, dan diff whitespace diperiksa. Cache hasil kompilasi QA dibersihkan. Flutter tidak berubah, sehingga tes Flutter tidak dijalankan ulang.

Alat reproduksi: `tools/mobile-qa/leave_policy_browser.cjs`, backend QA di direktori bertanda `.naltech-qa` dengan seed dan `workflow-fixtures`. Server pengujian hanya loopback dan dihentikan setelah pemeriksaan.

Pembaruan mobile 21 Oktober: [pemilih jenis dan kebijakan tersimpan di Flutter](hris-h4-mobile-leave.md) tersedia; API katalog dan pengajuan diuji dengan datasource asli. Bagian mobile yang belum tersedia di laporan tahap Dashboard ini kini dilengkapi; QA HP dan penerapan utama tetap terbuka.
