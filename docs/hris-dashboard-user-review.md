# Paket Review Pengguna — Dashboard Web HRIS (H1, H2, H5, Lembur & Reimbursement)

Tanggal: 4 November 2026, Asia/Jakarta.  
Status Dokumen: **Disetujui Pengguna (Review Visual Ditutup)**.

---

> [!IMPORTANT]
> **Status Persetujuan Pengguna Saat Ini**:
> - **Detail Pengajuan Mobile (Izin, Lembur, Reimbursement)**: **Telah Disetujui Pengguna** pada 4 November 2026.
> - **Dashboard Web (H1, H2, H5, Lembur & Reimbursement)**: **Telah Disetujui Pengguna** pada 4 November 2026 setelah seluruh perbaikan temuan P1–P4 diverifikasi dan diselaraskan penuh.
> - **Operasi Git & Rilis**: Commit, push, deployment, dan pengemasan komersial tetap ditunda sesuai instruksi pengguna.

---

## Panduan Peninjauan

Dokumen ini disusun untuk memudahkan pengguna meninjau implementasi antarmuka Dashboard Web yang telah diverifikasi secara teknis (termasuk verifikasi otomatis Chrome headless CDP dan tes backend terarah).

Untuk setiap bagian, disediakan:
1. **Skenario dan Kondisi Pengujian**: Deskripsi state antarmuka.
2. **Konteks Viewport & Tema**: Desktop (1440×1000) dan Sempit/Mobile (390×844) pada tema Terang (*Light*) dan Gelap (*Dark*).
3. **Catatan Perubahan & Verifikasi**: Perilaku teknis yang dibuktikan (pencegahan lost update, retensi isian saat konflik, baseline resolver jadwal, audit trail koreksi, dan integritas rekap).
4. **Bukti Gambar**: Tautan dan sematan gambar aktual hasil tangkapan browser.
5. **Tabel Keputusan Pengguna**: Kolom checklist bagi pengguna untuk memberikan evaluasi `[Disetujui / Perlu Revisi]`.

---

## 1. Area H1: Master Karyawan, Lifecycle, Arsip & Penanganan Konflik

### Skenario & Fitur yang Ditinjau
- **Daftar & Filter Status**: Pemisahan status aktif, nonaktif, dan arsip dengan badge status yang jelas.
- **Formulir Tambah & Edit Karyawan**: Dukungan unit organisasi dan retensi unit/atasan historis nonaktif dengan label `(nonaktif)`.
- **Modal Dialog Arsipkan Karyawan**: Dialog modal konfirmasi tindakan destruktif dengan input alasan wajib dan penonaktifan akses login.
- **Pencegahan Lost Update & Konflik Konkurensi**: Saat dua pengelola mengedit atau mengarsipkan karyawan yang sama, request dengan `version` usang ditolak (HTTP 409 Conflict), pesan notice semantik amber `#user-conflict-notice` ditampilkan, dan seluruh isian pengguna (nama, telepon, alasan perubahan/arsip) dipulihkan utuh via `old()` tanpa mutasi database. Pada arsip, modal target ID yang tepat terbuka kembali secara otomatis.

### Bukti Visual Aktual

#### 1.1 Daftar Karyawan (Light Desktop, Dark Desktop, Dark Narrow)
- **Light Desktop (1440×1000)**:  
  ![H1 Daftar Karyawan Light Desktop](screenshots/web-visual-review/01-h1-users-index-light-desktop.png)  
  *Path file*: `docs/screenshots/web-visual-review/01-h1-users-index-light-desktop.png`
- **Dark Desktop (1440×1000)**:  
  ![H1 Daftar Karyawan Dark Desktop](screenshots/web-visual-review/01-h1-users-index-dark-desktop.png)  
  *Path file*: `docs/screenshots/web-visual-review/01-h1-users-index-dark-desktop.png`
- **Dark Narrow (390×844)**:  
  ![H1 Daftar Karyawan Dark Narrow](screenshots/web-visual-review/01-h1-users-index-dark-narrow.png)  
  *Path file*: `docs/screenshots/web-visual-review/01-h1-users-index-dark-narrow.png`

