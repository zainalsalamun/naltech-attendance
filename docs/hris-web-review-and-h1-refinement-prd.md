# PRD — Review visual H5 dan penyempurnaan lifecycle karyawan H1

Tanggal: 30 Oktober 2026, Asia/Jakarta.  
Status: **Rencana, belum merupakan hasil implementasi/pengujian.**

## Tujuan dan urutan

Tutup review visual Dashboard H5, lalu audit dan perbaiki celah nyata alur karyawan H1. HR harus memahami dampak perubahan status, atasan, unit dan akses tanpa kehilangan riwayat. H1 sudah tersedia: jangan membangun ulang atau mengubah aturan bisnis tanpa bukti kebutuhan.

Tahap 1: review visual H5 dan perbaikan UX dalam lingkup. Tahap 2: audit H1 dan perbaikan celah terhadap aturan di bawah. Hasil H5 yang belum ditinjau pengguna harus dicatat sebagai menunggu review pengguna, bukan dianggap disetujui oleh agen. Audit H1 dapat berjalan sambil menunggu review tersebut. H2 cukup dicatat sebagai backlog; implementasi H2/mobile tidak termasuk tugas ini.

## Acuan dan kondisi awal

- Baca AGENTS.md, RTK.md yang dirujuk, agent.md, design.md, skills.md dan checklist Dashboard.
- Baca docs/hris-h5-flow-refinement-prd.md beserta laporan/hasil H5 terbaru; docs/hris-h1-implementation.md; docs/hris-h1-organization.md; docs/hris-approval-flow-refinement-report.md; docs/hris-core-timeline.md.
- Dokumen lama dapat memuat status migrasi/pengujian pada tanggal lampau. Jangan menganggapnya status database utama saat ini atau mengulang penerapan migrasi berdasarkan catatan lama.
- EmployeeLifecycle::save() sudah memiliki transaksi, lock perusahaan, perlindungan admin terakhir/self-deactivation, validasi rantai atasan, snapshot lifecycle dan pencabutan sesi/token. Audit jalur controller/web/API yang benar-benar memakai aturan ini.
- Hubungan atasan organisasi berbeda dari hak reviewer approval. Atasan tidak wajib role supervisor; jangan melarang penempatan itu hanya karena ia tidak memenuhi syarat reviewer. Resolver approval tetap menentukan reviewer/fallback sah.
- Hubungan atasan/unit nonaktif yang sudah ada dipertahankan sebagai riwayat; penempatan baru harus aktif dan satu perusahaan. department adalah teks legacy, bukan otomatis master unit.

## A. Review visual H5

Periksa form koreksi, dialog before/after, alasan, riwayat audit, rekap dan drilldown harian terhadap design.md. Gunakan tema light/dark, desktop 1440×1000, sempit 390×844, keyboard serta teks panjang.

Kriteria: tanggal kerja asal dan zona waktu jelas; hari libur tetap status utama dengan konteks izin; angka rekap dapat ditelusuri; filter/kembali/CSV konsisten; empty/error/loading jelas; fokus kembali setelah dialog ditutup; aksi penting tidak tertutup atau terpotong. Tabel boleh bergulir dalam kontainernya, bukan membuat halaman meluap.

Manfaatkan screenshot/bukti H5 yang sudah ada untuk review awal. Jalankan ulang browser hanya pada bagian yang berubah atau bukti yang belum memadai. Jangan mengulang semua suite tanpa alasan. Simpan temuan dan before/after per perbaikan; jangan menandai screenshot lama sebagai hasil baru.

## B. Otorisasi perubahan karyawan

Petakan role dan cakupan aktual untuk list/detail/create/update/status/archive/reactivate serta endpoint API terkait. Pertahankan aturan sah yang sudah berlaku. Bila cakupan supervisor saat ini ambigu antara pengelolaan karyawan dan review tim, dokumentasikan dan minta keputusan sebelum memperluas akses; jangan mengasumsikan semua modul memiliki cakupan identik.

