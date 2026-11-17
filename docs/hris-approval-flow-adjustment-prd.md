# PRD — Perapian approval untuk HR dan atasan

Tanggal: 27 Oktober 2026, Asia/Jakarta.
Status: **rencana implementasi**, bukan laporan fitur selesai atau hasil testing.
Pelaksana berikutnya: Antigravity. [Prompt siap pakai](hris-approval-antigravity-prompt.md).

## 1. Tujuan

Merapikan pengalaman peninjau untuk cuti/izin, lembur dan reimbursement: mengetahui pengajuan yang membutuhkan tindakannya, memahami keputusan berikutnya, meninjau dampaknya sebelum menyimpan, serta membaca riwayat tanpa salah mengartikan keputusan pembatalan.

Fokus pada fitur operasional yang sudah tersedia. Pengemasan, instalasi pembeli, payroll dan modul baru tetap ditunda. Commit menunggu perintah pengguna; tidak push/deploy.

## 2. Kondisi awal dan acuan

- Backend Laravel, Dashboard Blade Workspace, aplikasi Flutter.
- Approval bertahap sudah tersedia melalui `ApprovalWorkflow` dan `SubmissionApproval`.
- `visibleTo()` menentukan pengajuan yang boleh dibaca; **dapat dibaca tidak berarti boleh diputuskan**. Peninjau tahap berikutnya dapat termasuk pengguna yang boleh membaca.
- Peninjau aktif memakai snapshot langkah pengajuan, pengguna yang masih aktif dan fallback yang memenuhi syarat. Konfigurasi workflow baru tidak menulis ulang snapshot pengajuan lama.
- Status `cancellation_requested` adalah alur keputusan pembatalan cuti, bukan kelanjutan approval awal.
- Alur cuti karyawan, preview saldo dan tinjauan sebelum kirim telah dirapikan. Jangan membangun ulang atau menghapus perubahan ini.

Baca `AGENTS.md`, `agent.md`, `design.md`, `skills.md`, [timeline](hris-core-timeline.md), [alur H3](hris-h3-workflows.md), [perapian H4](hris-h4-flow-adjustment.md), dan [bukti MySQL sebelumnya](hris-review-fixes-and-mysql-testing.md). Periksa kode aktual; angka tes lama bukan bukti kelulusan perubahan baru.

## 3. Lingkup

### A. Daftar pengajuan dan antrean tindakan

Pada masing-masing daftar Cuti/Izin, Lembur, Reimbursement, tambahkan filter konteks tindakan:

| Pilihan | Arti |
|---|---|
| Semua yang dapat saya lihat | Mempertahankan scope baca yang ada |
| Perlu tindakan saya | Saya saat ini berwenang mengambil keputusan pengajuan atau permohonan pembatalan |
| Menunggu peninjau lain | Pengajuan masih berjalan, tetapi keputusan berikutnya bukan kewenangan saya |

Status dan konteks tindakan merupakan filter terpisah. Pertahankan pencarian, tanggal dan pagination. Jika kombinasi filter tidak memiliki hasil, jelaskan kondisi tersebut dan sediakan reset filter.

Tampilkan status pengajuan, tahap aktif, nama peninjau atau keterangan peninjau belum tersedia, serta jenis tindakan: **Tinjau pengajuan** atau **Tinjau pembatalan**. Jangan menggunakan label generik “Setujui” pada daftar tanpa ringkasan konteks.

Pengajuan sendiri tidak masuk Perlu tindakan saya. Jika peninjau utama tidak aktif dan fallback sah, tindakan mengikuti resolver server. Jika tidak ada peninjau yang memenuhi syarat, tampilkan “Peninjau belum tersedia; hubungi pengelola”. Jangan memberikan kewenangan otomatis kepada HR di luar aturan layanan yang berlaku.

Jika indikator/jumlah antrean ditambahkan pada Dashboard, sumber dan scope harus sama dengan daftar. Jangan menghitung seluruh pending sebagai pekerjaan pengguna yang sedang login.

### B. Detail dan tindakan berikutnya

Detail menampilkan:
- Pemohon, jenis pengajuan, tanggal/rentang, durasi atau nominal sesuai modul.
- Status pengajuan saat ini.
- Tahap aktif dan peninjau untuk pengajuan pending bertahap.
- Tindakan berikutnya dan pihak yang berwenang.
- Riwayat, catatan keputusan dan bukti pendukung sesuai akses yang berlaku.

Jika pengguna hanya memiliki akses baca, tampilkan alasan ringkas mengapa tindakan tidak tersedia. Jangan merender form keputusan yang nantinya selalu ditolak oleh server.

Pada pengajuan tanpa workflow bertahap, jelaskan sebagai persetujuan satu tahap; gunakan aturan role yang sudah ada. Data legacy `needs_review` tidak boleh otomatis menjadi pending atau memperoleh tombol keputusan normal.

