# Status Cakupan Fitur HRIS (Feature Scope Status & Freeze)

Tanggal Snapshot: 4 November 2026, Asia/Jakarta.  
Status Dokumen: **Pembekuan Resmi Cakupan Versi (Version Scope Freeze)**.  
Acuan PRD: [`docs/hris-dashboard-review-closure-prd.md`](hris-dashboard-review-closure-prd.md), [`docs/hris-operational-guides-prd.md`](hris-operational-guides-prd.md).

---

> [!IMPORTANT]
> **Prinsip Pembekuan Cakupan Versi**:
> 1. **Persetujuan Pengguna Penuh**: Detail pengajuan mobile dan seluruh area Dashboard Web (H1, H2, H5, Approval, Cuti, Lembur, Reimbursement) telah **resmi disetujui pengguna** per 4 November 2026. Review visual Dashboard Web resmi ditutup.
> 2. **Cakupan Fitur Dibekukan**: Tidak ada penambahan fitur baru pada siklus ini. Pengerjaan difokuskan pada pemeliharaan stabilitas, verifikasi alur operasional, dan kepatuhan panduan penggunaan.
> 3. **Biometrik Wajah Tetap Backlog**: Fitur absensi biometrik wajah mandiri di HP tetap berada dalam status **Ditunda (Deferred)** untuk lingkungan produksi.
> 4. **Operasi Repositori & Rilis Tetap Menunggu Perintah Pengguna**: Seluruh tindakan git commit, git push, deployment server produksi, dan pengemasan komersial tetap ditunda.

---

## 1. Modul Disetujui Pengguna (User Approved)

Modul-modul berikut telah mendapatkan persetujuan visual dan fungsional final langsung dari pengguna:

### 1.1 Detail Pengajuan Mobile (Izin, Lembur, Reimbursement)
- **Tanggal Persetujuan**: 4 November 2026.
- **Cakupan yang Disetujui**:
  1. **Pemisahan Tampilan Informasi**: Catatan pemohon, keputusan peninjau terakhir, dan riwayat keputusan per tahap ditampilkan pada kartu terpisah dengan hierarki visual yang jelas.
  2. **Pelabelan Waktu Perusahaan**: Waktu keputusan dan riwayat ditampilkan dalam zona waktu perusahaan resmi (WIB, WITA, WIT) atau offset tetap; menampilkan UTC transparan jika zona IANA tidak dikenali guna mencegah salah interpretasi jam kerja.
  3. **Validasi Lampiran Sisi Klien**: Menolak file di atas 2.048 KiB (2.097.152 bytes) secara instan sebelum upload dengan pesan human-readable; mempertahankan pilihan lampiran valid sebelumnya bila penggantian file gagal.
  4. **Fallback Catatan Keputusan**: Mengutamakan atribut `decision_reason`; fallback ke `note` hanya digunakan bila terbukti berasal dari peninjau pada kontrak payload legacy (bukan catatan pemohon atau sekadar status `is_approved: false`).
  5. **Coba Lagi (Retry) Lampiran Privat**: Indikator loading mandiri dan tombol "Muat ulang lampiran" tanpa perlu memuat ulang seluruh halaman; pemutusan binding state saat pergantian akun/logout agar tidak membocorkan gambar pengguna sebelumnya; pembedaan error jaringan vs akses ditolak (403/401).
  6. **Ketahanan UI**: Bebas dari `RenderFlex` overflow pada resolusi sempit (320px–360px), teks panjang dengan wrap aman, serta kontras warna konsisten pada tema Terang (*Light*) dan Gelap (*Dark*).
- **Dasar Bukti**: 24 pengujian widget Flutter (`submission_refinement_verification_test.dart`) dan 8 pengujian approval Flutter (`submission_approval_mobile_test.dart`) lulus 100%.

