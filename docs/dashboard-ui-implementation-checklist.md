# Tracking Implementasi UI Dashboard Web — Naltech Attendance

Terakhir diperbarui: 19 Oktober 2026

## Prioritas dan ruang lingkup

**Dashboard web dikerjakan terlebih dahulu. Implementasi mobile dilanjutkan setelah Dashboard direview.**

Target implementasi: aplikasi Laravel pada `apps/backend`, menggunakan arah desain **Workspace** yang disetujui. Ruang lingkup awal adalah halaman Ringkasan/Dashboard beserta sidebar, header, tema, komponen, dan integrasi datanya. Desain Daily Brief tidak digunakan.

Status saat ini: implementasi Dashboard Workspace tersedia dan telah diperiksa melalui tes backend serta preview Blade dengan data uji terisolasi. Pemeriksaan desktop/tablet/layar sempit dan skenario pagination/persetujuan telah dilakukan. Dashboard siap untuk review visual pengguna; batas aturan bisnis dicatat di bawah.

| Tahap | Prioritas | Status | Bergantung pada |
| --- | --- | --- | --- |
| 0. Audit UI dan sumber data | P0 | Berjalan | — |
| 1. Tema dan komponen dasar | P0 | Selesai | Tahap 0 |
| 2. Sidebar, header, dan layout | P0 | Berjalan | Tahap 1 |
| 3. Ringkasan kehadiran | P0 | Berjalan | Tahap 0–2 |
| 4. Tabel kehadiran karyawan | P0 | Selesai | Tahap 0–2 |
| 5. Pengajuan dan informasi kantor | P1 | Berjalan | Tahap 0–2 |
| 6. Integrasi dan pemeriksaan | P0 | Berjalan | Tahap 3–5 |
| 7. Review hasil Dashboard | P0 | Berjalan | Tahap 6 |

P0 = fondasi dan fungsi utama. P1 = panel pendukung yang diselesaikan sebelum review Dashboard lengkap.

## Keputusan desain

- [x] Memilih layout Workspace dengan sidebar navy dan panel putih pada light mode.
- [x] Mendukung light mode dan dark mode.
- [x] Menggunakan ikon garis yang konsisten, tanpa emoji.
- [x] Merevisi warna Terlambat dari `#A66620` menjadi amber.
- [x] Menerapkan keputusan tersebut pada aplikasi Laravel.

| Elemen | Light | Dark |
| --- | --- | --- |
| Latar halaman | `#F5F6F8` | `#141B28` |
| Panel dan header | `#FFFFFF` | `#1D2635` |
| Teks utama | `#202C40` | `#E8EDF6` |
| Teks sekunder | `#626F83` | `#A1AEC3` |
| Garis pembatas | `#E5E9EF` | `#344054` |
| Aksen biru | `#315BCE` | `#A6BCFF` |
| Teks pada aksen | `#FFFFFF` | `#162747` |
| Aksen lembut | `#EDF2FF` | `#283A5A` |
| Hadir / sukses | `#34725B` | `#9ED2B9` |
| Terlambat | `#B45309` | `#FBBF24` |
| Sidebar | `#192A48` | `#192A48` |

Palet pada preview berfungsi untuk review desain; strip kode warna tidak perlu ditampilkan di Dashboard produksi. Status harus disertai label atau ikon, bukan dibedakan dengan warna saja.

## 0. Audit UI dan sumber data

- [x] Periksa halaman `resources/views/pages/dashboard.blade.php`, layout, header, sidebar, dan aset yang dipakai.
- [x] Periksa route Dashboard, middleware, query, model, serta pembatasan akses perusahaan.
- [ ] Catat baseline screenshot dan perilaku Dashboard sebelum perubahan.
- [x] Identifikasi sumber jumlah karyawan terjadwal, hadir, tepat waktu, terlambat, izin, dan belum check-in.
- [ ] Pastikan aturan tanggal, zona waktu, shift lintas hari, dan toleransi terlambat dipahami sebelum menghitung ringkasan.
- [x] Identifikasi sumber pengajuan izin/lembur serta informasi kantor, radius, dan metode absensi.
- [x] Catat field yang belum tersedia dan kebutuhan perubahan backend; jangan mengisi data palsu pada tampilan produksi.

