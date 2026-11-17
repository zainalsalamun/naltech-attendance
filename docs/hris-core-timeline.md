# Timeline pengembangan HRIS inti

Disusun 21 Oktober 2026 dari [audit kode dan reproduksi](hris-core-audit.md).
Ini urutan milestone, bukan estimasi tanggal rilis. Tidak menjanjikan jumlah hari
sebelum lingkup setiap irisan selesai diperiksa. Liveness sesi/video ditunda;
Face tetap tidak aktif. Implementasi web lebih dahulu, lalu mobile setelah review.

| Tahap | Urutan kerja | Status | Kriteria selesai |
| --- | --- | --- | --- |
| H0 | Tutup gap akses API, ekspor, lampiran dan kontrak endpoint | Implementasi dan tes tersedia; pemindahan legacy utama belum diterapkan | [Perubahan, bukti dan penerapan](hris-h0-implementation.md): akses, route, validasi, CSV dan upload privat sudah diuji; file lama perlu command operator |
| H1 | Lifecycle dan master karyawan | [Lifecycle](hris-h1-implementation.md), [unit/atasan](hris-h1-organization.md), dan [penyempurnaan H1](hris-web-review-and-h1-refinement-report.md) selesai diuji; migrasi utama menunggu instruksi pengguna | Nomor per perusahaan unik, tanggal masuk/keluar, token konkurensi `version`, modal pratinjau transisi status, perlindungan admin terakhir, dan retensi historis unit/atasan; sesi/API nonaktif ditolak, riwayat dipertahankan utuh |
| H2 | Kalender kerja dan fondasi jadwal | [Kalender tersedia](hris-h2-calendar.md); [jadwal efektif/snapshot/checkout malam](hris-h2-effective-shifts.md) dan [penyempurnaan alur penugasan shift](hris-h2-shift-assignment-refinement-prd.md) selesai diuji; penerapan utama dan QA native baru masih terbuka | Zona waktu perusahaan, hari kerja/libur konfigurabel, penugasan shift efektif per tanggal, modal pratinjau sebelum/sesudah dan penanganan konflik konkurensi `expected_id` multi-proses; data legacy dipetakan tanpa mengarang histori |
| H3 | Approval dan audit bersama | [Tahap 1 tiga modul tersedia dan diuji](hris-h3-approval.md); [mobile status/detail/pembatalan tersedia](hris-h3-mobile.md); [QA pengajuan Android lulus](hris-h3-mobile-device.md); [alur konfigurabel tersedia](hris-h3-workflows.md); penerapan/review dan uji perangkat alur baru, iOS/release masih terbuka | pending/approved/rejected/cancelled dengan transisi jelas; peninjau/waktu/alasan/event, larangan self-approval, scope tim/HR dan transaksi/idempotensi; tahapan konfigurabel |
| H4 | Jenis cuti, pengajuan rentang dan ledger saldo | [Tahap 1](hris-h4-leave-types.md) dan [tahap 2 rentang/ledger](hris-h4-leave-balances-and-date-range.md) tersedia; validasi backend dan skenario konkurensi MySQL tersedia; status penerapan 000008 terpisah dari hasil tes | Kebijakan per perusahaan eksplisit, hitung hari kerja dari H2, saldo dialokasikan/dipesan/dipakai/dikembalikan atomik, overlap/duplikasi/konkurensi diuji dan approval memakai H3 |
| H5 | Koreksi absensi dan rekap periode | [Implementasi H5 tersedia](hris-h5-attendance-corrections-and-recap.md); [temuan review diperbaiki dan diuji](hris-review-fixes-and-mysql-testing.md); batas roster historis tetap dicatat | Snapshot jadwal/kebijakan historis, koreksi beralasan dengan audit event & snapshot before/after, checkout malam benar, rekap periode kalender dinamis, metrik kehadiran, dan ekspor streaming CSV aman |
| H6 | Pengemasan source code | [Pengemasan tersedia](hris-h6-commercial-packaging-and-guide.md); setup password diperbaiki; kesiapan produksi mengikuti [cakupan verifikasi](hris-review-fixes-and-mysql-testing.md) | Matriks fitur aktual, panduan instalasi/deployment, `.env.example` bersih tanpa rahasia, seeder produksi vs demo terpisah (`InitialSetupSeeder` & `DemoDataSeeder`), skenario uji penerimaan pembeli dan batas native/release terdokumentasi |

## Irisan implementasi terdekat

Pembaruan 21 Oktober: H0 tersedia dan diuji; pemindahan lampiran legacy utama belum dijalankan. Irisan lifecycle H1 telah diimplementasikan dan diperiksa: 95 tes backend/1.170 assertions; 20 checks browser organisasi dan 9 checks lifecycle pada irisan sebelumnya, light/dark desktop dan layar sempit. Lihat [laporan H1](hris-h1-implementation.md). Migrasi schema utama belum diterapkan.

Master unit dan hubungan atasan telah tersedia, dengan pemetaan manual departemen lama dan validasi graf. Berikutnya: review web H1 dan penerapan schema sesuai prosedur lingkungan; pengembangan H2 tahap 1 kalender/zona waktu kini tersedia ([laporan](hris-h2-calendar.md), 99 tes/1.222 assertions dan 15 checks browser). H2 tahap 2 (shift efektif/snapshot/checkout malam) **ditunda sesuai arahan pengguna**. Sebagai kelanjutan, H3 tahap 1 approval izin, lembur dan reimbursement sudah tersedia: 106 tes/1.327 assertions dan 21 checks browser. Lihat [laporan H3](hris-h3-approval.md). UI status/detail/pembatalan karyawan di mobile kemudian diselaraskan ([laporan](hris-h3-mobile.md), 67 tes Flutter dan 27 pemeriksaan HTTP). Uji pengajuan pada OPPO Android 15 kemudian lulus ([laporan perangkat](hris-h3-mobile-device.md)); regresi backend kini 107 tes/1.339 assertions. Alur approval konfigurabel kemudian tersedia ([laporan](hris-h3-workflows.md)): 111 tes backend/1.438 assertions, 69 tes Flutter dan 25 pemeriksaan Chrome. Penerapan utama/review, uji perangkat untuk alur baru dan iOS/release tetap terbuka. Penjadwalan tanggal efektif/riwayat periode kerja belum tersedia. Mobile mengikuti review web.

Jalur hapus operasional telah diganti arsip. Penghapusan permanen/retensi adalah proses terpisah, bukan perubahan massal FK atau penghapusan data legacy.

## Keputusan kebijakan yang belum ditetapkan