### C. Tinjauan sebelum menyimpan keputusan

Sesudah peninjau memilih keputusan dan mengisi alasan wajib, tampilkan ringkasan sebelum request mutasi dikirim.

Ringkasan berisi pemohon, modul, tanggal/rentang, keputusan, alasan dan dampak:

| Kondisi | Penjelasan dampak |
|---|---|
| Setuju tahap antara | Lanjut ke tahap berikutnya; cuti bersaldo tetap dicadangkan |
| Setuju tahap terakhir | Pengajuan disetujui; cadangan cuti yang terkait menjadi terpakai |
| Tolak pengajuan | Alur berakhir; cadangan cuti terkait dilepas ke tersedia |
| Setujui pembatalan approved | Pengajuan menjadi dibatalkan; saldo yang pernah terpakai dikembalikan |
| Tolak pembatalan approved | Pengajuan kembali disetujui dan tetap berlaku; saldo tetap terpakai |
| Jenis non-saldo / lembur / reimbursement | Tidak menampilkan klaim mutasi saldo cuti |

Bedakan kuota kebijakan, saldo agregat terbaru dan dampak transaksi pengajuan ini. Untuk legacy tanpa reservasi yang dapat dipastikan, jangan menjanjikan pengembalian saldo rekaan. Ringkasan tidak menulis data dan tidak membekukan saldo.

Tindakan dalam dialog: **Kembali mengedit** dan **Konfirmasi keputusan**. Kembali mempertahankan pilihan/alasan. Menutup dialog tidak menyimpan keputusan. Saat mengirim, tahan klik ganda. Server tetap memeriksa kewenangan, status dan `approval_version` di dalam transaksi.

### D. Keputusan permohonan pembatalan

Pertahankan kewenangan server aktual: layanan saat ini mengizinkan HR/admin atau supervisor yang merupakan atasan langsung, sesuai kondisi pengajuan. Perapian UI tidak boleh mempersempit/memperluas kebijakan ini diam-diam.

Pada `cancellation_requested`:
- Judul form: **Keputusan pembatalan**.
- Opsi: **Setujui pembatalan** / **Tolak pembatalan**.
- Jelaskan bahwa pengajuan tetap berlaku selama menunggu keputusan dan saldo terpakai belum kembali.
- Jangan menampilkan “tahap approval awal berikutnya” sebagai peninjau pembatalan bila alurnya berbeda.
- Permintaan pembatalan approved hanya tersedia untuk cuti/izin sesuai aturan yang ada; jangan menambahnya pada lembur/reimbursement.

### E. Istilah dan riwayat lintas modul

Gunakan label status konsisten: Menunggu persetujuan, Disetujui, Ditolak, Dibatalkan, Menunggu pembatalan, Perlu ditinjau.

Label kejadian harus membedakan makna transisi:
- pending → pending karena langkah maju: “Tahap disetujui; menunggu tahap berikutnya”.
- pending → approved: “Pengajuan disetujui”.
- approved → cancellation_requested: “Pembatalan diajukan”.
- cancellation_requested → cancelled: “Pembatalan disetujui”.
- cancellation_requested → approved: “Pembatalan ditolak; pengajuan tetap disetujui”.

Jangan menyebut transisi terakhir sebagai persetujuan pengajuan baru. Simpan dan tampilkan aktor, waktu, alasan serta tahap jika relevan. Perubahan label tidak menulis ulang event historis. Tanggal/jam ditampilkan dengan zona waktu yang jelas.

### F. Mobile karyawan

Sesudah web diperiksa, selaraskan label status, kejadian riwayat, peninjau aktif, serta dampak pembatalan di detail mobile. Jangan menambahkan konsol approval HR di mobile dalam lingkup ini. Jika menambahkan metadata API, gunakan perubahan aditif dan pertahankan kontrak lama.

## 4. Pendekatan teknis

Area awal yang diperiksa, bukan daftar file yang wajib semuanya diubah:
- `apps/backend/app/Services/SubmissionApproval.php`
- `apps/backend/app/Services/ApprovalWorkflow.php`
- `apps/backend/app/Models/Concerns/HasSubmissionApproval.php`
- Controller daftar/detail permission, overtime, reimbursement dan Dashboard.
- `apps/backend/resources/views/components/submission-approval.blade.php`
- View index/detail tiga modul dan aset Workspace terkait.
- Model/datasource/detail pengajuan Flutter.

Pusatkan penentuan **boleh bertindak sekarang** pada aturan bersama yang konsisten dengan layanan mutasi, termasuk fallback, self-approval dan pembatalan. Jangan menyalin kondisi role berbeda pada controller, Blade dan JavaScript.

Filter dilakukan sebelum pagination; jangan mengambil satu halaman lalu menyaringnya di PHP sehingga jumlah dan pagination salah. Hindari query per baris; gunakan batch/eager loading atau query terukur. Jangan mengambil seluruh tabel untuk menghitung badge.

