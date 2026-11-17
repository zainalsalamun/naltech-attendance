# PRD — Panduan operasional HR dan karyawan

Tanggal: 4 November 2026, Asia/Jakarta.  
Status: Rencana dokumentasi; bukan hasil verifikasi aplikasi atau persetujuan Dashboard baru.

## 1. Tujuan

Menyediakan panduan berbahasa Indonesia yang membantu HR/Admin dan karyawan menyelesaikan pekerjaan sehari-hari serta memahami batas aplikasi. Pembaca source code juga dapat menelusuri implementasi melalui referensi teknis terpisah tanpa membebani instruksi pengguna dengan jargon.

Panduan harus sesuai fitur yang tersedia. Audit dokumentasi existing sebelum membuat materi baru. Ini dokumentasi penggunaan operasional, bukan pembukaan kembali pengemasan H6, instalasi pembeli, deployment, atau penambahan fitur.

## 2. Sumber dan prinsip penulisan

- Baca AGENTS.md dan instruksi yang dirujuknya, agent.md, design.md, skills.md.
- Audit README.md, docs/hris-h6-commercial-packaging-and-guide.md, docs/hris-feature-scope-status.md, docs/hris-core-timeline.md, serta dokumen H1–H5 dan laporan refinement terkait.
- Cocokkan langkah dengan route, controller, policy/service, Blade dan Flutter aktual. Dokumentasi lama bisa menggambarkan status pada tanggal lampau; jangan menyatukannya menjadi status terbaru tanpa pemeriksaan.
- Buat daftar sumber per bab: file/route/label UI, bukti yang tersedia, tanggal pemeriksaan dan ketidakpastian. Jangan mencetak .env, token, password, konfigurasi Firebase atau isi data pribadi.
- Bedakan aturan akses berdasarkan role dan cakupan perusahaan/tim dari sekadar keberadaan tombol. Jangan menyebut supervisor dapat mengelola semua karyawan atau menyetujui semua pengajuan tanpa bukti server.
- Gunakan label UI yang ada dan bahasa tugas: “Pilih…”, “Periksa…”, “Simpan…”. Istilah internal seperti auth_epoch, lockForUpdate dan nama tabel ditempatkan pada lampiran untuk pembelajar.
- Tidak ada password bawaan nyata, angka kuota universal, kebijakan lembur legal, atau janji kemampuan wajah produksi. Contoh memakai identitas sintetis dan kebijakan perusahaan yang dilabeli sebagai contoh.

## 3. Bentuk setiap prosedur

Setiap alur utama memuat: tujuan; peran/cakupan; prasyarat; menu dan langkah berurutan; data yang perlu disiapkan; hasil setelah simpan; dampak penting; pesan gagal dan tindakan pemulihan; serta bukti/rujukan terkait.

Jelaskan hanya aksi yang memang didukung. Jika tombol/fitur berbeda web dan mobile, beri label platform. Jangan membuat nama menu atau screenshot rekaan. Bila belum dapat diverifikasi, tandai secara spesifik dan catat apa yang perlu diperiksa.

## 4. Panduan HR/Admin

| Bab | Cakupan wajib dan batas penting |
| --- | --- |
| Akses dan navigasi | Login/logout, tema, grup sidebar, perusahaan aktif, role dan cakupan tim. Perbedaan HR/Admin/supervisor dan akses yang ditolak |
| Karyawan | Tambah/edit, nomor induk, tanggal masuk/keluar, unit/atasan, hubungan nonaktif lama, role, preview transisi, alasan, nonaktif/arsip/reaktivasi dan audit. Arsip bukan hapus permanen; reaktivasi tidak menghidupkan sesi lama. Jelaskan konflik versi dan review ulang data terbaru |
| Organisasi | Unit aktif/nonaktif, relasi atasan, larangan lintas perusahaan/self/cycle; pemindahan atasan tidak otomatis memindahkan snapshot approval existing |
| Kalender | Zona waktu, aturan mingguan bertanggal efektif, pengecualian libur/hari pengganti, urutan prioritas, kalender belum dikonfigurasi, batas backdate/perubahan zona sesuai implementasi |
| Shift | Master normal/malam, penugasan efektif, preview baseline tanggal target, revisi tanggal sama, alasan, tanpa shift bukan hari libur, konflik expected_id, hubungan shift legacy dan penugasan efektif |
| Metode absensi | Pengaturan metode/lokasi/radius yang tersedia, QR sebagai alternatif sesuai konfigurasi; wajah produksi tetap tidak tersedia sampai verifikasi andal. Jangan menjanjikan bypass radius atau dukungan kamera/lokasi yang belum dibuktikan |
| Cuti dan saldo | Tipe bersaldo/non-saldo, alokasi periode dan ledger sesuai UI, perhitungan hari kerja/rentang, reserve/use/restore dalam bahasa pengguna, pembatalan approved dan dampaknya |
| Approval | Konfigurasi workflow jika tersedia bagi peran pembaca, snapshot, antrean all/actionable/waiting_others, tahap antara/final, alasan, preview, larangan self-approval, fallback sah/tidak tersedia, needs_review memerlukan rekonsiliasi; jangan mengarang layar rekonsiliasi yang belum ada |
| Lembur/reimbursement | Pemilik aktif saat pengajuan baru, pemilik tetap saat edit, batas durasi 1–1.440 menit sebagai batas teknis satu pengajuan, nominal/format dan bukti privat, perubahan terkunci setelah proses tertentu sesuai server. Approved bukan otomatis pembayaran atau payroll |
| Koreksi absensi | Tanggal kerja asal vs tanggal kejadian, timezone, jam/tanggal checkout malam, alasan, diff, preview/back/confirm, no-op, konflik dan audit |
| Rekap | Periode maksimal 90 hari inklusif, filter, populasi historis, detail per tanggal, hari libur, izin menunggu pembatalan, hadir bersamaan izin, CSV dan batas data roster historis. Jam kerja/rekap bukan penetapan payroll final |

