# H2 tahap 2 — Jadwal efektif dan checkout lintas malam

Diperiksa 21 Oktober 2026. Dilanjutkan kembali sesuai arahan pengguna setelah sebelumnya ditunda. Implementasi backend, Dashboard dan integrasi status Beranda Flutter tersedia. **Database utama belum dimigrasikan.** Commit tetap ditunda. Face tetap tidak tersedia.

## Perilaku yang tersedia

- Menu **Jadwal efektif**: HR/admin memilih karyawan, master shift atau Tanpa shift, tanggal mulai dan alasan. Supervisor dapat membaca. Data dibatasi perusahaan aktif; akun nonaktif ditolak saat penyimpanan.
- Riwayat bersifat tambah revisi: jam, nama dan zona waktu disalin saat penempatan. Tanggal efektif terbaru berlaku; pada tanggal sama, revisi terakhir berlaku. Form lama ditolak 409 bila ada perubahan sejak dimuat. Tanggal lampau dan tanggal hari ini yang sudah memiliki absensi ditolak.
- Penempatan pertama untuk masa depan menyalin penempatan lama sebagai baseline hari ini. Tanggal sebelum baseline tidak direka. Form karyawan mengunci penempatan lama setelah ada riwayat efektif dan menyediakan tautan ke menu baru. Master yang memiliki riwayat tidak bisa dihapus; perubahan master tidak mengganti salinan penempatan.
- Check-in baru menyimpan jadwal, zona waktu, status kalender saat kejadian, awal/akhir shift UTC dan waktu masuk UTC. Jam keluar lebih kecil daripada masuk berarti berakhir keesokan hari. Keterlambatan catatan baru membandingkan instan UTC, termasuk masuk pertama setelah tengah malam untuk shift malam sebelumnya.
- Sesi terbuka tetap menjadi sasaran checkout setelah tengah malam. Tanggal kerja tetap tanggal asal; QR menggunakan tanggal perusahaan saat pemindaian. Radius, metode, isolasi perusahaan dan penolakan duplikasi tetap diperiksa server.
- Batas teknis checkout **24 jam sejak masuk**. Sesi lebih lama menahan check-in/checkout sampai HR mengoreksinya. Ini kebijakan implementasi eksplisit, bukan klaim ketentuan ketenagakerjaan. Belum ada konfigurasi batas/toleransi atau aturan seberapa awal karyawan boleh masuk.
- Koreksi catatan baru meminta tanggal/jam aktual dan alasan; durasi maksimal 24 jam, tanggal masuk pada tanggal kerja atau hari berikutnya. HR/admin aktif saja yang dapat menyimpan. Audit mencatat aktor, alasan, waktu serta sebelum/sesudah jam UTC dan koordinat; salinan jadwal dipertahankan. Hapus catatan baru ditolak. Belum ada layar penelusuran audit/koreksi berjenjang atau penjagaan versi formulir koreksi.
- Zona waktu perusahaan dikunci setelah ada absensi atau penempatan efektif. Jam shift/koreksi pada transisi DST yang hilang/ambigu ditolak untuk ditinjau HR.
- Dashboard dan daftar kehadiran memakai salinan catatan baru; CSV menambahkan nama/jam jadwal, zona waktu serta check-in/out UTC setelah tujuh kolom lama.
- `/api/is-checkin` mengembalikan tanggal kerja, tanggal QR, sesi, jadwal dan kelayakan tindakan. Beranda Flutter memakai konteks server dan menahan tindakan sesi kedaluwarsa. Server lama tetap didukung lewat jalur status legacy. Pemindai QR memakai tanggal server.

## Pemeriksaan

- Backend: **123 tes / 1.605 assertions lulus**, SQLite memory terisolasi. Enam tes baru mencakup versi/role/scope, baseline dan salinan master, shift lintas malam, masuk pertama setelah tengah malam, sesi kedaluwarsa/koreksi/audit, QR/radius dan DST.
- Flutter: suite regresi **81 tes lulus**; tambahan satu tes tanggal QR kemudian lulus bersama empat tes konteks (5/5). Lima tes baru memeriksa tanggal HP berbeda, sesi kedaluwarsa, pemilik/tanggal salah, tanggal kalender invalid dan tanggal QR server.
- Analysis Server SDK melalui `dartaotruntime`: tidak ada diagnostic pada enam file produksi terkait dan tidak ada error proyek. Jalur ini dipakai karena wrapper analyzer lokal bermasalah dengan runtime AOT.
- Chrome nyata headless: **10 checks lulus**, formulir create/edit, salinan master, penguncian penempatan lama, light/dark 1440×1000 dan 390×844. [Laporan](effective-shift-browser-qa.json), [light sempit](screenshots/effective-shifts/light-mobile.png), [dark desktop](screenshots/effective-shifts/dark-desktop.png). Screenshot diperiksa visual.
- Blade dikompilasi dan PHP hasil kompilasi diperiksa di cache disposable lalu dibersihkan. PHP terkait diformat dengan Pint; dependency lokal memberi peringatan deprecated PHP 8.4.
- Tidak mengklaim uji native untuk perubahan H2 ini. QA OPPO H4 sebelumnya tidak mewakili alur shift baru. Tidak mengubah jam HP, URL pengguna, konfigurasi Firebase atau database utama.

## Batas dan penerapan

Migrasi baru: `2026_10_21_000007_add_effective_shifts.php`, mengikuti migrasi fondasi H0–H4. Uji penerapan pada salinan staging dan review hasil sebelum operator memigrasikan database utama. Tidak melakukan backfill timestamp/snapshot rekaan pada catatan lama. Jangan rollback kolom setelah dipakai tanpa prosedur preservasi data.

Catatan legacy tetap terbatas: jam lama tidak memiliki tanggal keluar atau timestamp UTC; shift malam lama perlu tinjau. Rekap tanggal lama memakai roster legacy, belum riwayat periode hubungan kerja. Angka Belum check-in bukan alpa; belum ada payroll, toleransi, penutupan periode, roster bergilir, lebih dari satu sesi per tanggal atau rekap periode final. Snapshot metode/radius/kebijakan lengkap dan tampilan audit koreksi masih lingkup H5. Kalender tersimpan pada catatan baru belum menjadi ledger cuti.

Berikutnya: review/penerapan terkontrol dan uji native alur shift baru, kemudian H4 rentang tanggal/overlap/perhitungan hari dan ledger saldo. Kebijakan saldo, periode, prorata dan carry-over perlu eksplisit sebelum implementasi ledger.

## Penerapan database utama — 21 Oktober 2026

Delapan migrasi tertunda berhasil diterapkan pada MySQL utama atas instruksi pengguna, dengan backup lokal di luar repositori. Seluruh migrasi berstatus Ran; jumlah baris bisnis sebelum/sesudah tetap sama. [Rincian verifikasi](database-main-migration.md). Status belum migrasi di bagian sebelumnya merupakan catatan historis. Commit tetap menunggu perintah pengguna; uji native shift masih terbuka.

## QA shift emulator — 21 Oktober 2026

[Pengujian H2 pada Android 15 emulator lulus](hris-h2-mobile-emulator.md): 1 suite/10 pemeriksaan, 4 screenshot dan verifikasi SQLite terisolasi. Label jadwal efektif diperbaiki; 14 tes Beranda/shift lulus. Migrasi utama sebelumnya telah diterapkan. HP fisik, kamera QR dan akurasi GPS belum diuji untuk H2. Commit tetap menunggu perintah pengguna.
