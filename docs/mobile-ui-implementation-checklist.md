# Tracking Implementasi UI Mobile — Naltech Attendance

Terakhir diperbarui: 19 Oktober 2026

## Tujuan dan status

Menerapkan rancangan mobile yang sudah disetujui ke `apps/mobile`, dengan identitas visual yang selaras dengan Dashboard Workspace. Implementasi dimulai dari fondasi tema dan Beranda, dilanjutkan layar lain setelah hasil awal dapat dicoba.

Status saat dokumen dibuat: **desain awal disetujui; implementasi UI Flutter belum dimulai**. Preview berisi data ilustrasi, bukan bukti fitur telah terhubung atau lolos pengujian.

| Tahap | Prioritas | Status | Ketergantungan |
| --- | --- | --- | --- |
| 0. Audit dan baseline | P0 | Belum dimulai | — |
| 1. Fondasi tema dan komponen | P0 | Belum dimulai | Tahap 0 |
| 2. Navigasi dan Beranda | P0 | Belum dimulai | Tahap 1 |
| 3. Alur absensi dan QR | P0 | Belum dimulai | Tahap 2 |
| 4. Riwayat kehadiran | P1 | Belum dimulai | Tahap 1–3 |
| 5. Profil dan pengaturan | P1 | Belum dimulai | Tahap 1–2 |
| 6. Login dan sesi | P1 | Belum dimulai | Tahap 1 |
| 7. Izin dan reimbursement | P1 | Belum dimulai | Tahap 1–2, verifikasi API |
| 8. QA dan dokumentasi hasil | P0 | Belum dimulai | Seluruh tahap terkait |

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
| Teks sekunder | `#69768A` | `#A1AEC3` | Label dan keterangan |
| Garis | `#E5E9EF` | `#344054` | Pembatas dan input |
| Aksen | `#315BCE` | `#A6BCFF` | Tombol dan navigasi aktif |
| Teks pada aksen | `#FFFFFF` | `#162747` | Label tombol utama |
| Aksen lembut | `#EDF2FF` | `#283A5A` | Sorotan dan avatar |
| Hadir / sukses | `#34725B` | `#9ED2B9` | Status berhasil |
| Terlambat | `#B45309` | `#FBBF24` | Status keterlambatan |
| Navy merek | `#192A48` | `#192A48` | Kartu jadwal |

Status selalu disertai teks atau ikon. Radius kantor, jadwal, nama perusahaan, jam, dan metode absensi harus berasal dari data aplikasi; angka contoh pada desain tidak boleh dijadikan nilai tetap produksi. Status yang belum ditentukan, termasuk error dan nonaktif, perlu didefinisikan serta diperiksa kontrasnya pada tahap fondasi.

## 0. Audit dan baseline

- [ ] Catat versi Flutter/Dart, target platform aktif, dan cara menjalankan aplikasi lokal.
- [ ] Jalankan pemeriksaan awal; pisahkan masalah lama dari regresi implementasi.
- [ ] Ambil screenshot Beranda, Riwayat, Profil, Login, QR, Izin, dan Reimbursement yang dapat diakses.
- [ ] Petakan state Bloc, model respons, dan endpoint yang dipakai setiap layar.
- [ ] Verifikasi field jadwal, zona waktu, status check-in/out, perusahaan, radius, serta metode absensi.
- [ ] Catat informasi desain yang belum tersedia di API; jangan menampilkan data ilustrasi sebagai data asli.
- [ ] Catat perilaku absensi di luar radius, izin lokasi ditolak, QR gagal, dan sesi kedaluwarsa.

**Selesai ketika:** baseline visual, daftar sumber data, dan hambatan menjalankan aplikasi terdokumentasi.

## 1. Fondasi tema dan komponen