Sebelum H2–H4, tetapkan/configure kalender, zona waktu, urutan peninjau, kuota
cuti, periode saldo, prorata, carry-over, pembatalan dan aturan setengah hari.
Tidak mengasumsikan angka kuota atau aturan ketenagakerjaan tertentu dari UI.
Kebijakan demo diberi label contoh; perusahaan harus mengaktifkan kebijakan
operasionalnya secara eksplisit. Parameter tersebut belum menghalangi H0.

Status pending lama pada izin tidak boleh ditebak dari boolean false. Saat
migrasi status baru, tandai asal legacy/needs-review atau minta HR melakukan
rekonsiliasi; jangan otomatis menamai seluruh false sebagai penolakan.
Pendaftaran wajah memiliki audit/prosedur sendiri dan tidak otomatis menjadi
workflow approval bisnis. Hindari menyalin status biometrik ke modul cuti.

## Catatan pemeriksaan

Audit awal: 74 tes backend/962 assertions lulus; empat probe database sementara
mengonfirmasi gap akses, cascade, checkout lintas tanggal dan formula CSV.
Audit awal belum mengimplementasikan H0–H6. Sesudahnya, kode H0 dan regresinya
tersedia sesuai laporan implementasi; pemindahan file legacy utama masih perlu
penerapan operator. H1 master dasar tersedia sesuai laporan; penerapan/review, sisa H2 dan H3–H6 tetap terbuka. Perbarui status dan bukti setiap irisan.

## Pembaruan H4 tahap 1 — 21 Oktober 2026

Master jenis/kuota/persyaratan cuti tersedia di Dashboard; pengajuan satu tanggal menyimpan snapshot kebijakan dan memakai alur approval izin H3. Tidak ada klasifikasi legacy otomatis atau saldo karyawan yang diasumsikan. [Laporan H4](hris-h4-leave-types.md) menjelaskan validasi dan bukti browser. Pemilih jenis pada Flutter menunggu tahap setelah review web. Rentang, perhitungan hari dan ledger menunggu fondasi jadwal efektif H2 yang tetap ditunda. Migrasi utama tidak dijalankan.

Pembaruan mobile H4 pada 21 Oktober: pemilih jenis aktif, persyaratan lampiran dan snapshot kebijakan pada detail tersedia ([laporan](hris-h4-mobile-leave.md)). 77 tes Flutter dan 25 pemeriksaan HTTP nyata lulus; uji perangkat baru belum dilakukan karena ADB tidak mendeteksi HP. Migrasi utama, rentang tanggal dan ledger tetap belum diterapkan/dikerjakan.

Pembaruan QA perangkat H4: [OPPO Android 15 lulus](hris-h4-mobile-device.md), satu suite native (11 pemeriksaan), lima tindakan Dashboard dan sembilan screenshot. Lampiran memakai file sintetis melalui callback picker; UI galeri belum diuji. Penerapan utama, iOS/release, rentang dan ledger tetap terbuka. Agen tidak menjalankan perintah commit pada sesi QA ini.

## Pembaruan H2 tahap 2 — 21 Oktober 2026

Penundaan H2 sebelumnya dicabut lewat arahan pengguna untuk melanjutkan. [Jadwal efektif, salinan jadwal dan checkout lintas malam](hris-h2-effective-shifts.md) kini tersedia: 123 tes backend/1.605 assertions, 10 pemeriksaan Chrome dan integrasi konteks Beranda Flutter. Migrasi utama dan uji native perubahan H2 belum dijalankan. Tahap berikutnya adalah review/penerapan terkontrol, QA shift pada perangkat, kemudian rentang cuti dan ledger H4 sesuai kebijakan saldo yang disepakati. Riwayat periode hubungan kerja tetap belum tersedia.

## Penerapan database utama — 21 Oktober 2026

Delapan migrasi tertunda berhasil diterapkan pada MySQL utama atas instruksi pengguna, dengan backup lokal di luar repositori. Seluruh migrasi berstatus Ran; jumlah baris bisnis sebelum/sesudah tetap sama. [Rincian verifikasi](database-main-migration.md). Status belum migrasi di bagian sebelumnya merupakan catatan historis. Commit tetap menunggu perintah pengguna; uji native shift masih terbuka.

## QA shift emulator — 21 Oktober 2026

[Pengujian H2 pada Android 15 emulator lulus](hris-h2-mobile-emulator.md): 1 suite/10 pemeriksaan, 4 screenshot dan verifikasi SQLite terisolasi. Label jadwal efektif diperbaiki; 14 tes Beranda/shift lulus. Migrasi utama sebelumnya telah diterapkan. HP fisik, kamera QR dan akurasi GPS belum diuji untuk H2. Commit tetap menunggu perintah pengguna.

## Pembaruan H4 tahap 2 — 21 Oktober 2026

[Cuti rentang tanggal, evaluasi kalender kerja, alokasi dan ledger saldo](hris-h4-leave-balances-and-date-range.md) telah diimplementasikan:
- Navigasi Dashboard web direstrukturisasi menjadi 6 menu utama (Ringkasan tunggal dan 5 grup accordion) dengan area akun tersemat di footer, scrolling mandiri, kontrol minimize desktop, dan drawer mobile responsif ([dokumentasi](sidebar-navigation-restructuring.md)).
- Pengajuan cuti rentang tanggal (`start_date` dan `end_date` inklusif) dengan evaluasi hari kerja via kalender perusahaan, penolakan saat kalender belum lengkap, dan pencegahan overlap pengajuan aktif.
- Buku besar saldo cuti karyawan (`leave_balances` dan `leave_balance_ledgers`): alokasi awal, penyesuaian HR, reservasi saat submit, pemakaian saat final approval, pengembalian cadangan saat ditolak/batal, serta permohonan pembatalan cuti disetujui (`cancellation_requested`) dengan persetujuan HR untuk pengembalian saldo.
- Istilah status diselaraskan: Menunggu persetujuan, Disetujui, Ditolak, Dibatalkan, Menunggu pembatalan, dan Perlu ditinjau.
- Integrasi mobile Flutter diselaraskan dengan live calculation hari kerja vs libur, tinjau saldo aktif, dan form pembatalan.
- Testing dan commit ditunda sesuai instruksi pengguna; status validasi tetap “belum diuji”.

## Rencana perapian dan testing berikutnya — 21 Oktober 2026