### 1.2 Dashboard Web HRIS (H1, H2, H5, Approval, Cuti, Lembur, Reimbursement)
- **Tanggal Persetujuan**: 4 November 2026 (Review Visual Resmi Ditutup via [`docs/hris-dashboard-user-review.md`](hris-dashboard-user-review.md)).
- **Cakupan yang Disetujui**:
  1. **H1 Karyawan & Lifecycle**: Filter status aktif/nonaktif/arsip, retensi unit dan manajer historis nonaktif `(nonaktif)`, modal konfirmasi arsip dengan alasan wajib dan `showModal()` backdrop gelap (P1), pemulihan isian nama/telepon/alasan saat konflik 409, proteksi admin terakhir, dan deteksi no-op.
  2. **H2 Jadwal Kerja & Shift Efektif**: Penugasan shift bertanggal efektif (`effective_from`), modal pratinjau sebelum vs sesudah, baseline resolver target date dan revisi tanggal sama, copy dampak shift malam presisi, status tanpa shift, dan penanganan konflik `expected_id` (409) yang mempertahankan isian form.
  3. **H5 Kehadiran, Koreksi Absensi & Rekapitulasi**: Diff badges semantik hijau/abu-abu, teks bantuan checkout shift malam, modal pratinjau koreksi dengan copywriting ramah pengguna (P3), jejak audit penyesuaian, rekapitulasi 90 hari kalender dengan pengakuan cuti `cancellation_requested`, ekspor streaming CSV UTF-8 BOM, drilldown harian dengan retensi filter, serta pemisahan tangkapan layar narrow drawer tertutup vs terbuka (P2).
  4. **Modul Pengajuan Pendukung (Approval H3, Cuti H4, Lembur & Reimbursement)**: Filter antrean tindakan `actionable`/`waiting_others`/`all`, alur persetujuan berjenjang, buku besar saldo cuti (*ledger*), validasi durasi lembur 1–1.440 menit, kepemilikan reimbursement readonly saat edit, dan isolasi penjelasan saldo hanya pada cuti bersaldo (P4).
- **Dasar Bukti**: 35 pengujian PHPUnit terarah (`H1FlowRefinementTest`, `EffectiveShiftTest`, `H5FlowRefinementTest`, `AttendanceCorrectionRecapTest`), 18 skenario approval Chrome CDP, dan verifikasi visual desktop/narrow tema terang/gelap.

### 1.3 Paket Dokumentasi Operasional HR & Karyawan
- **Tanggal Penyelesaian**: 4 November 2026.
- **Cakupan yang Diverifikasi**:
  - Panduan lengkap pengelola HR & Administrator ([`docs/operations/hr-admin-guide.md`](operations/hr-admin-guide.md)) mencakup matriks kewenangan server, 7 checklist UI, dan 11 prosedur operasional.
  - Panduan mandiri karyawan mobile ([`docs/operations/employee-mobile-guide.md`](operations/employee-mobile-guide.md)) mencakup 12 bab alur kerja mandiri.
  - Matriks troubleshooting ([`docs/operations/troubleshooting.md`](operations/troubleshooting.md)) mencakup 10 gejala operasional wajib, langkah aman, dan aturan eskalasi nonrahasia.
  - Peta pembelajaran teknis ([`docs/operations/source-code-learning-map.md`](operations/source-code-learning-map.md)) dan batasan sistem ([`docs/operations/feature-boundaries.md`](operations/feature-boundaries.md)).
  - Indeks navigasi terpusat pada [`docs/operations/README.md`](operations/README.md) dan tautan referensi pada root `README.md`.

---

## 2. Kekurangan & Keterbatasan Operasional Terverifikasi (Known Operational Limitations)

Hasil pencocokan langsung panduan operasional terhadap aplikasi pada alur utama (tambah karyawan → atur jadwal → absensi → pengajuan → approval → rekap) mengonfirmasi batasan sistem berikut:

1. **Komponen Pemilih Karyawan Masih Menggunakan Dropdown HTML Standar**:
   - Pemilihan karyawan pada jadwal efektif (`effective-shifts`) dan formulir cuti admin (`permissions/create`) menggunakan tag `<select>` standar tanpa live search. Navigasi dropdown membutuhkan pencarian manual saat skala karyawan melebihi ratusan.
2. **Ketiadaan Notifikasi Push Otomatis saat Alur Berpindah Tahap**:
   - Perpindahan tahapan approval (Tahap 1 Supervisor ke Tahap 2 HR) berjalan otomatis di basis data dan UI, namun belum memicu notifikasi push ponsel atau email otomatis. Peninjau perlu memeriksa antrean secara berkala menggunakan filter `Konteks tindakan: Perlu tindakan saya`.
3. **Format Lampiran Bukti Khusus Gambar (JPG/PNG)**:
   - Formulir izin dan reimbursement di mobile hanya menerima berkas gambar JPG, JPEG, dan PNG (maksimal 2.048 KiB). Berkas dokumen seperti PDF atau DOCX ditolak oleh sistem mobile.
4. **Batas Agregasi Rekapitulasi Maksimal 90 Hari Kalender**:
   - Filter rekapitulasi kehadiran dibatasi maksimal 90 hari kalender per tarikan demi menjaga kestabilan kueri basis data. Laporan tahunan ditarik bertahap per triwulan.
5. **Pemulihan Sesi Menggantung > 24 Jam Memerlukan Tindakan Manual HR**:
   - Karyawan yang lupa checkout lebih dari 24 jam mengalami penguncian sesi absensi otomatis oleh server dan mewajibkan tindakan **Koreksi Absensi** manual oleh HRD/Atasan.
6. **Agregasi Unit pada Rekapitulasi Mengikuti Unit Saat Ini (*Current Unit*)**:
   - Jika karyawan mutasi divisi di pertengahan bulan, seluruh riwayat kehadiran bulan berjalan teragregasi di bawah unit kerja terbarunya.
7. **Persetujuan Pembatalan Cuti Menunggu Keputusan HR**:
   - Pembatalan cuti yang telah disetujui berstatus `cancellation_requested` dan tetap sah memotong hari kerja sampai HRD menyetujui pembatalan tersebut.

---

## 3. Fitur Ditunda (Deferred)

Modul dan tindakan berikut secara tegas **DITUNDA** dari lingkungan produksi:

1. **Absensi Wajah Produksi (Face Recognition & Liveness)**:
   - Evaluasi eksperimental MobileNetV3 / MiniFASNet tetap berada dalam mode audit/PoC.
   - Absensi wajah mandiri di HP tidak diaktifkan pada jalur produksi utama demi menjaga keandalan operasional.
2. **Operasi Repositori & Distribusi**:
   - **Git Commit & Git Push**: Ditunda sesuai instruksi pengguna.
   - **Deployment Server Produksi**: Ditunda.
   - **Pengemasan Komersial Source Code**: Ditunda.

---

## 4. Backlog Opsional (Non-Blocking Backlog)

Poin-poin berikut telah dicatat sebagai rencana peningkatan sekunder untuk rilis di masa depan:

1. **Rotasi Shift Otomatis**: Penjadwalan pola rotasi bergilir berulang (misal: 4 hari kerja pagi, 2 hari libur, 4 hari malam) secara otomatis.
2. **Penugasan Shift Massal (*Bulk Shift Assignment*)**: Antarmuka unggah file lembar kerja (*spreadsheet*) untuk menugaskan jadwal ratusan karyawan sekaligus.
3. **Pencarian Cepat Karyawan Dropdown (*Virtualized Live Search*)**: Komponen pemilih karyawan interaktif berbasis virtual scrolling untuk perusahaan berskala besar (> 10.000 karyawan).
4. **Kalender Visual Grid (*Visual Work Calendar*)**: Tampilan kalender interaktif bulanan/mingguan bergaya matriks kalender meja.
5. **Dukungan Shift Terpisah (*Split Shift*)**: Pengaturan jadwal kerja terpisah dalam 1 hari kalender yang sama (misal: sesi siang dan sesi malam).

