# Tracking Implementasi UI Mobile — Naltech Attendance

Terakhir diperbarui: 20 Oktober 2026

## Tujuan dan status

**Urutan proyek: implementasi dan review Dashboard web terlebih dahulu, kemudian mobile.** Lihat [timeline web menyeluruh](web-ui-timeline.md) untuk prioritas pengerjaan saat ini; Home saja belum memenuhi gerbang review web. Arahan pengguna “Oke lanjutkan nextnya” setelah review teknis W12 menjadi dasar melanjutkan mobile pada 20 Oktober 2026. Batas QA web tetap tercatat; tidak berarti seluruh aturan bisnis web sudah berubah.

Menerapkan rancangan mobile yang sudah disetujui ke `apps/mobile`, dengan identitas visual yang selaras dengan Dashboard Workspace. Implementasi dimulai dari fondasi tema dan Beranda, dilanjutkan layar lain setelah hasil awal dapat dicoba.

Status terkini: **M1–M6 tersedia; M7 regresi/HTTP lulus dan native diperiksa sebagian, belum tuntas**. Uji perangkat dan API nyata belum selesai. Preview berisi data ilustrasi, bukan bukti fitur telah terhubung atau lolos pengujian.

| Tahap | Prioritas | Status | Ketergantungan |
| --- | --- | --- | --- |
| 0. Audit dan baseline | P0 | Berjalan | Audit kode/SDK tersedia; baseline perangkat belum |
| 1. Fondasi tema dan komponen | P0 | Berjalan | ThemeData dan persistensi tersedia; komponen domain menyusul |
| 2. Navigasi dan Beranda | P0 | Implementasi tersedia; review widget | Uji perangkat dan API nyata masih menunggu |
| 3. Alur absensi dan QR | P0 | Implementasi dan QA lokal tersedia | Uji kamera/GPS/API nyata belum |
| 4. Riwayat kehadiran | P1 | Implementasi dan QA lokal tersedia | Status waktu historis terbatas; uji perangkat/API nyata belum |
| 5. Profil dan pengaturan | P1 | Implementasi dan QA lokal tersedia | Galeri native/API nyata belum diuji |
| 6. Login dan sesi | P1 | Implementasi dan QA lokal tersedia | Restore/401/logout diuji terisolasi; API nyata belum |
| 7. Izin dan reimbursement | P1 | Implementasi dan QA lokal tersedia | Galeri/perangkat/API nyata belum diuji |
| 8. QA dan dokumentasi hasil | P0 | Lokal/API lulus; native belum tuntas | Full Android/iOS dan perangkat fisik menunggu lingkungan memadai |

P0 = diperlukan untuk irisan implementasi awal yang dapat digunakan. P1 = dikerjakan setelah fondasi dan alur inti stabil.

## Aturan desain

- [x] Menggunakan arah desain Workspace dan rancangan mobile yang disetujui.
- [x] Mendukung light mode dan dark mode.
- [x] Menggunakan ikon garis yang konsisten, tanpa emoji.
- [x] Merevisi warna Terlambat menjadi amber.
- [ ] Membawa keputusan desain tersebut ke tema dan komponen Flutter.

| Token | Light | Dark | Pemakaian |
| --- | --- | --- | --- |
| Latar | `#F5F6F8` | `#141B28` | Latar layar |
| Panel | `#FFFFFF` | `#1D2635` | Kartu, formulir, navigasi |
| Teks utama | `#202C40` | `#E8EDF6` | Judul dan nilai |
| Teks sekunder | `#626F83` | `#A1AEC3` | Label dan keterangan |
| Garis | `#E5E9EF` | `#344054` | Pembatas dan input |
| Aksen | `#315BCE` | `#A6BCFF` | Tombol dan navigasi aktif |
| Teks pada aksen | `#FFFFFF` | `#162747` | Label tombol utama |
| Aksen lembut | `#EDF2FF` | `#283A5A` | Sorotan dan avatar |
| Hadir / sukses | `#34725B` | `#9ED2B9` | Status berhasil |
| Terlambat | `#B45309` | `#FBBF24` | Status keterlambatan |
| Navy merek | `#192A48` | `#192A48` | Kartu jadwal |

