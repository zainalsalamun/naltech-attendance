# PRD: Peningkatan Keterbatasan Operasional HRIS (Fase 1: Backend & Dashboard Web)

Tanggal: 18 November 2026, Asia/Jakarta.  
Acuan: [`docs/hris-feature-scope-status.md`](hris-feature-scope-status.md) Bagian 2 (*Known Operational Limitations*), [`AGENTS.md`](../AGENTS.md).

---

## 1. Latar Belakang & Tujuan

Pada snapshot 4 November 2026 ([`docs/hris-feature-scope-status.md`](hris-feature-scope-status.md)), tiga keterbatasan operasional utama tercatat pada alur HRIS:
1. Perpindahan tahap *approval* belum memicu notifikasi push atau email otomatis ke peninjau/pemohon.
2. Pemilih karyawan (`<select name="user_id">`) di Dashboard Web masih berupa dropdown HTML standar tanpa *live search*.
3. Lampiran pengajuan izin dan reimbursement hanya menerima format gambar (`JPG`/`PNG`/`GIF`), belum mendukung dokumen **PDF**.

Sesuai arahan pengguna dan aturan repositori (`AGENTS.md`), pengerjaan dilakukan bertahap:
- **Fase 1 (Saat Ini)**: Implementasi pada **Backend Laravel & Dashboard Web** (`apps/backend`), disertai pengujian otomatis PHPUnit.
- **Fase 2 (Setelah Review Web)**: Implementasi sisi **Mobile Flutter** (`apps/mobile`) untuk upload/preview PDF dan penanganan tap notifikasi FCM.

---

## 2. Cakupan Fitur Fase 1 (Backend & Dashboard Web)

### F1. Notifikasi Push (FCM) & Email Otomatis pada Alur Approval
- **Tujuan**: Memberi tahu peninjau aktif saat ada pengajuan baru atau perpindahan tahap *approval*, serta memberi tahu pemohon saat status pengajuannya berubah (*disetujui*, *ditolak*, *dibatalkan*, atau *permintaan pembatalan*).
- **Pemicu (*Lifecycle Events*)**:
  1. **Pengajuan Baru (`created`)**: Saat `Permission`, `Overtime`, atau `Reimbursement` dibuat dalam status `pending` → kirim notifikasi ke **peninjau tahap aktif** (`ApprovalWorkflow::reviewer($row)`).
  2. **Naik Tahap (`pending` → `pending` tahap berikutnya)**: Saat peninjau tahap 1 menyetujui dan masih ada tahap lanjutan → kirim notifikasi ke **peninjau tahap berikutnya**, serta info progres ke **pemohon**.
  3. **Keputusan Final (`approved` / `rejected`)**: Kirim notifikasi hasil keputusan beserta `decision_reason` ke **pemohon**.
  4. **Permintaan Pembatalan (`cancellation_requested`)**: Saat pemohon mengajukan pembatalan cuti yang sudah disetujui → kirim notifikasi ke **peninjau aktif / HRD/Admin perusahaan**.
  5. **Keputusan Pembatalan (`cancellation_requested` → `cancelled` / `approved`)**: Kirim notifikasi hasil keputusan HR ke **pemohon**.
- **Kanal Pengiriman**:
  - **Email**: Menggunakan Laravel `Mail` (`App\Mail\ApprovalStatusNotification`) ke alamat `email` penerima jika valid.
  - **Push Notification (FCM)**: Menggunakan `kreait/laravel-firebase` (`Messaging`) ke `user.fcm_token` jika tersedia dan konfigurasi Firebase aktif.
- **Keandalan & Isolasi**:
  - Pengiriman dijalankan melalui `DB::afterCommit(...)` agar tidak pernah mengirim notifikasi untuk transaksi yang di-rollback.
  - Kegagalan pengiriman (mis. kredensial Firebase belum diatur di lokal, token kadaluarsa, atau SMTP error) dicatat ke log non-rahasia (`Log::warning`) tanpa menggagalkan proses *approval* utama.
  - Tidak ada kredensial/token yang ditulis ke log.

