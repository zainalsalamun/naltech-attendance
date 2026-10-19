# Panduan Teknis Implementasi

Terakhir diperbarui: 19 Oktober 2026.

Dokumen ini mencatat cara kerja teknis proyek. Ini bukan paket skill Codex atau klaim bahwa plugin tertentu sudah diinstal. Prioritas saat ini: Dashboard web, kemudian mobile.

## 1. UI Laravel dan Blade

Area awal yang diperiksa:

- `apps/backend/resources/views/pages/dashboard.blade.php`
- `apps/backend/resources/views/layouts/app.blade.php`
- `apps/backend/resources/views/components/header.blade.php`
- `apps/backend/resources/views/components/sidebar.blade.php`
- Route, controller, query, dan aset yang digunakan oleh halaman tersebut.

Petakan sumber data sebelum membuat komponen. Gunakan token semantik dari [design.md](design.md), scoped CSS/komponen Blade sesuai struktur aplikasi, dan satu sistem ikon. Periksa apakah aset dilayani langsung atau melalui Vite sebelum mengubah pipeline.

Hindari query di view. Hitung ringkasan melalui lapisan backend yang sesuai, batasi berdasarkan perusahaan aktif, dan jaga konsistensi tanggal/shift dengan tabel. Gunakan pagination untuk hasil besar. Pertahankan CSRF dan otorisasi pada mutasi.

## 2. Tema dan aksesibilitas

Simpan preferensi Light/Dark dan terapkan sebelum render sejauh memungkinkan. Periksa panel, tabel, input, dropdown, dialog, dan feedback pada kedua tema. Pastikan fokus terlihat, label kontrol tersedia, status memiliki teks, serta layout dapat digunakan pada layar sempit dan teks panjang.

## 3. Integrasi data

Konfirmasi aturan karyawan terjadwal, izin, keterlambatan, zona waktu, dan shift lintas hari sebelum menghitung angka. Catat field yang belum tersedia. Jangan menyamakan data gagal dimuat dengan nol, atau menampilkan informasi real-time tanpa mekanisme pembaruan yang sesuai.

## 4. Pemeriksaan backend dan aset

Jalankan dari `apps/backend`, sesuai bagian yang berubah:

```sh
rtk proxy php artisan test
rtk proxy php artisan view:cache
rtk proxy php artisan view:clear
rtk proxy npm run build
```

`view:cache` memeriksa kompilasi Blade; bersihkan kembali cache setelah pemeriksaan lokal bila diperlukan. Build aset relevan bila menggunakan pipeline Vite yang sudah dikonfigurasi. Periksa prasyarat dan lingkungan test sebelum menjalankan pengujian database; gunakan database pengujian terisolasi. Jangan menjalankan migrasi/reset pada database pengguna untuk menyiapkan tes.

Tambahkan tes bermakna jika aturan agregasi, filter, atau otorisasi berubah. Untuk perubahan visual sederhana, utamakan pemeriksaan tampilan dan interaksi. Catat hasil aktual, termasuk kegagalan baseline atau alat yang belum tersedia.

## 5. Flutter — setelah review Dashboard

Gunakan `ThemeData` light/dark, komponen yang dapat dipakai ulang, dan state Bloc yang sudah ada. Petakan respons API ke state loading, sukses, kosong, dan error. Pastikan status absensi dikonfirmasi server serta mencegah submit berulang saat loading.

Perintah dari `apps/mobile` setelah lingkungan tersedia:

```sh
rtk proxy flutter analyze
rtk proxy flutter test
```

Format hanya file Dart yang berubah. Uji perangkat/emulator yang tersedia, tema, izin kamera/lokasi, keyboard, serta alur masuk/pulang. Bedakan preview desain dengan hasil aplikasi yang benar-benar berjalan.

## 6. Bukti dan tracking

Perbarui checklist terkait dengan status, hasil pemeriksaan, screenshot atau commit bila tersedia. Review Dashboard mendahului implementasi mobile. Commit, push, publikasi, dan deployment mengikuti instruksi pengguna pada pekerjaan terkait.