Status selalu disertai teks atau ikon. Radius kantor, jadwal, nama perusahaan, jam, dan metode absensi harus berasal dari data aplikasi; angka contoh pada desain tidak boleh dijadikan nilai tetap produksi. Status yang belum ditentukan, termasuk error dan nonaktif, perlu didefinisikan serta diperiksa kontrasnya pada tahap fondasi.

## 0. Audit dan baseline

- [x] Catat versi Flutter/Dart, target platform aktif, dan cara menjalankan aplikasi lokal.
- [x] Jalankan pemeriksaan awal; pisahkan masalah lama dari regresi implementasi.
- [ ] Ambil screenshot Beranda, Riwayat, Profil, Login, QR, Izin, dan Reimbursement yang dapat diakses.
- [ ] Petakan state Bloc, model respons, dan endpoint yang dipakai setiap layar.
- [ ] Verifikasi field jadwal, zona waktu, status check-in/out, perusahaan, radius, serta metode absensi.
- [ ] Catat informasi desain yang belum tersedia di API; jangan menampilkan data ilustrasi sebagai data asli.
- [ ] Catat perilaku absensi di luar radius, izin lokasi ditolak, QR gagal, dan sesi kedaluwarsa.

**Selesai ketika:** baseline visual, daftar sumber data, dan hambatan menjalankan aplikasi terdokumentasi.

## 1. Fondasi tema dan komponen

- [x] Definisikan `ThemeData` light/dark dan token semantik agar layar tidak memakai warna literal berulang.
- [ ] Tentukan skala tipografi, jarak, radius, garis pembatas, dan ukuran ikon.
- [x] Terapkan pilihan tema; simpan preferensi dan pulihkan saat aplikasi dibuka kembali.
- [ ] Periksa status bar, navigation bar sistem, keyboard, dialog, dan snackbar pada kedua tema.
- [ ] Siapkan tombol utama/sekunder dengan state normal, loading, dan nonaktif.
- [ ] Siapkan input dengan label, validasi, error, dan dukungan keyboard yang tepat.
- [ ] Siapkan kartu jadwal, baris informasi, badge status, avatar fallback, dan header layar.
- [ ] Siapkan tampilan loading, kosong, error dengan aksi coba lagi, dan offline.
- [ ] Periksa area sentuh, kontras, pembesaran teks, serta label aksesibilitas.

**Selesai ketika:** komponen dapat digunakan ulang, tema berubah tanpa restart, dan preferensi tersimpan.

## 2. Navigasi dan Beranda

- [x] Pertahankan tiga tujuan utama: Beranda, Riwayat, dan Profil; gunakan istilah Indonesia secara konsisten.
- [x] Pertahankan state layar saat berpindah tab dan pastikan tombol kembali bekerja sesuai platform.
- [x] Terapkan header merek, nama pengguna, avatar fallback, dan nama perusahaan dari akun aktif.
- [ ] Tampilkan tanggal, zona waktu, dan kartu jadwal kerja dari sumber data yang terverifikasi.
- [x] Tampilkan status lokasi: memuat, izin ditolak, GPS mati, dalam radius, di luar radius, atau gagal.
- [x] Tampilkan jam masuk/pulang asli; bedakan data belum tersedia dengan belum absen.
- [x] Tentukan aksi utama berdasarkan state: Absen masuk, Absen pulang, atau Absensi selesai.
- [x] Tampilkan metode absensi perusahaan yang aktif; sediakan informasi bila metode belum tersedia.
- [x] Hubungkan pintasan Ajukan izin dan Reimbursement ke layar terkait.
- [ ] Verifikasi ketersediaan data Pengajuan terakhir; tampilkan keadaan kosong jika belum ada data.
- [x] Muat ulang data setelah absensi berhasil dan saat pengguna melakukan refresh.
- [x] Tangani akun tanpa perusahaan, perusahaan tanpa jadwal, dan respons API gagal.

**Selesai ketika:** Beranda mengikuti rancangan, menggunakan data asli, dan aksi utama sesuai status absensi.

## 3. Alur absensi dan QR

