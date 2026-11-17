# Prompt pengerjaan berikutnya — perapian dan testing HRIS

Tanggal: 21 Oktober 2026, Asia/Jakarta.
Salin blok berikut ke sesi pengerjaan. Dokumen ini adalah instruksi untuk pelaksanaan berikutnya, bukan bukti bahwa testing sudah dijalankan.

```text
Lanjutkan pengembangan Naltech Attendance pada workspace ini dengan merapikan alur yang sudah tersedia sambil menjalankan testing per tahap. Commit tetap menunggu perintah saya. Jangan push, deploy atau memublikasikan aplikasi.

Baca dan ikuti:
- AGENTS.md, agent.md, design.md dan skills.md.
- docs/hris-next-development-and-testing-plan.md sebagai rencana utama dan matriks penerimaan.
- docs/hris-h4-leave-balances-and-date-range.md.
- docs/sidebar-navigation-restructuring.md.
- docs/hris-core-timeline.md dan docs/database-main-migration.md.

Konteks:
H4 rentang tanggal, alokasi/ledger saldo, approval dan pembatalan sudah dikembangkan, tetapi validasi menyeluruhnya belum selesai. Sidebar sudah berisi Ringkasan tunggal dan lima grup accordion (enam menu utama). Jangan membangun ulang fitur yang sudah tersedia. Periksa kode aktual sebelum menyimpulkan ada atau tidaknya fitur.

Instruksi pelaksanaan ini mengizinkan testing yang sebelumnya ditunda. Kerjakan per irisan, perbaiki masalah yang ditemukan, kemudian uji bagian terkait. Jangan hanya membuat laporan audit tanpa memperbaiki bug yang masih dalam lingkup. Jangan mengklaim lulus tanpa bukti aktual.

Urutan wajib:

P0. Periksa kondisi Git, route, schema, kontrak API dan status implementasi. Pertahankan seluruh perubahan lokal yang ada. Jalankan baseline pada database testing terisolasi; pisahkan kegagalan lama dan regresi baru.

P1. Tuntaskan sidebar:
- Pastikan submenu dengan atribut hidden benar-benar display:none, termasuk pointer coarse.
- Accordion independen, auto-open route aktif, penanda index/create/edit/detail/ledger, preferensi per akun/perusahaan.
- Validasi isi localStorage dan tangani storage yang tidak tersedia tanpa merusak navigasi.
- Periksa akses nyata ke saldo/alokasi serta ledger melalui halaman induk; jangan hanya mengaktifkan indikator menu tanpa menyediakan jalurnya.
- Periksa footer akun, body scroll, drawer/backdrop, Escape, fokus keyboard dan role.
- Jalankan pemeriksaan Chrome dengan fixture pada light/dark, desktop dan layar sempit. Simpan bukti yang relevan.
- Selaraskan dokumentasi: enam menu utama berarti satu Ringkasan + lima accordion; perbarui catatan H4 lama yang masih menyebut tujuh kelompok.

P2. Periksa dan perbaiki backend H4:
- Rentang inklusif, kalender belum lengkap, pengecualian/libur, shift malam dan overlap pengajuan aktif.
- Jenis non-saldo tidak membuat mutasi; jenis yang mengurangi saldo wajib memiliki alokasi yang cukup.
- Periode saldo harus eksplisit. Jangan membebankan rentang lintas tahun ke tahun awal diam-diam; bila belum didukung, tolak dengan instruksi memisahkan pengajuan dan dokumentasikan batasnya.
- Alokasi awal/penyesuaian/massal tidak menghapus reserved atau used.
- Submit melakukan reserve sekali; approval antara mempertahankan reserve; approval akhir melakukan use; reject/cancel pending melakukan release; persetujuan pembatalan approved melakukan restore_used sekali.
- Jangan membuat pengembalian saldo rekaan untuk pengajuan legacy tanpa reservasi terkait.
- Jaga invariant allocated = remaining + reserved + used dan semua nilai tidak negatif.
- Verifikasi role/scope, self-approval, akun nonaktif, versi lama, request ganda, rollback transaksi dan konkurensi.
- Tambahkan tes bermakna untuk aturan bisnis. Gunakan MySQL terisolasi untuk membuktikan locking/konkurensi; SQLite saja tidak cukup untuk klaim tersebut.

P3. Rapikan dan uji alur Dashboard sebenarnya:
Konfigurasi → alokasi → pemilihan jenis/rentang → preview rincian hari/saldo → tinjau → submit → tahap approval → keputusan → pembatalan → ledger.
Periksa kondisi kosong/error, tautan kembali, status/peninjau, filter/pagination, lampiran, light/dark dan layar sempit. Server menghitung ulang saat submit; preview bukan reservasi atau jaminan saldo.

P4. Setelah alur web direview, selaraskan Flutter:
Periksa kontrak API, date range, preview yang kedaluwarsa atau respons terlambat, saldo, status/tahap peninjau, pembatalan dan pembaruan halaman induk. Jalankan tes model/Cubit/widget terkait, kemudian Android emulator menggunakan backend fixture nyata. Nyatakan batas cakupan kamera/GPS dan jangan menyebutnya uji HP fisik.

P5. Jalankan regresi yang relevan setelah perbaikan selesai, perbarui laporan/timeline, serta periksa kesiapan migrasi.
Migrasi utama sampai 000007 sebelumnya sudah diterapkan; periksa status aktual 000008 secara terpisah. Validasi pada salinan staging sebelum penerapan utama. Untuk migrasi maju dalam lingkup yang sudah diizinkan, buat backup baru di luar repo, pertahankan data, verifikasi hasil dan jangan seed/reset database utama. Jika ada perubahan destruktif atau keputusan kebijakan saldo yang belum jelas, jelaskan kebutuhan keputusan sebelum tindakan tersebut; lanjutkan pekerjaan independen yang aman.

Batasan:
- Semua shell command diawali rtk.
- Jangan menggunakan database utama untuk testing, migrate:fresh, reset atau fixture.
- Pertahankan isolasi perusahaan, otorisasi server, radius dan validasi absensi.
- Face tetap tidak tersedia.
- Jangan mengubah URL mobile, .env, rahasia atau Firebase tanpa kebutuhan yang disetujui.
- Jangan mencetak token/password/header autentikasi atau memasukkan backup ke Git.
- Jangan mengarang kuota, prorata, carry-over, setengah hari atau saldo negatif.
- Jangan menambah payroll, rekrutmen atau performance management.
- Jangan melakukan commit sampai saya memerintahkannya.

Berikan update ringkas selama pengerjaan. Setelah setiap tahap, catat perubahan, hasil aktual, bukti dan batasan. Lanjutkan sampai lingkup selesai atau ada hambatan nyata yang perlu input saya; jangan berhenti setelah hanya menyusun rencana.
```