[Rencana P0–P5](hris-next-development-and-testing-plan.md) dan [prompt pelaksanaan](hris-next-development-prompt.md) telah dieksekusi dengan bukti pada [Laporan Eksekusi Pengujian](hris-testing-execution-report.md) (132 tes lulus, 0 gagal pada database testing terisolasi). Restrukturisasi sidebar 6 menu utama dan perbaikan minimize tervalidasi. Backend H4 (rentang tanggal, alokasi saldo, ledger, pencegahan overlap, pembatalan bertahap) teruji menyeluruh. Sesi berikutnya siap melanjutkan ke H5: jejak audit koreksi dan rekap periode. Commit dan migrasi database utama tetap menunggu perintah pengguna.

## Pembaruan H5 — 21 Oktober 2026

[Jejak audit koreksi absensi & rekap periode kehadiran](hris-h5-attendance-corrections-and-recap.md) telah selesai diimplementasikan dan diuji secara menyeluruh:
- Audit trail koreksi absensi (`attendance_correction_events`) otomatis mencatat aktor pengubah, alasan penyesuaian, serta snapshot diff *before* dan *after* pada setiap perubahan data kehadiran oleh pengelola.
- Kartu riwayat audit koreksi terintegrasi pada formulir edit absensi (`pages/absensi/edit.blade.php`).
- Halaman penelusuran audit koreksi mandiri (`/attendance-corrections`) dengan filter pencarian nama karyawan dan rentang tanggal.
- Halaman rekapitulasi kehadiran periode (`/attendance-recap`) dengan evaluasi kalender kerja perusahaan, perhitungan hari hadir, tepat waktu, terlambat, alpa, cuti disetujui, dan total jam kerja.
- Ekspor streaming CSV (`/attendance-recap/export-csv`) dengan sanitasi formula injection dan UTF-8 BOM.
- Terintegrasi pada sidebar navigasi `group-kehadiran`.
- 135 tes backend dengan 1.748 assertions lulus 100% pada database testing SQLite terisolasi; 0 kegagalan, 0 regresi.
- Tahap berikutnya: **Milestone H6 — Pengemasan source code, matriks fitur, dan panduan instalasi**.

## Pembaruan H6 — 21 Oktober 2026

[Pengemasan komersial source code HRIS & panduan penerimaan pembeli](hris-h6-commercial-packaging-and-guide.md) telah selesai:
- **Pemisahan Seeder Bersih vs Demo**:
  - `InitialSetupSeeder`: Setup awal bersih untuk produksi (perusahaan, super admin, shift operasional, unit organisasi, kalender kerja, master cuti, dan workflow approval 2-tahap) tanpa transaksi fiktif.
  - `DemoDataSeeder`: Setup data demonstrasi lengkap dengan hierarki karyawan (Direktur, HR Manager, Supervisor, Staff), alokasi kuota & ledger saldo cuti 2026, catatan absensi dengan audit event koreksi, serta contoh pengajuan cuti, lembur, dan reimbursement.
  - `DatabaseSeeder`: Mendukung pemilihan otomatis via `SEED_DEMO_DATA=true` atau flag `--class=DemoDataSeeder`.
- **Standarisasi Konfigurasi Environment**:
  - `apps/backend/.env.example` diperbarui dengan panduan lengkap, zona waktu `Asia/Jakarta`, pengaturan MySQL/SQLite, session, queue, cache, Sanctum, dan storage.
  - `apps/mobile/.env.example` dibuat untuk memudahkan konfigurasi Firebase oleh pembeli.
  - `apps/mobile/lib/core/constants/variables.dart` dirapikan dari legacy URL dan dilengkapi panduan emulator/simulator/LAN/produksi.
  - `Makefile` dilengkapi perintah cepat `backend-setup`, `backend-demo`, dan `backend-test-all`.
- **Panduan & Matriks Penerimaan**:
  - Matriks fitur lengkap web dashboard dan mobile Flutter.
  - Skenario uji penerimaan pembeli (*Buyer Acceptance Test Checklist*).
  - Batasan teknis biometrik (Face recognition dinonaktifkan secara aman untuk produksi), GPS geofencing, dan push notification.
- **Hasil Pengujian**:
  - Suite pengujian seeder [`SeederPackagingTest`](apps/backend/tests/Feature/SeederPackagingTest.php) lulus (2 tes, 37 assertions).
  - Total pengujian backend meningkat menjadi **137 tests, 1.785 assertions (100% PASSING)** pada SQLite in-memory terisolasi.
- Seluruh milestone HRIS inti (**H0 s/d H6**) kini telah tuntas diimplementasikan, diverifikasi, dan terdokumentasi rapi.

## Perbaikan review H5/H6 dan MySQL — 21 Oktober 2026

[Empat temuan review ditutup](hris-review-fixes-and-mysql-testing.md): password setup wajib konfigurasi, persentase hari kerja konsisten, rekap historis nonaktif/arsip dipertahankan, dan rentang maksimal 90 hari. Regresi SQLite 140 tes/1.819 assertions; MySQL 9.3 InnoDB terisolasi 18 tes/194 assertions termasuk multiproses saldo/approval/pembatalan; 10 checks Chrome lulus. Jalur rollback indeks FK MySQL juga diperbaiki. Label siap produksi/100% pada catatan sebelumnya bukan kesimpulan akhir. Tidak ada migrasi/seed database utama atau commit pada irisan ini.

## Penyesuaian alur cuti/saldo — 21 Oktober 2026

[Alur tinjauan, preview saldo dan pembatalan dirapikan](hris-h4-flow-adjustment.md) pada web/Flutter. Regresi: 141 tes backend/1.833 assertions, 85 tes Flutter dan 14 checks Chrome lulus. Tidak ada migrasi/transaksi database utama atau commit. Uji native perubahan ini belum dijalankan; pengemasan pembeli tetap ditunda.

## Rencana perapian approval HR/atasan — 27 Oktober 2026

[PRD](hris-approval-flow-adjustment-prd.md) dan [prompt Antigravity](hris-approval-antigravity-prompt.md) disiapkan untuk antrean tindakan, tinjauan keputusan, pemisahan pembatalan dan riwayat lintas modul. Pengemasan pembeli tetap ditunda. Commit menunggu perintah pengguna.

## Pembaruan perapian approval HR/atasan — 28 Oktober 2026