Jangan menganggap konfigurasi awal telah siap pada semua instalasi. Sediakan checklist kesiapan operasional: identitas perusahaan/zona, kalender, shift/penempatan, relasi organisasi, metode/lokasi, tipe/kuota dan reviewer. Checklist ini memeriksa konfigurasi melalui UI, tidak menyuruh pembaca menjalankan migrasi atau seeder produksi.

## 5. Panduan karyawan mobile

- Login/logout, sesi berakhir/nonaktif dan cara meminta bantuan; tanpa membagikan kredensial contoh.
- Membaca jadwal server, nama/jam/zona, lokasi dan status; tidak menafsirkan tanpa shift sebagai bebas aturan.
- Check-in dan checkout metode yang tersedia, izin lokasi/kamera yang relevan dan penolakan radius/metode. Jangan memandu metode wajah yang masih ditunda.
- Shift malam: attendance_date adalah tanggal kerja server; qr_date adalah tanggal QR yang divalidasi. Pakai contoh malam 22:00 ke pagi 06:00, termasuk check-in setelah tengah malam bila server menjangkarkan ke jadwal kemarin. Jam perangkat bukan sumber keputusan.
- Sesi melewati batas 24 jam: aksi terkunci dan perlu koreksi HR; jangan menyarankan check-in baru, ubah jam perangkat, atau hapus data sebagai jalan pintas.
- Izin/cuti rentang tanggal: preview perhitungan/saldo, alasan/lampiran jika tersedia, submit, validasi, perubahan/cancel sesuai status aktual.
- Lembur: menit bulat 1–1.440, contoh 120 menit; batas teknis tidak berarti semua durasi pasti diizinkan kebijakan perusahaan.
- Reimbursement: nominal, deskripsi, bukti JPG/JPEG/PNG maksimal 2.048 KiB = 2.097.152 byte untuk mobile. File tidak valid tidak mengganti pilihan valid sebelumnya; validasi server tetap berlaku.
- Riwayat dan muat lebih banyak, refresh detail, status tahap/final, catatan pemohon vs keputusan vs riwayat tahap, label waktu perusahaan/offset.
- Pembatalan: pending vs approved cuti, cancellation_requested tetap efektif sampai disetujui; penolakan pembatalan kembali approved. Jangan mengajarkan pembatalan approved lembur/reimbursement bila tidak didukung.
- Lampiran privat: loading, retry khusus lampiran, akses ditolak/sesi berakhir; tidak menyalin URL privat sebagai tautan publik.
- Gagal memuat Beranda bukan “belum check-in”. Coba lagi dan tunggu status server sebelum bertindak. Pergantian akun membuang respons lama.

## 6. Matriks troubleshooting

Wajib memetakan gejala → penyebab yang dapat dipastikan/kemungkinan → langkah aman pengguna → kapan menghubungi HR/Admin → data nonrahasia yang dapat diberikan. Jangan menjanjikan penyebab tunggal hanya dari pesan error.

Gejala minimum:
1. Login ditolak, akun nonaktif/arsip atau sesi berakhir.
2. Data sudah berubah/409 pada karyawan, jadwal, koreksi, approval.
3. Kalender/jadwal belum dikonfigurasi, tanpa shift, jadwal belum efektif.
4. Lokasi belum siap/izin ditolak/di luar radius dan QR tidak sesuai tanggal.
5. Sesi terbuka kemarin atau terkunci >24 jam.
6. Saldo belum dialokasikan/tidak cukup, rentang nol hari kerja/overlap.
7. Reviewer tidak tersedia, menunggu pihak lain, needs_review legacy.
8. Ukuran/format file ditolak, lampiran 401/403/timeout/gagal koneksi dan retry.
9. Beranda gagal memuat, HTTP 503 vs timeout/offline: sebut hanya kondisi yang terbukti, bukan semua telah diuji fisik.
10. Rekap berbeda dari perkiraan karena libur/periode/masa kerja/unit saat ini/izin/hadir; data historis tidak lengkap.

