# Rencana pengerjaan berikutnya dan testing bertahap

Tanggal: 21 Oktober 2026, Asia/Jakarta.
Status: **rencana**, belum merupakan laporan pelaksanaan atau kelulusan tes.
Prompt pelaksanaan: [prompt siap pakai](hris-next-development-prompt.md).

## 1. Tujuan dan sumber acuan

Tuntaskan perapian navigasi dan validasi alur H4 yang sudah dikembangkan, sebelum menambah modul lain. Pengembangan dilakukan bersama testing per irisan: periksa → perbaiki → uji bagian terkait → dokumentasikan bukti. Jangan mengulang implementasi yang sudah ada hanya karena status pada ringkasan lama belum diperbarui.

Acuan:
- `agent.md`, `design.md`, `skills.md`.
- [Implementasi H4 rentang tanggal dan ledger](hris-h4-leave-balances-and-date-range.md).
- [Restrukturisasi sidebar](sidebar-navigation-restructuring.md).
- [Timeline HRIS](hris-core-timeline.md).
- [Penerapan database utama sebelumnya](database-main-migration.md).

Instruksi terbaru untuk menyiapkan pekerjaan sambil testing menggantikan penundaan testing pada rencana pelaksanaan berikutnya. Pembuatan dokumen ini sendiri **tidak menjalankan testing atau migrasi**. Commit tetap ditunda sampai perintah pengguna; tidak melakukan push/deployment.

## 2. Kondisi awal yang perlu dipertahankan

- H4 rentang cuti dan ledger sudah memiliki implementasi backend, Dashboard dan Flutter; validasi menyeluruhnya masih terbuka.
- Struktur sidebar aktual adalah **6 menu utama: Ringkasan tunggal dan 5 grup accordion**, bukan 6 accordion ditambah Ringkasan.
- Grup: Karyawan, Kehadiran, Jadwal kerja, Pengajuan, Pengaturan. Area akun di footer terpisah dari hitungan menu utama.
- Temuan pembacaan kode: `.sidebar-submenu` menggunakan `display:flex` sementara toggle memakai atribut `hidden`; perlu aturan eksplisit agar submenu tertutup benar-benar tersembunyi, termasuk pada media pointer coarse.
- Dokumen H4 awal masih menyebut 7 kelompok; tabel ringkasan timeline juga perlu diselaraskan dengan pembaruan H4 di bagian bawah.
- Delapan migrasi sebelumnya sampai `000007` sudah diterapkan ke database utama. Migrasi H4 baru `2026_10_21_000008_add_leave_balances_and_date_range.php` harus diperiksa status aktualnya; jangan menyimpulkan sudah diterapkan berdasarkan laporan batch sebelumnya.
- Face tetap tidak tersedia. Jangan mengubah URL mobile, Firebase, rahasia konfigurasi atau data utama untuk menyiapkan testing.

## 3. Urutan pekerjaan dan hasil tiap tahap

| Tahap | Pengerjaan | Testing yang menyertai | Bukti/hasil wajib |
|---|---|---|---|
| P0 — Baseline | Periksa Git, instruksi, route, schema dan implementasi aktual; petakan celah alur | Jalankan baseline di database terisolasi; pisahkan kegagalan yang sudah ada dari regresi | Daftar temuan berprioritas, status migrasi, hasil baseline |
| P1 — Sidebar | Perbaiki hidden, akses submenu, indikator aktif, persistensi, drawer dan footer; selaraskan dokumentasi | Chrome dengan fixture terisolasi, light/dark, desktop dan sempit, keyboard dan role | Pemetaan menu final, screenshot, daftar checks aktual |
| P2 — Inti H4 | Tinjau perhitungan tanggal, overlap, periode, alokasi dan ledger; perbaiki bug yang ditemukan | Tes backend aturan bisnis, API, otorisasi, transaksi dan idempotensi | Hasil tes serta aturan yang didukung/tidak didukung |
| P3 — Alur web | Rapikan konfigurasi → alokasi → preview → submit → approval → pembatalan → ledger | Browser melalui formulir sebenarnya dan backend fixture | Bukti alur HR/karyawan dan kondisi gagal |
| P4 — Mobile | Selaraskan kontrak, tanggal, preview, saldo, status, pembatalan dan navigasi setelah hasil web direview | Tes model/Cubit/widget, kemudian Android emulator dengan API fixture nyata | Bukti status UI/HTTP; batas kamera/GPS dinyatakan |
| P5 — Penutupan | Jalankan regresi yang relevan, selaraskan timeline dan daftar sisa; siapkan penerapan schema | Verifikasi migrasi pada salinan database dan pemeriksaan pascamigrasi bila diterapkan | Laporan akhir, bukti dan batasan; tanpa commit |

