# H3 tahap 2 — Alur persetujuan bertingkat

Diperiksa 21 Oktober 2026. Implementasi Dashboard dan backend tiga pengajuan tersedia; tampilan tahap/riwayat karyawan di Flutter diselaraskan setelah pemeriksaan visual web. Database utama belum dimigrasikan. H2 tahap 2 dan verifikasi wajah tetap ditunda.

## Perilaku

Menu **Alur persetujuan** (`/approval-workflows`) mengatur izin, lembur, dan reimbursement secara terpisah per perusahaan. HR/admin/super admin dapat menyimpan; supervisor hanya membaca. Setiap penyimpanan menambah versi konfigurasi dengan aktor dan waktu, bukan menimpa riwayat. Versi form usang ditolak 409. Form memiliki maksimal empat tahap berurutan, nama tahap, pilihan atasan langsung atau pengelola tertentu, dan pengganti yang wajib berbeda dari peninjau tertentu. Peninjau harus pengelola aktif di perusahaan yang sama. Tahap tanpa nama pada form diabaikan; tanpa seluruh tahap berarti kembali memakai satu keputusan sesuai scope H3 tahap 1, khusus pengajuan berikutnya. Konfigurasi tidak boleh dikirim tanpa konfirmasi.

Pengajuan baru menyalin urutan, nama tahap dan ID peninjau/pengganti. Atasan langsung diambil saat pengajuan dibuat. Mengubah konfigurasi atau hubungan atasan kemudian tidak mengganti salinan ini. Pengajuan sebelum konfigurasi/migrasi tetap mengikuti proses sebelumnya; tidak ada tebakan atau backfill tahap untuk data lama. Payload API tidak dapat memasang urutan atau melewati tahap.

Hanya peninjau tahap aktif yang bisa memutuskan. Pengganti dipakai bila peninjau utama belum ditetapkan, nonaktif/diarsipkan, tidak lagi memiliki role pengelola, tidak berada di perusahaan yang sama, atau merupakan pemohon sendiri. Pengganti tidak dapat mengambil alih selama peninjau utama masih memenuhi syarat. Jika keduanya tidak memenuhi syarat, proses tetap menunggu; HR/admin tidak memperoleh jalur bypass. Pemilik tidak dapat menyetujui atau menolak pengajuannya sendiri.

Persetujuan tahap awal menaikkan tahap dan versi, tetapi status tetap `pending` dan boolean izin tetap false. Hanya persetujuan tahap terakhir menghasilkan `approved`. Penolakan wajib beralasan dan langsung menghasilkan `rejected`. Pemilik masih dapat membatalkan selama pending, termasuk setelah tahap awal disetujui. Keputusan final terkunci. Alasan maksimal 1.000 karakter berlaku pada semua keputusan. Isi pengajuan tidak dapat diedit setelah tahap pertama disetujui, agar persetujuan sebelumnya tidak berlaku pada isi yang berubah.

Keputusan tetap memakai transaksi dan urutan kunci perusahaan → aktor → pengajuan. Pemeriksaan peninjau aktif dilakukan ulang di transaksi; perubahan lifecycle memakai kunci perusahaan yang sama. Form usang/replay tidak menambah event; karena otorisasi tahap diperiksa dahulu, peninjau tahap sebelumnya dapat menerima 403 setelah tahap berpindah, sedangkan peninjau yang masih berwenang dengan versi usang menerima 409. Belum ada uji paralel pada engine database produksi.

Daftar/detail web dan bukti privat juga dapat dibaca pengelola yang ditetapkan sebagai peninjau/pengganti dalam salinan alur. Ini diperlukan bagi supervisor yang meninjau pengajuan di luar bawahan langsungnya berdasarkan penugasan eksplisit. API lampiran dan daftar/detail pemilik mempertahankan scope sebelumnya. Detail API menambahkan `approval_progress`, `approval_current_reviewer`, dan maksimal 50 `approval_history` terbaru (aktor, waktu ber-offset, tindakan, tahap, alasan), tanpa mengirim snapshot bisnis event mentah. Nama aktor/peninjau adalah nama akun saat dibaca, bukan snapshot nama historis. Detail Flutter menampilkan tahap, urutan, peninjau saat ini, keputusan terakhir, dan riwayat; status pending tetap memungkinkan pembatalan.

## Batas