- Perusahaan target, unit dan atasan divalidasi server; ID dari perusahaan lain tidak dapat dibaca/diubah melalui URL atau request langsung.
- HR/supervisor tidak dapat menaikkan role melalui payload tersembunyi atau API profil. Role istimewa mengikuti pembatasan admin yang ada.
- Akun nonaktif/arsip tidak dapat melakukan mutasi menggunakan sesi lama. Aktor divalidasi ulang dalam transaksi untuk perubahan sensitif.
- Admin aktif terakhir tidak dapat dihilangkan lewat demosi, nonaktif atau arsip, termasuk persaingan dua admin. Akun sendiri tidak dapat dinonaktifkan/diarsipkan melalui endpoint alternatif.

## C. Form dan transisi lifecycle

- Bedakan status nonaktif dan arsip melalui penjelasan serta aksi yang tersedia. Arsip tidak menghapus absensi, pengajuan, audit atau lampiran historis.
- Nomor induk unik per perusahaan sesuai aturan existing; validasi create/update konsisten. Tanggal masuk/keluar berurutan; tampilkan tanggal keluar inklusif dan keterbatasan tanggal historis kosong.
- Alasan transisi nonaktif/arsip/reaktivasi wajib setelah trim; batas panjang divalidasi. Perubahan unit/atasan/role harus tercatat before/after; bila membutuhkan alasan tambahan untuk perubahan tersebut, jelaskan dan terapkan konsisten di semua jalur terkait.
- Preview aksi status/arsip/reaktivasi memuat karyawan, status awal/tujuan, alasan, dampak akses, dan pelestarian riwayat. Back/Escape tidak memutasi dan mempertahankan isian/fokus. Konfirmasi mengunci pengiriman ganda.
- Reaktivasi adalah transisi eksplisit, bukan efek samping menyimpan edit profil biasa. Sesi/token lama yang dicabut tidak boleh aktif kembali setelah reaktivasi; pengguna harus login ulang.
- Tidak menetapkan pengaktifan otomatis pada tanggal masuk atau penonaktifan otomatis pada tanggal keluar tanpa aturan existing yang jelas. Bila belum ada, jelaskan bahwa tanggal masa kerja dan status akses adalah dua kontrol berbeda.
- Audit atomik: gagal validasi/transaksi tidak menghasilkan perubahan/event; perubahan efektif menghasilkan event tepat sekali; no-op tidak menambah riwayat palsu.
- Dua editor tidak boleh menimpa perubahan sensitif diam-diam. Audit mekanisme concurrency yang tersedia. Bila belum ada proteksi stale form, implementasikan versi/token yang andal dengan validasi di transaksi, konflik jelas dan input tetap tersedia untuk ditinjau. Lock saja tidak membuktikan stale input aman.

## D. Atasan, unit dan dependensi approval

- Tolak self-manager, siklus langsung/tidak langsung dan hubungan lintas tenant; pertahankan hubungan nonaktif lama dengan label yang jelas, jangan hapus diam-diam.
- Penonaktifan atasan/unit tidak otomatis memindahkan seluruh bawahan atau mengubah approval snapshot. Tampilkan dampak dan tautan pengelolaan yang berwenang; jangan mengklaim semua pengajuan otomatis berpindah ke atasan baru.
- Buat matriks perilaku aktual untuk pengajuan existing vs pengajuan baru ketika atasan diganti, peninjau nonaktif/arsip/berubah role, atau fallback tidak tersedia. Verifikasi terhadap ApprovalWorkflow/SubmissionApproval dan query antreannya.
- Pengajuan existing mengikuti kontrak snapshot/resolver yang berlaku; pengajuan baru memakai konfigurasi saat dibuat. Jika resolver dan antrean berbeda, perbaiki kesesuaiannya tanpa rewrite massal snapshot existing.
- Tidak ada self-approval baru akibat perubahan organisasi; fallback tetap harus aktif, sah dan satu perusahaan. Bila tidak ada peninjau sah, tampilkan kebutuhan penanganan HR dengan jujur, bukan persetujuan otomatis.

## E. Akses dan laporan historis

