# Tracking Implementasi UI Dashboard Web — Naltech Attendance

Terakhir diperbarui: 19 Oktober 2026

## Prioritas dan ruang lingkup

**Dashboard web dikerjakan terlebih dahulu. Implementasi mobile dilanjutkan setelah Dashboard direview.**

Target implementasi: aplikasi Laravel pada `apps/backend`, menggunakan arah desain **Workspace** yang disetujui. Ruang lingkup awal adalah halaman Ringkasan/Dashboard beserta sidebar, header, tema, komponen, dan integrasi datanya. Desain Daily Brief tidak digunakan.

Status awal: desain Workspace dan kombinasi warna sudah disetujui; implementasi Dashboard baru belum dimulai. Data pada preview adalah ilustrasi dan harus diganti dengan data perusahaan pengguna yang masuk.

| Tahap | Prioritas | Status | Bergantung pada |
| --- | --- | --- | --- |
| 0. Audit UI dan sumber data | P0 | Belum dimulai | — |
| 1. Tema dan komponen dasar | P0 | Belum dimulai | Tahap 0 |
| 2. Sidebar, header, dan layout | P0 | Belum dimulai | Tahap 1 |
| 3. Ringkasan kehadiran | P0 | Belum dimulai | Tahap 0–2 |
| 4. Tabel kehadiran karyawan | P0 | Belum dimulai | Tahap 0–2 |
| 5. Pengajuan dan informasi kantor | P1 | Belum dimulai | Tahap 0–2 |
| 6. Integrasi dan pemeriksaan | P0 | Belum dimulai | Tahap 3–5 |
| 7. Review hasil Dashboard | P0 | Belum dimulai | Tahap 6 |

P0 = fondasi dan fungsi utama. P1 = panel pendukung yang diselesaikan sebelum review Dashboard lengkap.

## Keputusan desain

- [x] Memilih layout Workspace dengan sidebar navy dan panel putih pada light mode.
- [x] Mendukung light mode dan dark mode.
- [x] Menggunakan ikon garis yang konsisten, tanpa emoji.
- [x] Merevisi warna Terlambat dari `#A66620` menjadi amber.
- [ ] Menerapkan keputusan tersebut pada aplikasi Laravel.

| Elemen | Light | Dark |
| --- | --- | --- |
| Latar halaman | `#F5F6F8` | `#141B28` |
| Panel dan header | `#FFFFFF` | `#1D2635` |
| Teks utama | `#202C40` | `#E8EDF6` |
| Teks sekunder | `#69768A` | `#A1AEC3` |
| Garis pembatas | `#E5E9EF` | `#344054` |
| Aksen biru | `#315BCE` | `#A6BCFF` |
| Teks pada aksen | `#FFFFFF` | `#162747` |
| Aksen lembut | `#EDF2FF` | `#283A5A` |
| Hadir / sukses | `#34725B` | `#9ED2B9` |
| Terlambat | `#B45309` | `#FBBF24` |
| Sidebar | `#192A48` | `#192A48` |

Palet pada preview berfungsi untuk review desain; strip kode warna tidak perlu ditampilkan di Dashboard produksi. Status harus disertai label atau ikon, bukan dibedakan dengan warna saja.

## 0. Audit UI dan sumber data

- [ ] Periksa halaman `resources/views/pages/dashboard.blade.php`, layout, header, sidebar, dan aset yang dipakai.
- [ ] Periksa route Dashboard, middleware, query, model, serta pembatasan akses perusahaan.
- [ ] Catat baseline screenshot dan perilaku Dashboard sebelum perubahan.
- [ ] Identifikasi sumber jumlah karyawan terjadwal, hadir, tepat waktu, terlambat, izin, dan belum check-in.
- [ ] Pastikan aturan tanggal, zona waktu, shift lintas hari, dan toleransi terlambat dipahami sebelum menghitung ringkasan.
- [ ] Identifikasi sumber pengajuan izin/lembur serta informasi kantor, radius, dan metode absensi.
- [ ] Catat field yang belum tersedia dan kebutuhan perubahan backend; jangan mengisi data palsu pada tampilan produksi.

**Selesai ketika:** sumber data dan aturan perhitungan setiap panel tercatat, serta baseline tersedia.