#### 1.2 Formulir Tambah & Edit Karyawan (Retensi Unit/Atasan Nonaktif)
- **Tambah Karyawan Desktop**:  
  ![H1 Tambah Karyawan](screenshots/web-visual-review/02-h1-users-create-desktop.png)  
  *Path file*: `docs/screenshots/web-visual-review/02-h1-users-create-desktop.png`
- **Edit Karyawan Desktop**:  
  ![H1 Edit Karyawan](screenshots/web-visual-review/03-h1-users-edit-desktop.png)  
  *Path file*: `docs/screenshots/web-visual-review/03-h1-users-edit-desktop.png`

#### 1.3 Modal Dialog Arsipkan Karyawan
- **Dialog Modal Arsip**:  
  ![H1 Modal Arsip Karyawan](screenshots/web-visual-review/04-h1-users-archive-dialog.png)  
  *Path file*: `docs/screenshots/web-visual-review/04-h1-users-archive-dialog.png`

#### 1.4 Penanganan Konflik Konkurensi & Pemulihan Input
- **Konflik Edit Data Karyawan (Notice Amber & Input Pulih)**:  
  ![H1 Konflik Edit Karyawan](screenshots/web-review-closure/P1-h1-conflict-update-recovered.png)  
  *Path file*: `docs/screenshots/web-review-closure/P1-h1-conflict-update-recovered.png`  
  *Catatan*: Input nama (`Deni Pratama Input Pulih`), telepon (`081299998888`), dan alasan perubahan tetap utuh di form edit.
- **Konflik Arsipkan Karyawan (Dialog Target Terbuka Kembali via showModal() & Alasan Pulih)**:  
  ![H1 Konflik Arsip Karyawan](screenshots/web-review-closure/P1-h1-conflict-archive.png)  
  *Path file*: `docs/screenshots/web-review-closure/P1-h1-conflict-archive.png`  
  *Catatan*: Dialog arsip karyawan sasaran (ID 25) otomatis dibuka kembali menggunakan `showModal()`. Backdrop modal aktif menggelapkan halaman latar belakang, fokus terkunci di dalam dialog pada textarea alasan, alasan arsip terpelihara utuh, dan notice konflik 409 tampil jelas.

---

## 2. Area H2: Penjadwalan, Shift Efektif, Baseline Resolver & Konflik

### Skenario & Fitur yang Ditinjau
- **Pemilih Karyawan & Riwayat Penugasan**: Daftar riwayat shift efektif per karyawan dengan tanggal mulai berlaku (`effective_from`).
- **Modal Pratinjau Penugasan Jadwal (`#shift-assignment-preview-dialog`)**: Tabel perbandingan sebelum vs sesudah (nama shift, jam masuk/pulang, dan tanggal berlaku) beserta alasan perubahan.
- **Baseline Resolver Tanggal Target**:
  - Penugasan tanggal mendatang: Baseline "Sebelum" membandingkan jadwal yang aktif pada tanggal target yang dipilih, bukan sekadar jadwal hari ini.
  - Revisi penugasan tanggal sama: Baseline "Sebelum" membandingkan penugasan revisi terakhir pada tanggal tersebut.
- **Pencegahan Lost Update (`expected_id`)**: Penugasan konkuren multi-admin ditolak saat versi basis usang, notice amber ditampilkan, dan seluruh isian (`shift_id`, `effective_from`, `reason`) dipulihkan utuh via `old()`.
- **Copy Teks Shift Malam & Tanpa Shift**:
  - Shift malam: *"Jam pulang berada pada hari berikutnya. Tanggal kerja mengikuti jadwal yang ditentukan server dan dapat berbeda dari tanggal kalender saat check-in."*
  - Hari libur vs Tanpa Shift: Tanpa shift tidak dianggap libur perusahaan otomatis, melainkan status eksplisit "Tanpa shift".

### Bukti Visual Aktual