Jangan melompati masalah saldo/otorisasi untuk sekadar mengejar tampilan. Setelah pemeriksaan terkait lulus, lanjut tahap berikutnya; jangan mengulang tes tanpa perubahan atau alasan baru.

## 4. Detail P1 — Sidebar

Perbaikan awal: tambahkan `.sidebar-submenu[hidden] { display: none; }` dengan prioritas yang tetap menang atas aturan submenu pada semua breakpoint.

Periksa juga akses saldo/alokasi dan ledger setelah dikelompokkan ke Cuti/Izin. Penanda aktif saja tidak cukup: pengguna harus memiliki tautan menuju setiap halaman yang masih digunakan. Tempatkan tautan pada halaman induk bila tidak ditampilkan sebagai submenu, tanpa menambah tingkat navigasi ketiga.

Matriks pemeriksaan:

| Skenario | Hasil yang diharapkan |
|---|---|
| Buka/tutup satu grup | Submenu benar-benar muncul/hilang; grup lain tidak berubah |
| Grup tertutup | Tautan tersembunyi tidak dapat difokuskan melalui Tab |
| Route index/create/edit/detail/ledger | Indikator modul benar dan grup terbuka otomatis |
| Muat ulang halaman | Preferensi dipulihkan; halaman aktif diprioritaskan |
| Akun/perusahaan berbeda | Preferensi tidak tercampur |
| localStorage tidak tersedia/isi tidak valid | Navigasi tetap dapat digunakan tanpa error yang memutus skrip |
| Desktop 1440 dan tablet 1024 | Body navigasi dapat digulir; header/footer tidak menutup menu |
| Lebar 390 dan 800/801 | Drawer/breakpoint konsisten; tidak ada overflow halaman |
| Drawer mobile | Buka, tutup, backdrop, Escape dan navigasi berfungsi; fokus dipulihkan |
| Keyboard | Enter/Space mengoperasikan toggle; fokus terlihat; ARIA sesuai keadaan |
| Staf/supervisor/HR/admin | Tidak ada grup kosong; menu sesuai izin; route tetap dilindungi server |

Persistensi harus memvalidasi hasil JSON sebagai objek preferensi sebelum dimutasi. Jangan memakai penyimpanan bersama antar akun sebagai fallback diam-diam.

## 5. Detail P2 — Aturan H4 dan ledger

