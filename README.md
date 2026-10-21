# Naltech Attendance

Aplikasi absensi karyawan: Flutter untuk mobile, Laravel untuk API dan dashboard admin.

Produk Naltech Studio · https://studio.naltech.web.id/

## Struktur

```text
naltech-attendance/
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

Konfigurasi Firebase juga bersifat lokal. Sebelum menjalankan mobile dari checkout baru, jalankan `flutterfire configure` di `apps/mobile` dengan proyek Firebase milik lingkungan tersebut. Perintah ini menyiapkan `lib/firebase_options.dart` serta konfigurasi platform yang dipilih, termasuk `android/app/google-services.json` dan `ios/Runner/GoogleService-Info.plist`. Untuk CI, sediakan file konfigurasi melalui pengaturan rahasia pipeline. File yang sudah ada pada komputer pengembang tetap dipertahankan.

Library CSS/JavaScript di `apps/backend/public/library` adalah aset dashboard yang sengaja disimpan dalam repositori. Folder `vendor` atau `build` di dalam source library tidak diperlakukan sebagai dependency PHP atau hasil build aplikasi. Salinan `node_modules` dan arsip ZIP bawaan library tidak disimpan.

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

Pendaftaran wajah dengan peninjauan HR/admin, referensi privat, penggantian dan pencabutan tersedia pada kode baru. Matching YuNet/SFace serta diagnostik anti-spoofing NCNN masih PoC dan tidak mengotorisasi absensi. Lihat [implementasi dan hasil uji](docs/face-enrollment-implementation.md), termasuk migrasi yang perlu diterapkan pada lingkungan pilihan operator dan batas pengujian native.

## Pengembangan HRIS inti

[Audit 21 Oktober 2026](docs/hris-core-audit.md) memetakan fitur aktual dan gap
akses, lifecycle karyawan, cuti/saldo, approval serta rekap. Lihat
[timeline HRIS inti](docs/hris-core-timeline.md) untuk milestone berikutnya.
Audit ini tidak berarti modul tersebut sudah diimplementasikan atau paket sudah
siap operasional. Liveness sesi/video ditunda; Face tetap tidak aktif.

[Implementasi H0](docs/hris-h0-implementation.md) memperbaiki akses API, CSV dan
upload lampiran privat. Kontrak attachment JSON berubah ke `attachment_url`;
mobile disesuaikan. File legacy utama belum dipindahkan: ikuti langkah command
dan maintenance pada laporan sebelum menyatakan seluruh lampiran lama privat.

## Git

Jalankan perintah Git dari root ini. Branch kerja bernama `naltech`. Remote GitHub belum dikonfigurasi dan tidak ada kode yang di-push. Riwayat sumber dapat ditelusuri melalui parent merge commit impor.

Path pada dokumentasi lama di dalam aplikasi mungkin masih memakai nama folder sumber. Gunakan `apps/mobile` dan `apps/backend` sebagai lokasi aktif.

- [Lifecycle karyawan H1 — implementasi, pemeriksaan dan batas penerapan](docs/hris-h1-implementation.md)

- [Unit organisasi dan hubungan atasan H1](docs/hris-h1-organization.md)

- [H2 tahap 1 — zona waktu dan kalender kerja](docs/hris-h2-calendar.md)

- [H3 tahap 1 — approval izin, lembur dan reimbursement](docs/hris-h3-approval.md)

- [H3 mobile — status, detail keputusan dan pembatalan](docs/hris-h3-mobile.md)

- [QA pengajuan H3 pada HP Android nyata](docs/hris-h3-mobile-device.md)

- [H3 alur persetujuan bertingkat: konfigurasi Dashboard dan tahap/riwayat mobile](docs/hris-h3-workflows.md).

- [H4 jenis dan kebijakan cuti: Dashboard, snapshot pengajuan dan approval H3](docs/hris-h4-leave-types.md).

- [H4 mobile: pemilih jenis cuti, persyaratan lampiran dan kebijakan historis](docs/hris-h4-mobile-leave.md).