**Selesai ketika:** sumber data dan aturan perhitungan setiap panel tercatat, serta baseline tersedia.

## 1. Tema dan komponen dasar

- [x] Definisikan token CSS light/dark untuk warna, tipografi, jarak, radius, dan garis pembatas.
- [x] Tentukan satu sistem ikon garis dan gunakan secara konsisten.
- [x] Siapkan komponen tombol, input pencarian, badge status, avatar fallback, panel, dan heading.
- [x] Definisikan state hover, focus, aktif, nonaktif, loading bila diperlukan, dan error.
- [x] Terapkan pilihan Light/Dark di header dan simpan preferensi pengguna pada browser.
- [x] Pulihkan tema saat halaman dimuat dan hindari kilatan tema yang salah sejauh memungkinkan.
- [x] Periksa kontras teks, ikon, status, dan tombol di kedua tema.
- [x] Pastikan CSS baru tidak merusak halaman lain yang memakai layout bersama.

**Selesai ketika:** tema dapat diganti, tersimpan setelah reload, dan komponen dasar siap dipakai.

## 2. Sidebar, header, dan layout

- [x] Terapkan merek Naltech Attendance dan susunan navigasi Workspace.
- [x] Hubungkan menu ke route yang benar; gunakan penanda menu aktif sesuai halaman.
- [x] Tampilkan nama perusahaan dari akun aktif serta identitas admin dari sesi.
- [x] Terapkan header, breadcrumb, judul Ringkasan hari ini, dan kontrol tanggal jika didukung.
- [x] Buat layout utama: ringkasan di atas, tabel di kiri, pengajuan/informasi kantor di kanan.
- [x] Sesuaikan layout desktop, tablet, dan layar sempit; sediakan navigasi yang tetap dapat digunakan.
- [x] Periksa judul/nama perusahaan panjang, keyboard navigation, fokus, dan label kontrol ikon.
- [ ] Pastikan profil dan keluar akun tetap dapat diakses sesuai alur aplikasi yang ada.

**Selesai ketika:** kerangka Dashboard mengikuti Workspace dan seluruh navigasi yang ditampilkan berfungsi.

## 3. Ringkasan kehadiran

- [x] Tampilkan Hadir hari ini, Tepat waktu, Terlambat, dan Belum check-in dari data asli.
- [ ] Tampilkan total karyawan terjadwal dan informasi izin bila sumber datanya mendukung.
- [x] Verifikasi kategori dan denominator agar angka tidak saling bertentangan; jangan menyamakan belum masuk dengan terlambat tanpa aturan.
- [x] Batasi semua query pada perusahaan pengguna yang masuk.
- [x] Tampilkan tanggal, zona waktu, serta waktu rekap yang sesuai dengan data; jangan menyebut data real-time jika tidak demikian.
- [x] Terapkan ikon garis dan warna status, khususnya amber untuk keterlambatan.
- [ ] Sediakan keadaan nol data serta pesan gagal memuat bila relevan dengan cara render yang digunakan.

**Selesai ketika:** nilai ringkasan cocok dengan catatan absensi dan aturan jadwal perusahaan.

## 4. Tabel kehadiran karyawan

- [x] Tampilkan nama/avatar fallback, informasi shift bila tersedia, jam masuk, jam pulang, status, dan aksi detail.
- [x] Hubungkan pencarian karyawan ke data sebenarnya.
- [x] Terapkan filter Semua, Terlambat, dan Belum masuk dengan definisi konsisten terhadap ringkasan.
- [x] Pastikan filter dan pencarian bekerja bersama serta dipertahankan saat pagination bila digunakan.
- [x] Terapkan pagination/jumlah hasil yang sesuai; hindari memuat seluruh data perusahaan besar sekaligus.
- [x] Hubungkan aksi detail ke halaman atau panel yang menampilkan catatan karyawan yang benar.
- [x] Bedakan belum absen, izin, dan data tidak tersedia dengan label yang jelas.
- [x] Tampilkan keadaan kosong untuk tanggal tanpa data dan pencarian tanpa hasil.
- [x] Pastikan tabel terbaca pada layar sempit tanpa memotong aksi penting.

