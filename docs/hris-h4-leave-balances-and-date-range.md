# H4 tahap 2 — Cuti Rentang Tanggal, Alokasi dan Ledger Saldo

Terakhir diperbarui: 21 Oktober 2026.
Implementasi alur cuti rentang tanggal, evaluasi kalender kerja, pemisahan tahap vs status, buku besar saldo karyawan, serta integrasi web Dashboard dan mobile Flutter.
Testing dan commit ditunda sesuai instruksi pengguna; status validasi tetap **belum diuji**.

## 1. Ruang lingkup dan arsitektur

Irisan ini melengkapi alur pengajuan cuti dan saldo dari ujung ke ujung:
1. **Navigasi Dashboard Web** dirapikan menjadi 7 kelompok terstruktur:
   - **Ringkasan**: Dashboard analitik dan ringkasan harian.
   - **Karyawan & Organisasi**: Master karyawan dan unit organisasi.
   - **Kehadiran & Koreksi**: Riwayat kehadiran, koreksi beralasan, QR absensi, verifikasi pensiun.
   - **Jadwal & Kalender**: Kalender kerja perusahaan, penugasan jadwal efektif, master shift.
   - **Pengajuan**: Pengajuan cuti & izin, lembur, dan reimbursement.
   - **Saldo Cuti**: Pengelolaan saldo karyawan per periode dan buku besar mutasi saldo (ledger).
   - **Kebijakan & Pengaturan**: Master jenis cuti, alur persetujuan bertingkat, lokasi kantor & profil perusahaan.
2. **Pengajuan Cuti Rentang Tanggal**:
   - Rentang tanggal awal (`start_date`) dan akhir (`end_date`) bersifat inklusif.
   - Evaluasi kalender kerja (`WorkCalendar`): setiap tanggal diperiksa apakah merupakan hari kerja atau hari libur (mingguan/nasional).
   - Hari libur dilewati secara otomatis dan dicatat alasannya.
   - Jika terdapat tanggal dalam rentang yang kalender kerjanya belum dikonfigurasi, pengajuan ditolak secara eksplisit dengan instruksi untuk melengkapi kalender kerja. Hari tanpa kalender tidak diasumsikan sebagai hari kerja atau hari libur.
   - Shift malam dihitung berdasarkan tanggal kerja asal; tidak dihitung sebagai dua hari cuti.
   - Validasi tumpang tindih (overlap): pengajuan baru ditolak jika bertumpuk dengan pengajuan aktif (`pending`, `approved`, `cancellation_requested`, `needs_review`). Pengajuan `rejected` atau `cancelled` tidak menghalangi pengajuan ulang.
   - Tahap awal menggunakan satuan hari penuh.
3. **Buku Besar Saldo Cuti (Leave Balance & Ledger)**:
   - Master jenis cuti menentukan apakah tipe mengurangi kuota (`deducts_balance`) dan kuota kebijakan acuan (`annual_quota_days`).
   - Kuota kebijakan pada master jenis cuti tidak otomatis menjadi saldo karyawan. HR/admin mengalokasikan saldo per karyawan per tahun/periode (`leave_balances`).
   - Setiap mutasi kuota dicatat di `leave_balance_ledgers` dengan referensi pengajuan, tipe transaksi, jumlah hari (+/-), saldo sebelum & sesudah, aktor, alasan, dan waktu.
4. **Siklus Hidup Saldo terhadap Pengajuan dan Persetujuan**:
   - **Pengajuan dikirim**: Saldo tersedia dipindahkan menjadi cadangan (`reserved_days` bertambah, `remaining_days` berkurang; transaksi `reserve`).
   - **Persetujuan bertahap (antara)**: Cadangan tetap dipertahankan.
   - **Persetujuan akhir disetujui**: Cadangan diubah menjadi saldo terpakai (`reserved_days` berkurang, `used_days` bertambah; transaksi `use`).
   - **Ditolak**: Cadangan dikembalikan ke saldo tersedia (`reserved_days` berkurang, `remaining_days` bertambah; transaksi `release_reserved`).
   - **Dibatalkan sebelum keputusan final (pending)**: Cadangan dikembalikan ke saldo tersedia (transaksi `release_reserved`).
   - **Cuti disetujui ingin dibatalkan**:
     - Pemilik mengajukan permohonan pembatalan (`status` menjadi `cancellation_requested`).
     - HR meninjau permintaan: jika disetujui pembatalannya, status menjadi `cancelled` dan saldo terpakai dikembalikan ke saldo tersedia (transaksi `restore_used`); jika ditolak HR, status kembali ke `approved` (saldo tetap terpakai).
