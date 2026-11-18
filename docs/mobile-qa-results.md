# Hasil QA mobile M7

Tanggal pencatatan: 20 Oktober 2026 (Asia/Jakarta).

**Status: QA regresi dan HTTP terisolasi lulus; QA native menyeluruh belum selesai.**
Tidak menjadi persetujuan rilis atau klaim semua perangkat telah lulus.

## Lingkungan dan isolasi

- Flutter/Dart dari SDK lokal proyek; Android emulator API 35, Android 15, arm64.
- Aplikasi diuji melalui salinan sementara dengan application ID
  `com.naltech.attendance.qa`, backend loopback port 8018, SQLite dan storage QA
  terpisah. URL, konfigurasi Firebase/.env, database dan paket aplikasi pengguna
  tidak dipakai sebagai target mutasi QA.
- Widget, route, Bloc/Cubit dan datasource dari aplikasi dipakai dalam salinan QA.
  Entry point QA mengecualikan Firebase messaging. Ini bukan pengujian notifikasi
  maupun build release/signing aplikasi utama.
- Lokasi emulator untuk fixture adalah 37.4219999,-122.0840575 dengan radius 500 m;
  bukan GPS fisik atau bukti berada di kantor. Backend QA memakai Asia/Jakarta;
  emulator QA baru disiapkan pada zona waktu yang sama. Batas zona waktu perusahaan
  pada kontrak aplikasi tetap berlaku sebagaimana M2–M6.
- Harness yang dapat diulang: [panduan](../tools/mobile-qa/README.md). Password QA
  acak hanya disimpan dalam direktori sementara dengan izin 0600. Tidak ada token,
  password aktual atau header autentikasi dalam laporan/screenshot hasil.

## Pemeriksaan yang lulus

| Pemeriksaan | Hasil dan batas |
| --- | --- |
| Regresi mobile | 53 tes lulus, meliputi light/dark, 320×640, teks 1,8×, keyboard, validasi/loading/error, QR, absensi, profil, sesi dan pengajuan memakai fixture/mocks |
| Regresi backend | 64 tes, 849 assertions lulus pada SQLite memory; bukan database pengguna |
| HTTP nyata lokal | 33 pemeriksaan lulus terhadap Laravel melalui socket HTTP dengan SQLite/storage disposable; [hasil JSON](mobile-qa-http-results.json) |
| Android QA debug APK | Berhasil dibangun, termasuk APK harness integration_test; bukan bukti release build aplikasi utama |
| Native login/Home | Login akun QA dan data perusahaan dimuat dari API nyata; Geolocator mendapatkan lokasi emulator dan Home menampilkan dalam radius |
| Native kamera | MobileScannerController mencapai isRunning=true dan halaman scanner dapat dibatalkan; QR optik belum diuji |
| Native pengajuan | Form tanggal/keterangan/nominal, submit Izin tanpa foto dan Reimbursement 150.000,50, kembali dan refresh daftar berhasil melalui API nyata |
| Native Profil/tema | Profil dari API, perubahan nama tersimpan ke backend/cache, pilihan dark tersimpan; tahap ini dilewati sebelum kegagalan integration test |
| Analisis statis | Analysis server SDK/dartaotruntime selesai: 78 temuan lama (6 warning, 72 info), tanpa error atau diagnostik pada main.dart/profile_page.dart yang diperiksa |
| Konfigurasi iOS | plutil Info.plist dan ruby -c Podfile lulus; belum bukti kompilasi/prompt izin iOS |
| Formatter | Dart format, Pint harness PHP, syntax Python dan diff whitespace diperiksa |

HTTP nyata mencakup login salah/benar, restore akun/perusahaan, profil dan foto,
serving foto storage, Izin tanpa/dengan lampiran, daftar/detail/status, Reimbursement
beserta nominal desimal dan foto, penolakan input, check-in/check-out manual, radius,
duplikasi, riwayat, QR salah/benar/jenis aksi, isolasi akun, Face tidak tersedia,
logout dan token yang dicabut. Upload diuji lewat klien HTTP; belum melalui pemilih
foto OS pada emulator/perangkat.

## Perbaikan dari QA

1. Info.plist belum memuat NSCameraUsageDescription,
   NSLocationWhenInUseUsageDescription dan NSPhotoLibraryUsageDescription. Ketiganya
   ditambahkan dengan deskripsi Indonesia sesuai fungsi aplikasi. Geolocator iOS
   diset memakai izin foreground, tanpa menambah kebutuhan Always Location.
   Acuan berasal dari README plugin versi yang terpasang di pub cache.
2. FirebaseMessaging sebelumnya tetap dikonstruksi setelah Firebase.initializeApp
   gagal. Inisialisasi messaging kini berada dalam jalur sukses yang sama dan
   log kegagalan memakai pesan umum. Update FCM token ditunggu agar exception
   jaringannya ikut ditangani oleh catch inisialisasi, bukan future terlepas.
   Pengiriman notifikasi tidak diuji oleh entry point QA.