| Area | Kasus wajib | Hasil yang diharapkan |
|---|---|---|
| Rentang | Satu hari, banyak hari, akhir sebelum awal, seluruhnya libur, kalender kosong, batas panjang | Preview dan submit konsisten; input invalid ditolak jelas |
| Kalender | Aturan mingguan, pengecualian kerja/libur, perubahan kalender setelah submit | Mengikuti konfigurasi eksplisit; rincian historis pengajuan dipertahankan |
| Shift malam | Jadwal melintasi tengah malam | Menggunakan tanggal kerja asal; tidak dihitung dua hari |
| Overlap | pending, approved, cancellation_requested, needs_review; rejected/cancelled | Pengajuan aktif bertumpuk ditolak; pengajuan selesai ditolak/batal tidak menghalangi ulang |
| Jenis cuti | Mengurangi saldo, tidak mengurangi saldo, jenis nonaktif, lampiran wajib | Kebijakan ditegakkan server; jenis non-saldo tidak membuat mutasi saldo |
| Alokasi | Awal, penyesuaian, massal, saldo sudah dicadangkan/terpakai | Tidak menghapus pemakaian/cadangan; semua perubahan tercatat dengan alasan |
| Saldo | Belum dialokasikan, tidak cukup, pas sejumlah kebutuhan | Jenis yang mengurangi saldo ditolak bila belum ada alokasi atau saldo kurang; tidak berubah menjadi cuti gratis |
| Approval | Antara, final, reject, cancel pending | reserve sekali, use pada final, release sekali pada reject/cancel |
| Pembatalan approved | Permohonan, HR setuju/tolak, permintaan/keputusan berulang | Saldo dikembalikan sekali saat pembatalan disetujui; penolakan mempertahankan used |
| Periode | Rentang Desember–Januari, tahun lampau/mendatang | Kebijakan eksplisit; jangan membebankan semua hari ke tahun awal secara diam-diam |
| Legacy | Pengajuan lama tanpa referensi reservasi | Tidak mengambil/mengembalikan saldo baru yang tidak pernah dicadangkan |
| Scope/role | Perusahaan lain, pengguna lain, staf, supervisor, akun nonaktif, self-approval | Ditolak server sesuai kewenangan aktual |
| Request berulang | Submit/approve/cancel diulang atau memakai versi lama | Tidak ada ledger ganda atau perubahan saldo kedua |
| Konkurensi | Dua submit bersamaan pada saldo terakhir; keputusan bersamaan | Tidak overspend; satu transisi sah dan jumlah saldo konsisten |

Invariant saldo: `allocated_days = remaining_days + reserved_days + used_days`, semua komponen tidak negatif. Ledger harus menjelaskan perubahan sebelum/sesudah dan memiliki referensi yang dapat ditelusuri.

Untuk lintas tahun, pilihan yang disarankan pada tahap awal adalah menolak rentang lintas tahun dengan pesan untuk memisahkan pengajuan. Jika produk sudah memiliki kebijakan lain yang eksplisit, pertahankan dan dokumentasikan; minta keputusan pengguna bila perubahan berdampak pada kebijakan saldo. Jangan mengarang prorata, carry-over, cuti setengah hari atau saldo negatif.

Tes SQLite membantu aturan fungsional, tetapi tidak membuktikan locking MySQL. Uji race/row lock memakai MySQL **terisolasi**, bukan database utama. Jika belum tersedia, laporkan cakupan konkurensi belum terbukti.

## 6. Detail P3–P4 — Alur web dan mobile

Alur utama yang harus dapat ditelusuri:

1. HR menyiapkan kalender, jenis cuti dan alur peninjau.
2. HR mengalokasikan saldo periode dengan alasan.
3. Karyawan memilih jenis dan rentang; melihat hari dihitung/dilewati, saldo serta persyaratan.
4. Pengguna meninjau ringkasan sebelum mengirim; server menghitung ulang saat submit.
5. Pengajuan tersimpan dan saldo dicadangkan; pengguna melihat peninjau/tahap aktif.
6. Approval akhir mengubah cadangan menjadi terpakai; penolakan/batal mengembalikannya.
7. Pengajuan approved yang ingin dibatalkan masuk keputusan HR; ledger dan riwayat menampilkan hasilnya.

Preview bukan jaminan saldo masih tersedia saat submit. Saat tanggal/jenis berubah, hasil preview lama tidak boleh tampak sebagai hasil terbaru. Respons terlambat dari request lama tidak boleh menimpa pilihan baru.

Periksa juga error koneksi, session expiry, validasi lapangan, submit ganda, nama panjang, teks besar, tema, pagination/filter, kembali dari detail dan pemuatan ulang daftar/saldo. Di mobile, hindari status sukses sebelum respons server diterima.