#### 2.1 Indeks Shift Efektif (Light Desktop, Dark Desktop, Dark Narrow)
- **Light Desktop (1440×1000)**:  
  ![H2 Shift Efektif Light Desktop](screenshots/web-visual-review/05-h2-effective-shifts-light-desktop.png)  
  *Path file*: `docs/screenshots/web-visual-review/05-h2-effective-shifts-light-desktop.png`
- **Dark Desktop (1440×1000)**:  
  ![H2 Shift Efektif Dark Desktop](screenshots/web-visual-review/06-h2-effective-shifts-dark-desktop.png)  
  *Path file*: `docs/screenshots/web-visual-review/06-h2-effective-shifts-dark-desktop.png`
- **Dark Narrow (390×844)**:  
  ![H2 Shift Efektif Dark Narrow](screenshots/web-visual-review/07-h2-effective-shifts-dark-narrow.png)  
  *Path file*: `docs/screenshots/web-visual-review/07-h2-effective-shifts-dark-narrow.png`

#### 2.2 Modal Pratinjau Penugasan Jadwal (Sebelum vs Sesudah)
- **Modal Pratinjau Light Desktop**:  
  ![H2 Modal Pratinjau Light](screenshots/effective-shifts/preview-dialog-light-desktop.png)  
  *Path file*: `docs/screenshots/effective-shifts/preview-dialog-light-desktop.png`
- **Modal Pratinjau Dark Desktop**:  
  ![H2 Modal Pratinjau Dark](screenshots/effective-shifts/preview-dialog-dark-desktop.png)  
  *Path file*: `docs/screenshots/effective-shifts/preview-dialog-dark-desktop.png`
- **Modal Pratinjau Dark Mobile (390×844)**:  
  ![H2 Modal Pratinjau Dark Mobile](screenshots/effective-shifts/preview-dialog-dark-mobile.png)  
  *Path file*: `docs/screenshots/effective-shifts/preview-dialog-dark-mobile.png`

#### 2.3 Baseline Resolver (Tanggal Mendatang & Revisi Tanggal Sama)
- **Baseline Penugasan Tanggal Mendatang (P3-05)**:  
  ![H2 Baseline Tanggal Mendatang](screenshots/web-review-closure/P3-h2-baseline-future-date.png)  
  *Path file*: `docs/screenshots/web-review-closure/P3-h2-baseline-future-date.png`  
  *Catatan*: Baseline membandingkan *Shift Siang (15:00–23:00)* yang berlaku per 2026-11-01 terhadap penugasan baru *Malam* per 2026-11-05.
- **Baseline Revisi Penugasan Tanggal Sama (P3-12)**:  
  ![H2 Baseline Revisi Tanggal Sama](screenshots/web-review-closure/P3-h2-baseline-same-date-revision.png)  
  *Path file*: `docs/screenshots/web-review-closure/P3-h2-baseline-same-date-revision.png`  
  *Catatan*: Baseline membandingkan penugasan revisi sebelumnya *Malam (22:00–06:00)* pada 2026-11-05 terhadap revisi baru *Reguler*.

#### 2.4 Penanganan Konflik Konkurensi & Pemulihan Input
- **Konflik Jadwal (Notice Amber & Input Pulih - Light)**:  
  ![H2 Konflik Light Desktop](screenshots/effective-shifts/conflict-preserved-light-desktop.png)  
  *Path file*: `docs/screenshots/effective-shifts/conflict-preserved-light-desktop.png`
- **Konflik Jadwal (Notice Amber & Input Pulih - Dark)**:  
  ![H2 Konflik Dark Desktop](screenshots/effective-shifts/conflict-preserved-dark-desktop.png)  
  *Path file*: `docs/screenshots/effective-shifts/conflict-preserved-dark-desktop.png`

---

## 3. Area H5: Kehadiran, Koreksi Absensi, Audit Trail & Rekapitulasi

