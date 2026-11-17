# H3 tahap 1 — Approval pengajuan dan audit transisi

Diperiksa 21 Oktober 2026. Berlaku untuk izin, lembur dan reimbursement pada backend/web. **Database utama belum dimigrasikan**. H2 tahap 2 (shift efektif/checkout malam) tetap ditunda sesuai arahan pengguna. Face tidak diaktifkan.

## Perilaku

- Status kanonis: `pending`, `approved`, `rejected`, `cancelled`; izin lama yang ambigu memakai `needs_review` sampai direkonsiliasi HR/admin. Form, filter dan label web membedakan seluruh status.
- HRD/admin/super admin meninjau perusahaan sendiri. Supervisor hanya meninjau bawahan langsung dari `users.manager_id`, bukan seluruh perusahaan atau bawahan bertingkat. Daftar/detail web dibatasi lingkup tersebut, ditambah pengajuan sendiri. Pengajuan sendiri tidak boleh disetujui/ditolak oleh pemilik, termasuk pemilik yang admin.
- Pengambilan keputusan memerlukan alasan (maksimal 1.000 karakter), versi form dan aktor aktif. Transaksi mengunci perusahaan, aktor dan pengajuan; scope/role/hubungan atasan diperiksa ulang terhadap data terbaru di dalam transaksi.
- Hanya pending yang dapat disetujui, ditolak atau dibatalkan. Pemilik saja boleh membatalkan pending, termasuk lewat API terautentikasi. HR/admin lain boleh menetapkan approved/rejected untuk izin needs_review dengan alasan rekonsiliasi. Pengajuan final tidak dibuka kembali melalui endpoint ini.
- Pengulangan keputusan atau form usang menghasilkan **409** tanpa event/efek tambahan. Ini penolakan replay, bukan pengembalian respons sukses idempotency-key. Pengujian paralel dengan engine produksi belum dilakukan.
- Metadata terakhir: aktor keputusan (`reviewed_by`, termasuk pemilik jika membatalkan), waktu dan alasan. Setiap transisi serta edit informasi pending menghasilkan event dengan perusahaan, jenis/ID pengajuan, aktor/waktu, versi, status sebelum/sesudah dan snapshot field bisnis sebelum/sesudah. Password, token, lokasi file dan gambar tidak disalin ke snapshot.
- Edit informasi lembur/reimbursement dipisah dari keputusan. Edit hanya di pending, menaikkan versi dan mencatat event. Pemilik reimbursement tidak dapat diganti. Form keputusan yang dibuka sebelum edit akan ditolak sampai dimuat ulang. Pengajuan final menolak edit nominal/durasi/lampiran sebelum menulis file.
- Jalur DELETE lembur/reimbursement menjadi alias pembatalan pemilik dengan alasan dan versi; **tidak ada hard delete operasional**. Tindakan Hapus di daftar dihilangkan. Pengajuan dan bukti privat tetap tersimpan setelah pembatalan.
- Web menampilkan 50 event terakhir, nama aktor, waktu dan alasan. Nama aktor merupakan nama akun saat ini, bukan snapshot nama historis.

Supervisor dapat membaca lampiran bawahan langsung melalui endpoint web untuk review. API lampiran mempertahankan kontrak sebelumnya: milik sendiri atau HR/admin perusahaan; akses supervisor API ke lampiran rekan tidak diperluas. Web memakai session dan CSRF; API pembatalan memakai autentikasi dan company middleware yang ada.

## Data legacy dan kompatibilitas

Migrasi `apps/backend/database/migrations/2026_10_21_000004_add_submission_approvals.php`:

1. Menambah status izin: boolean true dipertahankan sebagai approved, false menjadi **needs_review**, tidak ditebak pending/rejected. Tidak mengarang reviewer, waktu keputusan atau event untuk data lama.
2. Menandai existing tiga jenis pengajuan `approval_legacy=true`, menambah versi dan metadata keputusan nullable. Status lembur/reimbursement lama dipertahankan.
3. Memperluas kolom enum status lembur menjadi varchar agar cancelled dapat disimpan; tidak mengubah nilai lama.
4. Membuat `submission_approval_events`, indeks unik jenis/ID/versi, serta foreign key aktor/perusahaan.