Modal/skrip memakai teks yang di-escape, bukan interpolasi HTML dari nama/alasan. Gunakan komponen dialog dan token desain Workspace, tombol keyboard yang benar, fokus terlihat dan pemulihan fokus. Periksa light/dark serta layar sempit. Tidak membutuhkan dependency atau schema baru kecuali kebutuhan nyata ditemukan dan dijelaskan.

## 5. Alur peninjau

1. Login sebagai atasan/HR.
2. Pilih modul dan filter Perlu tindakan saya.
3. Buka detail; pahami pemohon, tahap/status, peninjau dan dampaknya.
4. Pilih keputusan dan isi alasan.
5. Tinjau ringkasan; kembali mengedit atau konfirmasi.
6. Server menyimpan keputusan secara atomik.
7. Detail dan daftar menampilkan status/antrean terbaru, dengan riwayat bermakna.

Jika request gagal/timeout/409, jangan mengasumsikan keputusan belum tersimpan. Muat ulang keadaan aktual sebelum menawarkan pengiriman berikutnya. Pertahankan alasan yang diketik sejauh aman, tetapi jangan mengirim otomatis berdasarkan versi lama.

## 6. Kriteria penerimaan dan testing

| Area | Skenario wajib |
|---|---|
| Scope | Tenant lain, staf, atasan langsung/bukan atasan, akun nonaktif, pemilik sendiri |
| Tahap | Peninjau aktif, peninjau langkah berikutnya, utama nonaktif/fallback aktif, tidak ada fallback, proses satu tahap |
| Filter | Tiga konteks, kombinasi status/nama/tanggal, pagination, count dan empty-state konsisten |
| Tinjauan | Belum ada mutasi sebelum konfirmasi; kembali mengedit menjaga isian; alasan wajib; klik ganda |
| Cuti bersaldo | Approval antara/final, penolakan, permintaan pembatalan approved, pembatalan disetujui/ditolak; ledger tidak ganda |
| Modul lain | Lembur/reimbursement tidak menjanjikan perubahan saldo atau pembatalan approved |
| Riwayat | Semua transisi di bagian E diberi label tepat; actor/waktu/alasan tetap ada |
| Konflik | Dua keputusan versi sama, hak berubah setelah halaman dibuka, timeout sesudah transaksi commit |
| UI | Light/dark, 1440px dan 390px, teks panjang, keyboard, fokus dialog, tidak overflow halaman |
| Flutter | Parsing metadata aditif, label dan riwayat, refresh detail/parent, session expiry dan respons terlambat |

Gunakan tes backend terisolasi serta browser dengan fixture. Uji MySQL terisolasi untuk klaim locking/konkurensi; SQLite saja tidak membuktikannya. Gunakan harness `tools/mysql-qa/` bila masih sesuai, baca panduannya dahulu. Jangan menjalankan seed/reset/tes mutasi pada database utama.

Sesuaikan tes yang tersedia dan tambah skenario yang membuktikan perubahan aturan. Laporkan baseline gagal terpisah dari regresi. Simpan screenshot dan hasil aktual, tidak memakai angka tes historis sebagai angka sesi baru.

## 7. Urutan pengerjaan

1. Audit aktual dan baseline bagian terkait; dokumentasikan mapping hak baca vs hak bertindak.
2. Resolver/konteks tindakan dan filter daftar, beserta tes role/tahap/pagination.
3. Detail, tinjauan keputusan dan pemisahan pembatalan; tes transaksi dan browser.
4. Riwayat/istilah tiga modul dan penyelarasan mobile karyawan.
5. Regresi relevan, dokumentasi hasil dan daftar sisa.

Prioritaskan menyelesaikan alur yang dapat digunakan dari awal sampai akhir; jangan berhenti pada rencana atau UI tanpa validasi server.

## 8. Batas kerja dan definisi selesai

- Pertahankan perubahan lokal; jangan reset Git atau menimpa pekerjaan di luar scope.
- Ikuti prefix `rtk` bila menjalankan shell sesuai instruksi repositori.
- Tidak mengubah `.env`, token, kredensial, URL mobile atau Firebase; jangan mencetak rahasia.
- Tidak mengubah kalender/kuota/approval policy perusahaan, snapshot lama, radius atau status Face.
- Tidak menjalankan migrasi baru atau merotasi akun utama untuk keperluan testing.
- Tidak ada commit, push, deployment atau pengemasan source code dalam tugas ini.

Selesai ketika antrean, detail, keputusan dan riwayat konsisten dengan server, skenario terkait lulus, dan bukti serta batas pengujian dicatat. Bedakan implementasi tersedia, teruji, belum diuji dan terhalang lingkungan. Tidak menyatakan produk keseluruhan “100% siap produksi”.
