# H4 mobile — Pemilih jenis cuti dan kebijakan tersimpan

Diperiksa 21 Oktober 2026. Pengembangan mengikuti Dashboard jenis cuti H4 tahap 1. Tidak mengubah URL mobile pengguna, database utama, dependensi atau konfigurasi Firebase. Tidak mengaktifkan Face atau melanjutkan H2 tahap 2 yang ditunda.

## Perilaku

Form Izin kini memiliki **Jenis pengajuan**: izin umum (bukan cuti) atau jenis cuti aktif perusahaan dari `/api/leave-types`. Katalog dimuat dengan sesi akun saat ini; pagination dibaca sampai selesai, maksimal 100 halaman/5.000 jenis sesuai ukuran halaman backend 50. Respons bermasalah, duplikat atau pagination tidak konsisten ditolak tanpa menganggap daftar parsial sebagai katalog lengkap. Tidak menyimpan cache kebijakan antarakun. Request katalog dan pengiriman tidak mengikuti redirect, sehingga autentikasi tidak diteruskan ke alamat pengalihan.

Form membedakan memuat, daftar aktif kosong, gagal dimuat, dan pilihan yang sudah tidak tersedia. Tombol muat ulang mempertahankan isian. Jenis cuti yang telah dipilih tidak otomatis berubah menjadi izin umum saat katalog gagal atau jenis hilang; pengiriman pilihan tersebut diblokir sampai tersedia kembali atau pengguna memilih kategori lain secara eksplisit. Izin umum tetap tersedia sebagai kategori terpisah, termasuk ketika katalog cuti gagal dimuat.

Jenis terpilih menampilkan nama/kode, lampiran wajib/opsional, aturan saldo, kuota dasar dan keterangan persyaratan. Kuota **bukan saldo karyawan**; belum ada pengurangan saldo. Form hanya satu tanggal. Lampiran wajib diperiksa sebelum memanggil pengiriman; picker dan datasource mempertahankan validasi JPG/PNG maksimal 2 MB, dan server tetap memvalidasi isi berkas. Pengiriman hanya menambah `leave_type_id`, tidak mengirim atau mempercayai snapshot kebijakan dari klien. Respons sukses diverifikasi terhadap identitas pemilik, tanggal, keterangan dan jenis yang dikirim. Kegagalan/hasil ambigu tidak dianggap sukses dan tidak dicoba ulang otomatis.

Penolakan server, termasuk jenis nonaktif atau perubahan persyaratan, tetap mempertahankan pilihan, tanggal, keterangan dan lampiran untuk diperiksa/dimuat ulang. Kebijakan katalog hanya panduan saat mengisi; server memakai kebijakan yang berlaku ketika pengajuan diterima dan mengembalikan snapshot yang benar-benar tersimpan. Perubahan kebijakan saat form terbuka masih dapat menyebabkan penolakan server atau snapshot lebih baru; tidak ada klaim kuota dikunci oleh katalog.

Daftar menampilkan nama jenis bila tersedia. Detail menampilkan **Kebijakan cuti saat diajukan** dari snapshot respons pengajuan, tanpa menggantinya dengan katalog terbaru. ID jenis dan snapshot yang tidak cocok atau snapshot malformed tidak diberi nilai default palsu. Data lama/izin umum diberi keterangan belum diklasifikasi. Urutan, tahap aktif, peninjau saat ini, riwayat keputusan dan pembatalan H3 tetap tersedia. Nilai kuota historis tetap mengikuti snapshot meskipun kebijakan master berubah.

## Pemeriksaan