Kegagalan sesudah menekan simpan tetapi sebelum menerima hasil: periksa detail/riwayat terbaru dahulu sebelum mengirim ulang. Jangan menjamin request gagal tersimpan hanya karena jaringan terputus. Jangan menyarankan hapus DB, reset aplikasi atau membuka akses publik sebagai langkah standar.

## 7. Materi pembelajar source code

Sediakan lampiran ringkas terpisah: peta modul web/mobile/service, sumber data utama dan aturan bisnis, contoh satu alur lengkap request → validasi/otorisasi → transaksi → response → state UI, lokasi tes serta cara menemukan bukti. Verifikasi nama file aktual; gunakan tautan relatif repository. Jangan menyalin seluruh kode atau membuat ulang panduan instalasi H6.

## 8. Skenario penerimaan dokumentasi

| ID | Pemeriksaan |
| --- | --- |
| D01 | Inventaris docs existing dan keputusan pakai ulang/perbarui/baru beserta sumber |
| D02 | HR dapat mengikuti prasyarat → karyawan → jadwal/kalender → approval tanpa menu rekaan |
| D03 | Transisi karyawan, konflik dan audit sesuai aturan server; riwayat tidak dihapus |
| D04 | Jadwal malam/tanggal kerja/QR/sesi terkunci dijelaskan tanpa asumsi jam perangkat |
| D05 | Cuti/saldo/tahap/batal konsisten; izin menunggu pembatalan tetap berlaku |
| D06 | Lembur/reimbursement: batas teknis, privasi bukti, pemilik, finalitas dan batas pembayaran jelas |
| D07 | Mobile error/retry/lampiran/muat lebih banyak dan catatan keputusan sesuai implementasi |
| D08 | Troubleshooting actionable, tidak meminta rahasia atau memberi jalan pintas merusak data |
| D09 | Screenshot aktual relevan, link internal valid, label peran/platform jelas |
| D10 | Fitur tersedia vs ditunda/backlog dan hasil pengujian vs panduan dibedakan |

Tandai lulus/gagal/belum diverifikasi/terkendala per ID. Membaca kode merupakan verifikasi dokumentasi terhadap implementasi, bukan bukti interaksi live. Jangan mengulang seluruh suite untuk tugas dokumentasi. Jika menemukan bug aplikasi, catat dengan bukti dan dampaknya; jangan memperluas menjadi perubahan kode tanpa arahan baru.

## 9. Keluaran

- docs/operations/README.md — indeks dan pemilihan panduan menurut peran/platform.
- docs/operations/hr-admin-guide.md — panduan HR/Admin dengan batas supervisor.
- docs/operations/employee-mobile-guide.md — panduan karyawan.
- docs/operations/troubleshooting.md — matriks kendala dan eskalasi.
- docs/operations/feature-boundaries.md — tersedia, keterbatasan dan backlog, merujuk snapshot fitur utama agar tidak menjadi sumber status yang bertentangan.
- docs/operations/source-code-learning-map.md — peta untuk pembelajar.
- docs/hris-operational-guides-report.md — inventaris, sumber per bab, D01–D10, temuan/batas verifikasi dan pemeriksaan link.
- Tambahkan tautan indeks operasional pada README utama tanpa menimpa bagian existing; update timeline sesuai hasil aktual.

Jika materi setara sudah ada, pakai ulang atau tautkan dan jelaskan keputusan pada laporan, hindari dua panduan normatif yang bertentangan. Screenshot existing diutamakan. Jika perlu gambar baru, gunakan fixture sintetis dan QA disposable; tidak wajib membuat gambar setiap langkah. Beri caption halaman, peran, tema dan tanggal sumber; jangan mengklaim screenshot lama sebagai pengambilan baru.

## 10. Batas dan definisi selesai

Semua shell command diawali rtk. Pertahankan working tree pihak lain. Tidak menyentuh database utama, migrate/seed/reset utama, .env/Firebase, password/token, atau data pribadi. Tidak menambah modul/dependency, mengubah aplikasi, mengaktifkan wajah, commit/push/deploy/package. Jangan mengulang persetujuan mobile yang sudah diberikan; review Dashboard akhir pengguna tetap dicatat sesuai status terakhir.

Selesai bila panduan dapat dibaca berurutan, langkah cocok dengan implementasi, tautan valid, D01–D10 memiliki hasil jujur, keterbatasan dan celah dicatat, serta indeks mudah ditemukan. Penyelesaian dokumentasi bukan pernyataan source code siap produksi atau izin pengemasan.
