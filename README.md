# Smart Attendance — Monorepo

Aplikasi absensi karyawan: Flutter untuk mobile, Laravel untuk API dan dashboard admin.

## Struktur

```text
absensi-monorepo/
├── apps/
│   ├── mobile/     # Asal: flutter_absensi_app-dev
│   └── backend/    # Asal: laravel-absensi-backend-master
├── Makefile
└── README.md
```

Satu repositori Git di root. Kedua aplikasi menggunakan dependency manager masing-masing; tidak memakai submodule atau repositori Git bersarang. Riwayat commit kedua sumber diimpor sebagai merge commit, dan perubahan lokal disimpan sesudahnya. Folder sumber tidak diubah atau dihapus.

## Persiapan

Prasyarat: PHP 8.2+, Composer, database yang sesuai konfigurasi Laravel, Flutter/Dart, serta Node.js/npm bila mengembangkan aset dashboard dengan Vite.

```sh
make backend-install
make mobile-install
```

Environment lokal dari folder sumber disalin bila tersedia dan diabaikan Git. Untuk checkout baru, salin `apps/backend/.env.example` menjadi `apps/backend/.env`, sesuaikan koneksi database, lalu jalankan `php artisan key:generate` dari folder backend. Buat `apps/mobile/.env` sesuai kebutuhan aplikasi; nilainya tidak boleh dimasukkan ke Git.

Migrasi database **tidak dijalankan otomatis** saat penyusunan monorepo. Tinjau [panduan stabilisasi](apps/backend/docs/ATTENDANCE_HARDENING.md) sebelum menjalankannya. Panduan tersebut menjelaskan penanganan absensi ganda serta keanggotaan perusahaan lama.

## Menjalankan

Di terminal pertama:

```sh
make backend-serve
```

Di terminal kedua:

```sh
make mobile-run
```

Atur URL API mobile pada `apps/mobile/lib/core/constants/variables.dart` sesuai perangkat dan port backend. Android emulator menggunakan `http://10.0.2.2:8000`, iOS simulator menggunakan `http://localhost:8000`, dan perangkat fisik menggunakan alamat LAN komputer pengembang. Periksa `apps/mobile/.env` bila konfigurasi lokal lain memakainya.

Untuk aset Vite dashboard:

```sh
make web-install
make web-dev
```

## Pengujian

```sh
make backend-test
make mobile-test
make mobile-analyze
```

Tes backend yang dipilih menggunakan SQLite dalam memori, bukan database operasional. Perintah ini menjalankan tes stabilisasi yang ditambahkan pada pekerjaan sebelumnya. Pengujian kamera/GPS di perangkat tetap dilakukan terpisah.

## Status fitur

Check-in/out dilindungi dari duplikasi, lokasi divalidasi di server, dan query operasional dibatasi menurut perusahaan pengguna. QR diperiksa kembali saat penyimpanan absensi. Mode Face sementara diblokir sampai tersedia verifikasi identitas sisi server yang tepercaya. Lihat panduan stabilisasi untuk batas pengujian dan langkah penerapan.

## Git

Jalankan perintah Git dari root ini. Branch utama bernama `main`. Remote GitHub belum dikonfigurasi dan tidak ada kode yang di-push. Riwayat sumber dapat ditelusuri melalui parent merge commit impor.

Path pada dokumentasi lama di dalam aplikasi mungkin masih memakai nama folder sumber. Gunakan `apps/mobile` dan `apps/backend` sebagai lokasi aktif.
