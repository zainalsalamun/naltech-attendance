# Verifikasi browser dan Flutter — approval HR/atasan

Tanggal: 28 Oktober 2026, Asia/Jakarta.
Status: **rencana verifikasi**, bukan hasil pengujian.
[Prompt Antigravity](hris-approval-verification-antigravity-prompt.md).

## Tujuan dan kondisi awal

Menuntaskan bukti antarmuka untuk implementasi A–G pada [PRD approval](hris-approval-flow-adjustment-prd.md). Verifikasi dengan tindakan pengguna sebenarnya dan respons server, bukan hanya keberadaan markup atau pembacaan kode.

Implementasi antrean SQL, otorisasi, tinjauan keputusan, pembatalan dan klasifikasi riwayat sudah tersedia. Tiga temuan review sebelumnya telah ditangani pada kode; suite terarah SQLite telah diperiksa ulang dan lulus **9 tes/113 assertions**. Laporan implementasi mencatat MySQL 9.3/InnoDB 9 tes/113 assertions dan regresi backend 150 tes/1.945 assertions. Angka tersebut adalah bukti sesi sebelumnya, **jangan diklaim sebagai hasil baru tanpa dijalankan ulang**.

Baca `AGENTS.md`, `agent.md`, `design.md`, `skills.md`, PRD, [laporan implementasi](hris-approval-flow-refinement-report.md), dan [perapian cuti karyawan](hris-h4-flow-adjustment.md). Periksa kondisi aktual karena perubahan lokal mungkin bertambah.

## Batasan

- Tidak commit, push, deploy atau mengerjakan pengemasan/uji instalasi pembeli.
- Tidak mengubah atau memakai database utama untuk fixture, seed, reset, rollback atau tes mutasi.
- Browser dan aplikasi memakai akun, storage dan database disposable. Jangan menggunakan data/foto/lampiran pribadi.
- Pertahankan pekerjaan lokal, isolasi perusahaan, snapshot workflow dan aturan saldo yang berlaku.
- Face tetap tidak tersedia. Jangan mengubah URL mobile asli, `.env`, Firebase atau rahasia konfigurasi.
- Semua shell command mengikuti prefix `rtk` sesuai instruksi repo.
- Flutter karyawan tetap untuk membaca status/riwayat dan mengajukan pembatalan; jangan membuat konsol approval HR mobile.

## Tahap 1 — Persiapan

1. Catat status Git dan baseline tes yang relevan.
2. Baca harness pada `tools/mobile-qa/`, khususnya `backend.php` dan skrip workflow/browser yang tersedia. Reuse bila sesuai; jangan menganggap fixture lama otomatis mendukung alur terbaru.
3. Siapkan perusahaan A/B dengan akun sintetis: pemohon staf, atasan tahap 1, HR tahap 2, fallback, pengelola di luar alur, dan akun nonaktif/arsip.
4. Siapkan pengajuan permission, overtime dan reimbursement, proses satu tahap/tanpa workflow, workflow kosong `[]`, peninjau kosong, serta data legacy `needs_review`.
5. Untuk cuti bersaldo, buat alokasi dan ledger melalui layanan/alur yang benar sehingga dapat diverifikasi sebelum/sesudah. Pisahkan fixture negatif/malformed dari alur operasional normal.
6. Pastikan email/push/layanan eksternal dinonaktifkan atau diganti transport lokal selama pengujian.

## Tahap 2 — Browser nyata pada Dashboard

Gunakan browser tersedia melalui alat yang sesuai atau Playwright yang sudah ada. Uji formulir, interaksi JavaScript, navigasi dan respons aktual.