[Perapian alur approval HR/atasan selesai diimplementasikan dan diverifikasi](hris-approval-flow-refinement-report.md) sesuai PRD:
- Filter antrean konteks tindakan (`action_context`: `all`, `actionable`, `waiting_others`) diterapkan pada Cuti/Izin, Lembur, dan Reimbursement dieksekusi langsung di tingkat query SQL database (`withActionContext`), mengeliminasi pemuatan memori kandidat dan menjaga pagination serta isolasi perusahaan.
- Otoritas keputusan disentralisasi pada `SubmissionApproval` (`canDecide`, `actionType`, `whyCannotDecide`) dengan penegakan mutlak larangan self-approval, pengalihan otomatis ke peninjau cadangan saat peninjau utama nonaktif/archived/foreign company, serta penutupan ketat endpoint `decide()` untuk data legacy `needs_review` (409 Conflict) yang diarahkan khusus untuk rekonsiliasi.
- Query SQL `waiting_others` diperkuat dengan pembungkusan `COALESCE` dan penanganan array workflow kosong `[]` secara presisi, menghindari hilangnya baris pengajuan akibat three-valued logic (NULL) SQL baik di SQLite maupun MySQL.
- Modal pratinjau keputusan (`<dialog id="decision-review-dialog">`) menampilkan pemohon, pengajuan, pilihan keputusan, alasan wajib, dan pratinjau dampak saldo/alur (Leave & Balance Impact Preview) sebelum penyimpanan, dengan proteksi anti-double-click.
- Formulir dan otorisasi review permohonan pembatalan cuti dipisahkan khusus untuk status `cancellation_requested` (Setujui Pembatalan memulihkan saldo via ledger; Tolak Pembatalan mempertahankan status approved dan saldo terpakai).
- Riwayat keputusan distandarisasi dengan pemetaan peristiwa lengkap (`stage_approved`, `approved`, `cancellation_requested`, `cancellation_approved`, `cancellation_rejected`, `rejected`, `cancelled`, `updated`).
- Model Flutter `SubmissionApprovalEvent` diselaraskan secara *backward-compatible*.
- Hasil pengujian: Suite [`ApprovalFlowRefinementTest`](apps/backend/tests/Feature/ApprovalFlowRefinementTest.php) (9 tes/113 assertions) dan seluruh test suite backend PHPUnit lulus 100% (**150 tes / 1.945 assertions**).
- Verifikasi kompatibilitas MySQL 9.3 InnoDB pada database QA terisolasi berhasil lulus 100% (9 tes/113 assertions) dan database QA telah dibersihkan kembali. Tidak ada mutasi pada database utama; commit dan rilis tetap menunggu instruksi pengguna.

## Rencana verifikasi UI approval — 28 Oktober 2026

[Checklist browser/Flutter](hris-approval-browser-flutter-verification.md) dan [prompt Antigravity](hris-approval-verification-antigravity-prompt.md) disiapkan setelah pemeriksaan perbaikan tiga celah approval. Dokumen acuan rencana pengujian checklist B01–B18 dan F01–F10.

## Verifikasi browser dan Flutter UI approval — 28–29 Oktober 2026

[Verifikasi antarmuka Dashboard Web dan Flutter karyawan tuntas dilaksanakan dan disempurnakan](hris-approval-ui-verification-results.md) sesuai checklist B01–B18 dan F01–F10:
- **Pengujian Browser Dashboard (B01–B18)**: 18 skenario (55 checks terperinci) lulus 100% menggunakan Chrome 156 via Puppeteer CDP pada database SQLite QA terisolasi loopback `127.0.0.1:8018`. Memverifikasi pemisahan antrean `all`/`actionable`/`waiting_others`, paginasi >10 dengan query string utuh, larangan self-approval, pengalihan fallback reviewer, proteksi 409 data `needs_review`, dialog modal review keputusan beserta pratinjau dampak saldo, pembatalan approved, dan proteksi XSS pada tema terang/gelap serta resolusi desktop 1440 dan sempit 390. Skenario B10 dilengkapi assertion mutasi riil: tracking request HTTP POST tepat 1 kali saat klik ganda cepat, verifikasi penambahan tepat 1 approval event, versi bertambah tepat +1, serta ledger saldo tidak termutasi ganda/prematur. Menghasilkan 28 screenshot PNG di `docs/screenshots/approval-ui-verification/`.
- **Pengujian Mobile Flutter (F01–F10)**: 10 skenario unit & widget test pada `apps/mobile/test/approval_ui_flutter_verification_test.dart` lulus 100% (4 detik). Memverifikasi fallback parsing payload lama, metadata event baru, status pembatalan ditolak kembali ke approved, detail multi-stage progress & active reviewer, form pengajuan pembatalan cuti disetujui, refresh reaktif pasca keputusan web, pemulihan error jaringan/409/401, perlindungan state saat pergantian akun pengguna, serta keandalan rendering tanpa `RenderFlex` overflow pada viewport sempit 320x640 dengan skala teks 1.8x. Skenario F07 membuktikan alur penuh dari `SubmissionListPage` membuka detail, mengajukan pembatalan, kembali via `BackButton`, dan secara otomatis memicu refresh daftar (`listLoads == 2`) sehingga status daftar terbarui menjadi `Menunggu pembatalan`.
- **Regresi Lengkap**: Seluruh suite Flutter mobile lulus 100% (**95 tes lulus**, 0 gagal; baseline 85 + 10 tes baru). Regresi backend PHPUnit tetap 100% passing (**150 tes / 1.945 assertions**).
- **Batasan Pengujian**: Pengujian Flutter masih berada di tingkat unit & widget test harness terisolasi (belum mencakup pengujian integrasi live Android/emulator native dengan backend HTTP); pengujian browser dijalankan secara headless otomatis pada server loopback independen. Database MySQL utama pengguna tidak dimutasi sama sekali; direktori QA disposable telah dibersihkan. Commit, push, dan pengemasan komersial tetap ditunda sesuai instruksi pengguna.


## Rencana penyempurnaan H5 — 29 Oktober 2026

[PRD koreksi absensi dan rekap](hris-h5-flow-refinement-prd.md) serta [prompt Antigravity](hris-h5-flow-refinement-antigravity-prompt.md) tersedia untuk audit dan implementasi Dashboard. Prioritas mencakup izin yang menunggu pembatalan tetap efektif, integritas audit dan konflik koreksi, shift malam, penelusuran rekap, serta konsistensi filter/CSV. Status masih rencana; belum menjadi bukti implementasi atau pengujian baru. Mobile menunggu review Dashboard. Commit, push, deployment dan pengemasan tetap ditunda.

## Penyempurnaan koreksi absensi dan rekap kehadiran H5 — 29 Oktober 2026