### Skenario & Fitur yang Ditinjau
- **Daftar Absensi & Formulir Edit Koreksi**: Bantuan tanggal pulang untuk shift malam (*"Isi tanggal pulang sesuai kejadian sebenarnya; pada shift malam biasanya hari berikutnya."*).
- **Indikator Badge Diff Sebelum vs Sesudah**: Badge semantik hijau `"Diubah"` dan abu-abu `"Tidak berubah"` untuk setiap kolom (Jam Masuk, Jam Pulang, Tanggal, Koordinat Masuk, Koordinat Pulang).
- **Modal Pratinjau Koreksi Absensi (`#correction-preview-dialog`)**: Penegakan alasan koreksi wajib hasil trim, penutupan tanpa mutasi dengan pemulihan fokus, dan deteksi no-op tanpa penambahan audit log fiktif.
- **Jejak Audit Koreksi Absensi (`/attendance-corrections`)**: Menampilkan histori perubahan lengkap dengan nama penyesuai, alasan, dan perbandingan nilai lama/baru.
- **Rekapitulasi Kehadiran (`/attendance-recap`)**:
  - Filter rentang tanggal maksimal 90 hari, pencarian nama, dan unit organisasi.
  - Sinkronisasi status: Hari libur tetap menjadi status utama (`holiday`), izin menunggu pembatalan (`cancellation_requested`) tetap dihitung cuti efektif (1 hari kerja).
  - Ekspor CSV streaming dengan UTF-8 BOM (`\xEF\xBB\xBF`) dan proteksi formula injection.
  - Rute drilldown detail harian (`/attendance-recap/{user}`) dengan tombol kembali yang mempertahankan query filter secara aman.

### Bukti Visual Aktual

#### 3.1 Daftar Absensi & Edit Koreksi dengan Diff Badges
- **Daftar Absensi Desktop**:  
  ![H5 Daftar Absensi](screenshots/web-visual-review/09-h5-attendances-desktop.png)  
  *Path file*: `docs/screenshots/web-visual-review/09-h5-attendances-desktop.png`
- **Diff Badges & Helper Text Shift Malam**:  
  ![H5 Diff Badges Koreksi](screenshots/web-review-closure/P2-h5-diff-badges.png)  
  *Path file*: `docs/screenshots/web-review-closure/P2-h5-diff-badges.png`  
  *Catatan*: Menampilkan badge `"Diubah"` (Jam Masuk, Tanggal, Koordinat Pulang) dan `"Tidak berubah"` (Jam Pulang, Koordinat Masuk) serta teks bantuan tanggal checkout malam.

#### 3.2 Modal Pratinjau Koreksi Absensi Sebelum Simpan
- Teks konfirmasi menggunakan kalimat produk yang lugas: *"Perubahan dan alasan koreksi akan disimpan dalam riwayat. Pastikan seluruh detail telah sesuai sebelum konfirmasi."* (menggantikan istilah teknis transaksi atomik).
- **Pratinjau Koreksi Light Desktop**:  
  ![H5 Pratinjau Koreksi Light](screenshots/h5-flow-refinement/H5-01-correction-preview-dialog-light-desktop.png)  
  *Path file*: `docs/screenshots/h5-flow-refinement/H5-01-correction-preview-dialog-light-desktop.png`
- **Pratinjau Koreksi Dark Desktop**:  
  ![H5 Pratinjau Koreksi Dark](screenshots/h5-flow-refinement/H5-01-correction-preview-dialog-dark-desktop.png)  
  *Path file*: `docs/screenshots/h5-flow-refinement/H5-01-correction-preview-dialog-dark-desktop.png`

#### 3.3 Halaman Penelusuran Jejak Audit Koreksi (`/attendance-corrections`)
- **Audit Koreksi Light Desktop (1440×1000)**:  
  ![H5 Audit Trail Light Desktop](screenshots/web-visual-review/11-h5-attendance-corrections-light-desktop.png)  
  *Path file*: `docs/screenshots/web-visual-review/11-h5-attendance-corrections-light-desktop.png`
