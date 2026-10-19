# Stabilisasi absensi — 19 Oktober 2026

Pasangan proyek: `flutter_absensi_app-dev` dan `laravel-absensi-backend-master`.

## Perubahan

- Check-in dan check-out diserialkan menggunakan transaksi dan penguncian baris pengguna. Check-in ganda dan check-out ulang mengembalikan HTTP 409 tanpa menimpa waktu sebelumnya.
- Indeks unik `(user_id, date)` menjadi perlindungan tambahan di database.
- Latitude/longitude divalidasi sebagai angka dengan rentang geografis yang benar. Jarak dihitung di server dengan Haversine dan dibandingkan dengan `radius_km` milik perusahaan pengguna. Tidak ada tambahan toleransi 50 meter tersembunyi di Flutter.
- Pengguna, shift, dan QR memiliki `company_id`. Middleware menetapkan konteks perusahaan sebelum route-model binding; query data operasional dibatasi melalui kepemilikan pengguna. Pengguna yang belum memiliki perusahaan ditolak, tanpa fallback ke perusahaan pertama.
- Menu administrasi web memerlukan peran admin/super admin/HRD/supervisor. API profil hanya mengizinkan pengguna membaca dan mengubah profilnya sendiri. Shift perusahaan lain tidak dapat dipilih saat membuat atau mengedit karyawan.
- QR divalidasi ulang pada endpoint check-in/out terhadap perusahaan, tanggal server, dan jenis tindakan. Flutter membawa hasil scan dalam memori, terkait sesi login dan tindakan, dengan batas lokal lima menit.
- Versi dev tidak memiliki log header Authorization yang ditemukan pada versi cmp. Log token FCM dan isi notifikasi di versi dev dihapus.

## Status verifikasi wajah

Temuan histogram berasal dari versi cmp, bukan versi yang dipilih. Versi dev memakai MobileFaceNet/TFLite di perangkat, tetapi API sebelumnya hanya menerima koordinat; hasil verifikasi perangkat dapat dilewati dengan memanggil API langsung.

Endpoint absensi sekarang menolak mode Face dengan HTTP 503. Flutter menampilkan penjelasan sebelum membuka alur wajah dan admin tidak dapat memilih Face sebagai konfigurasi baru. Data pendaftaran wajah lama tidak dihapus. Mode QR dan None tetap tersedia, keduanya memerlukan lokasi dalam radius.

Ini **belum implementasi pengenalan wajah tepercaya**. Pengaktifan kembali membutuhkan layanan verifikasi sisi server, alur enrollment yang terkontrol, dan pengujian identitas/liveness. Boolean atau embedding dari klien saja tidak cukup.

## Migrasi data lama dan penerapan

Migrasi belum dijalankan terhadap database development/operasional. Pengujian menggunakan SQLite dalam memori.

1. Tinjau dan cadangkan database yang akan diperbarui. Periksa waktu aplikasi melalui `APP_TIMEZONE`; tanggal dan jam absensi mengikuti zona waktu server yang dikonfigurasi.
2. Jalankan pemeriksaan duplikasi berikut pada database target:

   ```sql
   SELECT user_id, date, COUNT(*) AS jumlah
   FROM attendances
   GROUP BY user_id, date
   HAVING COUNT(*) > 1;
   ```

3. Migrasi keanggotaan otomatis menghubungkan pengguna/shift/QR lama hanya jika tepat satu perusahaan sudah ada. Bila ada nol atau beberapa perusahaan, tetapkan kepemilikannya secara eksplisit sebelum mengizinkan pengguna masuk. Pengguna baru yang dibuat admin melalui web otomatis mengikuti perusahaan admin.
4. Jalankan `php artisan migrate` dari folder backend target. Jika ada duplikasi absensi, migrasi indeks berhenti dan meminta peninjauan; tidak ada absensi yang dihapus atau digabung otomatis.
5. Untuk konfigurasi Face lama, admin harus memilih QR atau None. Rilis backend dan Flutter bersama karena mode QR sekarang memerlukan `qr_code` saat transaksi akhir. Aplikasi lama yang hanya mengirim koordinat akan ditolak pada mode QR.
6. Uji login, perusahaan yang tampil, check-in, check-out, QR hari ini, riwayat, dan ekspor melalui perangkat staging sebelum rilis.

## Verifikasi

- Backend: 18 tes, 58 assertion berhasil. Mencakup duplikasi, indeks unik, check-out sebelum check-in, check-out ulang, radius, koordinat invalid, perusahaan pengguna, pengguna tanpa perusahaan, pembatasan lintas perusahaan, QR, mode Face yang ditolak, serta migrasi data lama.
- Flutter: 2 tes bukti QR berhasil.
- Pemeriksaan sintaks: 69 file PHP tanpa kesalahan.
- `dart analyze lib/data`: tanpa temuan. Halaman home/result masih memiliki lint informasional lama; tidak ada error analyzer pada pemeriksaan tersebut.
- `flutter analyze` mengalami crash dari launcher analysis server SDK lokal. Pemeriksaan alternatif `dart analyze` berhasil berjalan; SDK tidak diubah.

Perintah uji backend:

```sh
rtk proxy php vendor/bin/phpunit tests/Feature/AttendanceIntegrityTest.php --no-progress
```

Perintah uji Flutter:

```sh
rtk proxy flutter test test/attendance_qr_proof_test.dart --no-pub
rtk proxy dart analyze lib/data
```

## Batas pengujian

Belum ada pengujian perangkat Android/iOS, integrasi kamera/GPS, atau permintaan paralel terhadap MySQL/PostgreSQL. Transaksi diuji secara fungsional pada SQLite; perilaku penguncian produksi masih perlu diuji pada mesin database target.

Validasi radius memeriksa koordinat yang dilaporkan, bukan membuktikan keaslian GPS. Pekerjaan ini juga belum mencakup onboarding perusahaan, paket langganan, audit keamanan menyeluruh, atau pengujian semua file unggahan. Pembatasan query menjadi fondasi pemisahan perusahaan, bukan pernyataan kesiapan SaaS produksi.