## 1. Tema dan komponen dasar

- [ ] Definisikan token CSS light/dark untuk warna, tipografi, jarak, radius, dan garis pembatas.
- [ ] Tentukan satu sistem ikon garis dan gunakan secara konsisten.
- [ ] Siapkan komponen tombol, input pencarian, badge status, avatar fallback, panel, dan heading.
- [ ] Definisikan state hover, focus, aktif, nonaktif, loading bila diperlukan, dan error.
- [ ] Terapkan pilihan Light/Dark di header dan simpan preferensi pengguna pada browser.
- [ ] Pulihkan tema saat halaman dimuat dan hindari kilatan tema yang salah sejauh memungkinkan.
- [ ] Periksa kontras teks, ikon, status, dan tombol di kedua tema.
- [ ] Pastikan CSS baru tidak merusak halaman lain yang memakai layout bersama.

**Selesai ketika:** tema dapat diganti, tersimpan setelah reload, dan komponen dasar siap dipakai.

## 2. Sidebar, header, dan layout

- [ ] Terapkan merek Naltech Attendance dan susunan navigasi Workspace.
- [ ] Hubungkan menu ke route yang benar; gunakan penanda menu aktif sesuai halaman.
- [ ] Tampilkan nama perusahaan dari akun aktif serta identitas admin dari sesi.
- [ ] Terapkan header, breadcrumb, judul Ringkasan hari ini, dan kontrol tanggal jika didukung.
- [ ] Buat layout utama: ringkasan di atas, tabel di kiri, pengajuan/informasi kantor di kanan.
- [ ] Sesuaikan layout desktop, tablet, dan layar sempit; sediakan navigasi yang tetap dapat digunakan.
- [ ] Periksa judul/nama perusahaan panjang, keyboard navigation, fokus, dan label kontrol ikon.
- [ ] Pastikan profil dan keluar akun tetap dapat diakses sesuai alur aplikasi yang ada.

**Selesai ketika:** kerangka Dashboard mengikuti Workspace dan seluruh navigasi yang ditampilkan berfungsi.

## 3. Ringkasan kehadiran

- [ ] Tampilkan Hadir hari ini, Tepat waktu, Terlambat, dan Belum check-in dari data asli.
- [ ] Tampilkan total karyawan terjadwal dan informasi izin bila sumber datanya mendukung.
- [ ] Verifikasi kategori dan denominator agar angka tidak saling bertentangan; jangan menyamakan belum masuk dengan terlambat tanpa aturan.
- [ ] Batasi semua query pada perusahaan pengguna yang masuk.
- [ ] Tampilkan tanggal, zona waktu, serta waktu rekap yang sesuai dengan data; jangan menyebut data real-time jika tidak demikian.
- [ ] Terapkan ikon garis dan warna status, khususnya amber untuk keterlambatan.
- [ ] Sediakan keadaan nol data serta pesan gagal memuat bila relevan dengan cara render yang digunakan.

**Selesai ketika:** nilai ringkasan cocok dengan catatan absensi dan aturan jadwal perusahaan.

## 4. Tabel kehadiran karyawan

- [ ] Tampilkan nama/avatar fallback, informasi shift bila tersedia, jam masuk, jam pulang, status, dan aksi detail.
- [ ] Hubungkan pencarian karyawan ke data sebenarnya.
- [ ] Terapkan filter Semua, Terlambat, dan Belum masuk dengan definisi konsisten terhadap ringkasan.
- [ ] Pastikan filter dan pencarian bekerja bersama serta dipertahankan saat pagination bila digunakan.
- [ ] Terapkan pagination/jumlah hasil yang sesuai; hindari memuat seluruh data perusahaan besar sekaligus.
- [ ] Hubungkan aksi detail ke halaman atau panel yang menampilkan catatan karyawan yang benar.
- [ ] Bedakan belum absen, izin, dan data tidak tersedia dengan label yang jelas.
- [ ] Tampilkan keadaan kosong untuk tanggal tanpa data dan pencarian tanpa hasil.
- [ ] Pastikan tabel terbaca pada layar sempit tanpa memotong aksi penting.

**Selesai ketika:** hasil pencarian/filter akurat, detail dapat diakses, dan data tidak bocor antarperusahaan.

## 5. Pengajuan dan informasi kantor