- **Audit Koreksi Dark Desktop (1440×1000)**:  
  ![H5 Audit Trail Dark Desktop](screenshots/web-visual-review/12-h5-attendance-corrections-dark-desktop.png)  
  *Path file*: `docs/screenshots/web-visual-review/12-h5-attendance-corrections-dark-desktop.png`
- **Audit Koreksi Dark Narrow (390×844)**:  
  ![H5 Audit Trail Dark Narrow](screenshots/web-visual-review/13-h5-attendance-corrections-dark-narrow.png)  
  *Path file*: `docs/screenshots/web-visual-review/13-h5-attendance-corrections-dark-narrow.png`

#### 3.4 Rekapitulasi Periode Kehadiran & Drilldown (`/attendance-recap`)
- **Rekapitulasi Kehadiran Light Desktop**:  
  ![H5 Rekapitulasi Light Desktop](screenshots/web-visual-review/14-h5-attendance-recap-light-desktop.png)  
  *Path file*: `docs/screenshots/web-visual-review/14-h5-attendance-recap-light-desktop.png`
- **Rekapitulasi Kehadiran Dark Desktop**:  
  ![H5 Rekapitulasi Dark Desktop](screenshots/web-visual-review/15-h5-attendance-recap-dark-desktop.png)  
  *Path file*: `docs/screenshots/web-visual-review/15-h5-attendance-recap-dark-desktop.png`
- **Rekapitulasi Kehadiran Dark Narrow (Drawer Tertutup)**:  
  ![H5 Rekapitulasi Dark Narrow](screenshots/web-visual-review/16-h5-attendance-recap-dark-narrow.png)  
  *Path file*: `docs/screenshots/web-visual-review/16-h5-attendance-recap-dark-narrow.png`  
  *Catatan*: Diambil dengan drawer mobile tertutup penuh. Judul, breadcrumb, subtitle, tombol ekspor CSV, kartu metrik agregat, dan kartu filter periode terlihat utuh tanpa terhalang sidebar.
- **Navigasi Drawer Mobile Layar Sempit (State Terpisah)**:  
  ![H5 Rekapitulasi Drawer Terbuka](screenshots/web-visual-review/16b-h5-attendance-recap-drawer-open-dark-narrow.png)  
  *Path file*: `docs/screenshots/web-visual-review/16b-h5-attendance-recap-drawer-open-dark-narrow.png`  
  *Catatan*: Pengujian drawer navigasi mobile sebagai state terpisah saat tombol hamburger menu diklik, menampilkan navigasi hirarkis dengan item aktif 'Rekap kehadiran' dan tombol tutup ✕.
- **Status Cuti Menunggu Pembatalan (`cancellation_requested`) Terhitung Efektif**:  
  ![H5 Rekap Cancellation Requested](screenshots/h5-flow-refinement/H5-06-recap-cancellation-requested-light-desktop.png)  
  *Path file*: `docs/screenshots/h5-flow-refinement/H5-06-recap-cancellation-requested-light-desktop.png`
- **Drilldown Detail Harian (Hari Libur Prioritas Status Utama)**:  
  ![H5 Detail Harian Prioritas Libur](screenshots/h5-flow-refinement/H5-09-daily-breakdown-light-desktop.png)  
  *Path file*: `docs/screenshots/h5-flow-refinement/H5-09-daily-breakdown-light-desktop.png`

---

## 4. Modul Pendukung: Pengajuan Lembur & Reimbursement Web

### Skenario & Fitur yang Ditinjau
- **Lembur Web**:
  - Batasan durasi lembur: Validasi form web dan API konsisten 1–1.440 menit (membatasi durasi pengajuan satu hari).
  - Paginasi 20 item dengan pengurutan deterministik tanggal dan ID.
  - Detail pengajuan memisahkan catatan pemohon, riwayat peninjau, dan durasi kerja.