Pengajuan baru tetap dipaksa pending oleh server. Payload klien yang memalsukan status, peninjau, versi atau pemilik saat create diabaikan. Respons create API kini memuat default database termasuk versi 0. Boolean `permissions.is_approved` tetap diproyeksikan dari keputusan approved untuk kompatibilitas; `status` adalah sumber kebenaran setelah migrasi. Home hanya menghitung izin pending sebagai antrean dan approved sebagai izin disetujui. Needs_review mempunyai filter tersendiri.

Aplikasi Flutter belum diubah pada irisan ini. Klien lama yang hanya membaca boolean izin belum membedakan pending/rejected/cancelled/needs_review; UI mobile dan pembatalan native perlu tahap setelah review web. Kontrak boolean dipertahankan dan diuji, bukan klaim tampilan native sudah mendukung status baru.

Email persetujuan langsung dari controller lama diganti pencatatan keputusan bersama tanpa pengiriman eksternal otomatis. Tahap ini tidak menjanjikan notifikasi email/push; antrean/outbox dan retry pengiriman belum tersedia. Pengujian/browser tidak mengirim email atau pesan nyata.

## Penerapan

Backup dan penerapan migrasi pending mengikuti prosedur operator. **Migrasi utama, deployment dan push tidak dilakukan**. Modul pengelolaan tiga pengajuan dan endpoint keputusan/pembatalan memberi 503 sebelum binding apabila schema approval belum lengkap. API create/detail lama tetap berjalan; pembatalan memerlukan schema baru. Home mempertahankan pembacaan boolean jika status belum tersedia, dengan label lama yang tidak menebak antrean.

Rollback H3 dahulu sebelum schema H2/H1/membership yang lebih tua. Rollback tidak mengembalikan enum lembur karena cancelled tidak muat dalam enum lama; downgrade memerlukan rekonsiliasi status dan backup. Penghapusan status izin saat rollback akan kehilangan pembedaan rejected/cancelled/needs_review pada aplikasi lama. Jangan menganggap rollback schema sebagai pemulihan penuh data workflow.

Pemindahan lampiran legacy H0 tetap langkah operator terpisah. Paket belum mempunyai approval bertingkat konfigurabel, delegasi, pembukaan kembali/koreksi keputusan final, pembatalan setelah approved, ledger saldo cuti, pembayaran reimbursement atau retensi permanen.

## Bukti

- **106 tes backend lulus, 1.327 assertions**, SQLite memory terisolasi. Tujuh tes workflow baru mencakup tiga jenis pengajuan, self-approval, tim/perusahaan, replay/stale/final, snapshot edit, pembatalan, file final, data legacy, payload palsu dan schema belum siap.
- Tes modul lama diperbarui untuk kontrak alasan/versi, keputusan final dan arsip pembatalan; tidak lagi mempertahankan perilaku self-approval atau overwrite keputusan.
- **21 checks browser Chrome headless lulus** pada SQLite/storage sementara: create API fixture, approval izin, nama/alasan history, penolakan replay, edit lalu approval lembur, penguncian file reimbursement ditolak, pembatalan pemilik dan filter cancelled. Light/dark 1440/390 tanpa overflow halaman; tabel riwayat dapat digeser.
- [Laporan browser](submission-approval-browser-qa.json), [form keputusan](screenshots/submission-approval/permission-review-light-desktop.png), [riwayat mobile dark](screenshots/submission-approval/permission-history-dark-mobile.png).
- Pint, kompilasi Blade dan diff whitespace diperiksa; cache view dibersihkan. Tidak menambah dependency. Database produksi, load/konkurensi produksi dan native mobile belum diuji ulang.

Berikutnya setelah review: pengalaman status/riwayat karyawan di mobile, lalu kebijakan/tahapan approval konfigurabel bila dipilih. H4 saldo cuti tetap menunggu fondasi jadwal H2 yang ditunda.

Pembaruan mobile pada 21 Oktober: status, detail aktor/waktu/alasan dan pembatalan tiga jenis pengajuan telah diselaraskan di Flutter. [Laporan mobile](hris-h3-mobile.md) memuat 67 tes Flutter, 106 tes backend/1.336 assertions, 27 pemeriksaan HTTP dan batas native/penerapan terkini.

Pembaruan 21 Oktober: [H3 tahap 2 alur konfigurabel](hris-h3-workflows.md) tersedia. Ketentuan satu keputusan di dokumen ini berlaku untuk pengajuan tanpa salinan alur; route API kini memerlukan schema H3 lengkap.