**Selesai ketika:** hasil pencarian/filter akurat, detail dapat diakses, dan data tidak bocor antarperusahaan.

## 5. Pengajuan dan informasi kantor

### Pengajuan perlu ditinjau

- [ ] Tampilkan jumlah dan daftar pengajuan yang menunggu, sesuai tipe pengajuan yang didukung backend.
- [x] Tampilkan nama, jenis pengajuan, tanggal/durasi dari data asli.
- [x] Hubungkan Tinjau ke detail pengajuan; gunakan alur persetujuan yang sudah ada.
- [x] Pastikan tindakan setuju/tolak memeriksa otorisasi dan perlindungan CSRF di server.
- [x] Sinkronkan daftar dan jumlah setelah tindakan berhasil, melalui reload atau pembaruan data yang sesuai.
- [ ] Tampilkan keadaan tidak ada pengajuan dan error yang dapat ditindaklanjuti.

### Informasi kantor

- [x] Tampilkan nama kantor, radius, dan metode absensi dari perusahaan aktif.
- [x] Tangani konfigurasi lokasi atau metode yang belum tersedia tanpa menggunakan nilai contoh sebagai fallback.
- [x] Hubungkan informasi lokasi ke halaman pengaturan/detail yang sudah tersedia jika diperlukan dan diizinkan.

**Selesai ketika:** pengajuan dapat ditinjau melalui alur sebenarnya dan informasi kantor sesuai konfigurasi.

## 6. Integrasi dan pemeriksaan

- [x] Pastikan tidak ada angka, nama karyawan, tanggal, atau radius ilustrasi tersisa pada tampilan produksi.
- [x] Uji Dashboard dengan data kosong, sedikit, banyak, dan teks panjang.
- [x] Uji akun perusahaan berbeda dan akun tanpa perusahaan; pertahankan pembatasan akses server.
- [x] Periksa konsistensi angka ringkasan terhadap tabel untuk tanggal yang sama.
- [x] Periksa query berulang/N+1 dan gunakan pembatasan hasil atau agregasi yang sesuai.
- [x] Jalankan pemeriksaan Laravel/aset yang relevan dengan perubahan; uji logika agregasi/otorisasi jika diubah.
- [x] Uji filter, pencarian, pagination, detail, navigasi, dan pergantian tema setelah reload.
- [x] Periksa tampilan desktop, tablet, layar sempit, dan browser yang tersedia.
- [x] Periksa konsol browser serta error aplikasi; dokumentasikan kendala yang belum terselesaikan.
- [x] Simpan screenshot light/dark beserta hasil pemeriksaan untuk review pengguna.

**Selesai ketika:** UI dan data berfungsi pada skenario utama, bukti pemeriksaan tersedia, dan keterbatasan dicatat.

## 7. Review hasil Dashboard dan kelanjutan

- [x] Tampilkan Dashboard yang sudah berjalan kepada pengguna.
- [ ] Catat revisi hasil review dan selesaikan revisi yang disepakati.
- [ ] Tandai Dashboard selesai setelah kriteria di atas terpenuhi dan hasil direview.
- [ ] Mulai checklist mobile setelah tahap Dashboard selesai direview.

## Urutan pengerjaan praktis

1. Audit → fondasi tema → sidebar/header/layout.
2. Ringkasan dan tabel kehadiran dengan data asli.
3. Pengajuan dan informasi kantor.
4. Pemeriksaan, screenshot, dan review Dashboard.
5. Lanjutkan `mobile-ui-implementation-checklist.md`.

Checklist ini khusus Home/Ringkasan. Sesuai perluasan lingkup pengguna, halaman CRUD lain kini masuk [timeline web menyeluruh](web-ui-timeline.md). Publikasi portfolio, deployment, serta push Git tetap di luar lingkup.

## Cara tracking

Centang `[x]` setelah tugas selesai dan diperiksa. Status tahap menggunakan Belum dimulai, Berjalan, Terhambat, atau Selesai. Jika terhambat, tulis penyebab serta langkah berikutnya. Perbarui tanggal dokumen dan lampirkan bukti ringkas berupa hasil pemeriksaan, screenshot, atau commit.