---

## 5. Ringkasan Matriks Status Fitur

| Area / Modul | Platform | Status Cakupan | Sumber Bukti Terakhir | Catatan Verifikasi |
|:---|:---:|:---:|:---|:---|
| **Detail Pengajuan (Izin, Lembur, Reimbursement)** | Mobile Flutter | **Disetujui Pengguna** | Persetujuan Pengguna (4 Nov 2026); 24 widget tests | Selesai dan disetujui penuh oleh pengguna. |
| **Master Karyawan & Lifecycle (H1)** | Web Dashboard | **Disetujui Pengguna** | Review Visual Pengguna (4 Nov 2026); `H1FlowRefinementTest` | Modal arsip showModal(), backdrop gelap, 409 pulih via old(). |
| **Penjadwalan & Shift Efektif (H2)** | Web Dashboard | **Disetujui Pengguna** | Review Visual Pengguna (4 Nov 2026); `EffectiveShiftTest` | Baseline target date, modal preview, konflik expected_id tervalidasi. |
| **Kehadiran, Koreksi & Rekap (H5)** | Web Dashboard | **Disetujui Pengguna** | Review Visual Pengguna (4 Nov 2026); `H5FlowRefinementTest` | Diff badges, copy koreksi lugas (P3), rekap 90 hari, CSV BOM tervalidasi. |
| **Workflow Approval (H3)** | Web & Mobile | **Disetujui Pengguna** | Review Visual Pengguna (4 Nov 2026); `ApprovalFlowRefinementTest` | Filter antrean actionable/waiting_others, snapshot alur, 409 legacy. |
| **Cuti, Rentang & Saldo (H4)** | Web & Mobile | **Disetujui Pengguna** | Review Visual Pengguna (4 Nov 2026); 141 tes backend | Evaluasi hari kerja dinamis, buku besar saldo cuti teruji. |
| **Pengajuan Lembur & Reimbursement** | Web Dashboard | **Disetujui Pengguna** | Review Visual Pengguna (4 Nov 2026); visual review | Validasi 1–1.440 menit, pemilik readonly, bebas bias saldo (P4). |
| **Paket Panduan Operasional** | Web & Mobile | **Terverifikasi Lengkap** | `docs/operations/` & `docs/hris-operational-guides-report.md` | 6 berkas operasional selaras 100% dengan alur kerja nyata. |
| **Absensi Wajah Produksi** | Mobile / Server | **Ditunda (Deferred)** | `face-mn3-poc.md`, `face-liveness-validation.md` | Biometrik tetap nonaktif untuk keamanan produksi. |
| **Git Commit, Push & Deployment** | Infrastruktur | **Ditunda (Deferred)** | Arahan Pengguna 4 Nov 2026 | Pembekuan cakupan bukan izin commit/deploy. |
| **Pola Rotasi & Bulk Shift Assignment** | Web Dashboard | **Backlog Opsional** | `docs/operations/feature-boundaries.md` | Rencana pengembangan masa depan. |

---

## 6. Pernyataan Pembekuan Cakupan Versi

Dengan disetujuinya hasil review visual Dashboard Web pada 4 November 2026 dan tuntasnya pencocokan panduan operasional pada enam alur utama (tambah karyawan → atur jadwal → absensi → pengajuan → approval → rekap), **seluruh cakupan fungsional HRIS versi ini dinyatakan DIBEKUKAN (FROZEN)**.

Tidak ada siklus penambahan fitur baru yang dibuka. Seluruh keterbatasan operasional telah terdokumentasi secara transparan, fitur biometrik wajah serta opsi sekunder tetap dicatat sebagai backlog, dan kode sumber siap menunggu instruksi lebih lanjut dari pengguna terkait operasi git commit, push, atau pengemasan rilis.
