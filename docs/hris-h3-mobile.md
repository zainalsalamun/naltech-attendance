# H3 mobile — Status, detail keputusan dan pembatalan

Diperiksa 21 Oktober 2026. Implementasi Flutter dan tambahan metadata API tersedia. Database utama **belum dimigrasikan**, aplikasi belum dipasang/diuji ulang pada HP nyata dalam irisan ini. H2 tahap 2 tetap ditunda dan Face tetap tidak tersedia.

## Yang berubah

- Daftar/detail izin, reimbursement dan lembur memakai status kanonis: Menunggu, Disetujui, Ditolak, Dibatalkan dan Perlu rekonsiliasi. Status baru diutamakan daripada boolean izin. Backend lama tanpa status tetap memakai label Belum disetujui; false tidak ditebak sebagai penolakan atau antrean yang dapat dibatalkan. Status tak dikenal tidak membuka aksi.
- Detail menampilkan nama peninjau/aktor pembatalan, waktu keputusan berlabel UTC dan alasan. Data legacy yang kosong tetap ditandai belum tersedia. Ini detail keputusan terakhir, bukan penampil seluruh event audit web.
- API list/detail/create dan respons keputusan memuat relasi `reviewer` terbatas ID/nama, serta `decision_time` ISO dengan offset eksplisit. Tidak menambah schema baru; memakai metadata migrasi H3 sebelumnya. Nama merupakan nama akun saat ini.
- Halaman detail selalu meminta data terbaru dari server. Tombol pembatalan hanya muncul setelah detail berhasil diverifikasi, status pending dan versi tersedia. Tidak memakai boolean atau data lama dari kartu sebagai otorisasi.
- Pembatalan meminta alasan dan konfirmasi. Request memakai ID/jenis pengajuan milik akun aktif, versi server dan endpoint tetap; redirect tidak diikuti. Tombol terkunci selama request berjalan. Jika konflik, timeout atau hasil meragukan, detail dimuat ulang sebelum aksi berikutnya; tidak otomatis mengulang mutasi.
- Setelah kembali dari detail, daftar dimuat ulang. Pengguna juga bisa menarik daftar atau menekan muat ulang detail untuk membaca keputusan terbaru. Belum ada push/realtime/polling background.
- Lembur tersedia dari menu **Jenis pengajuan** di daftar Izin/Reimbursement. Menu tersebut berpindah ke daftar Lembur, yang memuat durasi dalam menit, detail keputusan dan pembatalan. Form lembur mengirim bilangan bulat menit tanpa nominal/lampiran. Tata letak Beranda yang disetujui tetap memakai pintasan sebelumnya.
- Lampiran tetap melalui endpoint terautentikasi yang dibentuk aplikasi; URL di payload tidak menjadi tujuan pengiriman token. Pemeriksaan pemilik dan pergantian sesi dipertahankan pada detail/submit/cancel. Respons terlambat dari akun sebelumnya tidak diterima.

## Integrasi dan batas penerapan

Tambahan `baseUrl` opsional pada datasource hanya untuk dependency injection pengujian; nilai default tetap konfigurasi aplikasi yang ada. File `lib/core/constants/variables.dart` tidak diubah pada irisan ini. Backend uji loopback memakai SQLite/storage sementara yang diberi marker, menjalankan seluruh migrasi di sana, serta membuat keputusan melalui service approval asli. Tidak mengirim notifikasi eksternal.

Pemindahan lampiran legacy H0 dan migrasi schema H1–H3 yang belum diterapkan tetap mengikuti prosedur operator yang didokumentasikan. Backend lama tanpa schema H3 menolak pembatalan dengan 503; aplikasi tidak menampilkan aksi jika versi tidak tersedia. Perubahan metadata API tidak mengaktifkan approval bertingkat, saldo cuti, koreksi final atau perubahan jadwal malam.

## Bukti pemeriksaan

- **67 tes Flutter lulus**: 60 tes sebelumnya, enam tes baru approval mobile dan satu tes form lembur. Meliputi status kanonis/fallback, endpoint/versi/alasan, payload tidak valid, konflik, pergantian akun, pembatalan sukses dan penguncian tap berulang, refresh detail serta durasi melalui form/cubit.
- **106 tes backend / 1.336 assertions lulus**, termasuk tambahan pemeriksaan metadata reviewer dan waktu dalam respons keputusan.
- **27 pemeriksaan HTTP langsung lulus** dari datasource Flutter ke backend sementara: list keputusan approved/rejected yang dibuat service, detail metadata, create dan cancel tiga jenis, reload cancelled, serta replay ditolak. [Laporan HTTP](mobile-approval-http-qa.json). Ini memakai proses Flutter test dan HTTP asli, bukan instalasi native.
- **Satu tes visual terpisah lulus**, menghasilkan 12 screenshot widget: lima status izin dan dialog konfirmasi, masing-masing light/dark pada lebar 390. Detail lembur juga diperiksa pada 320×640 dengan teks 1,8× tanpa exception. [Detail approved](screenshots/mobile-approval/permission-approved-light-widget.png), [pending dark](screenshots/mobile-approval/permission-pending-dark-widget.png), [dialog dark](screenshots/mobile-approval/cancel-dialog-dark-widget.png). Screenshot merupakan fixture sintetis Flutter, bukan kamera/HP.
- Lima file produksi Dart yang berubah tanpa diagnostik; tidak ada error proyek pada analysis server. Wrapper `flutter analyze` gagal karena masalah AOT SDK lokal, lalu server SDK yang sama dijalankan melalui `dartaotruntime` seperti alur yang sebelumnya terdokumentasi. [Ringkasan analisis](mobile-approval-analysis.json). SDK/dependency tidak diedit.
- Dart format, Pint pada PHP terkait dan `git diff --check` berhasil. Salinan tes QA sementara di folder test dibersihkan; template pengujian tetap di `tools/mobile-qa/`. Server QA milik agen dihentikan setelah selesai.

Belum ada build APK baru, pengujian HP/iPhone, deployment, migrasi database utama atau pengujian ulang notifikasi native. Review/penerapan backend dan verifikasi native merupakan langkah terpisah dari kelulusan unit/widget/HTTP ini.

Pembaruan perangkat pada 21 Oktober: satu suite pengajuan pada OPPO CPH2591 Android 15 lulus, mencakup Dashboard, pembatalan, backend terputus/pulih dan sesi kedaluwarsa. [Laporan native](hris-h3-mobile-device.md) menjelaskan artefak, perbaikan urutan lembur dan batas cakupan. Database utama tetap belum dimigrasikan.

Pembaruan 21 Oktober: [alur bertingkat](hris-h3-workflows.md) menambahkan tahap aktif, peninjau saat ini dan riwayat pada detail mobile; 69 tes Flutter lulus. Bukti HP sebelumnya tidak mencakup perubahan ini.