| Tanggal | Tahap / tugas | Hasil / bukti | Hambatan / langkah berikutnya |
| --- | --- | --- | --- |
| 19 Oktober 2026 | Perencanaan Dashboard | Checklist dibuat; Dashboard diprioritaskan sebelum mobile | Mulai audit dan implementasi fondasi tema + layout |

## Bukti lanjutan — 19 Oktober 2026

- Implementasi yang ditemukan: controller Dashboard, layout Workspace, CSS/JS khusus, tabel berhalaman, pencarian/filter, pengajuan, dan informasi perusahaan. Layout lama halaman CRUD tidak diubah.
- `php artisan test`: **22 lulus, 76 assertion**, termasuk dua tes Dashboard baru. Tes memakai SQLite `:memory:` dan tidak memigrasikan database pengguna. Memeriksa ringkasan/status, tanggal, gabungan pencarian/filter, batas perusahaan pada anggota/pengajuan, akses staf, input tidak valid, dan akun tanpa perusahaan.
- `php artisan view:cache`: berhasil. Cache dibersihkan setelah pemeriksaan.
- Browser in-app, viewport 661 px: preview HTML hasil render Blade dengan data uji; light/dark, pemulihan tema setelah reload, buka navigasi dan tutup dengan Escape diperiksa. Label tombol tema diperbaiki agar tetap terbaca pembaca layar ketika teks disembunyikan pada layar sempit.
- Screenshot: [light](screenshots/dashboard-light-narrow.png), [dark](screenshots/dashboard-dark-narrow.png). Screenshot berisi fixture tes, bukan data produksi atau sesi login pengguna.
- Belum diperiksa: browser desktop/lebar lain, data besar dan teks panjang, pagination lintas halaman, console browser, serta alur persetujuan hingga selesai. Screenshot baseline sebelum implementasi tidak tersedia karena perubahan awal sudah ada saat pekerjaan dilanjutkan.

### Sumber dan batas perhitungan

- Keanggotaan: `users.company_id` akun aktif; middleware `EnsureCompany` menolak akun tanpa perusahaan. Role pengelola mengikuti middleware pengelola yang ada. Staf hanya melihat dirinya.
- Kehadiran: join `attendances` per anggota dan tanggal pilihan; constraint unik user/tanggal mencegah penggandaan hitungan. Shift hanya diambil dari perusahaan aktif.
- Tepat waktu/terlambat memakai jam shift saat ini tanpa toleransi; shift hilang/tidak lengkap/lintas hari dengan check-in diberi status **Perlu tinjau**. Tidak tersedia kalender kerja atau snapshot shift historis; denominator adalah anggota terdaftar, bukan karyawan terjadwal. Hal ini dijelaskan di UI.
- Izin disetujui memakai `permissions.is_approved` pada tanggal pilihan dan tidak termasuk belum check-in. Check-in mengambil prioritas bila ada izin sekaligus absensi.
- Boolean izin belum membedakan menunggu/ditolak; panel memakai label **Belum disetujui**. Lembur memakai status `pending`. Keduanya mengikuti scope perusahaan pada model; daftar tiga terbaru, jumlah dihitung seluruhnya, tidak mengikuti tanggal rekap.
- Nama/alamat/radius/metode berasal dari perusahaan aktif. Wajah tetap ditandai belum tersedia. Zona waktu mengikuti konfigurasi aplikasi; zona waktu per perusahaan belum tersedia dan perlu keputusan lanjutan.
- Tahap mobile belum dimulai; menunggu review Dashboard.

## Pemeriksaan lanjutan — desktop dan integrasi

