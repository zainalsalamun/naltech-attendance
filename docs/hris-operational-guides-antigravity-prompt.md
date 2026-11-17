# Prompt Antigravity — Panduan operasional HRIS

Tanggal: 4 November 2026, Asia/Jakarta. Salin seluruh blok berikut.

```text
Susun dan rapikan panduan operasional HR/Admin serta karyawan mobile untuk repository:
/Users/macbookpro/development/fic/absensi/naltech-attendance

Spesifikasi lengkap: docs/hris-operational-guides-prd.md

Baca PRD penuh, AGENTS.md beserta instruksi yang dirujuk, agent.md, design.md dan skills.md. Semua perintah shell diawali rtk. Periksa working tree; pertahankan perubahan lokal pihak lain.

Ini tugas dokumentasi operasional dan materi pembelajar source code. Tidak membuka kembali pengemasan H6 dan tidak menambah fitur/kode aplikasi.

Tahap 1 — Audit sumber
- Inventaris README/docs existing, terutama panduan H6, snapshot fitur, timeline dan dokumen H1–H5/refinement. Pilih materi yang dipakai ulang, diperbarui, atau ditautkan agar tidak ada dua sumber normatif bertentangan.
- Cocokkan setiap alur dengan route/controller/service/policy/Blade/Flutter aktual. Buat daftar sumber per bab dan ketidakpastian. Jangan menyatakan perilaku hanya dari laporan lama jika kode sudah berbeda.

Tahap 2 — Panduan per peran
- HR/Admin: kesiapan konfigurasi melalui UI, navigasi/akses, lifecycle karyawan/arsip/reaktivasi, organisasi, kalender/zona waktu, shift efektif, metode/lokasi/QR, cuti/kuota, approval, lembur/reimbursement, koreksi dan rekap/CSV.
- Karyawan mobile: login, jadwal server/lokasi, check-in/out, tanggal kerja shift malam vs tanggal QR, sesi terkunci >24 jam, izin/cuti/saldo, lembur, reimbursement/lampiran, riwayat/pagination, pembatalan, keputusan tahap dan waktu perusahaan, error/retry.
- Setiap prosedur: tujuan, peran/cakupan, prasyarat, menu/langkah, input, hasil/dampak, kegagalan dan pemulihan. Gunakan label UI asli dan bahasa Indonesia sederhana. Letakkan istilah teknis pada lampiran pembelajar.
- Bedakan supervisor/HR/Admin dan web/mobile; jangan memperluas akses berdasarkan asumsi. Atasan organisasi bukan otomatis reviewer sah. Arsip dapat direaktivasi dan tidak menghapus sejarah.
- Tanpa shift bukan otomatis libur/bebas aturan; tanggal kerja mengikuti server. Cuti cancellation_requested tetap efektif sampai pembatalan disetujui. Lembur 1–1.440 menit adalah batas teknis, bukan kebijakan universal. Approved reimbursement bukan pembayaran otomatis.
- Lampiran mobile maksimal 2.048 KiB (2.097.152 byte), JPG/JPEG/PNG; jangan menyamakan kontrak file web dan mobile bila berbeda. Gagal memuat Beranda bukan belum check-in. Catatan pemohon, keputusan dan riwayat tahap dibedakan.

Tahap 3 — Kendala dan batas fitur
- Susun troubleshooting gejala → kemungkinan penyebab → langkah aman → eskalasi HR/Admin. Cakup 409, sesi berakhir/akun nonaktif, kalender/jadwal, radius/QR, sesi >24 jam, saldo/rentang, reviewer/legacy needs_review, file/lampiran privat, kegagalan layanan dan perbedaan angka rekap.
- Setelah error jaringan saat simpan, arahkan pembaca memeriksa detail/riwayat sebelum kirim ulang; jangan menganggap transaksi pasti gagal.
- Wajah produksi tetap ditunda sampai verifikasi andal. Rotasi/bulk/split shift/employee search/kalender visual tetap backlog. Jangan membuat panduan untuk tombol yang belum ada.
- Buat peta pembelajar terpisah dengan sumber file aktual, aturan bisnis, contoh alur dan lokasi tes. Jangan menyalin seluruh kode atau mengerjakan instalasi pembeli/deployment.

Tahap 4 — Bukti dan validasi dokumentasi
- Gunakan screenshot aktual yang relevan dengan caption peran/platform/tema/tanggal. Utamakan bukti existing; pengambilan baru hanya jika dibutuhkan, memakai QA disposable dan data sintetis.
- Periksa semua link/file rujukan baru, konsistensi label/status/angka dan jalur langkah. Laporkan D01–D10 PRD sebagai lulus/gagal/belum diverifikasi/terkendala. Pemeriksaan kode bukan uji live; jangan mengklaim tes yang tidak dijalankan.
- Jangan mengulang seluruh suite aplikasi untuk tugas docs. Catat bug aplikasi jika ditemukan berikut bukti/dampak, tanpa memperluas ke perubahan kode.

Deliverable:
docs/operations/README.md
docs/operations/hr-admin-guide.md
docs/operations/employee-mobile-guide.md
docs/operations/troubleshooting.md
docs/operations/feature-boundaries.md
docs/operations/source-code-learning-map.md
docs/hris-operational-guides-report.md

Tambahkan tautan indeks operasional pada README utama dan update docs/hris-core-timeline.md sesuai hasil aktual. Jika materi setara existing dipakai ulang, jelaskan keputusan dan tautkan dengan jelas.

Batas wajib: tidak memutasi DB utama/migrate/seed/reset, tidak mengedit .env/Firebase, tidak mencetak password/token/data pribadi, tidak reset Git, tidak mengubah kode aplikasi/dependency, tidak commit/push/deploy/package. Bersihkan hanya resource QA tugas ini bila digunakan. Persetujuan mobile existing tidak diulang dan status review Dashboard tidak disetujui sepihak.

Akhiri dengan link indeks panduan, ringkasan isi, hasil pemeriksaan dokumentasi, bagian yang belum dapat diverifikasi dan temuan aplikasi bila ada. Jangan menyatakan seluruh HRIS siap produksi hanya karena panduan selesai.
```