- Pengganti belum berdasarkan rentang delegasi/cuti atau tombol serah-terima manual. Ketidaktersediaan mengikuti kondisi akun di atas. Bila dua peninjau nonaktif permanen, pemilik dapat membatalkan dan mengajukan ulang setelah konfigurasi baru siap; perubahan peninjau pengajuan berjalan belum tersedia.
- Pengelola yang sama dapat ditetapkan pada beberapa tahap; tahap tetap memerlukan keputusan terpisah. Belum ada aturan pemisahan peninjau antartahap, percabangan nominal, paralel, SLA, outbox notifikasi, pembukaan kembali keputusan, atau ledger cuti/pembayaran.
- Pengujian Android sebelumnya tetap bukti tahap 1. Fitur bertingkat ini belum diuji ulang pada HP nyata/iOS atau APK release. Screenshot Flutter yang baru adalah hasil widget dengan fixture sintetis.

## Penerapan

Migrasi tambahan `2026_10_21_000005_add_approval_workflows.php` membuat konfigurasi versi dan menambah salinan alur/tahap/ID peninjau pada tiga tabel pengajuan; tidak mengubah data lama. Penerapan, backup, dan rollback mengikuti prosedur operator. Semua route web pengajuan/konfigurasi dan API create/list/detail/pembatalan memberi 503 jika schema H3 belum lengkap, agar konfigurasi tidak terlewati saat deployment parsial. Ini memperketat kontrak API lama yang sebelumnya masih berjalan sebelum migrasi.

Tidak menjalankan migrasi, reset atau perubahan data pada database utama; tidak deploy/push. URL mobile pengguna dan aplikasi utama tidak diubah. Rollback menghapus salinan alur dan konfigurasi, sehingga bukan pemulihan proses bisnis lengkap: jangan downgrade saat ada pengajuan bertingkat berjalan tanpa rekonsiliasi dan backup. Server QA dihentikan setelah pemeriksaan.

## Bukti

- **111 tes backend / 1.438 assertions** lulus pada SQLite memory: tiga jenis, urutan, final hanya setelah seluruh tahap, snapshot tetap setelah versi baru, peninjau pengganti, self-review, penolakan, pembatalan setelah tahap awal, reviewer tidak tersedia, edit terkunci, validasi perusahaan/role dan riwayat API. Tes baseline H0–H3 ikut lulus.
- **69 tes Flutter** lulus, termasuk parsing tahap/riwayat dan widget 320×640 dengan skala teks 1,8. Analisis menggunakan analysis server AOT SDK yang sama karena wrapper analyzer lokal bermasalah; nol error proyek dan nol diagnostik pada lima file pengajuan yang dipantau.
- **25 pemeriksaan Chrome** lulus pada database/storage sementara: penyimpanan konfigurasi melalui form/CSRF, pengajuan API memakai salinan alur, HR tidak melewati atasan, dua keputusan web, riwayat, hasil final API, supervisor hanya membaca. Light/dark desktop 1440 dan layar 390 tanpa overflow halaman. Pergantian tema tidak menutup accordion konfigurasi. [Laporan](approval-workflow-browser-qa.json).
- Satu tes ekspor widget lulus dan menghasilkan dua screenshot light/dark: [mobile light](screenshots/approval-workflows/workflow-light-widget.png), [mobile dark](screenshots/approval-workflows/workflow-dark-widget.png).
- [Konfigurasi web](screenshots/approval-workflows/configuration-dark-desktop.png), [tahap HR pada layar sempit](screenshots/approval-workflows/waiting-hr-light-narrow.png). Pemeriksaan visual dilakukan pada screenshot aktual tersebut.
- Format file terkait, sintaks, kompilasi Blade dan whitespace diperiksa; tidak menambah dependency. Kompilasi view memakai cache/storage QA terisolasi.

Alat reproduksi: `tools/mobile-qa/approval_workflow_browser.cjs`, `approval_workflow_visual_test.dart`; backend QA menyediakan `workflow-fixtures` untuk direktori disposable baru yang sudah di-seed. Jangan menjalankan fixture pada database utama.

Pemeriksaan akhir menemukan interaksi accordion dengan handler klik luar menu akun dan kesalahan sintaks pada label riwayat Blade. Handler dibatasi untuk mempertahankan accordion konfigurasi; blok riwayat ditulis ulang dengan directive terpisah. Tes backend/browser diulang pada hasil perbaikan, dan helper view QA kini memeriksa sintaks PHP hasil kompilasi, bukan hanya menjalankan `view:cache`.
