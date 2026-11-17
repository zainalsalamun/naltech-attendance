# H4 — Uji cuti pada HP Android

Diperiksa 21 Oktober 2026 pada **OPPO CPH2591, Android 15**, lewat USB. Satu suite integration test native lulus, dengan 11 pemeriksaan alur dan lima tindakan terverifikasi melalui Dashboard web. Pengujian memakai aplikasi **Naltech Leave QA**, paket `com.naltech.attendance.leaveqa`, mode debug, source produksi yang disalin ke checkout disposable. Database, storage, akun dan kebijakan memakai fixture sintetis terisolasi. Database utama tidak dimigrasikan; agen tidak menjalankan perintah commit sesuai arahan pengguna.

## Hasil aktual

1. Login melalui UI HP dengan akun fixture, katalog dua jenis aktif dimuat dari API perusahaan akun.
2. Jenis pertama mewajibkan lampiran: percobaan kirim tanpa lampiran ditahan di form HP. Setelah gambar sintetis dipilih, form mengirim file ke backend dan bukti dapat diakses melalui endpoint privat. Jenis kedua berhasil dikirim tanpa lampiran.
3. Kedua pengajuan menyalin kebijakan kuota 12 dan alur Atasan → HR. Atasan menyetujui keduanya melalui form Dashboard dengan session/CSRF. HP menunjukkan tahap 2, peninjau HR, status tetap pending dan satu keputusan historis; belum dianggap final.
4. HR mengubah master jenis pertama menjadi nama revisi dan kuota 20 melalui Dashboard setelah pengajuan dikirim. HR lalu menyetujui pengajuan pertama dan menolak pengajuan kedua, masing-masing beralasan. HP membaca status akhir, nama HR, alasan dan dua event keputusan. Snapshot pengajuan tetap nama lama dan kuota 12. Pengajuan final tidak menawarkan pembatalan.
5. Pengajuan ketiga dibuat melalui form HP menggunakan jenis dengan bukti opsional. Pemilik membatalkannya lewat dialog alasan. Detail menjadi cancelled dan daftar induk memuat ulang serta menampilkan status Dibatalkan.

Suite mencatat **11 pemeriksaan**, lulus dalam **2 menit 5 detik** pada percobaan yang berjalan. Waktu ini merupakan waktu test body, termasuk koordinasi dan ekspor, bukan benchmark performa aplikasi. Dashboard menjalankan dua keputusan atasan, satu perubahan kebijakan dan dua keputusan HR. [Progress native lengkap](mobile-leave-device-progress.json), [bukti Dashboard](mobile-leave-device-dashboard.json), [ringkasan QA](mobile-leave-device-qa.json), [log runner](mobile-leave-device-test.txt).

## Bukti visual

Sembilan PNG diambil dari Flutter surface yang berjalan pada perangkat dan diekspor dari cache privat **paket QA** sebelum runner teardown. Ini bukti HP aktual, terpisah dari screenshot widget sesi sebelumnya.

- [Jenis dengan bukti wajib](screenshots/mobile-leave-device/leave-type-1.png).
- [Gambar sintetis pada form HP](screenshots/mobile-leave-device/leave-proof.png).
- [Menunggu HR setelah persetujuan atasan](screenshots/mobile-leave-device/leave-waiting-hr-1.png).
- [Kebijakan lama dan dua keputusan](screenshots/mobile-leave-device/leave-final-0.png).
- [Pembatalan oleh pemilik](screenshots/mobile-leave-device/leave-cancelled.png).

Gambar bukti adalah PNG 32×32 berwarna biru yang dibuat di cache QA pada perangkat. Callback pemilih file disuntikkan ke form produksi untuk menghindari membuka galeri pribadi. Validasi lampiran, pembacaan/pratinjau file, multipart HTTP, penyimpanan dan akses privat benar-benar berjalan; **UI galeri Android serta dialog izinnya tidak diuji**. Tidak ada foto atau data pribadi pengguna yang diambil.

## Instalasi dan kendala harness

Percobaan instalasi pertama gagal karena USB sempat terputus; suite belum berjalan. HP terdeteksi kembali, kemudian namespace dan package MainActivity pada salinan QA diselaraskan dengan application ID QA agar Flutter mengenali target instalasi/pembersihan dengan benar. APK sudah diverifikasi ber-ID `com.naltech.attendance.leaveqa`. Pada kegagalan awal, Flutter sempat mencoba cleanup berdasarkan namespace lama; perintah itu gagal karena perangkat terputus. Tidak ada aplikasi utama yang dihapus atau ditimpa. Percobaan berikutnya berhasil.

Eksportir artefak awal menganggap output `ls` satu file per baris, sedangkan perangkat mengembalikan beberapa kolom. Pada tahap complete, suite sengaja menunggu sinyal finish sehingga screenshot belum terhapus. Eksportir diperbaiki menggunakan `ls -1`, lalu sembilan PNG beserta progress berhasil disimpan sebelum test dilepas dan lulus. Perbaikan ini hanya pada alat QA, bukan logika aplikasi produksi.

## Isolasi, cleanup dan batas

- Salinan QA menggunakan loopback melalui `adb reverse tcp:8018 tcp:8018`; URL mobile utama, environment, dependencies dan package aplikasi utama tidak diubah.
- Entry QA memakai MyApp/theme/routes/widget/datasource asli, tetapi mengecualikan inisialisasi Firebase messaging. Ini tidak membuktikan notifikasi push, build release/signing atau instalasi store.
- Gambar sintetis di cache dihapus oleh suite; runner kemudian menghapus paket QA. Server PHP dihentikan dan reverse 8018 milik QA dilepas. Storage/database backend fixture tetap di direktori disposable, terpisah dari database utama.
- Tidak mengubah pengaturan jaringan HP, izin galeri/kamera, atau data aplikasi lain. Face tetap tidak aktif. H2 tahap 2 tetap ditunda.
- Pengujian ini memakai tema light pada HP. Tema dark, nama panjang dan skala teks besar tetap berdasarkan tes/widget pada [laporan H4 mobile](hris-h4-mobile-leave.md); tidak dilabeli sebagai pengujian dark di HP.
- iOS, galeri native, offline/401 khusus alur cuti baru, perangkat lain, release build, penerapan database utama, rentang tanggal dan ledger saldo belum termasuk. Uji offline/401 native H3 sebelumnya tidak diganti label menjadi cakupan suite ini.
- Source produksi Flutter/backend tidak berubah pada sesi pengujian ini. Suite unit sebelumnya (77 Flutter dan baseline 117 backend) tidak dijalankan ulang; verifikasi baru adalah suite native dan tindakan Dashboard di atas.

Alat reproduksi: `prepare_leave.py`, `leave_device_test.dart`, `leave_device_dashboard.cjs`, dan `coordinate_leave_device.py` di `tools/mobile-qa/`. Gunakan direktori bertanda `.naltech-qa` yang baru dengan seed, workflow-fixtures dan mobile-leave-fixtures. Ekspor artefak pada complete sebelum control-finish agar runner tidak menghapus bukti.
