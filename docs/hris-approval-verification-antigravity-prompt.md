# Prompt Antigravity — verifikasi browser dan Flutter approval

Disiapkan 28 Oktober 2026. Salin blok berikut ke Antigravity. Ini instruksi pelaksanaan berikutnya, bukan bukti testing sudah dilakukan.

```text
Lanjutkan pekerjaan pada repo:
/Users/macbookpro/development/fic/absensi/naltech-attendance

Tugas sekarang: verifikasi browser dan Flutter untuk perapian alur approval HR/atasan yang sudah diimplementasikan, lalu perbaiki bug yang ditemukan dalam scope. Jangan mengulang implementasi A–G dari awal. Pengemasan source code dan uji instalasi pembeli tetap ditunda. Jangan commit/push/deploy sampai saya memerintahkannya.

Baca:
- AGENTS.md, agent.md, design.md, skills.md.
- docs/hris-approval-browser-flutter-verification.md — checklist utama B01–B18 dan F01–F10.
- docs/hris-approval-flow-adjustment-prd.md.
- docs/hris-approval-flow-refinement-report.md.
- docs/hris-h4-flow-adjustment.md.
- docs/hris-core-timeline.md.

Konteks:
Tiga temuan sebelumnya sudah ditangani: endpoint menolak approval biasa needs_review, SQL COALESCE mencegah baris waiting_others hilang, dan kondisi reviewer/fallback serta workflow [] diselaraskan. Suite terarah SQLite telah diperiksa ulang: 9 tes/113 assertions. Laporan sebelumnya mencatat MySQL 9.3/InnoDB 9 tes/113 assertions dan regresi backend 150 tes/1.945 assertions. Jangan menyalin angka tersebut sebagai hasil baru tanpa menjalankan pemeriksaannya.

Urutan:
1. Periksa kondisi Git, implementasi aktual dan baseline bagian terkait. Pertahankan perubahan lokal, jangan reset.
2. Siapkan backend/database/storage dan akun sintetis disposable. Reuse tools/mobile-qa bila cocok, baca dahulu. Jangan memakai database utama untuk fixture/testing mutasi.
3. Uji browser nyata menggunakan formulir sebenarnya: filter all/actionable/waiting_others pada tiga modul, pagination >10, scope role/tenant, utama/fallback, reviewer kosong, needs_review, tinjauan keputusan, kembali mengedit, konfirmasi, klik ganda, keputusan pembatalan, konflik versi dan riwayat.
4. Periksa light/dark, desktop 1440×1000 dan sempit 390×844, keyboard, Escape, fokus dialog, alasan panjang, karakter HTML serta console. Verifikasi tidak ada mutasi sampai konfirmasi, dan keputusan/cadangan/use/release/restore tidak ganda. Jangan menyamakan assertion markup dengan pengujian interaksi browser.
5. Uji Flutter model/Cubit/widget: payload lama tanpa metadata baru, action_label/event_type baru, label pembatalan ditolak, tahap/peninjau, refresh detail dan daftar, konflik/error/session expiry serta teks besar.
6. Jalankan satu alur Android emulator dengan API fixture nyata bila lingkungan tersedia: login pemohon → pantau approval web tahap 1/2 → approved → ajukan pembatalan → keputusan web → refresh mobile. Gunakan package QA dan salinan konfigurasi terpisah. Jangan meminta HP fisik sebagai prasyarat. Jangan mengklaim uji kamera/GPS/galeri jika tidak dilakukan.
7. Perbaiki masalah yang ditemukan, tambah tes regresi yang bermakna, dan ulangi bagian terdampak. Jika SQL diubah, verifikasi SQLite serta MySQL terisolasi; jangan memakai database utama sebagai fallback. Gunakan tools/mysql-qa/README.md bila relevan.
8. Jalankan regresi yang sesuai setelah perubahan selesai. Dokumentasikan hasil aktual dan batasnya, kemudian bersihkan server/database/akun/paket QA yang dibuat tanpa menyentuh data/aplikasi utama.

Ketentuan:
- Kewenangan baca berbeda dari hak memutuskan. Pertahankan larangan self-approval, scope perusahaan, peninjau aktif/fallback, approval_version dan transaksi server.
- cancellation_requested adalah keputusan pembatalan yang berbeda dari approval awal. Jika pembatalan ditolak, pengajuan tetap approved dan saldo tetap used; riwayat harus menyebut makna ini.
- Jangan menambah konsol approval HR di mobile. Mobile karyawan hanya diselaraskan status, riwayat dan pembatalannya.
- Jangan mengubah kuota/kalender/workflow perusahaan atau merekayasa saldo legacy tanpa dasar.
- Face tetap tidak tersedia; jangan mengubah .env, rahasia, URL mobile asli atau Firebase.
- Jangan mencetak token/password/header autentikasi atau menyimpan dump ke Git. Gunakan file sintetis bila lampiran diperlukan; jangan membuka media pribadi.
- Semua shell command mengikuti prefix rtk sesuai instruksi repo.
- Jika tooling tidak tersedia, jelaskan kendala dan jalankan bagian independen yang dapat dilakukan. Bedakan belum dijalankan, gagal dan terhalang; jangan membuat bukti palsu.
- Sebelum perubahan destruktif atau keputusan kebijakan di luar scope, jelaskan kebutuhan input saya.

Hasil wajib:
- docs/hris-approval-ui-verification-results.md: tabel B01–B18 dan F01–F10 dengan status, lingkungan, bukti dan batasan.
- Screenshot relevan di docs/screenshots/approval-ui-verification/; bedakan widget dan emulator.
- Jumlah tes/check aktual, bug yang diperbaiki dan sisa yang belum tertutup.
- Timeline diperbarui sesuai tanggal pelaksanaan, tanpa klaim seluruh produk 100% siap produksi.

Kerjakan sampai scope selesai atau ada hambatan nyata. Jangan berhenti setelah hanya membuat rencana. Berikan update ringkas selama pengerjaan. Tidak ada commit sampai saya memberikan perintah.
```