- [ ] Definisikan `ThemeData` light/dark dan token semantik agar layar tidak memakai warna literal berulang.
- [ ] Tentukan skala tipografi, jarak, radius, garis pembatas, dan ukuran ikon.
- [ ] Terapkan pilihan tema; simpan preferensi dan pulihkan saat aplikasi dibuka kembali.
- [ ] Periksa status bar, navigation bar sistem, keyboard, dialog, dan snackbar pada kedua tema.
- [ ] Siapkan tombol utama/sekunder dengan state normal, loading, dan nonaktif.
- [ ] Siapkan input dengan label, validasi, error, dan dukungan keyboard yang tepat.
- [ ] Siapkan kartu jadwal, baris informasi, badge status, avatar fallback, dan header layar.
- [ ] Siapkan tampilan loading, kosong, error dengan aksi coba lagi, dan offline.
- [ ] Periksa area sentuh, kontras, pembesaran teks, serta label aksesibilitas.

**Selesai ketika:** komponen dapat digunakan ulang, tema berubah tanpa restart, dan preferensi tersimpan.

## 2. Navigasi dan Beranda

- [ ] Pertahankan tiga tujuan utama: Beranda, Riwayat, dan Profil; gunakan istilah Indonesia secara konsisten.
- [ ] Pertahankan state layar saat berpindah tab dan pastikan tombol kembali bekerja sesuai platform.
- [ ] Terapkan header merek, nama pengguna, avatar fallback, dan nama perusahaan dari akun aktif.
- [ ] Tampilkan tanggal, zona waktu, dan kartu jadwal kerja dari sumber data yang terverifikasi.
- [ ] Tampilkan status lokasi: memuat, izin ditolak, GPS mati, dalam radius, di luar radius, atau gagal.
- [ ] Tampilkan jam masuk/pulang asli; bedakan data belum tersedia dengan belum absen.
- [ ] Tentukan aksi utama berdasarkan state: Absen masuk, Absen pulang, atau Absensi selesai.
- [ ] Tampilkan metode absensi perusahaan yang aktif; sediakan informasi bila metode belum tersedia.
- [ ] Hubungkan pintasan Ajukan izin dan Reimbursement ke layar terkait.
- [ ] Verifikasi ketersediaan data Pengajuan terakhir; tampilkan keadaan kosong jika belum ada data.
- [ ] Muat ulang data setelah absensi berhasil dan saat pengguna melakukan refresh.
- [ ] Tangani akun tanpa perusahaan, perusahaan tanpa jadwal, dan respons API gagal.

**Selesai ketika:** Beranda mengikuti rancangan, menggunakan data asli, dan aksi utama sesuai status absensi.

## 3. Alur absensi dan QR

- [ ] Revisi layar pemindaian QR, petunjuk kamera, dan informasi lokasi mengikuti tema baru.
- [ ] Tangani izin kamera/lokasi ditolak, ditolak permanen, serta layanan lokasi mati.
- [ ] Tampilkan progres pemeriksaan dan cegah pengiriman berulang saat request berjalan.
- [ ] Tangani QR tidak valid/kedaluwarsa, di luar radius, sudah absen, dan kegagalan jaringan sesuai respons server.
- [ ] Pastikan status lokal tidak dianggap sebagai bukti absensi sebelum server mengonfirmasi.
- [ ] Revisi layar hasil: jenis absensi, tanggal/jam dari respons, status, dan aksi kembali ke Beranda.
- [ ] Setelah berhasil, sinkronkan Beranda dan Riwayat tanpa membuat catatan ganda.
- [ ] Pertahankan pembatasan absensi wajah sampai verifikasi server tersedia; jangan mengaktifkannya melalui revisi UI.
- [ ] Verifikasi mode manual hanya dapat digunakan ketika diizinkan konfigurasi perusahaan/server.

**Selesai ketika:** alur masuk dan pulang dapat diuji dengan server, termasuk kegagalan dan percobaan ulang.

## 4. Riwayat kehadiran

- [ ] Revisi pemilih tanggal dan daftar masuk/pulang dengan navigasi tanggal yang jelas.
- [ ] Tampilkan tanggal terpilih, zona waktu, dan waktu absensi secara konsisten.
- [ ] Verifikasi aturan status tepat waktu/terlambat terhadap jadwal dan toleransi yang berlaku.
- [ ] Terapkan amber pada keterlambatan beserta label durasi bila datanya valid.
- [ ] Tampilkan metode/lokasi hanya bila tersedia pada respons.
- [ ] Pertahankan informasi lembur yang sudah tersedia, tanpa mengarang perhitungan baru.
- [ ] Bedakan hari libur, tidak ada data, belum absen, loading, dan error berdasarkan data yang tersedia.
- [ ] Pastikan tanggal baru selalu memuat data yang sesuai dan tidak menampilkan hasil tanggal sebelumnya.

