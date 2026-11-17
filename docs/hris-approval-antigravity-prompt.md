# Prompt untuk Antigravity — perapian approval HR/atasan

Disiapkan 27 Oktober 2026. Salin blok di bawah sebagai instruksi kerja di Antigravity.
Dokumen ini bukan bukti implementasi atau testing sudah dijalankan.

```text
Kerjakan perapian alur approval HR/atasan pada proyek:
/Users/macbookpro/development/fic/absensi/naltech-attendance

Baca dahulu:
1. AGENTS.md, agent.md, design.md dan skills.md.
2. docs/hris-approval-flow-adjustment-prd.md — acuan detail scope, aturan dan penerimaan.
3. docs/hris-core-timeline.md.
4. docs/hris-h3-workflows.md dan docs/hris-h4-flow-adjustment.md.
5. docs/hris-review-fixes-and-mysql-testing.md bila perlu menyiapkan pengujian MySQL.

Fokus saat ini perbaikan fitur yang sudah ada, bukan pengemasan source code, uji instalasi pembeli atau pembuatan modul baru. Commit tetap menunggu perintah saya. Jangan commit/push/deploy.

Periksa kode aktual dan perubahan Git sebelum mengedit; pertahankan pekerjaan lokal yang sudah ada. Approval bertahap, saldo cuti, tinjauan pengajuan karyawan dan pembatalan approved sudah tersedia; jangan membangunnya ulang.

Implementasikan bertahap:

A. Pisahkan daftar cuti/izin, lembur dan reimbursement berdasarkan konteks:
- Semua yang dapat saya lihat.
- Perlu tindakan saya sekarang.
- Menunggu peninjau lain.
Pertahankan pencarian, tanggal, status, pagination dan scope perusahaan. Hak membaca visibleTo tidak sama dengan hak memutuskan. Filter sebelum pagination, jumlah harus konsisten. Jangan memasukkan pengajuan sendiri atau pengajuan tahap berikutnya sebagai pekerjaan saya sekarang.

B. Gunakan aturan bersama yang sesuai layanan mutasi untuk menentukan tindakan saat ini, termasuk peninjau utama/fallback aktif, larangan self-approval, proses tanpa workflow dan permohonan pembatalan. Jangan menyalin kondisi role yang berbeda di beberapa view/controller. Jangan memberi HR kewenangan tambahan hanya agar tombol terlihat.

C. Rapikan detail: status pengajuan, tahap berjalan, peninjau aktif, tindakan berikutnya dan alasan bila hanya dapat membaca. Peninjau yang tidak tersedia harus diberi pesan jelas. Data legacy needs_review tidak boleh otomatis berubah menjadi pending.

D. Tambahkan Tinjau keputusan sebelum menyimpan. Tampilkan pemohon, modul, tanggal/rentang, keputusan, alasan wajib dan dampak yang benar:
- Setuju antara: lanjut tahap, cadangan cuti tetap.
- Setuju final: cadangan terkait menjadi terpakai.
- Tolak pengajuan: cadangan terkait dikembalikan.
- Setujui pembatalan approved: saldo terpakai terkait dikembalikan.
- Tolak pembatalan: pengajuan tetap approved, saldo tetap terpakai.
- Jenis non-saldo/lembur/reimbursement: tanpa klaim dampak saldo cuti.
Dialog memiliki Kembali mengedit dan Konfirmasi keputusan. Kembali mempertahankan isian. Tidak ada mutasi sampai konfirmasi. Cegah klik ganda dan tetap periksa versi/kewenangan di server dalam transaksi. Setelah timeout/409, muat ulang status aktual sebelum tindakan berikutnya; jangan retry otomatis.

E. Pisahkan form Keputusan pembatalan dari approval awal. Opsi Setujui pembatalan/Tolak pembatalan harus jelas. Pengajuan tetap berlaku selama cancellation_requested, saldo belum kembali. Pertahankan kewenangan server aktual (termasuk supervisor atasan langsung bila memang diizinkan); jangan mengubahnya diam-diam menjadi khusus HR. Jangan menambahkan pembatalan approved untuk lembur/reimbursement.

F. Samakan riwayat tiga modul. Bedakan tahap disetujui, pengajuan disetujui, pembatalan diajukan, pembatalan disetujui dan pembatalan ditolak. Khusus cancellation_requested -> approved harus tertulis pembatalan ditolak/pengajuan tetap berlaku, bukan seolah persetujuan awal baru. Jangan menulis ulang event historis.

G. Setelah web diperiksa, selaraskan label dan riwayat pada mobile karyawan. Tidak membuat konsol approval HR di mobile. Metadata API baru harus aditif dan tidak merusak klien lama.

Ikuti desain Workspace Naltech light/dark, tampilan sempit dan akses keyboard. Render nama/alasan sebagai teks yang di-escape. Hindari query per baris dan pemuatan seluruh tabel untuk badge.

Testing diizinkan dan dilakukan per irisan. Gunakan database/akun/berkas fixture terpisah. Jalankan tes backend role/tahap/versi/ledger, browser formulir sebenarnya, dan tes Flutter terkait. Jangan menggunakan database utama untuk seed, reset, migrate:fresh atau testing mutasi. SQLite tidak cukup untuk klaim konkurensi MySQL; gunakan MySQL terisolasi jika menguji locking dan dokumentasikan lingkungan sebenarnya. Jangan mengklaim uji HP fisik bila hanya emulator atau widget.

Batas lain:
- Semua shell command mengikuti prefix rtk sesuai instruksi repo.
- Jangan mengubah .env, rahasia, URL mobile atau Firebase.
- Jangan mencetak token/password/header autentikasi.
- Pertahankan isolasi perusahaan, validasi server, radius, snapshot historis dan Face tetap tidak tersedia.
- Jangan mengubah kuota, kalender atau kebijakan approval bisnis tanpa kebutuhan dan keputusan yang jelas.
- Hindari dependency dan migrasi baru untuk pekerjaan ini. Jika menemukan perubahan destruktif atau keputusan kebijakan yang belum jelas, jelaskan sebelum tindakan itu; lanjutkan bagian independen yang aman.

Kerjakan sampai scope PRD selesai, jangan berhenti setelah hanya membuat rencana. Beri update singkat sepanjang pengerjaan. Di akhir, laporkan perubahan, skenario/jumlah tes aktual, bukti screenshot, dan batas atau masalah yang tersisa. Perbarui dokumen hasil serta timeline dengan tanggal aktual. Jangan menyalin angka kelulusan lama sebagai hasil baru. Jangan commit sampai saya memerintahkannya.
```
