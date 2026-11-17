# QA shift malam Android emulator — H2

Diperiksa 21 Oktober 2026 sesuai arahan pengguna memakai emulator, menggantikan rencana HP fisik. Perangkat `emulator-5554`, Android 15 ARM64. Aplikasi debug terpisah **Naltech Shift QA**, paket `com.naltech.attendance.shiftqa`, dibuat dari salinan source produksi. Backend/storage/akun SQLite disposable; database MySQL utama tidak diubah oleh pengujian ini. Migrasi utama sebelumnya telah selesai sesuai [laporan penerapan](database-main-migration.md). Commit tetap menunggu perintah pengguna.

Hasil akhir setelah perbaikan label: **1 suite native emulator lulus, 10 pemeriksaan, 52 detik** (waktu runner; build/instalasi terpisah). Empat screenshot diekspor sebelum paket QA dibersihkan runner.

## Cakupan

Satu suite integration test Android memeriksa 10 hal: login, jadwal efektif, konfirmasi masuk 22.05, status setelah tengah malam, tanggal perangkat berbeda, QR tanggal lama ditolak, radius server, konfirmasi pulang 06.00 pada tanggal kerja asal, duplikasi, serta sesi lebih dari 24 jam yang ditolak UI/HTTP. Rincian pemeriksaan ada di [hasil JSON](mobile-shift-emulator-qa.json).

Konfirmasi masuk/pulang memakai `AttendanceResultPage`, Bloc, HTTP dan API produksi. Harness membuka halaman konfirmasi langsung dengan penyedia koordinat sintetis. Status Beranda asli dimuat ulang melalui pull-to-refresh; Cubit produksi juga diuji dengan waktu perangkat berbeda dan lokasi deterministik. Pemeriksaan QR memakai datasource HTTP produksi dan bukti QR temporer; **kamera/pemindai visual QR dan akurasi GPS tidak diuji**. Koordinat di luar radius benar-benar dikirim ke server dan ditolak. Screenshot Beranda masih menampilkan pemeriksaan lokasi asli berlangsung; keberhasilan checkout di suite menggunakan lokasi sintetis yang disuntikkan pada halaman konfirmasi. Karena itu suite ini tidak membuktikan tombol absensi Beranda aktif setelah GPS nyata selesai. Tidak mengubah jam Android: hanya clock backend yang disimulasikan dalam bootstrap QA bertanda khusus.

Simulasi server: 21 Oktober 22.05 → 22 Oktober 01.00 → 06.00 → 22.00 → 23 Oktober 23.01. Pemeriksaan SQLite memastikan sesi pertama tetap tanggal kerja 21 Oktober dan checkout UTC `2026-10-21 23:00:00`; sesi kedua tanggal 22 Oktober tetap terbuka dan terblokir karena kedaluwarsa. Tidak melakukan backfill data utama atau membuat akun demo di database utama.

## Temuan dan bukti

- Label jadwal efektif semula masih “Jam kantor”. Diperbaiki menjadi “Jadwal shift”, nama shift dan “Kehadiran sesi ini”; jalur server legacy tetap memakai label jam kantor. Tes widget khusus mencegah label tertukar kembali.
- Skrip awal gagal pada interaksi refresh sebelum transisi route selesai. Harness diperbaiki menunggu route, menggulir dan pull-to-refresh; satu percobaan compile juga diperbaiki dengan menambahkan import harness yang tertinggal. Ini kegagalan harness, bukan hasil lulus yang disembunyikan.
- [Masuk](screenshots/mobile-shift-emulator/shift-checkin.png), [setelah tengah malam](screenshots/mobile-shift-emulator/shift-midnight.png), [pulang](screenshots/mobile-shift-emulator/shift-checkout.png), [sesi kedaluwarsa](screenshots/mobile-shift-emulator/shift-expired.png).
- Tes Flutter konteks/label shift dan Beranda: **14 tes lulus**. Analysis Server SDK: tidak ada diagnostic pada enam file produksi terkait, tidak ada error proyek.

Cakupan belum termasuk HP fisik, iOS, release, kamera QR, reliabilitas GPS, payroll/toleransi, kalender/ledger cuti dan layar penelusuran koreksi HR. Face tetap tidak tersedia. QA sebelumnya pada OPPO untuk cuti tidak dipakai sebagai bukti native shift ini.
