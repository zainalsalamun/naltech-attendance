# H0 — akses API, CSV dan lampiran privat

Tanggal: 21 Oktober 2026. Implementasi kode dan pengujian terisolasi tersedia.
**Pemindahan lampiran lama belum dijalankan pada database/storage utama.**
Tidak ada migrasi schema, perubahan URL server mobile atau aktivasi Face.

## Perubahan yang diterapkan

- API detail lembur: staf dan supervisor hanya dapat melihat miliknya sendiri.
  HRD/admin/super admin dapat meninjau dalam perusahaan aktif. ID milik rekan
  tanpa hak atau perusahaan lain mendapat 404. Belum ada model tim, sehingga
  supervisor tidak diberi akses API lintas pemilik secara otomatis.
- Resource API hanya mendaftarkan method yang tersedia: izin/reimbursement/lembur
  index/show/store; shift index/show/store/update; catatan index/store. Operasi
  yang belum diimplementasikan tidak lagi menghasilkan route ke method kosong.
- Shift web dan API memakai validasi bersama: nama, format waktu, normalisasi
  detik, jam berbeda; shift malam tetap boleh disimpan. API update mengharuskan
  seluruh field, tanpa mass assignment request mentah. Validasi pengajuan lembur
  API diselaraskan dengan web pada tanggal, batas durasi dan panjang keterangan.
  Ini belum menyelesaikan checkout shift malam (tahap H2/H5).
- Ekspor Kehadiran dan Verifikasi memakai sanitizer CSV bersama untuk semua
  kolom teks, termasuk awalan formula/whitespace. Data dibaca bertahap, dengan
  company_id dikunci eksplisit pada query sebelum respons streaming; tidak
  bergantung pada CompanyContext yang sudah dibersihkan middleware.

## Lampiran baru

Upload izin dan reimbursement sekarang memakai disk `submission_private`, root
`storage/app/private/submission-attachments`. Tidak membuat symlink publik.
Nama file acak; kolom database memakai penanda internal `private:`. JSON tidak
lagi menampilkan kolom `image`/path storage, melainkan `attachment_url` relatif
yang terikat record, misalnya `/api/submission-attachments/permission/123`.

Endpoint API memakai Sanctum dan perusahaan; endpoint web memakai sesi login
dan perusahaan. Pemilik atau HRD/admin/super admin perusahaan aktif dapat
mengambil bukti; staf/supervisor lain, perusahaan lain dan akun tanpa perusahaan
ditolak. Respons memeriksa MIME gambar dan memakai private/no-store, nosniff
serta CSP sandbox. Tautan web tidak lagi mengarah ke `/storage/...`.

Penyimpanan record/upload memakai transaksi; file baru dibersihkan jika simpan
database gagal. Penggantian menghapus file privat lama setelah commit. Kegagalan
pembersihan file lama tidak boleh menghapus upload baru yang sudah committed;
orphan privat masih memerlukan maintenance retensi. Hapus reimbursement juga
mencoba membersihkan file privatnya. Lifecycle/arsip karyawan tetap H1, bukan
diselesaikan dengan hard delete baru.

Mobile mengambil byte gambar dari endpoint API yang dibentuk sendiri, dengan
Bearer sesi aktif. Nilai URL/path pada record tidak menjadi tujuan pengiriman
token; redirect tidak diikuti, MIME/ukuran diperiksa (maksimal 2 MB), dan hasil
request akun lama ditolak setelah pergantian sesi. Tampilan memakai Image.memory,
bukan URL publik. Backend/mobile perlu diperbarui bersama agar lampiran tetap
terlihat; klien lama yang hanya membaca `image` tidak mendapat path publik baru.

## Lampiran lama: langkah penerapan per lingkungan

File legacy **tetap dapat berada di storage publik sampai perintah diterapkan**.
Endpoint terotorisasi mengembalikan 409 untuk referensi lama; tidak melakukan
fallback membaca URL publik. Jangan menyatakan H0 melindungi seluruh data lama
hanya karena kode baru sudah tersedia.

Operator menjalankan dari `apps/backend`, setelah backup database serta storage
dan menghentikan penulisan pengajuan/worker selama maintenance:

```sh
rtk proxy php artisan submissions:privatize-attachments
rtk proxy php artisan submissions:privatize-attachments --apply
```

Perintah pertama hanya pratinjau jumlah file dan referensi invalid/hilang, tanpa
menulis file/database. Jika ada invalid/hilang, apply berhenti sebelum perubahan;
operator harus merekonsiliasi referensi, tidak menebak atau mengambil URL eksternal.
Tidak ada nama/path file pribadi dicetak pada output command.

Apply menyalin ke private, membandingkan SHA256, menyimpan receipt hash privat,
mengubah semua referensi file yang dibagi beberapa record dalam transaksi,
kemudian menghapus salinan publik. Format legacy izin basename maupun prefix
permissions didukung; reimbursement memakai prefix assets/reimbursements.
Path traversal, sumber di luar prefix dan file terlalu besar ditolak.

Filesystem dan database tidak memiliki satu transaksi bersama. Jika proses
terhenti, kelompok sebelumnya bisa sudah selesai. Salinan privat terverifikasi
dipertahankan; jalankan ulang setelah pemeriksaan. Pemulihan ketika public copy
sudah hilang hanya menerima private copy dengan receipt hash yang cocok.
Perintah idempoten untuk referensi yang sudah dipindahkan. Jangan menjalankan
bersamaan dengan penulisan/penggantian pengajuan.

Sesudah apply berhasil, periksa URL publik lama tidak lagi melayani file dan
endpoint privat bisa diakses akun yang tepat. Jangan membuka disk private lewat
web server atau menyertakan backup storage/database dalam paket Git.
Tahap ini baru diuji pada storage dan SQLite sementara, **bukan dijalankan pada
lingkungan utama**. Tidak ada deployment, push atau pengiriman notifikasi.

## Bukti pengujian

- Backend: **83 tes, 1.040 assertions** lulus dengan SQLite memory. Sembilan tes
  H0 mencakup kepemilikan/role/perusahaan, kontrak route, shift, scope streaming
  dan formula CSV, unduh API/web, penggantian, rollback upload, preview/migrasi
  legacy bersama, idempotensi, path invalid, recovery dan kegagalan tulis.
- Mobile: **60 tes lulus**, termasuk tujuan endpoint tetap, redirect/MIME/ukuran,
  401 dan pergantian sesi. Analisis empat berkas terkait tanpa temuan.
- Pint pada 19 berkas terkait selesai; Blade view:cache berhasil dan cache
  dibersihkan kembali. Pemeriksaan whitespace diff bersih.
- Pengujian mobile menggunakan widget/HTTP fixture; belum menguji ulang unduh
  privat secara native Android/iOS. Tidak ada klaim build release atau audit
  keamanan menyeluruh. Lampiran baru, pergantian role/tim, retensi dan deployment
  tetap memerlukan pemeriksaan operasional sesuai lingkungan pembeli.

Tahap berikutnya adalah H1 lifecycle/master karyawan agar nonaktif/arsip
mempertahankan histori. Approval bertingkat, kalender dan cuti/saldo belum
diimplementasikan oleh H0. Liveness sesi/video tetap ditunda.