- [x] Revisi layar pemindaian QR, petunjuk kamera, dan informasi lokasi mengikuti tema baru.
- [ ] Tangani izin kamera/lokasi ditolak, ditolak permanen, serta layanan lokasi mati.
- [x] Tampilkan progres pemeriksaan dan cegah pengiriman berulang saat request berjalan.
- [x] Tangani QR tidak valid/kedaluwarsa, di luar radius, sudah absen, dan kegagalan jaringan sesuai respons server.
- [x] Pastikan status lokal tidak dianggap sebagai bukti absensi sebelum server mengonfirmasi.
- [x] Revisi layar hasil: jenis absensi, tanggal/jam dari respons, status, dan aksi kembali ke Beranda.
- [x] Setelah berhasil, sinkronkan Beranda dan Riwayat tanpa membuat catatan ganda.
- [x] Pertahankan pembatasan absensi wajah sampai verifikasi server tersedia; jangan mengaktifkannya melalui revisi UI.
- [ ] Verifikasi mode manual hanya dapat digunakan ketika diizinkan konfigurasi perusahaan/server.

**Selesai ketika:** alur masuk dan pulang dapat diuji dengan server, termasuk kegagalan dan percobaan ulang.

## 4. Riwayat kehadiran

- [x] Revisi pemilih tanggal dan daftar masuk/pulang dengan navigasi tanggal yang jelas.
- [ ] Tampilkan tanggal terpilih, zona waktu, dan waktu absensi secara konsisten.
- [ ] Verifikasi aturan status tepat waktu/terlambat terhadap jadwal dan toleransi yang berlaku.
- [ ] Terapkan amber pada keterlambatan beserta label durasi bila datanya valid.
- [ ] Tampilkan metode/lokasi hanya bila tersedia pada respons.
- [x] Pertahankan informasi lembur yang sudah tersedia, tanpa mengarang perhitungan baru.
- [x] Bedakan hari libur, tidak ada data, belum absen, loading, dan error berdasarkan data yang tersedia.
- [x] Pastikan tanggal baru selalu memuat data yang sesuai dan tidak menampilkan hasil tanggal sebelumnya.

**Selesai ketika:** pergantian tanggal dan detail kehadiran akurat pada kedua tema.

## 5. Profil dan pengaturan

- [x] Revisi profil: foto/avatar, nama, email, nomor telepon, dan perusahaan aktif.
- [x] Terapkan desain edit profil dan validasi mengikuti kemampuan API yang ada.
- [x] Tangani pemilihan foto, pembatalan, unggah gagal, dan fallback gambar.
- [x] Sediakan pilihan tema dalam Profil dan pulihkan preferensi tersimpan.
- [ ] Periksa keselarasan tema semua layar setelah migrasi Izin/Reimbursement dan QA menyeluruh.
- [x] Rapikan aksi keluar beserta state loading/error; hapus sesi lokal setelah logout berhasil sesuai kontrak autentikasi.
- [x] Periksa tombol kembali dan data profil setelah pembaruan berhasil.

**Selesai ketika:** profil dapat dilihat/diedit, tema tersimpan, dan keluar akun bekerja.

## 6. Login dan sesi

- [x] Revisi splash dan login agar selaras dengan identitas Naltech tanpa jarak kosong berlebihan.
- [x] Gunakan label email/kata sandi, keyboard email, dan tombol tampil/sembunyikan kata sandi.
- [x] Tampilkan validasi dan pesan gagal login yang jelas; cegah submit ganda saat loading.
- [x] Pastikan layout tetap dapat digunakan saat keyboard terbuka dan teks diperbesar.
- [x] Tangani sesi valid, sesi kedaluwarsa, dan akun belum terhubung perusahaan.
- [x] Pastikan kredensial dan token tidak dicetak ke log selama pengujian UI.

**Selesai ketika:** login, pemulihan sesi, dan pengalihan saat sesi kedaluwarsa dapat diuji.

## 7. Izin dan reimbursement

### Izin

- [x] Cocokkan jenis izin, tanggal, keterangan, dan lampiran dengan kontrak API yang ada.
- [x] Revisi formulir, date picker, validasi, serta loading saat pengiriman.
- [x] Tampilkan keberhasilan dan status pengajuan berdasarkan respons server.
- [x] Verifikasi dukungan daftar/detail pengajuan; catat kebutuhan backend bila belum tersedia.

### Reimbursement

