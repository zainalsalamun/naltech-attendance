# Batasan Cakupan & Keterbatasan Fitur Sistem (Feature Boundaries)

Tanggal Dokumen: 4 November 2026, Asia/Jakarta.  
Status Dokumen: **Dokumen Resmi Batasan Fungsional & Teknis Aplikasi**.  
Acuan Utama: [`docs/hris-feature-scope-status.md`](../hris-feature-scope-status.md), [`docs/hris-operational-guides-prd.md`](../hris-operational-guides-prd.md) Bagian 7.

---

## 1. Prinsip Penetapan Batas Sistem

Dokumen ini mendefinisikan batasan tegas mengenai apa yang **didukung secara penuh**, apa yang merupakan **batasan teknis/arsitektur**, apa yang **ditunda (deferred)**, serta apa yang menjadi **rencana pengembangan masa depan (backlog)** dalam sistem Naltech Attendance.

> [!IMPORTANT]
> **Status Persetujuan Sistem (Per 4 November 2026)**:
> - **Aplikasi Mobile (Detail Pengajuan Izin, Lembur, Reimbursement)**: Telah **disetujui secara visual dan fungsional oleh pengguna** pada 4 November 2026.
> - **Dashboard Web (H1 Karyawan, H2 Shift, H5 Rekap/Koreksi, H4 Approval)**: Telah selesai secara teknis dan terverifikasi uji otomatis, saat ini berstatus **Menunggu Review Pengguna**.
> - **Rilis & Pengemasan**: Seluruh aksi git commit, push, deployment produksi, dan pengemasan komersial H6 tetap berada dalam status **Ditunda (Deferred)**.

---

## 2. Fitur yang Tersedia & Terverifikasi (Available Features)

Modul-modul berikut telah dibangun, memiliki cakupan pengujian regresi otomatis, dan dapat digunakan secara operasional:

### 2.1 Pengelolaan Karyawan & Organisasi (Web H1)
- **Lifecycle Karyawan**: Pendaftaran baru, perbaruan data, penonaktifan sementara, pengarsipan permanen dengan alasan wajib, dan reaktivasi akun terarsip.
- **Integritas Organisasi**: Penugasan unit kerja dan atasan langsung (*manager*). Proteksi hierarki melingkar (*circular hierarchy*) dan larangan menetapkan diri sendiri sebagai atasan.
- **Retensi Historis**: Karyawan yang terhubung dengan unit atau atasan yang telah nonaktif tetap menampilkan histori dengan penanda `(nonaktif)`.
- **Proteksi Admin Terakhir**: Sistem menolak penonaktifan atau penurunan peran akun Admin aktif terakhir.
- **Audit Jejak**: Seluruh perubahan status karyawan dicatat dalam tabel audit log secara transparan.

### 2.2 Penjadwalan & Shift Kerja Efektif (Web H2)
- **Master Shift**: Pembuatan shift kerja normal, fleksibel, maupun shift malam lintas hari (*cross-midnight*).
- **Penugasan Bertanggal Efektif (`effective_from`)**: Penetapan jadwal mendatang tanpa mengganggu jadwal kerja yang sedang berjalan hari ini.
- **Pratinjau Penugasan Jadwal**: Modal pratinjau perbandingan kondisi *Sebelum* vs *Sesudah* berdasarkan baseline tanggal target atau revisi tanggal yang sama.
- **Status "Tanpa Shift"**: Penanganan terdefinisi ketika karyawan belum memiliki penugasan shift aktif.

### 2.3 Kalender Kerja & Hari Libur (Web H3)
- **Konfigurasi Hari Kerja Mingguan**: Penentuan hari kerja aktif (misal: Senin–Jumat atau Senin–Sabtu).
- **Kalender Hari Libur**: Pencatatan hari libur nasional dan cuti bersama perusahaan yang mengecualikan perhitungan hari kerja pada cuti dan rekapitulasi.
- **Multi-Timezone Perusahaan**: Dukungan penanganan waktu kerja berbasis zona waktu resmi IANA (WIB, WITA, WIT, dan UTC).