- **77 tes Flutter lulus**: delapan tes baru meliputi parsing snapshot/ID dan kuota, pagination katalog, respons malformed/unavailable/redirect, perubahan akun dan 401, pengiriman ID jenis saja, kewajiban lampiran, penolakan respons dengan jenis tidak cocok, isian setelah penolakan, retry serta pilihan hilang, snapshot historis dan approval, tema light/dark, nama panjang dan viewport 320×640 dengan skala teks 1,8.
- Fixture tes pengajuan lama diperbarui untuk katalog kosong deterministik dan argumen jenis opsional. Tes detail layar sempit menggulir ke bagian lampiran karena informasi kebijakan menambah tinggi konten; pemeriksaan lampiran tidak dihapus.
- Analysis server AOT dari SDK yang sama selesai: **0 error proyek dan 0 diagnostik pada tujuh file pengajuan terkait**. Menggunakan runtime alternatif karena wrapper analyzer lokal bermasalah; tidak mengubah SDK. [Laporan analisis](mobile-leave-policy-analysis.json).
- **25 pemeriksaan HTTP nyata** pada production datasource ke backend loopback dengan SQLite/storage QA: login fixture, katalog aktif, validasi lampiran, submit dua jenis dengan/tanpa bukti, snapshot kuota, tahap approval dua tingkat, detail, unduh bukti privat, pembatalan dan riwayat, izin umum serta daftar. [Laporan](mobile-leave-policy-http-qa.json). Tidak mengirim foto pribadi atau pesan eksternal.
- Satu tes ekspor widget lulus, menghasilkan delapan screenshot light/dark untuk pemilih, lampiran, detail dan riwayat. [Form light](screenshots/mobile-leave-policy/leave-picker-light-widget.png), [detail dark](screenshots/mobile-leave-policy/leave-detail-dark-widget.png). Fixture visual memperlihatkan katalog kuota 20 dengan lampiran wajib, sedangkan detail historis kuota 12 dengan lampiran opsional, untuk memeriksa bahwa kebijakan lama tidak ditimpa. Ini screenshot widget sintetis, bukan screenshot HP.
- Format Dart, sintaks helper PHP QA dan whitespace diperiksa. Tidak ada perubahan logika produksi backend di tahap ini; 117 tes backend/1.542 assertions adalah baseline dari tahap Dashboard sebelumnya, bukan suite yang dijalankan ulang pada sesi mobile ini.

Tidak ada perangkat Android yang terdeteksi saat pemeriksaan ADB. Pengujian HP/galeri native, iOS, build APK release dan review pengguna masih terbuka. Tidak mengklaim bukti native H3 sebelumnya mencakup pemilih jenis cuti baru. Salinan tes ekspor/HTTP sementara di folder test mobile dihapus setelah selesai; template reproduksi berada di `tools/mobile-qa/leave_policy_http_test.dart` dan `leave_policy_visual_test.dart`. Backend QA menyediakan `mobile-leave-fixtures` untuk direktori disposable yang sudah di-seed dengan `workflow-fixtures`; jangan jalankan fixture pada database utama. Server QA dihentikan setelah pemeriksaan.

## Penerapan dan sisa timeline

Flutter memerlukan API katalog serta migrasi H4 tahap 1 yang sudah disediakan di source. Migrasi utama belum diterapkan dan aplikasi terpasang pengguna belum diperbarui oleh pekerjaan ini. Bila server belum mendukung katalog, tampilkan pesan gagal dan pertahankan kategori izin umum; jangan mengarang jenis cuti atau saldo.

Tahap berikutnya: review hasil, penerapan terkontrol dan QA perangkat. Rentang tanggal dan ledger saldo tetap menunggu fondasi jadwal efektif H2 yang ditunda. Fitur ini tidak menghitung saldo tersedia, overlap cuti, prorata, carry-over atau hari lintas shift.

Pembaruan perangkat 21 Oktober: [uji OPPO Android 15](hris-h4-mobile-device.md) lulus untuk form cuti, lampiran sintetis, persetujuan Atasan → HR, penolakan, snapshot setelah perubahan master dan pembatalan/list refresh. Sembilan screenshot native berhasil diekspor. Galeri Android tidak diuji karena picker memakai callback file sintetis; deployment utama, iOS/release tetap terbuka.
