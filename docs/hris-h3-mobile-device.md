# QA pengajuan pada HP nyata — 21 Oktober 2026

**Satu suite integrasi Android lulus** pada OPPO CPH2591, Android 15, arm64, build debug. Runner selesai dalam 3 menit 56 detik setelah instalasi. Ini validasi irisan pengajuan, bukan kelulusan seluruh native/release aplikasi.

## Yang dijalankan

- Login akun fixture dari HP dan mengirim izin, reimbursement serta lembur melalui form Flutter asli dan HTTP ke backend sementara.
- Melalui Dashboard Chrome headless dengan session/CSRF nyata, HR menyetujui izin/lembur dan menolak reimbursement. HP memuat status, nama HR serta alasan yang sama.
- Pemilik membatalkan izin dari dialog native dengan alasan; pengajuan tetap tersimpan dan daftar dimuat ulang setelah kembali.
- Backend QA dihentikan tanpa mengubah Wi-Fi/data seluler HP. Detail menandai hasil belum terkonfirmasi dan tidak membuka aksi. Setelah server dinyalakan lagi, detail pulih.
- Token akun fixture dicabut hanya pada database QA. Permintaan berikutnya menerima 401; aplikasi menghapus sesi lokal/rute privat dan kembali ke login. Assertion akhir serta suite lulus.

[Hasil agregat](mobile-approval-device-qa.json), [log runner](mobile-approval-device-test.log), dan [keputusan Dashboard](mobile-approval-device-dashboard.json) disimpan tanpa kredensial/token. Ada empat keputusan web karena satu sampel lembur dari percobaan awal yang terputus ikut ditinjau, selain sampel pengujian ulang.

Percobaan awal sempat dihentikan saat progres keliru dibaca sebagai macet. Cache kemudian membuktikan ketiga form telah berjalan. Pengujian ulang selesai penuh. Template kini memberi penanda per tahap dan deskripsi unik per run agar sampel ulang tidak tertukar.

## Perbaikan dari pemeriksaan

API daftar lembur kini mengurutkan tanggal menurun **lalu ID menurun**, sehingga pengajuan bertanggal sama memiliki urutan terbaru yang konsisten. Regresi khusus ditambahkan. **107 tes backend / 1.339 assertions lulus** setelah perubahan. Klien Flutter tetap pada hasil sebelumnya: 67 tes lulus dan lima file tanpa diagnostik; tidak ada perubahan produksi Flutter baru dalam irisan perangkat ini.

## Lingkungan dan artefak

Paket khusus `com.naltech.attendance.approvalqa` berlabel Naltech Approval QA, dibuat dari salinan source sementara dengan URL loopback lewat `adb reverse`. Paket aplikasi utama dan URL checkout utama tidak ditimpa. Seluruh migrasi diterapkan hanya pada SQLite/storage sementara yang ditandai `.naltech-qa`; **database utama belum dimigrasikan**. Tidak ada pesan/email nyata atau data biometrik dikirim.

APK QA biasa berhasil dibangun sebelum APK instrumentasi. Lokasinya tercatat pada file metadata lokal di direktori QA; bukan build release atau APK yang mengarah ke database utama. Entry point QA mengecualikan Firebase messaging, sehingga hasil ini tidak membuktikan notifikasi push, signing/release, iOS, galeri native, QR atau absensi wajah. H2 tahap 2 dan Face tetap ditunda/tidak tersedia.

Runner otomatis menghapus paket QA berikut cache screenshot saat selesai. Screenshot approval perangkat tidak sempat diekspor, sehingga laporan ini mengandalkan assertion native/log dan tidak menyebut screenshot widget sebagai bukti HP. Template pengulangan kini menunggu sinyal `finish` setelah tahap complete agar screenshot bisa diekspor sebelum teardown. Aplikasi utama tetap tersimpan. Server QA dan forwarding USB milik pengujian dihentikan setelah selesai.

## Mengulang

Gunakan `tools/mobile-qa/prepare_approval.py`, seed backend pada direktori baru, pub get offline dan build QA. Jalankan `integration_test/approval_device_test.dart` dari salinan ke serial HP dengan `flutter test`. Pantau `cache/approval-progress.json` melalui `adb shell run-as com.naltech.attendance.approvalqa`; jangan membaca preference auth.

Pada `awaiting_dashboard`, ekspor JSON progres dan jalankan `approval_device_dashboard.cjs` dengan direktori QA dan file progres, lalu buat `cache/control-decisions`. Pada `awaiting_offline`, hentikan server QA sebelum sinyal `control-offline`; setelah `offline_verified`, hidupkan server lalu sinyal `control-online`. Pada `awaiting_expiry`, cabut token **akun fixture di SQLite QA saja** lalu sinyal `control-expiry`. Pada `complete`, ekspor artefak agregat/screenshot, lalu sinyal `control-finish` untuk teardown. Jangan memakai paket/DB produksi untuk langkah ini.