### 2.4 Cuti, Lembur, dan Reimbursement (Web & Mobile H4)
- **Master & Buku Besar Saldo Cuti**: Pengelolaan tipe izin/cuti bersaldo dan non-saldo, serta alokasi kuota tahunan karyawan.
- **Pengajuan Mobile**: Formulir pengajuan mandiri untuk izin, lembur, dan reimbursement dengan validasi data di sisi klien dan server.
- **Validasi Berkas Lampiran**: Batasan ukuran kuitansi reimbursement maksimal 2.048 KiB (2 MB) format JPG/PNG dengan pemulihan berkas valid jika terjadi kegagalan input.
- **Alur Persetujuan Berjenjang**: Persetujuan berurutan (Atasan Langsung → HR Manager) dengan antrean terfilter (*All*, *Actionable*, *Waiting Others*).
- **Penanganan Pembatalan**: Pembatalan mandiri untuk pengajuan berstatus *Menunggu*, serta permohonan pembatalan (*cancellation requested*) untuk cuti yang telah disetujui.

### 2.5 Pencatatan Kehadiran, Koreksi, dan Rekapitulasi (Web & Mobile H5)
- **Absensi Mandiri Mobile**: Check-in dan checkout kehadiran menggunakan verifikasi QR Code terenkripsi dan batas geofencing radius GPS kantor.
- **Shift Malam Lintas Hari**: Pengaitan jam absensi dini hari ke tanggal penugasan shift kerja sebenarnya (`attendance_date`) bukan tanggal kalender lokal perangkat.
- **Koreksi Data Absensi HR**: Antarmuka koreksi jam kerja, penambahan jam checkout yang terlewat, modal konfirmasi pratinjau perubahan (*diff*), dan alasan wajib non-kosong.
- **Rekapitulasi Kehadiran & CSV**: Laporan ringkasan kehadiran, keterlambatan, pulang awal, durasi kerja total, dan ekspor data ke format CSV.

---

## 3. Batasan Teknis dan Rancangan (Technical & Design Boundaries)

Sistem menetapkan batasan arsitektur spesifik demi menjaga kestabilan, performa, dan konsistensi data:

| Komponen / Modul | Batasan Teknis / Desain | Alasan Arsitektur & Dampak Operasional |
|:---|:---|:---|
| **Rentang Filter Rekapitulasi** | Maksimal **90 hari kalender inklusif** per kueri/ekspor CSV. | Mencegah kehabisan memori server (*exhaustion*) dan waktu tunggu kueri terlalu lama (*timeout*) saat agregasi data besar. Untuk kebutuhan tahunan, tarik per kuartal. |
| **Durasi Formulir Lembur** | Input durasi dibatasi antara **1 hingga 1.440 menit** (maksimal 24 jam) per baris pengajuan. | Batasan integritas form numerik. Ini adalah batas teknis masukan, bukan izin kebijakan lembur legal perusahaan. |
| **Ukuran Lampiran Kuitansi** | Maksimal tepat **2.048 KiB (2.097.152 byte)** dengan format JPG/JPEG/PNG pada mobile. | Mencegah kegagalan pengiriman pada jaringan seluler tidak stabil dan menjaga kapasitas penyimpanan server. File PDF tidak didukung pada mobile. |
| **Sesi Menggantung (Check-in Tanpa Checkout)** | Otomatis **dikunci oleh server setelah 24 jam** kalender sejak waktu check-in. | Mencegah durasi kerja fiktif ratusan jam pada rekapitulasi. Sesi yang terkunci wajib diselesaikan via modul Koreksi Absensi oleh HR/Atasan. |
| **Kontrol Konkurensi (Optimistic Locking)** | Menggunakan token versi (`version` pada User/Approval, `expected_id` pada Shift Assignment). | Menolak pembaruan data bersamaan oleh dua pengelola dengan respon HTTP 409 Conflict untuk mencegah fenomena kehilangan perubahan (*lost updates*). |
| **Pemetaan Unit pada Rekapitulasi** | Rekapitulasi mengelompokkan data berdasarkan **unit kerja karyawan saat ini** (*current unit*). | Jika karyawan mutasi divisi pada pertengahan bulan, seluruh rekap historis bulan tersebut teragregasi di unit terbarunya, kecuali ditarik sebelum mutasi. |
| **Snapshot Alur Persetujuan** | Rantai peninjau persetujuan **dibekukan (*snapshot*)** pada saat karyawan menekan tombol kirim pengajuan. | Pemindahan jabatan atau mutasi atasan langsung setelah pengajuan terkirim tidak secara otomatis mengubah rantai persetujuan pengajuan yang sedang berjalan. |
| **Status Pembatalan Cuti Disetujui** | Permohonan pembatalan cuti approved berstatus **`cancellation_requested`**. | Cuti tetap dianggap sah dan aktif di jadwal absensi sampai HRD secara resmi menyetujui pembatalan. Jika HR menolak, status cuti kembali ke `approved`. |
| **Pembatalan Lembur & Biaya Approved** | Pengajuan lembur dan reimbursement yang berstatus **Approved tidak dapat dibatalkan mandiri** via aplikasi mobile. | Persetujuan biaya dan jam lembur terkait dengan komitmen administratif; penyesuaian wajib dikoordinasikan secara manual dengan HR/Finance. |
| **Otorisasi Supervisor** | Supervisor **terbatas secara ketat pada bawahan langsung** (`manager_id = supervisor.id`). | Supervisor dilarang melihat karyawan di luar timnya, mengedit kalender kerja perusahaan, atau mengekspor rekapitulasi seluruh kantor (HTTP 403). |