3. Centang default ChoiceChip menimpa ikon matahari/bulan pada tema terpilih.
   Centang visual dinonaktifkan, sementara state selected dan persistensi tetap.
   Hasil sesudah perbaikan diverifikasi pada render widget terpisah.
4. Ditambahkan harness backend/device/integration yang terisolasi dan menolak
   penggunaan marker fixture yang tidak tersedia. Helper screen/state/tap menolak
   membaca aplikasi lain ketika paket QA tidak foreground.

## Bukti visual dan asalnya

| Bukti | Asal |
| --- | --- |
| [Login](screenshots/mobile-qa/android-login-light.png) | Flutter surface pada Android emulator QA |
| [Home](screenshots/mobile-qa/android-home-light.png) | Android emulator, data API/Geolocator emulator nyata |
| [Scanner](screenshots/mobile-qa/android-camera-preview.png) | Android emulator; surface kamera tidak masuk capture, start controller telah diassert |
| [Izin](screenshots/mobile-qa/android-permission-live.png) | Android emulator sesudah submit HTTP sukses |
| [Reimbursement](screenshots/mobile-qa/android-reimbursement-live.png) | Android emulator sesudah submit HTTP sukses |
| [Profil dark](screenshots/mobile-qa/android-profile-dark.png) | Android emulator, sebelum perbaikan ikon tema |
| [Ikon tema setelah perbaikan](screenshots/mobile-qa/profile-dark-chip-fix-widget.png) | Render widget Flutter fixture 390×1100, Arial/Material Icons review; bukan screenshot perangkat |

Semua bukti di atas sudah diperiksa secara visual. Capture widget sementara dihapus
setelah verifikasi. Screenshot native berasal dari tahap yang benar-benar dicapai;
tidak ada screenshot check-in, checkout atau logout native yang diklaim berhasil.

## Percobaan yang belum lulus

- Integration test Android pertama mencapai login, lokasi, start/cancel kamera,
  pengajuan dan edit Profil, lalu gagal menunggu halaman konfirmasi absensi.
  Harness diperbarui agar menunggu canAttend/lokasi siap sebelum menekan aksi;
  akar masalah alur native belum dinyatakan tuntas karena pengulangan penuh belum
  berhasil. Tidak menganggap kegagalan harness sebagai absensi berhasil.
- Pemasangan APK sempat gagal karena ruang emulator lama menipis. Paket QA
  terpisah dipasang ulang. Lingkungan tersebut kemudian terhenti dan tidak lagi
  tersedia saat pekerjaan dilanjutkan.
- Emulator QA baru dibuat pada direktori disposable. Boot/adb/install sangat
  lambat, sempat offline, dan pengulangan full integration test belum berjalan
  sampai selesai. Debug APK tetap berhasil dikompilasi.
- Build iOS simulator --no-codesign dicoba pada salinan sementara. CocoaPods sudah
  mulai mengambil dependency, tetapi dihentikan sebelum selesai; tidak ada klaim
  build iOS lulus atau ketidakcocokan dependency yang sudah terbukti.
- Saat diagnosis, mesin memiliki RAM 8 GB dan swap sekitar 16,7 GB terpakai.
  Proses QA berat dihentikan untuk mengurangi beban. Emulator/AVD QA yang dibuat
  sendiri dan server backend QA telah dihentikan/dibersihkan. Tidak menghapus
  aplikasi, AVD, database atau dependency pengguna.

## Sisa M7, berurutan

- [ ] Ulangi full native integration test pada lingkungan dengan memori/ruang cukup:
  konfirmasi manual masuk/pulang, hasil, Riwayat, 401 saat halaman privat terbuka,
  login kembali dan logout; pastikan keseluruhan test benar-benar lulus.
- [ ] Uji cold start dengan sesi valid, tema tersimpan, server tidak terjangkau dan
  pemulihan koneksi melalui aplikasi native, bukan hanya widget/mocks.
- [ ] Uji pemilih foto OS: pilih/ganti/hapus/batal, izin ditolak, upload dari UI dan
  pemulihan lost-data setelah proses Android dihentikan.
- [ ] Uji QR optik/camera lifecycle dan izin ditolak/permanen; verifikasi GPS/radius
  pada perangkat fisik. Face tetap tidak tersedia.
- [ ] Selesaikan dependency/build iOS simulator, kemudian uji izin/prompt, keyboard,
  safe area dan alur aplikasi pada iOS. Belum memasang aplikasi pada iPhone pengguna.
- [ ] Uji release/signing, notifikasi Firebase dan matriks perangkat sebelum rilis.

Tidak ada deploy, push, migrasi database pengguna atau publikasi store dalam M7 ini.