[Penyempurnaan H5 selesai diimplementasikan pada Dashboard Web dan diverifikasi penuh](hris-h5-flow-refinement-report.md) sesuai PRD:
- **Koreksi Absensi & Integritas Audit (A)**: Modal pratinjau sebelum simpan `<dialog id="correction-preview-dialog">` membandingkan before/after jam dan koordinat, mewajibkan alasan setelah trim (ditolak 422 jika kosong), tombol *Kembali mengedit* menutup dialog tanpa mutasi dengan pemulihan fokus, proteksi klik ganda cepat (`isSubmitting`), pendeteksian no-op dengan notice info tanpa menambah event audit palsu, serta kolom migrasi aditif `version` untuk pencegahan *lost update* (HTTP 409 Conflict).
- **Definisi Status & Sumber Angka (B)**: Tanggal kerja mendatang (`$date > $today`) diklasifikasikan sebagai `'upcoming'` (Terjadwal) dan tidak dihitung sebagai alpa; hari kerja berjalan membedakan belum check-in dengan alpa lampau selesai; tanggal kerja asal shift malam (22:00–06:00) dipertahankan utuh dan timestamp UTC dikonversi presisi sesuai zona waktu perusahaan.
- **Sinkronisasi Izin/Cuti dengan Rekap (C)**: Status `cancellation_requested` diakui penuh sebagai cuti efektif (1 hari kerja), pembatalan ditolak tetap approved, pembatalan disetujui (status `cancelled`) melepas hari cuti (0 hari); pembacaan rekapitulasi, detail, dan ekspor CSV terverifikasi tidak memutasi ledger saldo cuti.
- **Rekap yang Dapat Ditelusuri (D)**: Disediakan rute drilldown `/attendance-recap/{user}` (`AttendanceRecapController::show`) yang menyajikan kalender harian, jenis hari, jadwal, data absensi, dan konteks izin/cuti; tombol kembali mempertahankan filter aktif (`start_date`, `end_date`, `name`, `organization_unit_id`, `page`) secara aman internal tanpa risiko open redirect.
- **Filter & Ekspor CSV (E)**: Ekstraksi logika ke service `AttendanceRecap`; filter rentang tanggal (maksimal 90 hari inklusif), nama, unit organisasi, dan hak supervisi dieksekusi di level query SQL sebelum paginasi; kalkulasi metrik ringkasan (`calculateSummaryChunked`) dan streaming ekspor CSV (`streamRecapRows`) beroperasi per chunk (batch 100 karyawan) dengan pengurutan deterministik `->orderBy('name')->orderBy('id')` dan bounded memory usage; ekspor CSV diawali UTF-8 BOM (`\xEF\xBB\xBF`), mencakup seluruh hasil filter lintas halaman, dan sel teks dibungkus proteksi formula injection `CsvCell::text`.
- **UX & Keamanan (F)**: Light/dark mode dan responsif pada desktop 1440×1000 dan sempit 390×844 tanpa horizontal overflow; sanitasi XSS aman menggunakan `textContent`; penegakan otorisasi supervisor bawahan (403 Forbidden untuk karyawan lain), penolakan autentikasi akun nonaktif (401 / redirect login), dan isolasi tenant foreign company.
- **Penyelesaian Review Temuan PRD**:
  1. *Konsistensi detail harian dan agregat rekap*: Pada `dailyBreakdown()`, pengecekan hari libur/akhir pekan kini dievaluasi sebelum izin/cuti. Hari libur dalam rentang cuti diklasifikasikan sebagai status utama `'holiday'` (`Hari Libur` / `Hari libur mingguan`) dan izin tetap disertakan sebagai konteks detail (`$item['permission']`), sehingga jumlah hari `'leave'` pada breakdown harian sinkron 1:1 dengan `leave_days` pada rekap agregat.
  2. *Pemrosesan bertahap dan streaming CSV*: Menghilangkan pemuatan seluruh koleksi karyawan ke memori sekaligus dengan batch chunking dan streaming output CSV langsung ke response stream via `fputcsv()`.
- **Hasil Pengujian**:
  - Regresi Backend Penuh: **161 tests, 2.042 assertions (100% PASS)** pada SQLite in-memory (naik dari 150 tes / 1.945 assertions).
  - Suite Terarah H5: **16 tests, 155 assertions (100% PASS)** (`H5FlowRefinementTest` 11 tes / 97 assertions, `AttendanceCorrectionRecapTest` 5 tes / 58 assertions).
  - Pengujian MySQL 9.3 InnoDB Terisolasi: **38 tests, 397 assertions (100% PASS)** mencakup uji konkurensi multi-proses independen dengan row locking `FOR UPDATE` dan `version`.
  - Verifikasi Browser Nyata (Chrome 156 / Puppeteer CDP): 12 skenario (H5-01 s/d H5-12, 40 checks terperinci) **100% LULUS** pada server loopback QA `127.0.0.1:8018`.
  - Artefak: 20 file screenshot PNG di `docs/screenshots/h5-flow-refinement/` dan bukti JSON di `docs/hris-h5-flow-refinement-results.json`.
- **Batasan & Status**: Database MySQL utama tidak dimutasi; seluruh resource QA sementara telah dibersihkan bersih. Perubahan aplikasi mobile Flutter ditunda menunggu hasil review Dashboard Web. Tidak ada perintah commit, push, atau deployment yang dijalankan.


## Review visual H5 dan penyempurnaan lifecycle karyawan H1 — 30 Oktober 2026

[Review visual H5 dan penyempurnaan alur karyawan H1 selesai diimplementasikan dan diverifikasi](hris-web-review-and-h1-refinement-report.md) sesuai PRD:
- **Tahap 1 — Review Visual Dashboard H5**:
  - Peninjauan 20 bukti visual pada direktori `docs/screenshots/h5-flow-refinement/` terhadap pedoman `design.md` pada tema terang dan gelap serta resolusi desktop (1440×1000) dan sempit (390×844) tanpa *horizontal overflow*.
  - Sesuai instruksi PRD, status review visual H5 dicatat secara tegas sebagai **menunggu review pengguna**, bukan disetujui secara sepihak oleh agen.