---

## 4. Kekurangan & Keterbatasan Operasional Terverifikasi (Known Operational Limitations)

Berdasarkan pencocokan langsung antara panduan operasional dan kode sumber aplikasi pada enam alur utama (tambah karyawan → atur jadwal → absensi → pengajuan → approval → rekap), berikut adalah keterbatasan konkret yang perlu dipahami pengguna dan pengelola:

1. **Pemilih Karyawan Masih Menggunakan Dropdown Standar**:
   - Pemilihan karyawan pada modul penugasan jadwal (`effective-shifts`) dan formulir cuti admin (`permissions/create`) masih menggunakan elemen `<select>` HTML standar tanpa pencarian instan (*live search*). Untuk perusahaan dengan jumlah karyawan sangat besar (> 500 karyawan), navigasi dropdown memerlukan *scrolling* panjang.
2. **Ketiadaan Notifikasi Push Otomatis saat Alur Berpindah Tahap**:
   - Perpindahan tahapan persetujuan (dari Tahap 1 Supervisor ke Tahap 2 HR) berjalan secara otomatis di basis data dan UI, namun belum memicu notifikasi push ponsel atau email otomatis. Peninjau perlu memeriksa antrean secara berkala menggunakan filter `actionable` (*Perlu tindakan saya*).
3. **Format Lampiran Bukti Terbatas pada Gambar (Khusus JPG/PNG)**:
   - Formulir pengajuan izin dan reimbursement pada aplikasi mobile hanya menerima berkas gambar format JPG, JPEG, dan PNG (maksimal 2.048 KiB). Berkas dokumen seperti PDF atau DOCX ditolak oleh sistem mobile demi menjaga keandalan rendering gambar dan batas payload jaringan.
4. **Batas Agregasi Rekapitulasi Maksimal 90 Hari Kalender**:
   - Kueri rekapitulasi kehadiran dibatasi maksimal 90 hari inklusif per penarikan untuk mencegah *memory exhaustion* server. Untuk pelaporan tahunan, penarikan data wajib dilakukan secara bertahap per kuartal/triwulan.
5. **Pemulihan Sesi Menggantung > 24 Jam Memerlukan Tindakan Manual HR**:
   - Karyawan yang lupa melakukan checkout lebih dari 24 jam kalender akan mengalami penguncian sesi absensi otomatis oleh server. Sesi tersebut tidak dapat diselesaikan mandiri dari aplikasi mobile dan mewajibkan tindakan **Koreksi Absensi** oleh atasan atau HRD.