**Selesai ketika:** pergantian tanggal dan detail kehadiran akurat pada kedua tema.

## 5. Profil dan pengaturan

- [ ] Revisi profil: foto/avatar, nama, email, nomor telepon, dan perusahaan aktif.
- [ ] Terapkan desain edit profil dan validasi mengikuti kemampuan API yang ada.
- [ ] Tangani pemilihan foto, pembatalan, unggah gagal, dan fallback gambar.
- [ ] Sediakan pilihan tema dan pastikan semua layar mengikuti pilihan yang sama.
- [ ] Rapikan aksi keluar beserta state loading/error; hapus sesi lokal setelah logout berhasil sesuai kontrak autentikasi.
- [ ] Periksa tombol kembali dan data profil setelah pembaruan berhasil.

**Selesai ketika:** profil dapat dilihat/diedit, tema tersimpan, dan keluar akun bekerja.

## 6. Login dan sesi

- [ ] Revisi splash dan login agar selaras dengan identitas Naltech tanpa jarak kosong berlebihan.
- [ ] Gunakan label email/kata sandi, keyboard email, dan tombol tampil/sembunyikan kata sandi.
- [ ] Tampilkan validasi dan pesan gagal login yang jelas; cegah submit ganda saat loading.
- [ ] Pastikan layout tetap dapat digunakan saat keyboard terbuka dan teks diperbesar.
- [ ] Tangani sesi valid, sesi kedaluwarsa, dan akun belum terhubung perusahaan.
- [ ] Pastikan kredensial dan token tidak dicetak ke log selama pengujian UI.

**Selesai ketika:** login, pemulihan sesi, dan pengalihan saat sesi kedaluwarsa dapat diuji.

## 7. Izin dan reimbursement

### Izin

- [ ] Cocokkan jenis izin, tanggal, keterangan, dan lampiran dengan kontrak API yang ada.
- [ ] Revisi formulir, date picker, validasi, serta loading saat pengiriman.
- [ ] Tampilkan keberhasilan dan status pengajuan berdasarkan respons server.
- [ ] Verifikasi dukungan daftar/detail pengajuan; catat kebutuhan backend bila belum tersedia.

### Reimbursement

- [ ] Revisi daftar dan formulir mengikuti field API, termasuk nominal, tanggal, keterangan, dan bukti bila didukung.
- [ ] Gunakan format Rupiah dan validasi nominal tanpa mengubah nilai yang dikirim secara keliru.
- [ ] Tangani lampiran, pembatalan pemilihan, upload gagal, dan pengiriman ulang.
- [ ] Tampilkan status serta feedback berhasil/gagal dari server.

**Selesai ketika:** kedua fitur terhubung ke API yang tersedia, tanpa tombol yang terlihat mengirim tetapi tidak bekerja.

## 8. QA dan kriteria penerimaan

- [ ] Jalankan formatter dan `flutter analyze`; dokumentasikan temuan lama yang masih ada.
- [ ] Jalankan pengujian yang relevan, termasuk transisi state absensi dan persistensi tema bila diubah.
- [ ] Uji Android pada emulator/perangkat; uji iOS bila menjadi target rilis dan lingkungan tersedia.
- [ ] Periksa ukuran layar kecil, safe area, keyboard, teks panjang, dan pembesaran font.
- [ ] Periksa semua layar/dialog pada light mode dan dark mode, termasuk pemulihan setelah restart.
- [ ] Uji masuk, pulang, sudah absen, lokasi ditolak/di luar radius, QR tidak valid, offline, dan sesi kedaluwarsa.
- [ ] Verifikasi Izin, Reimbursement, edit Profil, serta refresh setelah perubahan data.
- [ ] Simpan screenshot hasil dan catatan perbedaan dari rancangan untuk review pengguna.
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