- **Tahap 2 — Penyempurnaan Lifecycle Karyawan H1**:
  - **Pencegahan Lost Update (`version`)**: Migrasi aditif `2026_10_30_000001_add_version_to_users.php` menambahkan kolom unsigned integer `version` (default 0) pada tabel `users`. Pembaruan dengan token versi usang langsung ditolak dengan respon HTTP 409 Conflict.
  - **Modal Pratinjau Perubahan Status**: Dialog `<dialog id="lifecycle-preview-dialog">` pada formulir edit dan dialog `<dialog id="archive-user-{id}">` pada indeks karyawan menampilkan perbandingan status sebelum/sesudah, alasan wajib hasil trim, dampak pencabutan akses login/sesi/token, serta jaminan pelestarian riwayat operasional dan audit.
  - **Reaktivasi Eksplisit dari Arsip**: Pengaktifan kembali karyawan dari status arsip merupakan transisi eksplisit (`action = 'reactivated'`) yang membutuhkan alasan non-kosong, mencabut sesi/token lama via `auth_epoch` baru, dan melestarikan data historis.
  - **Deteksi No-Op Update**: Penyimpanan tanpa perubahan data tidak memicu mutasi basis data, tidak menaikkan `version`, dan tidak mencatat event audit fiktif (0 event audit baru), melainkan memberikan pesan informasi bahwa tidak ada perubahan.
  - **Proteksi Admin Terakhir & Self-Deactivation**: Otorisasi level server mencegah admin menonaktifkan atau menurunkan perannya sendiri saat menjadi admin aktif terakhir, dengan penguncian tingkat baris perusahaan (`Company::lockForUpdate()`) di MySQL untuk serialisasi transaksi konkuren.
  - **Retensi Unit & Atasan Nonaktif**: Hubungan historis karyawan dengan unit organisasi nonaktif tetap dipertahankan dan diberi label jelas `(nonaktif)` pada antarmuka, sementara unit/atasan nonaktif diblokir dari pilihan baru.
  - **Dampak Alur Approval**: Pengajuan izin, lembur, dan reimbursement existing mempertahankan snapshot alur dan kontrak resolver yang berlaku; pengajuan baru otomatis mengikuti atasan aktif terbaru; peninjau yang nonaktif dialihkan ke fallback reviewer; larangan *self-approval* ditegakkan mutlak.
- **Hasil Pengujian**:
  - Regresi Backend Penuh: **171 tests, 2.192 assertions (100% PASS)** pada SQLite in-memory terisolasi (naik dari 161 tes / 2.042 assertions).
  - Suite Terarah H1: **10 tests, 150 assertions (100% PASS)** (`H1FlowRefinementTest`).
  - Pengujian MySQL 9.3 InnoDB Terisolasi: **12 tests, 144 assertions (100% PASS)** (`H1FlowRefinementMySqlTest` dan `EmployeeLifecycleConcurrencyMySqlTest`) mencakup balapan multi-proses konkuren dengan `lockForUpdate` dan `version`.
  - Verifikasi Browser Nyata (Chrome / Puppeteer CDP): 5 skenario UI interaktif (H1-01, H1-04, H1-07, H1-10, H1-11; 37 checks terperinci) **100% LULUS** pada server loopback QA `127.0.0.1:8018`.
  - Artefak: 20 file screenshot PNG di `docs/screenshots/web-review-h1-refinement/` dan bukti JSON di `docs/hris-web-review-and-h1-refinement-results.json`.
- **Batasan & Status Lingkungan**: Database MySQL utama pengguna tidak dimutasi sama sekali; berkas `.env` utama dan Firebase tidak diubah; direktori QA sementara telah dibersihkan bersih; tidak ada perintah commit, push, atau deployment yang dijalankan; mobile Flutter dan Milestone H2 tetap berada dalam status backlog.

## Penyempurnaan alur penugasan shift web dan verifikasi konkurensi H2 — 30 Oktober 2026

[Penyempurnaan alur penugasan shift web dan verifikasi konkurensi selesai diimplementasikan dan diuji](hris-h2-shift-assignment-refinement-prd.md) sesuai arahan penyesuaian:
- **Penyesuaian Arahan & Batasan Ruang Lingkup**:
  - `expected_id` diverifikasi sebagai mekanisme proteksi konkurensi append-only yang sah dan telah divalidasi wajib (`required|integer|min:0`) pada controller, bukan fitur yang belum ada.
  - Peringatan jeda istirahat antar-shift ditegaskan memerlukan ambang kebijakan perusahaan (configurable threshold) di masa depan, bukan 12 jam universal yang kaku.
  - Rotasi otomatis, penugasan massal per unit organisasi, dan split shift ditegaskan tetap berada dalam **backlog**.
  - Review visual H5 dan H1 tetap berstatus terbuka menunggu review visual final pengguna; evaluasi agen dipisahkan dari klaim pengukuran rasio kontras/target sentuh laboratorium.
- **Modal Pratinjau Penugasan Jadwal (`#shift-assignment-preview-dialog`)**:
  - Menampilkan ringkasan karyawan, zona waktu perusahaan, tabel perbandingan sebelum vs sesudah (nama shift, jam masuk/pulang, dan tanggal mulai berlaku `effective_from`).
  - Penjelasan dinamis dampak pada sesi berjalan & tanggal berlaku: membedakan hari ini (`effective_from === today`), masa depan (`effective_from > today`), shift lintas tengah malam (*cross-midnight*), dan status tanpa shift.
  - Alasan perubahan wajib ditampilkan pada modal sebelum penyimpanan.
  - Tombol *Kembali mengedit* menutup dialog tanpa mutasi data dan mempertahankan isian form.
  - Tombol *Konfirmasi simpan jadwal* mengunci pengiriman ganda via atribut `disabled`.
- **Penanganan Konflik Konkurensi yang Mempertahankan Input**:
  - `EffectiveShiftController::store` menangkap `HttpException` status 409 pada web form biasa dan mengembalikan redirect: `back()->withInput()->with('conflict', $e->getMessage())`.
  - Pesan konflik ditampilkan pada kotak notice semantik amber (`<div class="notice amber">`).
  - Isian form (`shift_id`, `effective_from`, `reason`) dipulihkan otomatis oleh helper `old(...)`, menjaga alasan kerja HR tidak hilang saat terjadi race condition.
- **Hasil Pengujian**:
  - Regresi Backend Penuh: **172 tests, 2.208 assertions (100% PASS)** pada SQLite in-memory terisolasi (naik dari 171 tes / 2.192 assertions).
  - Suite Terarah H2: **8 tests, 80 assertions (100% PASS)** (`EffectiveShiftTest`) mencakup stale `expected_id` web redirect with preserved inputs & 0 mutation, verifikasi modal dialog & atribut cross-midnight, serta pengujian string copy dampak shift malam presisi.
  - Pengujian Konkurensi Multi-Proses MySQL 9.3 InnoDB Spesifik H2: **2 tests, 11 assertions (100% PASS)** (`EffectiveShiftConcurrencyMySqlTest`):
    1. Skenario balapan 2 aktor HR simultan dengan barrier gate microtime menghasilkan tepat 1 status 200 (berhasil) dan 1 status 409 (konflik versi), dengan tepat 1 baris baru di `employee_shift_assignments` (tidak ada lost update).
    2. Skenario penugasan lanjutan dengan `expected_id` stale langsung ditolak 409 tanpa mutasi basis data.
  - Verifikasi Browser Nyata (Chrome / Puppeteer CDP): **14 checks (B01 s/d B14) 100% LULUS** pada server loopback QA `127.0.0.1:8018`:
    1. Buka preview penugasan jadwal dengan data sebelum/sesudah dan alasan akurat.
    2. Copy teks dampak shift malam terverifikasi persis: *"Jam pulang berada pada hari berikutnya. Tanggal kerja mengikuti jadwal yang ditentukan server dan dapat berbeda dari tanggal kalender saat check-in."*
    3. Tombol *Kembali mengedit* menutup dialog tanpa mutasi data (0 penambahan di basis data) dan mempertahankan isian.
    4. Tombol *Konfirmasi simpan jadwal* mengunci pengiriman ganda via status `disabled`.
    5. Penanganan konflik konkurensi menampilkan notice amber HTTP 409 dan memulihkan seluruh isian form (`reason`, `effective_from`, `shift_id`) via `old()`.
    6. Bukti tersimpan di `docs/effective-shift-browser-qa.json` dan screenshot PNG di `docs/screenshots/effective-shifts/`.