| ID | Skenario | Hasil yang diharapkan |
|---|---|---|
| B01 | Filter all/actionable/waiting_others pada tiga modul | Scope baca dan hak bertindak berbeda dengan benar; status/nama/tanggal tetap bekerja |
| B02 | Lebih dari 10 pengajuan dan navigasi halaman | Total akurat, tidak ada kandidat hilang/duplikat, filter dipertahankan dalam URL |
| B03 | Peninjau tahap 2 membuka pending tahap 1 | Dapat membaca bila diizinkan, tetapi tidak memutuskan atau masuk actionable tahap 2 |
| B04 | Pemohon membuka pengajuan sendiri | Tidak ada self-approval; bukan actionable untuk keputusan |
| B05 | Utama nonaktif/arsip atau foreign, fallback sah | Antrean dan detail mengikuti resolver server; akun foreign tidak memperoleh akses |
| B06 | Reviewer/fallback kosong; workflow NULL/[] | waiting_others tidak kehilangan baris; workflow kosong mengikuti proses satu tahap |
| B07 | Legacy needs_review | Hanya Detail/penjelasan rekonsiliasi; tidak ada form biasa; POST langsung ke endpoint keputusan tetap ditolak |
| B08 | Pilih keputusan dan alasan lalu Tinjau | Dialog berisi pemohon, modul, tanggal/durasi/nominal yang relevan, keputusan, alasan dan dampak |
| B09 | Kembali mengedit/tutup dialog/Escape | Tidak ada request mutasi; alasan/pilihan dipertahankan; fokus kembali sesuai konteks |
| B10 | Konfirmasi dan klik berulang | Satu keputusan sah; tombol busy; status dan versi diperbarui tanpa event/ledger ganda |
| B11 | Persetujuan tahap antara/final dan penolakan | Tahap serta peninjau berubah benar; cadangan/use/release sesuai transaksi |
| B12 | Pembatalan approved menunggu keputusan | Form Keputusan pembatalan; pengajuan tetap berlaku dan saldo terpakai belum kembali |
| B13 | Pembatalan ditolak | Status kembali approved, saldo tetap used; riwayat menyebut pembatalan ditolak |
| B14 | Pembatalan disetujui | Status cancelled, restore_used hanya sekali; riwayat menyebut pembatalan disetujui |
| B15 | Jenis non-saldo, lembur dan reimbursement | Tidak ada janji mutasi saldo cuti; tidak menawarkan pembatalan approved di modul yang tidak mendukung |
| B16 | Halaman lama/dua peninjau/versi lama | Konflik tidak menampilkan sukses palsu; muat ulang keadaan sebelum tindakan berikutnya |
| B17 | Penolakan akses lintas perusahaan dan akun nonaktif | Server tetap menolak meskipun URL/POST dirangkai langsung |
| B18 | Nama/alasan panjang dan karakter HTML | Ditampilkan sebagai teks; layout dan dialog tidak rusak |

Jalankan skenario tampilan utama pada light/dark, desktop 1440×1000 dan sempit 390×844. Periksa keyboard, focus trap/pemulihan fokus dialog, tombol Enter, scroll dialog, overlay, pagination serta overflow halaman. Catat error console yang benar-benar muncul; jangan menyembunyikan error agar hasil tampak lulus.

Untuk risiko timeout setelah commit, gunakan harness terkontrol yang tetap mengeksekusi layanan produksi tetapi menunda/memutus respons. Jika tidak dapat direproduksi, tandai belum diuji; jangan menebak dari kode. Jangan memutus layanan utama pengguna.

## Tahap 3 — Flutter karyawan

Mulai dari tes model/Cubit/widget yang relevan, lalu satu alur Android emulator dengan backend fixture nyata jika lingkungan tersedia. Jangan meminta HP fisik sebagai prasyarat; emulator sudah menjadi pilihan pengguna sebelumnya.