- [x] Revisi daftar dan formulir mengikuti field API, termasuk nominal, tanggal, keterangan, dan bukti bila didukung.
- [x] Gunakan format Rupiah dan validasi nominal tanpa mengubah nilai yang dikirim secara keliru.
- [x] Tangani lampiran, pembatalan pemilihan, upload gagal, dan pengiriman ulang.
- [x] Tampilkan status serta feedback berhasil/gagal dari server.

**Selesai ketika:** kedua fitur terhubung ke API yang tersedia, tanpa tombol yang terlihat mengirim tetapi tidak bekerja.

## 8. QA dan kriteria penerimaan

- [x] Jalankan formatter dan `flutter analyze`; dokumentasikan temuan lama yang masih ada.
- [x] Jalankan pengujian yang relevan, termasuk transisi state absensi dan persistensi tema bila diubah.
- [ ] Uji Android pada emulator/perangkat; uji iOS bila menjadi target rilis dan lingkungan tersedia.
- [ ] Periksa ukuran layar kecil, safe area, keyboard, teks panjang, dan pembesaran font.
- [ ] Periksa semua layar/dialog pada light mode dan dark mode, termasuk pemulihan setelah restart.
- [ ] Uji masuk, pulang, sudah absen, lokasi ditolak/di luar radius, QR tidak valid, offline, dan sesi kedaluwarsa.
- [ ] Verifikasi Izin, Reimbursement, edit Profil, serta refresh setelah perubahan data.
- [x] Simpan screenshot hasil dan catatan perbedaan dari rancangan untuk review pengguna.
- [ ] Catat pemeriksaan yang belum dapat dijalankan dan alasannya; jangan menandainya selesai.

**Selesai ketika:** irisan fitur yang dikerjakan lolos pemeriksaan, bukti tersedia, dan hasil UI ditinjau pengguna.

## Urutan review

1. Fondasi tema + Beranda dengan data asli.
2. Pemindaian QR + hasil absensi + Riwayat.
3. Profil + Login + pengaturan tema.
4. Izin + Reimbursement + pemeriksaan menyeluruh.

Setiap irisan harus dapat dicoba sebelum dilanjutkan. Perbaikan API yang diperlukan dicatat sebagai pekerjaan terpisah, dengan kebutuhan field dan perilaku yang jelas. Deployment, publikasi store, dan push Git tidak termasuk checklist implementasi UI ini.

## Cara memperbarui tracking

- Tandai `[x]` hanya setelah kriteria tugas terpenuhi dan hasilnya diperiksa.
- Gunakan status tabel: Belum dimulai, Berjalan, Terhambat, atau Selesai.
- Jika terhambat, tulis alasan dan langkah berikutnya di log; jangan menandai tugas selesai.
- Perbarui tanggal dan tambahkan bukti ringkas, misalnya hasil pemeriksaan, screenshot, atau commit.

| Tanggal | Tahap / tugas | Hasil / bukti | Hambatan / langkah berikutnya |
| --- | --- | --- | --- |
| 19 Oktober 2026 | Perencanaan | Desain mobile ditinjau dan checklist dibuat | Mulai audit dan fondasi tema + Beranda |


## Log irisan M1 — Fondasi tema dan Login — 20 Oktober 2026