6. **Agregasi Unit pada Rekapitulasi Mengikuti Unit Saat Ini (*Current Unit*)**:
   - Jika karyawan mengalami mutasi divisi di pertengahan bulan, seluruh rekap kehadiran bulan berjalan akan teragregasi di bawah unit kerja terbarunya.
7. **Persetujuan Pembatalan Cuti Menunggu Keputusan HR**:
   - Karyawan yang membatalkan cuti yang telah disetujui harus menunggu HR menyetujui permohonan pembatalan (`cancellation_requested`) agar hari kerja kembali aktif dan saldo dikreditkan ke buku besar.

---

## 5. Fitur yang Ditunda untuk Produksi (Deferred Features)

Modul-modul berikut secara sadar **ditunda (deferred)** dari implementasi produksi operasional:

### Pengenalan Wajah Biometrik (Facial Recognition)
- **Status Saat Ini**: **Ditunda (Deferred)** untuk lingkungan produksi.
- **Alasan Penundaan**:
  - Model inferensi wajah pada perangkat bergerak membutuhkan pengujian mendalam terhadap variasi pencahayaan ekstrem (redup/terik), sudut kemiringan wajah (*pose variation*), serta keandalan pencegahan pemalsuan (*anti-spoofing / liveness detection*).
  - Mencegah timbulnya keluhan operasional akibat penolakan absensi palsu (*false rejection*) pada karyawan di lapangan.
- **Metode Pengganti Resmi**: Kehadiran kerja mengandalkan kombinasi verifikasi **QR Code dinamis terenkripsi** di lokasi kerja dan **validasi geofencing radius GPS** koordinat kantor.

---

## 5. Rencana Pengembangan Masa Depan (Optional Backlog)

Fitur-fitur berikut berada dalam daftar prioritas pengembangan masa depan (*backlog*) setelah fase stabilisasi saat ini selesai ditinjau oleh pengguna:

1. **Pola Rotasi Shift Otomatis (Recurring Shift Patterns)**:
   - Kemampuan menjadwalkan rotasi shift berulang otomatis (misal: 4 hari kerja pagi, 2 hari libur, 4 hari kerja malam) tanpa input manual mingguan oleh HR.
2. **Penugasan Shift Massal (Bulk Assignment via CSV/Excel)**:
   - Fitur unggah file lembar kerja (*spreadsheet*) untuk menugaskan jadwal ratusan karyawan pabrik/lapangan sekaligus dalam satu transaksi.
3. **Dropdown Pemilih Karyawan Tervirtualisasi (Virtualized Employee Selector)**:
   - Optimasi antarmuka pemilih karyawan menggunakan teknik virtual scrolling untuk perusahaan berskala besar (> 10.000 karyawan aktif) agar tidak membebani memori browser DOM.
4. **Tampilan Kalender Grid Bulanan Interaktif (Visual Calendar Grid)**:
   - Antarmuka visual kalender bulanan interaktif bergaya kalender meja untuk memudahkan HR melihat sebaran cuti dan shift tim dalam satu layar komprehensif.
5. **Dukungan Shift Terpisah (Split Shifts)**:
   - Dukungan penugasan shift terpisah dalam satu hari (misal: sesi siang pukul 11:00–14:00 dan sesi malam pukul 18:00–22:00 untuk industri kuliner/hospitality).

---

## 6. Rujukan Silang Terkait

- [`docs/hris-feature-scope-status.md`](../hris-feature-scope-status.md): Rincian status pembekuan fitur per modul dan hasil audit.
- [`docs/operations/README.md`](README.md): Indeks utama dokumentasi operasional sistem.
- [`docs/operations/hr-admin-guide.md`](hr-admin-guide.md): Panduan lengkap tata cara kerja pengelola HR dan Administrator.
- [`docs/operations/employee-mobile-guide.md`](employee-mobile-guide.md): Panduan lengkap penggunaan aplikasi absensi karyawan.
- [`docs/operations/troubleshooting.md`](troubleshooting.md): Matriks penanganan kendala dan prosedur eskalasi.