| ID | Skenario | Hasil yang diharapkan |
|---|---|---|
| F01 | Payload API lama tanpa action_label/event_type | Tetap diparse dan memiliki fallback label yang dapat dipahami |
| F02 | Payload baru untuk seluruh event | Tahap disetujui, final, reject, cancel, pembatalan diminta/disetujui/ditolak tampil benar |
| F03 | Pembatalan ditolak lalu status approved | UI tidak mengartikannya sebagai approval awal baru; pengajuan tetap berlaku |
| F04 | Pending tahap 1 → tahap 2 → final melalui Dashboard | Detail mobile membaca tahap, peninjau dan riwayat terbaru dari server |
| F05 | Ajukan pembatalan approved dari mobile | Status cancellation_requested; saldo belum kembali; tombol pengiriman berulang tertahan |
| F06 | Keputusan pembatalan dari web | Mobile refresh menampilkan cancelled/restored atau approved/used sesuai keputusan |
| F07 | Kembali dari detail | Daftar induk menampilkan status terbaru, tanpa menambah route tak perlu |
| F08 | Error jaringan, 409 atau sesi berakhir | Tidak sukses palsu/tindakan memakai versi lama; pengguna memperoleh langkah pemulihan |
| F09 | Detail dibuka lalu respons terlambat/akun berubah | Respons lama tidak menimpa sesi atau record yang salah |
| F10 | Tema, sempit, teks besar, alasan panjang | Riwayat, dialog dan aksi tidak overflow dan tetap dapat digunakan |

Utamakan datasource, layar dan layanan produksi. Jika lokasi, picker atau transport tertentu disuntikkan fixture, nyatakan batasnya. Widget screenshot bukan screenshot native; emulator bukan HP fisik. Kamera/GPS/galeri tidak wajib dalam scope approval ini.

## Tahap 4 — Jika ditemukan masalah

Perbaiki bug dalam lingkup PRD, tambahkan regresi yang membuktikan masalah, lalu ulangi pemeriksaan yang terdampak. Pertahankan aturan bisnis dan kompatibilitas API aditif.

Jika menemukan ketidaksesuaian kebijakan baru atau perubahan destruktif, jelaskan dan minta keputusan untuk bagian tersebut; lanjutkan pekerjaan independen yang aman. Jangan hanya mengganti teks dokumentasi menjadi “lulus” tanpa perbaikan dan rerun.

## Tahap 5 — Regresi dan bukti

- Jalankan tes backend terkait setelah perbaikan logika; perluas regresi sekali setelah perubahan selesai.
- Jalankan tes Flutter model/approval/leave/submission terkait; analisis file yang berubah.
- Jika SQL antrean diubah, uji cabang SQLite dan MySQL terisolasi. Gunakan `tools/mysql-qa/README.md`; jangan mengalihkan tes ke database utama ketika provisioning gagal.
- Jika hanya perubahan tampilan tanpa perubahan transaksi, jangan mengklaim MySQL concurrency diuji ulang berdasarkan tes lama.
- Hentikan server QA, bersihkan paket/koneksi emulator QA yang dibuat dan database/akun MySQL disposable menggunakan scope terverifikasi. Jangan menghapus aplikasi/data utama.

## Deliverables

Buat `docs/hris-approval-ui-verification-results.md` dan hasil terstruktur bila bermanfaat, berisi:

| ID | Status | Lingkungan | Hasil aktual/bukti | Batasan atau tindak lanjut |
|---|---|---|---|---|
| B01–B18 / F01–F10 | Lulus / gagal / belum dijalankan / terhalang | Browser/widget/emulator + DB | Link laporan/screenshot relevan | Jangan kosong bila belum selesai |

Simpan screenshot sintetis di `docs/screenshots/approval-ui-verification/`. Jangan memasukkan token, password, `.env`, dump database atau data pribadi. Laporkan jumlah tes aktual, bukan jumlah baris checklist. Perbarui timeline dengan tanggal pelaksanaan dan status terukur.

## Definisi selesai

Antrean/detail/form/riwayat sesuai layanan server; browser membuktikan dialog dan pengiriman nyata; Flutter membuktikan parsing dan tampilan event serta alur refresh. Semua kegagalan yang ditemukan dalam lingkup diperbaiki dan diuji ulang, sedangkan cakupan yang terhalang dijelaskan secara eksplisit. Status boleh “terverifikasi sesuai cakupan” bila ada batas native/lingkungan; jangan menyebut seluruh produk 100% siap produksi.