## 7. Pelaksanaan teknis dan isolasi

- Semua perintah shell diawali `rtk` sesuai instruksi repositori.
- Baca konfigurasi tes sebelum menjalankan suite. Pastikan database testing benar-benar terisolasi; jangan jalankan `migrate:fresh`, reset atau seed pada database utama.
- Backend: mulai dengan tes yang relevan seperti `LeavePolicyTest`, `SubmissionApprovalTest`, `MobileSubmissionTest`, `PermissionWorkspaceTest`, serta tes H4 baru bila belum ada. Jalankan regresi lengkap setelah perubahan logika selesai.
- Flutter: tes `leave_policy_mobile_test.dart`, `submission_approval_mobile_test.dart`, `submission_workspace_test.dart`, dan cakupan baru untuk rentang/ledger. Analisis file produksi yang berubah; catat jika tooling standar gagal dan jalur penggantinya.
- Blade dikompilasi di cache disposable lalu dibersihkan. Aset Workspace berasal dari `public`; jangan menambah pipeline build tanpa kebutuhan.
- Browser memakai akun sintetis dan server QA yang terikat loopback. Jangan menulis data pengujian ke perusahaan utama.
- Emulator memakai salinan source dan package QA terpisah. Jangan mengubah URL asli/Firebase atau memakai foto/file pribadi sebagai fixture.
- Jangan mencetak token, password, header autentikasi, `.env` atau dump database ke laporan. Backup tetap di luar Git.

## 8. Penerapan migrasi H4 baru

Periksa `migrate:status` pada koneksi yang benar. Jika `000008` belum diterapkan, validasi dahulu pada salinan staging. Periksa khusus backfill tanggal izin lama, jumlah baris dan preservasi status/riwayat. Backfill tanggal tunggal tidak berarti membuat ledger atau saldo historis rekaan.

Migrasi utama sebelumnya telah diizinkan pengguna dan commit merupakan tindakan yang ditunda. Untuk penerapan berikutnya dalam lingkup yang sama: buat backup baru di luar repo, periksa migrasi tertunda, gunakan migrasi maju yang mempertahankan data, lalu verifikasi status, jumlah baris serta pemetaan data. Jika ditemukan operasi destruktif, hentikan operasi tersebut dan jelaskan kebutuhan keputusan pengguna. Jangan rollback otomatis ketika sebagian migrasi sudah berjalan.

Pembuatan dokumen ini tidak menerapkan `000008`.

## 9. Kriteria selesai dan pelaporan

- Sidebar dapat digunakan tanpa menu hilang/terbuka palsu dan semua halaman penting dapat ditemukan.
- Tidak ada bug terbuka yang menyebabkan akses lintas perusahaan, saldo negatif, pemotongan ganda atau pengembalian saldo yang tidak semestinya.
- Preview, submit, keputusan dan riwayat web/mobile menjelaskan hasil yang konsisten.
- Hasil aktual dibedakan menjadi lulus, gagal, belum dijalankan dan terhalang lingkungan.
- Tabel ringkasan timeline sesuai keadaan terakhir; catatan historis tetap diberi konteks.
- Tidak menyamakan “kode tersedia”, “tes lulus” dan “schema utama diterapkan”.

Format laporan per tahap:

| Tahap | Perubahan | Pemeriksaan dan hasil aktual | Bukti | Sisa/batasan |
|---|---|---|---|---|
| P0–P5 | Diisi setelah pengerjaan | Jumlah/skenario aktual, bukan target | File laporan/screenshot relevan | Termasuk tooling/lingkungan |

Setelah P0–P5 selesai, prioritas berikutnya adalah H5: penelusuran audit koreksi dan rekap periode, lalu H6 pengemasan source code. Jangan membuka modul payroll/rekrutmen/performance dalam pekerjaan ini.