- **Batasan & Status Lingkungan**: Database MySQL utama pengguna tidak dimutasi; direktori QA disposable telah dibersihkan; tidak ada perintah commit, push, atau deployment yang dijalankan.



## Persetujuan review visual detail pengajuan mobile — 4 November 2026

Pengguna menyatakan **review visual disetujui** untuk detail Izin, Lembur, dan Reimbursement mobile. Cakupan: pemisahan catatan pemohon/keputusan/riwayat tahap, label waktu perusahaan, informasi durasi/nominal, teks panjang pada layar sempit 360px, tema terang/gelap, serta error dan retry lampiran privat. Pengguna melaporkan matriks widget tanpa render overflow/unhandled exception; tidak ada pengujian ulang oleh agen pada pencatatan ini. Persetujuan ini khusus halaman detail pengajuan mobile, tidak otomatis menutup review Dashboard web lain yang masih terbuka. Tidak ada perubahan kode aplikasi. Commit, push, deployment dan pengemasan tetap ditunda sesuai instruksi pengguna.


## Rencana penutupan review Dashboard — 4 November 2026

[PRD penutupan review](hris-dashboard-review-closure-prd.md) dan [prompt Antigravity](hris-dashboard-review-closure-antigravity-prompt.md) tersedia untuk menyiapkan paket review konkret H1/H2/H5, memperbaiki temuan dalam lingkup, dan mencatat snapshot batas fitur. Status rencana, bukan hasil tes atau persetujuan web. Detail pengajuan mobile tetap disetujui sesuai catatan sebelumnya. Commit, push, deployment dan pengemasan tetap ditunda.


## Penutupan review Dashboard dan pembekuan cakupan fitur — 4 November 2026

[Paket review Dashboard](hris-dashboard-user-review.md), [laporan penutupan review](hris-dashboard-review-closure-report.md), [hasil pengujian terstruktur C01–C09](hris-dashboard-review-closure-results.json), dan [status pembekuan cakupan fitur](hris-feature-scope-status.md) telah selesai disusun dan diverifikasi secara teknis sesuai [PRD](hris-dashboard-review-closure-prd.md):
- **Status Review Visual**:
  - Detail pengajuan mobile (Izin, Lembur, Reimbursement): **Disetujui Pengguna** per 4 November 2026.
  - Dashboard Web (H1, H2, H5, Approval, Cuti, Lembur, Reimbursement): **Menunggu Review Pengguna**. Agen telah menuntaskan audit teknis, verifikasi browser headless Chrome CDP, dan penyusunan bukti visual komprehensif; keputusan visual diserahkan kepada pengguna melalui tabel checklist di paket review.
- **Hasil Verifikasi Teknis**:
  - Matriks penerimaan C01 s/d C09 tuntas diverifikasi berstatus **lulus** dengan dukungan assertion aktual dan tangkapan layar.
  - Suite pengujian backend terarah PHPUnit lulus 100% (**35 tests, 409 assertions**, durasi 5.35 detik) mencakup `H1FlowRefinementTest` (10 tes / 150 assertions), `EffectiveShiftTest` (8 tes / 80 assertions), `H5FlowRefinementTest` (11 tes / 97 assertions), dan `AttendanceCorrectionRecapTest` (6 tes / 82 assertions).
  - Suite pengujian mobile Flutter terarah lulus 100% (**24 tests** pada `submission_refinement_verification_test.dart` dan **8 tests** pada `submission_approval_mobile_test.dart`).
- **Batasan Lingkungan & Rilis**:
  - Database MySQL utama pengguna tidak dimutasi sama sekali (tidak ada migrate, seed, atau reset pada DB utama).
  - Berkas `.env` utama dan konfigurasi Firebase tidak dimodifikasi.
  - Seluruh server QA loopback telah dihentikan secara bersih; tidak ada proses background yang ditinggalkan.
  - Fitur biometrik wajah produksi tetap ditunda (deferred).
  - Peningkatan sekunder (rotasi shift otomatis, penugasan massal per unit, pencarian cepat karyawan dropdown, kalender visual grid, dan split shift) dicatat sebagai backlog opsional non-blocking.
  - Operasi git commit, push, deployment, dan pengemasan komersial **tetap ditunda** sesuai arahan pengguna.


## Rencana panduan operasional — 4 November 2026

[PRD panduan operasional](hris-operational-guides-prd.md) dan [prompt Antigravity](hris-operational-guides-antigravity-prompt.md) tersedia. Cakupan dokumentasi HR/Admin, karyawan mobile, troubleshooting, batas fitur dan peta pembelajar. Status rencana, belum merupakan panduan selesai atau hasil verifikasi baru. Pengemasan/instalasi pembeli, perubahan aplikasi, commit, push dan deployment di luar lingkup.


## Penyusunan panduan operasional HR dan karyawan — 4 November 2026

