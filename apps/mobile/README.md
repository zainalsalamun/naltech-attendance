# 📱 Smart Attendance System

Aplikasi Presensi cerdas berbasis Flutter yang menggunakan teknologi Face Detection (Pengenalan Wajah) dan validasi titik lokasi Radius (GPS) untuk mendata kehadiran karyawan secara *real-time* dan mencegah kecurangan.

## ✨ Fitur Utama

- **Face ID Attendance**: Mendata absen Datang dan Pulang menggunakan deteksi wajah di kamera secara mandiri (Selfie).
- **Verifikasi Lokasi Geofencing**: Validasi ketat membandingkan koordinat perangkat *(device location)* dengan batas radius kantor yang disetel di server pusat. Memblokir absen bila terdeteksi lokasi palsu *(Mock Location)*.
- **Smart Dashboard**: Panel interaktif *Premium UI* yang menampilkan Waktu Server *real-time* (Stream) dan Jadwal Shift Karyawan.
- **Reimbursement System**: Formulir digital terintegrasi untuk mengajukan *reimbursement* / klaim biaya kepada perusahaan lengkap dengan foto bukti transaksi.
- **Menu Cepat**: Pengajuan izin kerja/tidak masuk, catatan tugas, absen lembur langsung di layar utama dengan animasi halus.
- **Update Profile**: Pengaturan khusus karyawan seperti foto profil, sinkronisasi *email* dan pelacakan riwayat versi aplikasi.

---

## 🚀 Getting Started (Persiapan Awal)

Untuk menjalankan proyek ini di *local machine* (komputer) Anda, pastikan Anda telah memasang Flutter SDK, lalu jalankan perintah berikut:

1. Tarik repository proyek *(Clone)* dan masuk ke direktori folder.
2. Unduh semua paket/dependensi yang dibutuhkan:
```bash
flutter pub get
```

3. **Catatan Khusus**: Proyek ini menggunakan `build_runner` untuk proses generator (seperti kode `freezed` model atau aset ikon SVG):
```bash
flutter pub run build_runner build --delete-conflicting-outputs
```

4. **Generate Launcher Icons**: Perintah ini digunakan untuk memperbarui ikon aplikasi di Android & iOS menggunakan aset yang baru:
```bash
flutter pub run flutter_launcher_icons:main
```

5. Jalankan aplikasi di emulator atau perangkat fisik (pastikan mode Debugging USB aktif untuk tes kamera):
```bash
flutter run
```

---

## 📦 Cara Build Release / Build APK

Saat tahap *development* selesai dan Anda butuh mendistribusikannya ke pengguna langsung sebagai aplikasi `.apk` (teroptimasi, lancar, no-debug banner), gunakan perintah di bawah ini:

### Membangun Berkas APK (Standar/Universal)
Jalankan perintah flutter ini di root terminal proyek Anda untuk membuat 1 APK gabungan (ukurannya besar, mendukung semua perangkat):
```bash
flutter build apk --release
```
🌟 **Hasil Output:** 👉 `build/app/outputs/apk/release/SmartAttendance-v1.0.0.apk`

### Membangun Berkas APK per Arsitektur (armeabi-v7a / arm64 - Jauh Lebih Ringan)
Jika Anda ingin ukuran APK *(file size)* yang jauh lebih kecil dan dioptimalkan hanya untuk tipe memori *chipset* HP modern (**arm**), gunakan opsi pemecah (split) ini:
```bash
flutter build apk --release --split-per-abi
```
🌟 **Hasil Output:** Perintah ini akan melahirkan 3 file APK terpisah di dalam folder 👉 `build/app/outputs/flutter-apk/`.
*(Tip Pintar: Anda cukup mendistribusikan file `app-arm64-v8a-release.apk` atau `app-armeabi-v7a-release.apk` ke pengguna. Ukurannya bisa menyusut drastis dari 100MB+ menjadi hanya puluhan MB saja!)*

*Jika ingin langsung dipasang (install) lewat kabel kabel data ke HP yang sedang menyolok:*
```bash
flutter install
```

### Membangun Android App Bundle (AAB untuk Google Play Store)

Format `.aab` adalah format standar yang wajib digunakan jika Anda hendak mengunggah aplikasi ke **Google Play Store**. Format ini akan memampatkan ukuran aplikasi secara otomatis saat pengguna mengunduhnya di Play Store:
```bash
flutter build appbundle --release
```
🌟 **Hasil Output:** 👉 `build/app/outputs/bundle/release/app-release.aab`

---

## 🛠 Troubleshooting (Masalah Umum)

Jika menjumpai Error aneh atau *Crash* tiba-tiba saat mencoba mem-build (misalnya gagal kompilasi R8/ProGuard):
```bash
# Untuk membersihkan cache mesin build Android
flutter clean
flutter pub get

# Untuk melihat Log error secara lengkap jika build apk terhambat
cd android
./gradlew assembleRelease --stacktrace
```

> **Catatan Debug**: Karena proyek ini sangat bergantung pada izin Kamera dan GPS Lokasi, pengujian di perangkat asli/fisik *sangat disarankan* daripada menggunakan Emulator Android murni.

---

## 📖 Panduan Penggunaan
Untuk panduan lengkap cara menggunakan aplikasi bagi karyawan (User Manual), silakan baca:
👉 **[USER_GUIDE.md](./USER_GUIDE.md)**