- **Reimbursement Web**:
  - Pemilik pengajuan bersifat *read-only* saat mode edit (mencegah pemindahan pemilik pengajuan).
  - Validasi nominal dan lampiran file.
  - Detail pengajuan memisahkan alasan, nominal disetujui, dan lampiran struk/nota.
  - **Pemberitahuan Keputusan Bebas Bias Saldo**: Penjelasan penolakan pada modul biaya/reimbursement dan lembur dirapikan menjadi *"Penolakan akan mengakhiri pengajuan."* tanpa memunculkan kalimat "mengembalikan saldo cadangan", yang kini dikhususkan hanya untuk cuti bersaldo.

### Bukti Visual Aktual

#### 4.1 Lembur Web
- **Indeks Lembur Light Desktop**:  
  ![Lembur Indeks Light](screenshots/web-visual-review/17-overtime-index-light-desktop.png)  
  *Path file*: `docs/screenshots/web-visual-review/17-overtime-index-light-desktop.png`
- **Indeks Lembur Dark Desktop**:  
  ![Lembur Indeks Dark](screenshots/web-visual-review/18-overtime-index-dark-desktop.png)  
  *Path file*: `docs/screenshots/web-visual-review/18-overtime-index-dark-desktop.png`
- **Form Pengajuan Lembur (Validasi 1–1.440 Menit)**:  
  ![Lembur Tambah Desktop](screenshots/web-visual-review/20-overtime-create-desktop.png)  
  *Path file*: `docs/screenshots/web-visual-review/20-overtime-create-desktop.png`
- **Detail Pengajuan Lembur**:  
  ![Lembur Detail Desktop](screenshots/web-visual-review/21-overtime-detail-desktop.png)  
  *Path file*: `docs/screenshots/web-visual-review/21-overtime-detail-desktop.png`

#### 4.2 Reimbursement Web
- **Indeks Reimbursement Light Desktop**:  
  ![Reimbursement Indeks Light](screenshots/web-visual-review/22-reimbursement-index-light-desktop.png)  
  *Path file*: `docs/screenshots/web-visual-review/22-reimbursement-index-light-desktop.png`
- **Indeks Reimbursement Dark Desktop**:  
  ![Reimbursement Indeks Dark](screenshots/web-visual-review/23-reimbursement-index-dark-desktop.png)  
  *Path file*: `docs/screenshots/web-visual-review/23-reimbursement-index-dark-desktop.png`
- **Form Edit Reimbursement (Pemilik Readonly)**:  
  ![Reimbursement Edit Readonly](screenshots/web-visual-review/26-reimbursement-edit-readonly-desktop.png)  
  *Path file*: `docs/screenshots/web-visual-review/26-reimbursement-edit-readonly-desktop.png`
- **Detail Pengajuan Reimbursement**:  
  ![Reimbursement Detail Desktop](screenshots/web-visual-review/27-reimbursement-detail-desktop.png)  
  *Path file*: `docs/screenshots/web-visual-review/27-reimbursement-detail-desktop.png`

---

## 5. Matriks Keputusan Review Pengguna

Hasil keputusan pemeriksaan visual pengguna per 4 November 2026:

| No | Modul / Skenario | Bukti Gambar Utama | Catatan Verifikasi Agen | Keputusan Pengguna |
|:---|:---|:---|:---|:---:|
| 1 | **H1: Karyawan - Indeks & Status** | `01-h1-users-index-light-desktop.png`<br>`01-h1-users-index-dark-desktop.png` | Badge status aktif/nonaktif/arsip jelas; layout responsif pada desktop & 390px. | `[x] Disetujui` |
| 2 | **H1: Karyawan - Retensi Organisasi Nonaktif** | `03-h1-users-edit-desktop.png` | Unit/atasan nonaktif dipertahankan dengan label `(nonaktif)`; pilihan baru diblokir. | `[x] Disetujui` |
| 3 | **H1: Karyawan - Dialog Arsip** | `04-h1-users-archive-dialog.png` | Modal konfirmasi mewajibkan alasan; proteksi admin terakhir aktif. | `[x] Disetujui` |
| 4 | **H1: Karyawan - Konflik Update & Arsip** | `P1-h1-conflict-update-recovered.png`<br>`P1-h1-conflict-archive.png` | Notice amber HTTP 409 tampil jelas; input nama, telp, alasan pulih via `old()`. Dialog arsip dibuka kembali via `showModal()` dengan backdrop aktif menggelapkan latar & fokus terkunci. | `[x] Disetujui` |
| 5 | **H2: Jadwal - Indeks Shift Efektif** | `05-h2-effective-shifts-light-desktop.png`<br>`06-h2-effective-shifts-dark-desktop.png` | Riwayat shift efektif per karyawan dengan zona waktu & tanggal berlaku jelas. | `[x] Disetujui` |
| 6 | **H2: Jadwal - Modal Pratinjau** | `preview-dialog-light-desktop.png`<br>`preview-dialog-dark-desktop.png` | Tabel Sebelum vs Sesudah jelas; tombol Kembali menutup tanpa mutasi; proteksi double click. | `[x] Disetujui` |
| 7 | **H2: Jadwal - Baseline Resolver** | `P3-h2-baseline-future-date.png`<br>`P3-h2-baseline-same-date-revision.png` | Baseline tanggal mendatang mengambil penugasan aktif pada target; revisi tanggal sama mengambil penugasan revisi terakhir. | `[x] Disetujui` |
| 8 | **H2: Jadwal - Copy Shift Malam & Konflik** | `conflict-preserved-light-desktop.png` | Copy dampak shift malam presisi; konflik versi mengembalikan notice amber dan mempertahankan input. | `[x] Disetujui` |
| 9 | **H5: Kehadiran - Diff Badges Koreksi** | `P2-h5-diff-badges.png`<br>`10-h5-correction-modal-diff-badges.png` | Badge "Diubah" & "Tidak berubah" akurat; helper text checkout shift malam tampil jelas; teks konfirmasi riwayat lugas tanpa istilah atomik. | `[x] Disetujui` |
| 10 | **H5: Kehadiran - Jejak Audit Koreksi** | `11-h5-attendance-corrections-light-desktop.png`<br>`12-h5-attendance-corrections-dark-desktop.png` | Menampilkan histori perubahan lengkap dengan alasan, aktor penyesuai, dan perbandingan diff. | `[x] Disetujui` |
| 11 | **H5: Kehadiran - Rekapitulasi Kehadiran** | `14-h5-attendance-recap-light-desktop.png`<br>`16-h5-attendance-recap-dark-narrow.png`<br>`16b-h5-attendance-recap-drawer-open-dark-narrow.png` | Filter 90 hari, CSV streaming UTF-8 BOM, cuti cancellation_requested terhitung efektif; tampilan narrow bersih tanpa terhalang drawer, dan drawer diuji sebagai state terpisah. | `[x] Disetujui` |
| 12 | **H5: Kehadiran - Drilldown Detail Harian** | `H5-09-daily-breakdown-light-desktop.png` | Hari libur diutamakan sebagai status utama (`holiday`); tombol kembali mempertahankan query filter. | `[x] Disetujui` |
| 13 | **Lembur & Reimbursement Web** | `20-overtime-create-desktop.png`<br>`26-reimbursement-edit-readonly-desktop.png` | Validasi batas 1.440 menit konsisten; pemilik reimbursement readonly saat edit; teks keputusan bebas bias saldo cadangan. | `[x] Disetujui` |

---

## 6. Kesimpulan & Penutupan Review

1. **Keputusan Visual**: Seluruh 13 area pengujian visual Dashboard Web telah **Disetujui oleh Pengguna** per 4 November 2026.
2. **Hasil Perbaikan Temuan**: Poin P1 (modal dialog arsip `showModal()`), P2 (rekap narrow drawer terpisah), P3 (copywriting ramah pengguna pada koreksi), dan P4 (isolasi saldo cadangan) telah selesai diperiksa dan diterima.
3. **Status Operasional**: Review visual Dashboard Web resmi **Ditutup**. Fitur biometrik pengenalan wajah tetap berada dalam backlog yang ditunda. Operasi commit, push, deployment, dan pengemasan komersial tetap ditunda sesuai instruksi pengguna.