- Verifikasi web session, API token, remember token dan auth_epoch sesuai mekanisme existing setelah nonaktif/arsip/perubahan role/password yang relevan. Jangan mencetak rahasia atau merekam token nyata sebagai bukti.
- Karyawan nonaktif/arsip tetap ditemukan melalui filter eksplisit dan laporan historis yang berwenang. Filter/pagination/detail/kembali mempertahankan konteks.
- Perubahan unit/atasan sekarang tidak boleh diberi label penempatan historis jika tidak ada data periodenya. Jeda kerja/reaktivasi tidak dapat direkonstruksi hanya dari joined_on/left_on; dokumentasikan batasnya, jangan membuat tanggal rekaan.
- Perubahan status tidak menghapus atau otomatis menyetujui/membatalkan pengajuan lama, menulis ledger saldo, maupun mengubah snapshot absensi tanpa aturan eksplisit.

## F. Matriks verifikasi

| ID | Skenario dan bukti minimum |
| --- | --- |
| R01 | Review visual H5: temuan per halaman/tema/viewport, screenshot aktual atau referensi bukti existing berlabel jelas |
| R02 | Role/cakupan dan lintas tenant: GET serta mutasi langsung, DB target tetap pada penolakan |
| R03 | Create/update nomor induk, tanggal dan ID relasi invalid: error jelas tanpa mutasi sebagian |
| R04 | Preview/back/Escape/confirm: 0 mutasi saat back; 1 request dan 1 event saat submit berulang |
| R05 | Nonaktif/arsip/reaktivasi: akses lama ditolak, login ulang setelah aktif, riwayat tetap |
| R06 | Self-deactivation dan admin terakhir: semua jalur termasuk dua worker/koneksi independen pada MySQL QA bila mengklaim bukti race |
| R07 | Atasan self/siklus/nonaktif/lintas tenant dan unit nonaktif: aturan baru vs hubungan lama sesuai D |
| R08 | Perubahan atasan/reviewer: pengajuan existing vs baru, fallback dan antrean sesuai resolver, tanpa self-approval |
| R09 | Audit no-op, rollback, stale form: snapshot akurat, konflik tidak menimpa atau menggandakan event |
| R10 | Rekap historis dan daftar arsip: data lama tetap, filter/kembali tepat, baca tidak menulis ledger |
| R11 | Light/dark, sempit, keyboard, XSS, teks panjang dan empty/error pada H1 |

Status per ID: lulus/gagal/belum diuji/terkendala. Bukti model/widget bukan pengujian live browser. SQLite bukan bukti lock MySQL. Assertion harus memeriksa perilaku nyata; dilarang stub selalu lulus.

## Pelaksanaan dan keluaran

1. Catat baseline dan pemetaan A–E: sudah sesuai/celah/ambigu, file sumber dan tes existing.
2. Review H5; perbaiki UX dalam lingkup. Audit H1, reproduksi celah lalu perbaiki secara bertahap. Jangan menambah fitur hanya untuk memenuhi daftar perubahan.
3. Jalankan tes relevan perubahan. Regresi penuh sekali setelah perubahan bisnis selesai bila lingkungan mendukung; ulangi hanya bila ada perubahan/kegagalan baru. MySQL QA diperlukan untuk perubahan SQL/locking/concurrency; gunakan tools/mysql-qa/README.md.
4. Keluaran: docs/hris-web-review-and-h1-refinement-report.md, docs/hris-web-review-and-h1-refinement-results.json, screenshot di docs/screenshots/web-review-h1-refinement/, dan pembaruan timeline berdasarkan hasil aktual. Cantumkan R01–R11, perintah/versi, hasil baru vs lama, hambatan, resource cleanup dan bagian yang menunggu review pengguna.

Semua shell command diawali rtk. Gunakan fixture sintetis serta DB/storage QA disposable. Jangan menyentuh database utama, menjalankan migrasi/seed/reset utama, mengedit .env/Firebase, membocorkan kredensial, mereset working tree, commit/push/deploy atau mengemas source. Jika migrasi aditif diperlukan, uji hanya pada QA dan catat belum diterapkan utama. Bersihkan hanya resource tugas ini. Tidak ada implementasi mobile atau H2 dalam penugasan ini.