- Dimulai atas arahan pengguna setelah review teknis web. [Timeline mobile](mobile-ui-timeline.md) menjadi urutan kerja terkini; Login dikerjakan bersama fondasi agar tema dan validasi dapat diuji tanpa data perusahaan atau akses perangkat.
- Flutter 3.38.9 stable, Dart 3.10.8. Target tersedia menurut Flutter: macOS, Chrome dan iPhone terhubung; tidak ada instalasi/run pada perangkat pengguna. Platform proyek Android/iOS dipertahankan.
- ThemeData light/dark Workspace memakai palet desain, input, tombol Material, dialog/snackbar dan konfigurasi system bar. Preferensi `naltech.theme` dibaca sebelum runApp, dapat diubah dari Login, dan dipulihkan pada controller baru. Pengujian system bar/keyboard native belum dilakukan. Layar lama masih memiliki warna literal dan akan dimigrasikan per modul.
- Login menggunakan label Indonesia, keyboard email, autofill, tampil/sembunyikan kata sandi, validasi, pesan gagal, state loading dan pencegahan request ganda di Bloc. Error transport ditangani; HTTP login diberi timeout 20 detik. Email ditrim, password tidak dimodifikasi. Penyimpanan sesi ditunggu sebelum navigasi dan kegagalan penyimpanan ditampilkan. Radius/QR/metode/otorisasi server tidak diubah.
- Tes counter bawaan gagal pada baseline karena tidak relevan dengan aplikasi absensi. Diganti tes tema dan Login yang bermakna. **6 tes lulus** termasuk 2 tes bukti QR yang sudah ada, persistensi tema, validasi/password toggle/loading/error/request ganda, kegagalan transport serta viewport 320×640, skala teks 1,8 dan inset keyboard 250 px pada widget test.
- `flutter analyze` gagal pada SDK lokal: analysis_server AOT dipanggil melalui runtime yang tidak sesuai. Menjalankan analysis server SDK yang sama melalui `dartaotruntime` dan protokol analyzer berhasil: 133 temuan (9 warning, 124 info), tanpa error dan tanpa diagnostik pada berkas yang diubah pada pemeriksaan tersebut. Warning tersisa berada pada kamera/wajah/scanner lama. SDK/dependency tidak diedit. Dart format serta pemeriksaan whitespace dijalankan.
- Upaya capture screenshot widget sementara tidak selesai dan dihentikan; harness sementara dibuang. Belum ada bukti visual perangkat untuk irisan ini. Tes widget bukan bukti login API nyata, pemulihan sesi kedaluwarsa, izin lokasi/kamera, atau UI semua halaman sudah sesuai tema.
- Berikutnya M2: navigasi Beranda/Riwayat/Profil dan Beranda dengan sumber data asli; splash, profil, alur absensi dan modul lain tetap mengikuti timeline dan status masing-masing.


## Log irisan M2 — Navigasi dan Beranda — 20 Oktober 2026