5. **Standarisasi Istilah dan Pemisahan Status**:
   - Status akhir pengajuan:
     - `Menunggu persetujuan` (`pending`)
     - `Disetujui` (`approved`)
     - `Ditolak` (`rejected`)
     - `Dibatalkan` (`cancelled`)
     - `Menunggu pembatalan` (`cancellation_requested`)
     - `Perlu ditinjau` (`needs_review`)
   - Tahap persetujuan saat ini (`approval_progress`) dan peninjau aktif dipisahkan dari status akhir pengajuan.

## 2. Perubahan database dan skema

Migrasi baru: `2026_10_21_000008_add_leave_balances_and_date_range.php`.
Struktur yang ditambahkan:
1. Kolom baru pada tabel `permissions`:
   - `start_date` (date, nullable)
   - `end_date` (date, nullable)
   - `duration_days` (unsignedSmallInteger, default 1)
   - `dates_breakdown` (json, nullable)
   - `cancellation_reason` (string 1000, nullable)
   - `cancellation_requested_at` (timestamp, nullable)
   - Backfill data historis: `start_date = date_permission`, `end_date = date_permission`, `duration_days = 1`.
2. Tabel `leave_balances`:
   - `id`, `company_id`, `user_id`, `leave_type_id`, `period_year`
   - `allocated_days`, `reserved_days`, `used_days`, `remaining_days`
   - Kunci unik: `['company_id', 'user_id', 'leave_type_id', 'period_year']`
3. Tabel `leave_balance_ledgers`:
   - `id`, `company_id`, `leave_balance_id`, `user_id`, `actor_id`, `permission_id`
   - `transaction_type` (`allocation`, `adjustment`, `reserve`, `use`, `release_reserved`, `restore_used`)
   - `amount_days`
   - Snapshot nilai: `previous_allocated`, `previous_reserved`, `previous_used`, `previous_remaining`
   - Snapshot nilai: `current_allocated`, `current_reserved`, `current_used`, `current_remaining`
   - `reason` (string 1000), `created_at`

## 3. Komponen backend dan web

- **Model**: `LeaveBalance.php`, `LeaveBalanceLedger.php`, pembaruan `Permission.php`, `LeaveType.php`, `User.php`.
- **Service**:
  - `LeaveBalanceService.php`: kalkulasi rentang tanggal hari kerja vs libur, penanganan kalender belum lengkap, alokasi kuota, pencadangan atomik, pemakaian, dan pengembalian saldo dengan row lock `lockForUpdate()`.
  - `LeavePolicy.php`: pembaruan `submit()` mendukung rentang tanggal, validasi overlap, pengecekan kalender, dan reservasi saldo.
  - `SubmissionApproval.php`: penanganan transisi pembatalan `cancellation_requested`, keputusan pembatalan HR, pelepasan cadangan saat ditolak/dibatalkan, dan pemindahan cadangan ke terpakai saat disetujui final.
- **Controller**:
  - `LeaveBalanceController.php`: halaman ringkasan alokasi, alokasi per karyawan, alokasi massal seluruh staf, buku besar mutasi saldo, dan endpoint preview hitung hari (`/leave-calculate`).
  - `PermissionController.php`: filter status terstandarisasi, tampilan rentang tanggal, dan integrasi saldo pengguna.
- **Views**:
  - `layouts/workspace.blade.php`: sidebar direstrukturisasi ke 6 menu utama (Ringkasan tunggal dan 5 grup accordion) dengan area akun tersemat, scrolling mandiri, dan drawer mobile responsif.
  - `pages/leave_balances/index.blade.php`: dashboard metrik alokasi saldo, daftar saldo per karyawan, form alokasi individu & massal.
  - `pages/leave_balances/ledger.blade.php`: tabel jejak audit mutasi saldo dengan referensi pengajuan dan aktor.
  - `pages/permission/create.blade.php`: pemilih jenis cuti dengan info sisa saldo aktif, input tanggal mulai & selesai, live preview kalkulasi hari kerja vs hari libur, dan validasi kalender.
  - `pages/permission/index.blade.php`: daftar pengajuan dengan label rentang tanggal, status terstandarisasi, dan tahap approval.
  - `pages/permission/details.blade.php` & `show.blade.php`: rincian kalender hari kerja/libur, status saldo karyawan, alasan pembatalan jika ada, dan aksi approval/pembatalan.
  - `components/submission-approval.blade.php`: formulir persetujuan bertahap, penanganan permintaan pembatalan cuti disetujui, dan riwayat keputusan.

