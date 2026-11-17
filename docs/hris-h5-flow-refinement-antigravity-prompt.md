# Prompt Antigravity — Penyempurnaan H5

Tanggal: 29 Oktober 2026, Asia/Jakarta. Salin blok berikut ke Antigravity.

```text
Kerjakan penyempurnaan alur koreksi absensi dan rekap kehadiran H5 pada repositori:
/Users/macbookpro/development/fic/absensi/naltech-attendance

Spesifikasi utama:
docs/hris-h5-flow-refinement-prd.md

Baca AGENTS.md dan instruksi yang dirujuknya, agent.md, design.md, skills.md, serta dokumentasi H5, H4 dan review yang dicantumkan PRD. Ikuti RTK.md: setiap perintah shell diawali rtk. Periksa status Git sebelum mengedit dan pertahankan seluruh perubahan lokal di luar lingkup. Dashboard web didahulukan; jangan mengerjakan mobile sebelum hasil Dashboard direview.

Ini penyempurnaan implementasi yang sudah ada. Audit route, otorisasi, controller, model, view, kalender/shift, dan tes terlebih dahulu. Buat pemetaan kondisi awal terhadap kebutuhan A–F dalam PRD. Jangan menganggap semua kebutuhan belum tersedia dan jangan menulis ulang modul secara keseluruhan.

Prioritas:
1. Buktikan dan perbaiki rekap cuti cancellation_requested: cuti sebelumnya tetap berlaku sampai pembatalannya disetujui; penolakan pembatalan tetap approved. Saat ini AttendanceRecapController tampak hanya memuat approved. Pembacaan rekap/CSV tidak boleh menulis ledger.
2. Periksa koreksi beralasan, transaksi atomik dengan snapshot before/after, preview keputusan, no-op, pencegahan submit ganda, dan konflik dua editor. Jangan membiarkan lost update diam-diam.
3. Pastikan koreksi shift malam mempertahankan tanggal kerja asal, konversi UTC benar, dan snapshot historis tidak ditimpa aturan shift saat ini.
4. Selaraskan definisi status dan angka antara sumber terkait. Jangan hitung tanggal mendatang sebagai alpa; label hari berjalan dan data historis yang tidak pasti secara jujur.
5. Sediakan penelusuran rekap per tanggal ke sumbernya; pertahankan filter saat kembali. Filter periode/karyawan/unit yang tersedia harus sama untuk halaman dan ekspor, diterapkan sebelum pagination. Jangan menambah master organisasi atau mengklaim penempatan historis yang tidak tersedia.
6. Pertahankan validasi 90 hari inklusif, roster nonaktif/arsip, deduplikasi izin, kehadiran hari libur, formula injection protection, isolasi tenant, dan cakupan supervisor yang berlaku.

Gunakan PRD untuk detail aturan dan acceptance criteria. Hindari dependency/framework baru dan fitur di luar lingkup seperti payroll, approval koreksi bertingkat, face attendance, pengemasan atau notifikasi eksternal.

Pengujian:
- Gunakan fixture sintetis pada DB QA disposable, bukan database MySQL utama. Jangan menjalankan migrate/seed/reset di database utama atau mengedit .env/Firebase.
- Tambahkan tes regresi yang membuktikan celah bisnis; jalankan AttendanceCorrectionRecapTest dan suite terkait perubahan, lalu regresi backend penuh bila lingkungan mendukung.
- Bila ada perubahan SQL/migrasi/locking, uji pada MySQL QA terisolasi sesuai tools/mysql-qa/README.md. SQLite bukan bukti concurrency MySQL. Gunakan worker/koneksi independen untuk skenario race.
- Verifikasi browser nyata otomatis sesuai H5-01 sampai H5-12: tema terang/gelap, 1440x1000 dan 390x844, dialog/back/keyboard, filter/ekspor, error, serta otorisasi langsung.
- Assertion wajib memeriksa request dan keadaan DB bila mengklaim mutasi tepat sekali. Tidak boleh stub selalu lulus, hanya mencocokkan copy sebagai bukti ledger, atau mengklaim pemeriksaan yang belum dijalankan.
- Jika tes gagal, perbaiki penyebab dalam lingkup dan jalankan ulang yang terdampak. Jika terhambat, catat sebab dan tandai belum diuji/terkendala; lanjutkan pekerjaan independen yang tersedia.

Keluaran:
- Implementasi dalam lingkup dan tes terkait.
- docs/hris-h5-flow-refinement-report.md: kondisi awal, perbaikan, pemetaan A–F dan H5-01–12, perintah serta hasil aktual, keterbatasan, kebutuhan migrasi bila ada, dan cleanup.
- docs/hris-h5-flow-refinement-results.json: hasil terstruktur per skenario.
- docs/screenshots/h5-flow-refinement/: screenshot aktual tanpa data pribadi/kredensial.
- Perbarui docs/hris-core-timeline.md berdasarkan hasil nyata; jangan menandai semua selesai hanya karena dokumen sudah dibuat.

Hentikan server dan bersihkan hanya resource QA buatan tugas ini setelah selesai. Jangan commit, push, deploy, mengemas source code, atau memutasi database utama. Akhiri dengan ringkasan perubahan, hasil tes aktual, temuan tersisa dan kesiapan untuk review Dashboard.
```