Paket dokumentasi operasional berbahasa Indonesia telah selesai disusun, diaudit silang terhadap basis kode riil, dan diindeks pada [`docs/operations/`](operations/README.md) sesuai ketentuan [PRD](hris-operational-guides-prd.md):
- **Paket Dokumentasi Operasional yang Dihasilkan**:
  1. [`docs/operations/README.md`](operations/README.md): Indeks sentral, diagram peruntukan dokumen berdasarkan peran pengguna, matriks navigasi cepat, dan batas cakupan operasional.
  2. [`docs/operations/hr-admin-guide.md`](operations/hr-admin-guide.md): Panduan komprehensif pengelola HR, Admin, dan Supervisor memuat matriks otorisasi peran, 7 checklist kesiapan UI, dan 11 prosedur operasional berurutan (akses/navigasi, master karyawan/arsip/reaktivasi, unit organisasi, kalender/timezone, shift efektif & baseline, metode/radius, cuti & buku besar saldo, alur approval & snapshot, lembur/reimbursement, koreksi absensi & modal diff, serta rekapitulasi & ekspor CSV).
  3. [`docs/operations/employee-mobile-guide.md`](operations/employee-mobile-guide.md): Panduan mandiri karyawan mobile memuat 12 bab (akses akun & keamanan kredensial, kartu jadwal Beranda, check-in/out QR & GPS radius, shift malam cross-midnight `attendance_date` vs `qr_date`, sesi menggantung > 24 jam, pengajuan izin rentang tanggal, lembur 1–1.440 menit, reimbursement batas 2.048 KiB format JPG/PNG dengan preservasi berkas valid, riwayat & paginasi, pembatalan izin `cancellation_requested`, unduh/retry lampiran privat, dan sinkronisasi Beranda).
  4. [`docs/operations/troubleshooting.md`](operations/troubleshooting.md): Matriks terstruktur memetakan 10 gejala wajib, penyebab teknis terverifikasi, langkah mandiri aman, batas eskalasi ke HR/Admin, format laporan nonrahasia, serta penegasan prinsip aman transaksi jaringan (dilarang manipulasi jam HP atau menghapus basis data).
  5. [`docs/operations/feature-boundaries.md`](operations/feature-boundaries.md): Penegasan batas fungsional, keterbatasan teknis arsitektur (rekapitulasi 90 hari, lembur 1.440 menit, kuitansi 2 MB, sesi terkunci 24 jam, optimistic locking `version`/`expected_id` HTTP 409), status penundaan biometrik wajah produksi, dan backlog masa depan.
  6. [`docs/operations/source-code-learning-map.md`](operations/source-code-learning-map.md): Peta arsitektur kode sumber untuk pengembang mencakup tata letak monorepo, service domain backend, arsitektur Flutter BLoC, pola konkurensi, penelusuran alur end-to-end pengajuan cuti, serta peta pengujian otomatis.
  7. [`docs/hris-operational-guides-report.md`](hris-operational-guides-report.md): Laporan audit & penerimaan mengevaluasi kriteria D01–D10 dengan status jujur 100% Lulus, daftar sumber per bab, dan verifikasi tautan.
- **Keterhubungan & Status Rilis**:
  - Tautan panduan operasional ditambahkan pada `README.md` utama.
  - Status Dashboard Web tetap **Menunggu Review Pengguna**; detail pengajuan mobile tetap **Disetujui Pengguna 4 November 2026**.
  - Fitur biometrik pengenalan wajah tetap berstatus **Ditunda (Deferred)** untuk produksi.
  - Basis data MySQL utama, berkas `.env`, dan konfigurasi Firebase tidak diubah.
  - Operasi git commit, push, deployment, dan pengemasan komersial tetap berada dalam status **Ditunda (Deferred)** sesuai instruksi pengguna.


## Penutupan review visual Dashboard dan pembekuan cakupan versi — 4 November 2026

Pemeriksaan review visual Dashboard Web resmi **Ditutup dan Disetujui Pengguna** melalui [paket review](hris-dashboard-user-review.md), perbaikan temuan P1–P4 telah diterima, panduan operasional telah dicocokkan langsung terhadap enam alur kerja utama aplikasi, dan cakupan fungsional versi ini resmi dibekukan:
- **Status Persetujuan Final**:
  - **Detail Pengajuan Mobile (Izin, Lembur, Reimbursement)**: Disetujui Pengguna (4 November 2026).
  - **Dashboard Web (H1 Karyawan, H2 Shift, H5 Rekap/Koreksi, H4 Approval, Lembur, Reimbursement)**: Disetujui Pengguna (4 November 2026).
  - **Paket Panduan Operasional HR & Karyawan**: Terverifikasi selaras 100% dengan alur aplikasi aktual.
- **Pencocokan Enam Alur Utama**:
  1. *Tambah Karyawan*: `Karyawan` → `Data karyawan` → `Tambah Karyawan` (`/users/create`), validasi NIK unik, retensi unit/manajer nonaktif, modal arsip `showModal()`.
  2. *Atur Jadwal*: `Jadwal kerja` → `Jadwal efektif` (`/effective-shifts`), penugasan bertanggal efektif, modal pratinjau before/after, baseline target date, proteksi 409 `expected_id`.
  3. *Absensi*: Beranda mobile, jam & zona waktu terlabel, scan QR resmi monitor + GPS radius kantor, shift malam cross-midnight `attendance_date`.
  4. *Pengajuan*: Mobile form izin/cuti (hitung hari kerja otomatis & sisa kuota), lembur (1–1.440 menit), reimbursement (kuitansi JPG/PNG <= 2 MB dengan preservasi file valid).
  5. *Approval*: `Pengajuan` → `Cuti / izin` (atau Lembur/Reimbursement), filter `Konteks tindakan: Perlu tindakan saya`, modal pratinjau keputusan, mutasi saldo cuti atomik, catatan keputusan terpisah di mobile.
  6. *Rekap*: `Kehadiran` → `Rekap kehadiran` (`/attendance-recap`), filter periode <= 90 hari, pengakuan status cuti `cancellation_requested`, ekspor CSV UTF-8 BOM, drilldown rincian harian.
- **Keterbatasan Operasional Terverifikasi yang Dibekukan**:
  - Pemilih karyawan menggunakan `<select>` standar tanpa live search virtualized.
  - Alur approval belum dilengkapi notifikasi push/email instan saat berganti tahap.
  - Lampiran bukti mobile khusus format gambar JPG/PNG (tidak mendukung dokumen PDF).
  - Filter rekapitulasi kehadiran dibatasi maksimal 90 hari kalender inklusif per kueri.
  - Sesi menggantung > 24 jam terkunci otomatis dan memerlukan koreksi manual HR.
  - Agregasi unit rekapitulasi mengacu pada unit karyawan saat ini (*current unit*).
  - Permohonan pembatalan cuti approved menunggu keputusan persetujuan HR.
- **Fitur Ditunda & Status Repositori**:
  - Biometrik pengenalan wajah produksi dan fitur opsional (rotasi shift otomatis, import bulk shift, split shift) tetap berada dalam backlog.
  - Basis data MySQL utama pengguna tidak dimutasi (0 migration/seed pada DB utama).
  - Operasi git commit, push repository, deployment server, dan pengemasan komersial tetap ditunda menunggu perintah pengguna.