## 4. Penyelarasan aplikasi mobile (Flutter)

- `submission_record.dart`:
  - Mendukung `startDate`, `endDate`, `durationDays`, `datesBreakdown`, `cancellationReason`.
  - Getter `dateRangeLabel` menampilkan rentang tanggal dan durasi hari kerja.
  - Status terstandarisasi: `Menunggu persetujuan`, `Disetujui`, `Ditolak`, `Dibatalkan`, `Menunggu pembatalan`, `Perlu ditinjau`.
  - Properti `canCancel` (untuk pengajuan pending) dan `canRequestCancel` (untuk pengajuan disetujui).
- `submission_remote_datasource.dart`:
  - `submit()` mengirimkan `start_date` dan `end_date`.
  - Mendukung `calculateLeave()` untuk kalkulasi hari dan dampak saldo via API `POST /api/leave-calculate`.
  - `cancel()` mendukung penerimaan respons `cancelled` maupun `cancellation_requested`.
  - Mendukung `leaveBalances()` via API `GET /api/leave-balances`.
- `submission_form_page.dart`:
  - Pilihan tanggal mulai dan tanggal selesai untuk cuti/izin.
  - Kalkulasi interaktif saat tanggal atau jenis cuti dipilih: menampilkan hari kerja, hari libur yang dilewati, dan estimasi sisa saldo.
  - Peringatan jika kalender kerja perusahaan belum lengkap.
- `submission_list_page.dart`:
  - Kartu pengajuan menampilkan `dateRangeLabel` dan badge status terstandarisasi.
  - Halaman detail menampilkan rincian kalender hari kerja/libur dan alasan pembatalan jika ada.
  - Tombol aksi pembatalan: pembatalan langsung jika masih pending, atau permohonan pembatalan ke HR jika cuti telah disetujui.

## 5. Keputusan kebijakan dan batasan

- Satuan perhitungan cuti pada tahap ini adalah **hari penuh**. Kebijakan prorata, carry-over, setengah hari, dan saldo negatif belum termasuk tahap awal.
- Kuota tahunan pada master jenis cuti tidak otomatis memotong saldo jika karyawan belum dialokasikan saldo periode tersebut oleh HR.
- Isolasi perusahaan, otorisasi server, radius absensi, dan penonaktifan verifikasi wajah tetap dipertahankan.
- Tidak ada data legacy yang dihapus atau direklasifikasi secara sembarangan.

## 6. Pekerjaan pengujian yang tertunda (Testing Deferred)

Pengujian sengaja ditunda sesuai instruksi pengguna. Bagian yang perlu diuji pada sesi verifikasi mendatang:
1. Uji backend (PHPUnit):
   - Alokasi saldo individu dan massal.
   - Pengajuan cuti rentang tanggal melintasi hari libur mingguan dan hari libur nasional.
   - Penolakan pengajuan saat kalender kerja belum dikonfigurasi pada rentang tanggal terkait.
   - Penolakan pengajuan bertumpuk (overlap) dengan pengajuan aktif.
   - Pengecekan saldo cuti tidak mencukupi.
   - Alur mutasi saldo: reserve pada submit, use pada final approve, release_reserved pada reject/cancel, restore_used pada persetujuan pembatalan HR.
   - Idempotensi keputusan persetujuan dan pembatalan dengan optimistic locking (`approval_version`).
2. Uji browser UI Dashboard:
   - Form alokasi saldo dan riwayat buku besar pada light dan dark mode.
   - Live calculation pada form pengajuan cuti web.
   - Tampilan rincian hari kerja/libur dan form keputusan pembatalan pada detail pengajuan.
   - Verifikasi 6 menu utama navigasi sidebar (Ringkasan tunggal dan 5 grup accordion) pada desktop dan layar sempit.
3. Uji Flutter Mobile:
   - Pemilihan rentang tanggal dan live calculation hari kerja pada form cuti mobile.
   - Pengajuan cuti bertipe saldo dan non-saldo.
   - Alur permohonan pembatalan cuti disetujui dari mobile ke web Dashboard.