Implementasi dan rincian pemeriksaan ada pada [timeline M2](mobile-ui-timeline.md#validasi-m2--20-oktober-2026). Beranda memakai data perusahaan/akun/kehadiran terpisah dari Riwayat, status lokasi eksplisit dan satu aksi sesuai state. Jadwal shift pribadi, zona waktu perusahaan dan pengajuan terakhir belum disediakan sebagai data tambahan; tidak ada data ilustrasi produksi.

12 tes lulus; screenshot light/dark berasal dari renderer widget fixture dan sudah diperiksa. Cakupan cek di atas berarti implementasi dan tes lokal terkait tersedia, bukan seluruh skenario perangkat telah lulus. Isi halaman Riwayat/Profil/Izin/Reimbursement masih implementasi lama dan akan dimigrasikan pada tahap masing-masing. M3 alur absensi/QR/hasil adalah tahap berikutnya; uji kamera/lokasi/API nyata tetap menunggu lingkungan uji.

Pemeriksaan statis akhir M2 melalui analysis server SDK dengan dartaotruntime selesai: 109 temuan lama (9 warning, 100 info), tanpa error dan tanpa diagnostik pada Home, navigasi, Cubit baru serta tes M2. Jumlah turun dari baseline M1 karena Home lama diganti. Formatter seluruh berkas irisan M2 tidak menghasilkan perubahan tambahan; diff whitespace bersih.


## Penerapan konsep Beranda yang disetujui — 20 Oktober 2026

Tampilan Beranda M2 direvisi mengikuti mockup baru yang disetujui pengguna. [Timeline dan bukti v2](mobile-ui-timeline.md#penerapan-desain-beranda-revisi--20-oktober-2026) menggantikan screenshot Beranda sebelumnya sebagai acuan visual. Layout lebih ringkas, dengan detail tambahan dibuka saat diperlukan; state absensi/lokasi dan aturan server tetap dipertahankan. Waktu pembaruan berasal dari snapshot yang berhasil dimuat, tanpa waktu contoh produksi.

14 tes lulus. Render Flutter light/dark 390×844 sudah diperiksa; tes layout 320×640 dengan pembesaran teks 1,8 tetap lulus. Analisis alternatif SDK tidak menemukan error atau diagnostik pada berkas perubahan; 109 temuan lama tetap ada. Perangkat nyata, kamera/lokasi dan absensi API end-to-end belum diuji. M3 belum selesai karena pekerjaan dialihkan pengguna ke review dan penerapan desain Beranda ini.


## Log M3 — 20 Oktober 2026

[Implementasi dan bukti M3](mobile-ui-timeline.md#validasi-m3--absensi-manualqr-dan-hasil--20-oktober-2026) tersedia. Scanner, konfirmasi dan hasil memakai Workspace; hasil membaca waktu server, submit tunggal, QR false tidak diteruskan, dan respons scan terlambat dibatalkan. Beranda serta tanggal Riwayat terpilih disinkronkan setelah konfirmasi sukses.

23 tes mobile lulus dengan HTTP/location fixture, tanpa absensi produksi. Render konfirmasi/hasil light/dark diperiksa, termasuk teks 1,8× pada tes hasil. Pemeriksaan statis alternatif: 97 temuan lama, tanpa error atau diagnostik pada inti M3. Checklist implementasi yang ditandai tidak berarti izin kamera/GPS native atau API nyata telah lulus; pengujian Android/iOS dan scan fisik masih belum dilakukan. Isi/desain Riwayat adalah M4 berikutnya. Face tetap tidak tersedia.


## Log M4 — 20 Oktober 2026

Riwayat sudah diselaraskan dengan Workspace dan sumber catatan asli. [Detail dan bukti M4](mobile-ui-timeline.md#validasi-m4--riwayat-kehadiran--20-oktober-2026) mencakup navigasi tanggal/kalender berbahasa Indonesia, pilihan tahun, loading/kosong/error/retry, jam masuk/pulang, detail koordinat yang valid dan nominal lembur. Tidak ada lokasi 0,0 sebagai fallback atau klaim “Sesuai spot” tanpa dasar.

29 tes lulus. Renderer light/dark diperiksa; layout, kalender yang dapat digulir, dan pembatalan diperiksa pada 320×640 dengan teks 1,8×. “Tidak ada catatan” tidak dilabeli hari libur atau alpa. Butir zona waktu perusahaan, tepat waktu/terlambat dan metode historis belum ditandai karena sumbernya belum tersedia; tampilan menjelaskan batas tersebut. Perangkat/API nyata dan pemuatan tiles peta belum diuji. M5 berikutnya adalah Profil, pengaturan dan sesi.

Pemeriksaan statis akhir M4 melalui analysis server SDK/dartaotruntime: 96 temuan lama (6 warning, 90 info), tanpa error dan tanpa diagnostik pada berkas M4. Formatter 8 berkas irisan tidak menghasilkan perubahan tambahan; diff whitespace bersih.


## Log M5 — 20 Oktober 2026

[Detail M5 dan bukti visual](mobile-ui-timeline.md#validasi-m5--profil-pengaturan-dan-sesi--20-oktober-2026) tersedia: Profil/edit/foto memakai Workspace dan API akun sendiri, tema tersimpan, logout terkonfirmasi, serta Splash/401 menangani sesi valid, kedaluwarsa, offline dan akun tanpa perusahaan. Respons request lama tidak menghapus atau menimpa sesi baru; navigasi privat dihapus saat sesi berakhir.

41 tes mobile dan 59 tes backend (769 assertions) lulus; backend memakai SQLite memory. Analisis alternatif SDK mencatat 87 temuan lama, tanpa error/diagnostik pada berkas M5 yang diperiksa. Formatter/Pint/diff whitespace diperiksa. Tiga render Flutter Profil light/dark dan edit dark sudah ditinjau. Checkbox profil/sesi berarti implementasi dan pemeriksaan terisolasi tersedia; galeri native, perangkat dan API nyata belum diuji. Tema seluruh layar menunggu M6/M7. Berikutnya **M6 Izin dan Reimbursement**.


## Log M6 — 20 Oktober 2026

[Implementasi M6 dan delapan bukti visual](mobile-ui-timeline.md#validasi-m6--izin-dan-reimbursement--20-oktober-2026) tersedia. Daftar/detail/form Izin dan Reimbursement mengikuti Workspace, memuat pengajuan akun sendiri, mendukung pagination/refresh/error, lampiran opsional dan pengiriman tunggal. Input Rupiah dinormalisasi tepat hingga dua desimal; API memvalidasi data dan mengunci pemilik/status awal. Izin tetap satu tanggal tanpa pilihan jenis/rentang yang belum disediakan; false ditulis “Belum disetujui”, bukan keputusan penolakan.

53 tes mobile dan 64 tes backend (849 assertions) lulus. Analisis alternatif SDK: 78 temuan lama, tanpa error/diagnostik pada M6; formatter/Pint/whitespace diperiksa. Semua render light/dark sudah ditinjau. Checkbox menunjukkan implementasi dan tes terisolasi tersedia, bukan bukti upload/perangkat/API nyata. Kontrak Izin sekarang membutuhkan index/detail serta record pada respons 201 dari backend lokal yang ikut diperbarui; daftar Reimbursement kini dipaginasi. Belum melakukan deploy atau migrasi database pengguna. Berikutnya **M7 QA mobile menyeluruh**.


## Log M7 — 20 Oktober 2026

[Hasil QA terperinci](mobile-qa-results.md) mencatat 53 tes mobile, 64 tes backend
(849 assertions), dan 33 pemeriksaan HTTP nyata yang lulus menggunakan SQLite/storage
QA terpisah. Android QA APK dibangun dan beberapa alur native berhasil dicapai;
screenshot bukan lagi hanya fixture, dengan asal setiap bukti dijelaskan.

Deskripsi izin iOS, fallback inisialisasi messaging dan ikon tema diperbaiki.
Info.plist/Podfile lolos sintaks; analisis SDK alternatif tetap 78 temuan lama tanpa
error. **Full native integration test belum lulus** dan build iOS belum selesai;
boot/adb/install serta keterbatasan memori/swap menghambat kelanjutan. Checkbox QA
menyeluruh tetap terbuka. Tidak ada klaim pengujian iPhone, QR optik, upload galeri
native, offline/cold start native maupun kesiapan release. Sisa M7 ada di laporan;
proses/server/emulator QA milik agen telah dihentikan tanpa reset data pengguna.

## Pendaftaran wajah — pemeriksaan 21 Oktober 2026

Alur pendaftaran, persetujuan HR, pencabutan, dan preview pencocokan server
dicatat dalam [laporan implementasi wajah](face-enrollment-implementation.md).
Pemeriksaan akhir: 57 tes Flutter lulus, analisis lima file terkait tanpa temuan,
dan `git diff --check` bersih. Screenshot widget light/dark tersedia di laporan;
layar kamera pendaftaran baru belum diuji pada HP nyata. Server QA sementara
telah dihentikan. Migrasi belum diterapkan pada database utama.

Tahap berikutnya adalah validasi liveness pada wajah langsung serta foto, layar,
dan video. Mesin liveness masih eksperimen; integrasi absensi masuk/pulang dan
UI hasil verifikasi menunggu validasi tersebut. Absensi wajah tetap tidak aktif.

Pembaruan sesi berikutnya pada 21 Oktober: halaman kamera pendaftaran berhasil
menghasilkan sampel pada OPPO nyata melalui wrapper QA. [Audit adapter](face-liveness-adapter-audit.md)
memperbaiki selisih crop; 1.005 fixture C++ cocok dan 14 tes Python lulus.
Uji ulang empat capture baru tetap menerima sampel cetak/layar/video. Karena
gerbang anti-spoofing gagal, Face tetap tidak aktif. Foto sementara HP/komputer
dihapus, paket QA dihentikan, dan laporan agregat disimpan. Ini bukan kelulusan
enrollment/absensi end-to-end. Alternatif server sendiri/cloud dicatat pada
keputusan mesin; belum ada unduhan kandidat baru atau panggilan cloud.

Pembaruan PoC MN3 pada 21 Oktober: [adapter baru dan uji perangkat](face-mn3-poc.md)
selesai, 20 tes Python dan enam smoke HTTP lulus. MN3 juga menerima ketiga sampel
spoof awal; inferensi OpenCV dan ONNX Runtime memberikan keputusan sama pada
foto yang sama. Kandidat tetap eksperimen dan Face tidak aktif. Foto relawan
dihapus dari HP/komputer; tidak ada data dikirim ke cloud atau migrasi utama.


## Pengajuan H3 — 21 Oktober 2026

Status kanonis, detail keputusan dan pembatalan izin/lembur/reimbursement telah tersedia; akses lembur melalui menu Jenis pengajuan. [Laporan H3 mobile](hris-h3-mobile.md): 67 tes Flutter lulus, lima file produksi tanpa diagnostik melalui analysis server, 106 tes backend/1.336 assertions, serta 27 pemeriksaan datasource HTTP nyata pada backend sementara. Light/dark diperiksa dengan 12 screenshot widget; bukan pengujian HP baru. Database utama belum dimigrasikan dan belum ada APK baru. H2 tahap 2 dan Face tetap ditunda/tidak tersedia.


## QA native pengajuan H3 — 21 Oktober 2026

[Satu suite pengajuan pada OPPO Android 15 lulus](hris-h3-mobile-device.md): form izin/lembur/reimbursement, keputusan Dashboard terbaca di HP, pembatalan, daftar dimuat ulang, backend terputus/pulih dan 401 kembali ke login. APK QA terpisah; aplikasi utama tidak ditimpa. Regresi urutan lembur tanggal sama: 107 tes backend/1.339 assertions lulus. Cache screenshot dihapus runner saat teardown; log/assertion native disimpan, screenshot widget tidak dilabeli sebagai bukti HP. Bukan kelulusan menyeluruh M7, iOS/release, notifikasi atau biometrik.

## H3 alur bertingkat — 21 Oktober 2026

- [x] Tahap aktif/urutan/peninjau dan riwayat pada detail, serta tahap ringkas pada daftar.
- [x] 69 tes Flutter; fixture widget light/dark dan layar 320×640 skala 1,8; [laporan](hris-h3-workflows.md).
- [ ] Review pengguna, penerapan schema utama dan uji HP/iOS/release untuk perubahan alur bertingkat.

## H4 pemilih jenis cuti — 21 Oktober 2026

- [x] Katalog perusahaan, jenis izin umum/cuti, persyaratan lampiran dan snapshot pada detail terhubung ke API.
- [x] 77 tes Flutter, 25 pemeriksaan HTTP nyata, delapan screenshot widget light/dark dan analisis tujuh file tanpa diagnostik. [Laporan](hris-h4-mobile-leave.md).
- [ ] Review pengguna, penerapan API/schema utama serta uji HP/iOS/release untuk perubahan ini.

## H4 QA HP — 21 Oktober 2026

- [x] OPPO Android 15: satu suite native/11 pemeriksaan, lima tindakan Dashboard dan sembilan screenshot perangkat. [Laporan](hris-h4-mobile-device.md).
- [x] Lampiran wajib, upload/bukti privat, dua tahap, keputusan akhir, kebijakan lama dan pembatalan/list refresh.
- [ ] Galeri native/izin, iOS/release dan penerapan utama. Agen tidak menjalankan perintah commit pada sesi QA ini.

## Pembaruan jadwal efektif H2 — 21 Oktober 2026

[Jadwal efektif dan checkout lintas malam](hris-h2-effective-shifts.md) tersedia dan diuji terisolasi. Dashboard memakai salinan jadwal catatan baru; Beranda mobile memakai tanggal kerja/sesi dari server. Sepuluh pemeriksaan Chrome light/dark desktop/sempit lulus. Migrasi database utama, review pengguna dan QA native perubahan H2 masih terbuka; QA OPPO H4 sebelumnya bukan bukti H2.

## QA shift emulator — 21 Oktober 2026

[Pengujian H2 pada Android 15 emulator lulus](hris-h2-mobile-emulator.md): 1 suite/10 pemeriksaan, 4 screenshot dan verifikasi SQLite terisolasi. Label jadwal efektif diperbaiki; 14 tes Beranda/shift lulus. Migrasi utama sebelumnya telah diterapkan. HP fisik, kamera QR dan akurasi GPS belum diuji untuk H2. Commit tetap menunggu perintah pengguna.

## Penyesuaian alur cuti/saldo — 21 Oktober 2026

[Alur tinjauan, preview saldo dan pembatalan dirapikan](hris-h4-flow-adjustment.md) pada web/Flutter. Regresi: 141 tes backend/1.833 assertions, 85 tes Flutter dan 14 checks Chrome lulus. Tidak ada migrasi/transaksi database utama atau commit. Uji native perubahan ini belum dijalankan; pengemasan pembeli tetap ditunda.