### Pengajuan perlu ditinjau

- [ ] Tampilkan jumlah dan daftar pengajuan yang menunggu, sesuai tipe pengajuan yang didukung backend.
- [ ] Tampilkan nama, jenis pengajuan, tanggal/durasi dari data asli.
- [ ] Hubungkan Tinjau ke detail pengajuan; gunakan alur persetujuan yang sudah ada.
- [ ] Pastikan tindakan setuju/tolak memeriksa otorisasi dan perlindungan CSRF di server.
- [ ] Sinkronkan daftar dan jumlah setelah tindakan berhasil, melalui reload atau pembaruan data yang sesuai.
- [ ] Tampilkan keadaan tidak ada pengajuan dan error yang dapat ditindaklanjuti.

### Informasi kantor

- [ ] Tampilkan nama kantor, radius, dan metode absensi dari perusahaan aktif.
- [ ] Tangani konfigurasi lokasi atau metode yang belum tersedia tanpa menggunakan nilai contoh sebagai fallback.
- [ ] Hubungkan informasi lokasi ke halaman pengaturan/detail yang sudah tersedia jika diperlukan dan diizinkan.

**Selesai ketika:** pengajuan dapat ditinjau melalui alur sebenarnya dan informasi kantor sesuai konfigurasi.

## 6. Integrasi dan pemeriksaan

- [ ] Pastikan tidak ada angka, nama karyawan, tanggal, atau radius ilustrasi tersisa pada tampilan produksi.
- [ ] Uji Dashboard dengan data kosong, sedikit, banyak, dan teks panjang.
- [ ] Uji akun perusahaan berbeda dan akun tanpa perusahaan; pertahankan pembatasan akses server.
- [ ] Periksa konsistensi angka ringkasan terhadap tabel untuk tanggal yang sama.
- [ ] Periksa query berulang/N+1 dan gunakan pembatasan hasil atau agregasi yang sesuai.
- [ ] Jalankan pemeriksaan Laravel/aset yang relevan dengan perubahan; uji logika agregasi/otorisasi jika diubah.
- [ ] Uji filter, pencarian, pagination, detail, navigasi, dan pergantian tema setelah reload.
- [ ] Periksa tampilan desktop, tablet, layar sempit, dan browser yang tersedia.
- [ ] Periksa konsol browser serta error aplikasi; dokumentasikan kendala yang belum terselesaikan.
- [ ] Simpan screenshot light/dark beserta hasil pemeriksaan untuk review pengguna.

**Selesai ketika:** UI dan data berfungsi pada skenario utama, bukti pemeriksaan tersedia, dan keterbatasan dicatat.

## 7. Review hasil Dashboard dan kelanjutan

- [ ] Tampilkan Dashboard yang sudah berjalan kepada pengguna.
- [ ] Catat revisi hasil review dan selesaikan revisi yang disepakati.
- [ ] Tandai Dashboard selesai setelah kriteria di atas terpenuhi dan hasil direview.
- [ ] Mulai checklist mobile setelah tahap Dashboard selesai direview.

## Urutan pengerjaan praktis

1. Audit → fondasi tema → sidebar/header/layout.
2. Ringkasan dan tabel kehadiran dengan data asli.
3. Pengajuan dan informasi kantor.
4. Pemeriksaan, screenshot, dan review Dashboard.
5. Lanjutkan `mobile-ui-implementation-checklist.md`.

Redesain menyeluruh halaman CRUD lain, publikasi portfolio, deployment, serta push Git bukan bagian dari checklist Dashboard ini.

## Cara tracking

Centang `[x]` setelah tugas selesai dan diperiksa. Status tahap menggunakan Belum dimulai, Berjalan, Terhambat, atau Selesai. Jika terhambat, tulis penyebab serta langkah berikutnya. Perbarui tanggal dokumen dan lampirkan bukti ringkas berupa hasil pemeriksaan, screenshot, atau commit.

| Tanggal | Tahap / tugas | Hasil / bukti | Hambatan / langkah berikutnya |
| --- | --- | --- | --- |
| 19 Oktober 2026 | Perencanaan Dashboard | Checklist dibuat; Dashboard diprioritaskan sebelum mobile | Mulai audit dan implementasi fondasi tema + layout |