### F2. Searchable Dropdown Karyawan di Dashboard Web
- **Tujuan**: Memudahkan HR/Admin mencari karyawan secara instan (*live search*) pada halaman manajemen Web tanpa mengubah kontrak `name="user_id"` maupun validasi server.
- **Lokasi Penerapan**:
  - `pages/effective-shifts/index.blade.php` (pemilih karyawan untuk lihat/atur jadwal efektif)
  - `pages/reimbursements/form.blade.php` (pemilih karyawan saat tambah reimbursement)
  - `pages/overtime/form.blade.php` (pemilih karyawan saat tambah lembur)
  - `pages/leave_balances/index.blade.php` (pemilih karyawan pada alokasi saldo cuti)
  - `pages/leave_balances/ledger.blade.php` (filter karyawan pada buku besar saldo cuti)
- **Desain Komponen**:
  - Komponen Blade reusable: `resources/views/components/searchable-select.blade.php`.
  - Membungkus `<select>` asli agar tetap *progressive enhancement*, mendukung `required`, `old()`, aksesibilitas keyboard (`ArrowUp`, `ArrowDown`, `Enter`, `Escape`), dan selaras dengan token tema Light/Dark (`design.md`).

### F3. Dukungan Lampiran PDF (Backend & Dashboard Web)
- **Tujuan**: Mengizinkan unggahan dan pratinjau berkas **PDF** (maksimal **2.048 KiB**) selain gambar (`JPG`/`JPEG`/`PNG`/`GIF`) pada pengajuan Izin/Cuti dan Reimbursement.
- **Cakupan Backend**:
  - Validasi Web & API untuk `Permission` dan `Reimbursement`: ganti aturan `image` menjadi `file|mimes:jpeg,png,jpg,pdf|max:2048` (tetap pertahankan `gif` pada web reimbursement bila perlu, plus `pdf`).
  - `App\Services\SubmissionAttachment`:
    - `privatePath()`: izinkan ekstensi `pdf`.
    - `response()`: izinkan MIME `application/pdf`, pertahankan header keamanan (`Cache-Control: private, no-store`, `X-Content-Type-Options: nosniff`). Untuk PDF gunakan `Content-Disposition: inline` agar dapat dibuka langsung di browser terautentikasi tanpa melemahkan isolasi gambar.
- **Cakupan Web**:
  - Input file (`accept="image/jpeg,image/png,application/pdf"` atau termasuk `image/gif` di reimbursement) dan teks bantuan pada form `permission/create`, `permission/edit`, dan `reimbursements/form`.
  - Tampilan detail/bukti (`permission/details.blade.php`, `reimbursements/proof.blade.php`) menampilkan tautan "Buka lampiran PDF" / pratinjau yang sesuai saat ekstensi file adalah `.pdf`.

---

## 3. Cakupan Fase 2 (Mobile Flutter — Menunggu Review Web)

Akan dikerjakan pada `apps/mobile` setelah Fase 1 direview:
1. Pemilih berkas PDF (selain galeri/kamera) pada form pengajuan Izin & Reimbursement dengan batas validasi klien 2.048 KiB.
2. Penampil/pembuka lampiran PDF privat terautentikasi pada halaman detail pengajuan mobile.
3. Handler FCM foreground/background & navigasi langsung ke detail pengajuan saat notifikasi di-tap.

---

## 4. Rencana Pengujian (Fase 1)

1. **PHPUnit**:
   - `ApprovalNotificationTest`: verifikasi email & FCM trigger pada pengajuan baru, naik tahap (tahap 1 → tahap 2), approval final, penolakan, permintaan pembatalan, serta ketahanan saat FCM/Mail gagal (tidak menggagalkan transaksi).
   - `SubmissionPdfAttachmentTest`: verifikasi upload PDF valid (web & API) untuk Permission dan Reimbursement, penolakan file > 2048 KB atau MIME non-diizinkan, serta respon `200 application/pdf` pada endpoint `submission-attachments.show` dengan isolasi otorisasi perusahaan/pemilik.
   - Regresi suite: `EffectiveShiftTest`, `H1FlowRefinementTest`, `H5FlowRefinementTest`, `ApprovalFlowRefinementTest`.
2. **Kompilasi Blade & Aset**:
   - `php artisan view:cache` & `php artisan view:clear`.