- **26 tes lulus, 101 assertion**. Tes baru mencakup 25 anggota hasil pencarian, halaman kedua dan URL halaman berikutnya, pencarian kosong tanpa mengubah ringkasan, shift lintas hari/jam sama, prioritas check-in atas izin, persetujuan izin/lembur, perubahan jumlah setelah reload, penolakan akses lintas perusahaan/staf, validasi input, serta penolakan mutasi web tanpa CSRF (419).
- Pengiriman email pada tes persetujuan diganti mock; tidak ada email dikirim. Pengiriman email produksi belum diperiksa.
- Controller izin sekarang memakai `findOrFail` untuk detail/edit/update dan validasi boolean pada persetujuan. Form edit izin diperbaiki dari field `is_approval` ke `is_approved` agar pilihan mengikuti nilai tersimpan.
- Browser in-app menjalankan route/controller/Blade sebenarnya dengan 26 anggota fixture, SQLite `:memory:` baru untuk tiap request, dan router sementara hanya GET pada loopback. Database pengguna tidak diubah. Router pengujian tidak ditambahkan ke aplikasi dan dihentikan setelah pemeriksaan.
- Viewport terukur: 1440×1000, 1024×900, 390×844. Nama perusahaan/karyawan panjang, pencarian, filter terlambat (6 hasil), halaman kedua (9–16 dari 26), detail, dan pencarian tanpa hasil diperiksa. Tidak ada overflow horizontal halaman pada 1024/390 px; tabel tetap dapat digeser.
- Detail karyawan sekarang memakai dialog native agar informasi tidak terpotong di dalam tabel sempit. Tombol Tutup berfungsi; fokus modal mengikuti perilaku browser. Navigasi sempit mengikuti tinggi header via ResizeObserver, memiliki scroll sendiri, dan mengembalikan fokus ke tombol menu saat Escape dari navigasi.
- Kontras teks sekunder light diperkuat dari `#69768A` menjadi `#626F83` karena warna sebelumnya hanya 4,26:1 pada latar halaman. Token baru memenuhi minimal 4,5:1 pada latar/panel/soft yang dipakai. Warna utama/status juga diperiksa. Keputusan diperbarui di `design.md`.
- Konsol browser pada skenario yang diperiksa: tidak ada warning/error. `view:cache` berhasil dan cache dibersihkan; `git diff --check` diperiksa. Pint dijalankan pada controller Dashboard/tes, berhasil dengan peringatan deprecation dependency pada PHP 8.4; dependency tidak diedit.
- CSS/JS Workspace dimuat hanya layout Workspace; halaman CRUD tetap menggunakan layout lama. Build Vite tidak diperlukan karena aset Dashboard dilayani langsung dari `public/`.

Bukti terbaru: [desktop light](screenshots/dashboard-light-desktop.png), [desktop dark](screenshots/dashboard-dark-desktop.png), [tablet dark](screenshots/dashboard-dark-tablet.png), [detail layar sempit](screenshots/dashboard-detail-mobile.png), [hasil pencarian kosong](screenshots/dashboard-empty-mobile.png).

### Sisa sebelum penutupan Dashboard

- Review visual pengguna atas screenshot terbaru; mobile tetap menunggu review Dashboard.
- Verifikasi bisnis kalender kerja, zona waktu per perusahaan, toleransi dan riwayat shift belum memiliki sumber pada skema saat ini. UI memakai definisi terbatas yang dijelaskan, bukan mengarang jumlah terjadwal.
- Status izin menunggu/ditolak masih satu boolean. Pemisahan status memerlukan pekerjaan backend tersendiri; label Dashboard tetap “Belum disetujui”.
- Profil/logout dan persetujuan diuji melalui struktur route/form dan tes server terkait; browser tidak menjalankan sesi login/logout produksi atau pengiriman email.
- Uji beban perusahaan besar serta kegagalan layanan nyata belum dilakukan. Pemeriksaan banyak data di sini berarti beberapa halaman (26 anggota), bukan benchmark beban produksi.

## Pembaruan lingkup

Home bukan keseluruhan Dashboard web. Status halaman lain kini ditrack pada [timeline web menyeluruh](web-ui-timeline.md). Kehadiran sudah diimplementasikan pada tahap W2; Karyawan berikutnya. Mobile menunggu review web menyeluruh.


## Status lintas web — 20 Oktober 2026

Modul W0–W11 sudah tersedia; status historis “Karyawan berikutnya” di atas menggambarkan tahap awal. Acuan terkini adalah [timeline web](web-ui-timeline.md) dan [laporan review W12](web-ui-qa-review.md). Review teknis lanjutan sudah memperbaiki fokus menu akun, akses keyboard tabel dan indikator fokus form. Login/logout memakai sesi fixture nyata telah diperiksa pada W11. Persetujuan visual pengguna dan batas aturan bisnis tetap terpisah dari kelulusan QA lokal.
