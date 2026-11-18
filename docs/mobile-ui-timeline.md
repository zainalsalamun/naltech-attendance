# Timeline UI mobile Workspace

Diperbarui: 20 Oktober 2026. Dimulai atas arahan pengguna setelah review teknis web W12. Milestone berurutan, tanpa tenggat kalender baru.

| Tahap | Lingkup | Status | Berikutnya / batas |
| --- | --- | --- | --- |
| M0 | Audit SDK, layar dan kontrak data | Berjalan | Audit kode tersedia; baseline visual perangkat belum |
| M1 | Fondasi tema dan Login | Implementasi awal tersedia | 6 tes lulus; review visual/perangkat dan sesi API nyata belum |
| M2 | Navigasi dan Beranda | Desain revisi disetujui sudah diterapkan | 14 tes lulus; bukti render light/dark v2 tersedia; uji perangkat/API nyata belum |
| M3 | Absensi manual/QR dan hasil | Implementasi dan QA lokal tersedia | 23 tes lulus; kamera/GPS dan API nyata belum diuji; Face tetap tidak tersedia |
| M4 | Riwayat | Implementasi dan QA lokal tersedia | 29 tes lulus; jadwal historis/zona waktu perusahaan tetap terbatas |
| M5 | Profil, pengaturan dan sesi | Implementasi dan QA lokal tersedia | Profil/edit/foto, tema, logout dan pemulihan sesi; perangkat/API nyata belum |
| M6 | Izin dan Reimbursement | Implementasi dan QA lokal tersedia | Daftar/detail/form, lampiran, nominal dan status; galeri/API nyata belum |
| M7 | QA mobile menyeluruh | QA lokal/API lulus; native belum tuntas | 53 tes mobile, 64 backend, 33 HTTP; full Android/iOS tertunda lingkungan memori |

## Audit sumber data awal

| Area | Sumber saat ini | Catatan untuk implementasi |
| --- | --- | --- |
| Login/sesi | LoginBloc, POST /api/login, AuthLocalDatasource | SharedPreferences auth_data mengikuti alur lama; logout/sesi kedaluwarsa belum dikerjakan di M1 |
| Identitas | GetUserBloc / profil API dan auth.user lokal | Jangan menyamakan profil cache dengan data perusahaan terbaru |
| Perusahaan | GetCompanyBloc, GET /api/company | Nama, alamat, koordinat, radius_km, time_in/out, attendance_type tersedia; model belum memuat zona waktu perusahaan/shift pribadi |
| Status hari ini | IsCheckedinBloc, GET /api/is-checkin | Dua boolean checkedin/checkedout; tidak menyediakan jam aktual |
| Kehadiran bertanggal | GetAttendanceByDateBloc, GET /api/api-attendances?date= | Gunakan jam aktual, jangan isi dari jam jadwal perusahaan |
| Submit | Checkin/CheckoutAttendanceBloc, POST /api/checkin atau /api/checkout | Bukti QR lokal terikat login/aksi; server tetap memeriksa perusahaan, tanggal, radius dan metode |
| Izin | AddPermissionBloc / PermissonRemoteDatasource | Kontrak form/list perlu audit saat M6 |
| Reimbursement | AddReimbursementBloc / GetReimbursementsBloc | Audit nominal/lampiran/status saat M6 |

Home lama sudah diganti pada M2 dengan Workspace, satu aksi berdasarkan status server dan parsing konfigurasi lokasi yang aman. Tidak menambah fallback koordinat/radius produksi. Jadwal perusahaan tidak boleh dilabeli shift pribadi; zona waktu/kalender/riwayat shift yang belum tersedia perlu dinyatakan jelas.

## Validasi M1

Detail hasil dan batas pada [checklist mobile](mobile-ui-implementation-checklist.md#log-irisan-m1--fondasi-tema-dan-login--20-oktober-2026). Belum menjalankan login terhadap data produksi, memasang aplikasi pada iPhone, atau menguji kamera/lokasi. Tidak ada deployment atau publikasi store.


## Validasi M2 — 20 Oktober 2026

- Beranda menggunakan snapshot terpisah melalui WorkspaceHomeCubit (pola Bloc/Cubit yang sudah dipakai proyek), tanpa berbagi state tanggal dengan Riwayat. Memuat profil perusahaan, profil pengguna, status hari ini dan catatan bertanggal secara paralel; kegagalan bukan angka nol.
- Jadwal dilabeli jam perusahaan, bukan shift pribadi. Tanggal mengikuti perangkat dan keterbatasan zona waktu dijelaskan. Catatan jam difilter berdasarkan akun dan tanggal; status yang bertentangan/duplikasi memblokir aksi. Belum menambah kalender, toleransi, zona waktu atau shift pribadi pada API.
- Lokasi membedakan memuat, GPS mati, izin ditolak/permanen, gagal, konfigurasi tidak valid, dalam/luar radius. Hasil pengecekan lama tidak menimpa hasil baru. Tidak ada fallback koordinat/radius produksi atau penyimpanan koordinat ke log.
- Satu aksi Absen masuk/pulang/selesai sesuai state, hanya aktif pada lokasi valid dan metode Manual/QR. Face tetap tidak tersedia; pintasan pendaftaran wajah lama dilepas dari Beranda. Saat aksi dipilih, status/lokasi diperiksa kembali sebelum membuka alur absensi lama. Server tetap memvalidasi submit.
- Refresh tersedia lewat tarik, tombol header, kembali ke tab Beranda, resume aplikasi dan respons submit sukses. Listener sukses tidak menganggap request lokal sebagai absensi berhasil. Detail QR/hasil dan sinkronisasi pilihan tanggal Riwayat setelah submit adalah lingkup M3–M4 berikutnya.
- Navigasi Beranda/Riwayat/Profil memakai tema aplikasi, membuat tab saat pertama dibuka dan mempertahankan state. Tombol kembali dari tab lain menuju Beranda. Riwayat tidak lagi direset ke hari ini setiap kali dipilih.
- **12 tes lulus**: suite Login/QR lama ditambah transisi aksi, jam asli, source mismatch, akun/tanggal lain, duplikasi, konfigurasi invalid, lokasi ditolak/GPS mati/luar radius, Face, error dan retry, hasil lokasi terlambat, tema/teks besar serta persistensi tab/back. Widget kecil 320×640 dengan teks 1,8× tidak memunculkan exception layout dalam skenario diperiksa.
- Screenshot merupakan render widget Flutter dengan data fixture, font sistem Arial untuk review dan Material Icons, viewport 390×1100: [light](screenshots/mobile-home-light-widget.png), [dark](screenshots/mobile-home-dark-widget.png). Bukan screenshot emulator/perangkat atau bukti GPS/login nyata. Capture sementara tidak disimpan sebagai tes yang bergantung font/path mesin.
- Formatter dan pemeriksaan whitespace dijalankan. Analysis server melalui dartaotruntime tetap dipakai karena masalah launcher SDK lokal; hasil akhir dicatat pada checklist. Android/iOS, lokasi/kamera nyata, tema seluruh halaman lama, pergantian tanggal/zona waktu nyata dan API end-to-end belum diperiksa. Tidak ada deployment atau perubahan database produksi.


## Penerapan desain Beranda revisi — 20 Oktober 2026

Pengguna menolak tampilan M2 sebelumnya, menyetujui mockup baru dan meminta penerapan. [Konsep acuan](design/mobile-home-approved-concept.png) disimpan; gambar tersebut tidak digunakan sebagai UI runtime. Tampilan dibangun ulang dengan widget Flutter: wordmark, sapaan dan perusahaan, kartu jadwal navy bergaris, lokasi ringkas, panel kehadiran dengan tombol utama biru, layanan sejajar dan navigasi berindikator garis.

Data dan pengamanan M2 dipertahankan. Nama, jadwal, tanggal, radius, jam absensi dan metode tetap berasal dari snapshot aktif. Waktu “Diperbarui” hanya ditampilkan setelah snapshot berhasil dimuat. Kontrol tema menggantikan ikon notifikasi mockup yang belum memiliki alur; avatar membuka Profil. Detail jadwal menjelaskan batas shift/zona waktu; detail lokasi menyediakan pemeriksaan ulang/pengaturan izin. Tidak ada pendaftaran wajah atau fallback data contoh baru.

Pada teks besar/layar sempit, kolom kehadiran dan layanan menumpuk; footer dapat dibungkus. Konten tetap dapat digulir termasuk saat ukuran layar atau teks memerlukan ruang tambahan. Semua 14 tes lulus, termasuk pembukaan detail jadwal/lokasi, aksi layanan/profil, tombol absensi nonaktif saat izin lokasi ditolak, state tab, serta layar 320×640 dengan skala teks 1,8. Pemeriksaan statis melalui runtime alternatif selesai tanpa error/diagnostik pada berkas irisan ini; 109 temuan lama (9 warning, 100 info) tetap tercatat. Formatter dan diff whitespace berhasil.

Bukti implementasi: [light 390×844](screenshots/mobile-home-v2-light.png), [dark 390×844](screenshots/mobile-home-v2-dark.png). Keduanya render widget Flutter dengan data fixture dan font review lokal, sudah diperiksa secara visual; bukan screenshot perangkat atau bukti absensi API nyata. Bukti M2 lama tetap disimpan sebagai riwayat dan bukan acuan desain terbaru.

Lingkup penerapan ini Beranda dan navigasi bawah. Login/Riwayat/Profil serta modul pengajuan tetap mengikuti tahap masing-masing. M3 absensi manual/QR dan hasil masih tahap berikutnya setelah interupsi review desain, belum dinyatakan selesai.


## Validasi M3 — Absensi manual/QR dan hasil — 20 Oktober 2026

- Scanner diselaraskan dengan Workspace: petunjuk Indonesia, area QR, feedback pemeriksaan/error, lampu kamera, coba lagi dan akses pengaturan izin. Kamera memiliki satu pengelola lifecycle; start/stop/dispose tidak lagi dilakukan ganda oleh halaman. QR mentah tidak ditampilkan atau dicetak ke log.
- QrScanCubit menolak payload kosong/lebih dari 255 karakter dan mengunci pemeriksaan selama satu scan berjalan. Hasil `is_valid=false` tidak lagi dianggap berhasil. Bukti QR tetap diikat sesi/aksi dan kedaluwarsa setelah lima menit; saat scanner dibatalkan, respons validasi yang datang terlambat tidak dapat menghidupkan bukti lagi. Bukti dibersihkan setelah submit berhasil. Validasi server saat submit tetap menjadi acuan.
- Konfirmasi Manual/QR memakai kartu navy, status lokasi dan satu tombol konfirmasi. Lokasi diminta ulang tepat sebelum submit; GPS mati, izin ditolak/permanen dan gagal mendapat feedback/pengaturan. Selama pemeriksaan/pengiriman, aksi berulang dan kembali dibatasi. Face serta QR yang belum valid tidak dapat dikirim dari halaman ini.
- Bloc checkin/checkout menolak event ganda selama loading dan menangani exception. Request submit memiliki timeout 20 detik, QR 15 detik. Respons 401/403, penolakan 422/409, payload rusak/tidak lengkap dan kegagalan koneksi tidak menjadi sukses. Saat hasil belum pasti, pengguna diminta kembali dan memuat ulang Beranda; tidak ada retry otomatis. Pemeriksaan radius, metode, tanggal, perusahaan, duplikasi dan QR server tidak diubah.
- Model respons ditambah time_out/latlon_out. Layar hasil menampilkan tanggal serta jam masuk/pulang dari catatan respons, menggantikan DateTime.now() yang sebelumnya dipakai sebagai jam sukses. Dua entry point absensi lama juga meneruskan respons. Tidak ada jam contoh sebagai fallback.
- Respons submit berhasil memicu pemuatan ulang snapshot Beranda serta tanggal Riwayat yang sedang dipilih. Bloc Riwayat menyimpan tanggal terpilih, menolak respons request lama yang selesai setelah request baru dan menangani timeout/error. Kembali dari hasil menuju root Beranda tanpa membuat MainPage baru.
- **23 tes lulus** (14 sebelumnya + 9 tes M3). Cakupan baru: validasi QR hingga payload checkout melalui MockClient, jam pulang asli dan pembersihan bukti, penolakan/sesi berakhir/payload invalid/timeout, event submit ganda, kegagalan transport, QR false/scan ganda, pembatalan QR saat respons tertunda, konfirmasi hingga hasil, lokasi ditolak/Face, refresh tanggal Riwayat terpilih, serta hasil light/dark pada 320×640 dan teks 1,8×.
- Analysis server SDK melalui dartaotruntime: 97 temuan lama (6 warning, 91 info), tanpa error/diagnostik pada berkas inti M3 yang diperiksa. Formatter berkas perubahan dan diff whitespace diperiksa. Tidak mengedit dependency/generated source, database produksi atau konfigurasi URL pengguna.
- Bukti renderer widget Flutter 390×844 dengan fixture: [konfirmasi light](screenshots/mobile-confirm-light.png), [konfirmasi dark](screenshots/mobile-confirm-dark.png), [hasil light](screenshots/mobile-result-light.png), [hasil dark](screenshots/mobile-result-dark.png). Gambar diperiksa; font sistem dipakai hanya untuk capture sementara, tidak menjadi dependency aplikasi.
- Batas: kamera fisik, prompt izin OS, lifecycle kamera pada Android/iOS, GPS nyata, scan QR cetak, gangguan jaringan nyata dan submit API end-to-end belum diuji. Error radius/QR/duplikasi diuji sebagai respons server terisolasi; ini bukan bukti perangkat dapat absen di lokasi nyata. Tanggal pemindaian masih mengikuti perangkat karena zona waktu perusahaan belum tersedia di kontrak mobile. M4 berikutnya adalah desain dan ketepatan tampilan Riwayat.


## Validasi M4 — Riwayat kehadiran — 20 Oktober 2026

- Halaman Riwayat memakai identitas Workspace, panel navy dan kartu masuk/pulang pada light/dark. Jam asli (termasuk detik) tetap ditampilkan; jam belum tercatat ditandai jelas. Format jam invalid mendapat label perlu ditinjau, bukan dinyatakan absensi lengkap.
- Navigasi hari sebelumnya/berikutnya, pintasan Hari ini, pemilih bulan/tahun dan kalender berbahasa Indonesia tersedia. Rentang kalender 1900 sampai hari ini mengikuti perangkat, tanpa mengasumsikan hari libur atau jadwal kerja. Pemilihan tanggal tetap mengikuti tab yang dipertahankan MainPage. Refresh memuat tanggal aktif.
- Bloc menolak tanggal invalid, respons lebih dari satu catatan/tanggal tidak cocok/tanggal catatan hilang serta payload tanpa data. Respons permintaan lama tidak dapat mengganti hasil tanggal terbaru. UI juga menahan tampilan catatan yang tidak cocok selama pergantian tanggal; error tidak ditampilkan sebagai kosong.
- Status hanya Masuk tercatat, Masuk & pulang tercatat, atau Perlu tinjau berdasarkan kelengkapan jam. Tidak menghitung tepat waktu/terlambat tanpa jadwal historis dan toleransi. Penjelasan zona waktu/kalender/keterlambatan tersedia di panel informasi, bukan deretan catatan teknis pada layar utama.
- Koordinat diparsing secara finite/rentang valid, tanpa fallback 0,0. Koordinat 0,0 yang benar-benar tersimpan tetap valid. Klaim lama “Sesuai spot Absensi” dihapus karena catatan koordinat tidak membuktikan kesesuaian radius historis. Detail lokasi dan peta hanya ditawarkan bila lokasi tersedia; peta tidak dibuka otomatis. Halaman peta memakai kontrol tema, identitas titik tersimpan dan atribusi OpenStreetMap, serta pesan bila koordinat invalid.
- Durasi dan nominal lembur ditampilkan hanya dari field yang tersedia. Nominal memakai Rupiah/desimal Indonesia; nilai yang tidak ada/invalid tidak menjadi Rp 0. Metode absensi tidak dikarang karena belum ada pada model riwayat.
- **29 tes lulus** (23 sebelumnya + 6 M4): koordinat/format jam/status/nominal, respons lama setelah tanggal baru, tanggal invalid/duplikasi/respons tidak sesuai, navigasi dan kalender/tahun, empty/error/retry, detail lokasi, tema dengan teks 1,8× pada 320×640, kalender dapat digulir/dibatalkan pada teks besar, serta koordinat peta invalid tanpa fallback. HTTP dan data memakai fixture, tidak memuat tiles peta atau mengubah catatan produksi.
- Bukti render widget Flutter 390×844 dengan data uji: [Riwayat light](screenshots/mobile-history-light.png), [Riwayat dark](screenshots/mobile-history-dark.png), [kalender dark](screenshots/mobile-history-calendar-dark.png), [kosong dark](screenshots/mobile-history-empty-dark.png). Semua gambar diperiksa; capture memakai font review lokal dan tidak menjadi tes yang bergantung path mesin.
- Formatter dan diff whitespace diperiksa. Analisis SDK melalui dartaotruntime tetap digunakan karena launcher flutter analyze lokal bermasalah; hasil final dicatat pada checklist.
- Batas: data API/perangkat nyata, peta jaringan/tiles, zona waktu lintas perangkat, kalender kerja, shift historis, metode absensi historis dan perhitungan keterlambatan belum diverifikasi/ditambahkan. Tidak ada perubahan backend/otorisasi/radius, migrasi database atau deployment. M5 berikutnya adalah Profil, pengaturan dan sesi.


## Validasi M5 — Profil, pengaturan dan sesi — 20 Oktober 2026

- Profil mengikuti Workspace dengan identitas akun/perusahaan dari API, avatar inisial saat foto belum tersedia/gagal, informasi email/telepon/jabatan/departemen/peran serta refresh. Profil dan perusahaan dimuat bersama; kegagalan perusahaan ditandai tanpa mengarang nama, dan respons refresh lama tidak menimpa hasil terbaru.
- Edit profil membatasi perubahan nama, email, nomor telepon serta foto JPG/PNG maksimal 2 MB. Form memvalidasi isian, menjaga foto lama saat pemilihan dibatalkan, menampilkan kegagalan galeri/API, mengunci submit ganda, dan kembali dengan hasil sukses setelah cache akun diperbarui. Foto memakai field multipart `image` sesuai backend. Jabatan, peran dan perusahaan tidak menjadi field edit mandiri.
- Pilihan Terang/Gelap tersedia di Profil dan tersimpan pada preferensi aplikasi; logout/sesi kedaluwarsa tidak menghapus tema. Form dan panel mengikuti tema, dapat digulir pada layar sempit/teks besar. Keselarasan semua layar lama tetap menunggu migrasi M6 dan QA M7.
- Logout memakai konfirmasi, loading dan feedback gagal; sesi lokal dihapus setelah server mengonfirmasi atau token sudah tidak valid (401). Kegagalan server/jaringan mempertahankan sesi, tanpa mengklaim logout berhasil. Bukti QR dibersihkan bersama sesi. Backend logout tetap wajib autentikasi, tetapi akun yang tidak terhubung perusahaan kini dapat mencabut tokennya sendiri.
- Splash memeriksa token tersimpan melalui `/api/user` satu kali per pembukaan; rebuild tema tidak mengulang permintaan. Akun tanpa perusahaan dan koneksi gagal mendapat retry/logout. Kegagalan koneksi tidak menghapus sesi. Respons identitas berbeda tidak diterima sebagai akun aktif.
- HTTP terautentikasi memakai penanganan 401 bersama: hapus hanya sesi yang tokennya cocok, beri pesan masuk kembali, dan kosongkan route privat. Penyimpanan autentikasi diserialisasi; 401 dari request lama atau respons edit akun sebelumnya tidak boleh menghapus/menimpa sesi login baru. JSON/sesi lokal rusak dibersihkan. Tidak mencetak token/kredensial ke log pada irisan ini.
- Perbaikan API terbatas pada kontrak profil dan logout: otorisasi profil sendiri, validasi email unik/isian/foto pada field upload yang benar, nama file storage acak, kegagalan penyimpanan foto tidak menyimpan profil parsial, dan respons kegagalan tidak dikembalikan sebagai HTTP 200. Pengamanan perusahaan pada data/absensi tetap berlaku.
- **41 tes mobile lulus** (29 sebelumnya + 12 M5): cache invalid, tema, 401 serentak/stale dan penolakan login tanpa bearer, restore/offline/403/identitas berbeda, logout 200/401/500/offline, multipart/cache, validasi/late response lintas sesi, refresh profil/partial company failure, galeri dibatalkan/gagal, validasi/submit ganda/kembali setelah sukses, startup retry dan penghapusan navigasi privat saat sesi berakhir. Tes layout Profil memakai 320×640 dan teks 1,8×.
- **59 tes backend lulus, 769 assertions**, database SQLite memory terisolasi. Lima tes baru memastikan profil sendiri, penolakan akses profil lain, field terlindungi, email duplikat, foto PNG/JPG versus SVG/ukuran besar, penyimpanan gagal tanpa perubahan parsial, dan pencabutan token akun tanpa perusahaan.
- Pemeriksaan statis melalui analysis server SDK/dartaotruntime: **87 temuan lama (6 warning, 81 info), tanpa error/diagnostik pada berkas M5 yang diperiksa**. Launcher `flutter analyze` lokal masih memiliki masalah runtime AOT sebagaimana M1–M4; SDK/dependency tidak diubah. Formatter Dart, Pint tiga berkas backend dan diff whitespace diperiksa.
- Bukti render widget Flutter dengan data fixture/font review lokal: [Profil light 390×1100](screenshots/mobile-profile-light.png), [Profil dark 390×1100](screenshots/mobile-profile-dark.png), [Edit dark 390×844](screenshots/mobile-profile-edit-dark.png). Ketiganya telah diperiksa visual; harness capture sementara dihapus. Bukan screenshot perangkat atau bukti API produksi.
- Batas: login/logout/edit dan kedaluwarsa token pada API nyata, galeri/izin foto native, keyboard/system bar Android/iOS, serta instalasi perangkat belum diuji. Cache autentikasi tetap memakai SharedPreferences mengikuti arsitektur proyek; belum migrasi penyimpanan token ke keychain/keystore. Tidak melakukan deployment, push atau migrasi database pengguna. **M6 berikutnya: audit dan implementasi Izin/Reimbursement (form, lampiran, daftar/status, loading/error).**


## Validasi M6 — Izin dan Reimbursement — 20 Oktober 2026

- Kedua pintasan Beranda sekarang membuka daftar pengajuan pribadi dengan desain Workspace light/dark, header navy, tombol ajukan, kartu status, detail, refresh, loading, kosong dan error/retry. Detail menampilkan keterangan lengkap dan lampiran dengan feedback bila gambar gagal dimuat. Daftar diperbarui setelah respons submit berhasil; form kembali ke route sebelumnya, tanpa membuat MainPage baru.
- Kontrak Izin yang tersedia hanya satu tanggal, keperluan dan foto opsional. Tidak mengarang jenis izin, rentang tanggal atau alasan keputusan. Status boolean server ditampilkan sebagai **Disetujui/Belum disetujui**, bukan menganggap false sebagai penolakan atau status pending yang berbeda. Reimbursement memakai tanggal, rincian biaya, nominal, foto opsional dan status pending/approved/rejected; status yang tidak dikenali ditandai belum tersedia.
- Form memakai kalender Indonesia yang juga dipakai Riwayat (batas atas pilihan pengajuan 31 Desember 2100), isian wajib, keterangan maksimal 5.000 karakter, nominal positif sesuai decimal(12,2), dan lampiran JPG/PNG maksimal 2 MB. Galeri dapat dibatalkan; foto yang dipilih dapat diganti/dihapus, kegagalan diberi pesan, dan preview tidak bergantung Image.file. Nilai Rupiah dinormalisasi secara tepat: `150.000,50` menjadi `150000.50`, tanpa menghapus desimal atau mengubah nominal diam-diam. Titik adalah pemisah ribuan dan koma pemisah desimal sesuai petunjuk form. Nilai invalid tidak ditampilkan sebagai nol.
- SubmissionSendCubit mengunci pengiriman ganda. Timeout/exception/payload sukses rusak tidak dianggap sukses; saat hasil tidak pasti, pengguna diminta memuat ulang daftar sebelum mengirim ulang. Tidak ada retry submit otomatis atau klaim idempotensi server. Validasi 422 menjadi pesan Indonesia berdasarkan field; 401 mengikuti mekanisme sesi M5. Respons dari sesi lama tidak diterima untuk sesi yang baru.
- SubmissionListCubit memisahkan refresh/pagination, menolak hasil refresh lama dan permintaan halaman ganda, menghapus duplikasi batas halaman, serta mempertahankan baris yang sudah dimuat bila pemuatan berikutnya gagal. Datasource memvalidasi pemilik, tanggal, ID unik, bentuk respons dan metadata halaman. Respons reimbursement lama tanpa metadata diterima hanya sebagai halaman pertama/terakhir.
- Backend menambahkan index/detail Izin dan detail Reimbursement yang dibatasi ke akun aktif, termasuk untuk akun admin mobile. Daftar keduanya memakai 20 baris per halaman dengan `data` tetap array dan tambahan `meta.current_page/last_page`; perilaku daftar Reimbursement lama yang sebelumnya seluruh baris berubah menjadi pagination. Submit Izin kini mengembalikan `data` record pada 201 agar keberhasilan dapat dikonfirmasi. Perubahan mobile/backend ini harus digunakan bersama untuk kontrak Izin baru.
- Validasi API diperketat pada tanggal nyata, keterangan string, nominal positif maksimal 9.999.999.999,99/dua desimal dan upload JPG/PNG 2 MB. Pemilik dan status awal dipaksakan server, bukan field kiriman. File Izin tetap disimpan sebagai basename di direktori public permissions agar kompatibel dengan web; path Reimbursement `assets/reimbursements/...` dipertahankan ketika membentuk URL storage. Gagal menyimpan lampiran tidak membuat pengajuan sukses. Tidak mengubah middleware perusahaan, aturan absensi, schema database, atau menambahkan email/notifikasi pengajuan.
- **53 tes mobile lulus** (41 sebelumnya + 12 M6). Cakupan baru: konversi nominal/desimal, tanggal invalid, status tidak dikenal, multipart tanpa lampiran/dengan lampiran, identitas akun, respons 422/401/500/offline/201 invalid, file tidak valid/terlalu besar, pagination/duplikasi/stale response, submit ganda, pergantian sesi, path gambar, validasi form/cancel galeri, kegagalan galeri, kembali dengan record sukses, daftar kosong/error/retry/detail, kalender dibatalkan, light/dark serta layout 320×640 dengan teks 1,8× dan inset keyboard 240 px.
- **64 tes backend lulus (849 assertions)** dengan SQLite memory, termasuk lima tes M6 untuk pemilik/status awal, pagination dan detail akun lain/perusahaan lain/rekan satu perusahaan, input invalid, foto valid/invalid, kegagalan storage tanpa record parsial serta akun tanpa perusahaan. Tidak menyentuh database pengguna.
- Formatter Dart, Pint tiga berkas backend dan diff whitespace diperiksa. Analysis server SDK melalui dartaotruntime selesai: **78 temuan lama (6 warning, 72 info), tanpa error/diagnostik pada berkas M6**. Runtime alternatif tetap diperlukan karena launcher analyze SDK lokal sebagaimana tahap sebelumnya; tidak mengedit SDK, dependency atau generated source.
- Delapan screenshot berasal dari renderer widget Flutter dengan fixture, font review Arial/Material Icons dan viewport 390×1000, telah diperiksa visual. Bukan screenshot perangkat/API nyata. Harness capture sementara dihapus.

| Layar | Light | Dark |
| --- | --- | --- |
| Daftar Izin | [light](screenshots/mobile-permission-light.png) | [dark](screenshots/mobile-permission-dark.png) |
| Form Izin | [light](screenshots/mobile-permission-light-form.png) | [dark](screenshots/mobile-permission-dark-form.png) |
| Daftar Reimbursement | [light](screenshots/mobile-reimbursement-light.png) | [dark](screenshots/mobile-reimbursement-dark.png) |
| Form Reimbursement | [light](screenshots/mobile-reimbursement-light-form.png) | [dark](screenshots/mobile-reimbursement-dark-form.png) |

Batas pemeriksaan: galeri/izin foto native dan pemulihan lost-data image_picker setelah proses OS dihentikan belum diuji; submit/list/upload serta pemuatan foto dari API/storage nyata belum diuji. Metadata tanggal tetap mengikuti pilihan perangkat, tanpa menambahkan aturan zona waktu perusahaan yang belum tersedia. Tidak ada deploy, push atau migrasi database produksi. **M7 berikutnya adalah QA mobile menyeluruh dan pengujian perangkat/API nyata yang tersedia.**


## Validasi M7 — QA regresi, HTTP dan emulator — 20 Oktober 2026

[Hasil dan matriks M7](mobile-qa-results.md) menjadi acuan status terkini. 53 tes
mobile, 64 tes backend (849 assertions) dan 33 pemeriksaan HTTP nyata pada backend
terisolasi lulus. Android QA debug APK berhasil dibangun; alur native login,
Geolocator emulator, start/cancel kamera, pengajuan Izin/Reimbursement, edit Profil
serta tema berhasil dicapai. Capture emulator dan asal bukti dicatat dalam laporan.

Perbaikan: deskripsi izin kamera/lokasi/foto iOS, konfigurasi foreground Geolocator,
penanganan kegagalan inisialisasi Firebase/messaging serta update token yang ditunggu,
dan ikon tema yang sebelumnya ditimpa centang ChoiceChip. Analisis statis alternatif
SDK: 78 temuan lama, tanpa error/diagnostik pada berkas main/profil yang diperiksa.
Sintaks Info.plist/Podfile, formatter dan whitespace lulus.

**M7 belum selesai menyeluruh.** Full native integration test berhenti sebelum
konfirmasi absensi selesai. Pengulangan mengalami masalah boot/adb/install dan
tekanan memori (RAM 8 GB, swap sekitar 16,7 GB terpakai). Build iOS dihentikan pada
CocoaPods, belum dinyatakan lulus. Proses/emulator/server QA milik agen dihentikan;
aplikasi dan database pengguna tidak direset. Sisa native, galeri, QR optik,
perangkat fisik, offline/cold start, iOS dan release tercantum dalam laporan.

## Eksplorasi wajah sebagai metode utama — 20 Oktober 2026

Sesuai arahan terbaru, wajah menjadi fokus absensi; QR adalah alternatif opsional.
[Eksplorasi dan rencana uji](design/mobile-face-attendance-exploration.md) memuat
preview Beranda/Kamera/Hasil dengan warna Naltech serta batas arsitektur verifikasi.
Konsep belum disetujui sebagai pengganti desain produksi. Target percobaan
`tools/mobile-qa/face_capture_spike.dart` dibuat terpisah untuk capture satu foto
dan pengukuran durasi deteksi; analisis statis target lulus tanpa temuan.
Pengukuran native, perbandingan baseline streaming, matching dan liveness belum
dijalankan. Tidak mengubah status M7 atau mengaktifkan Face di server.

## Uji wajah pada HP fisik dan pilihan provider — 20 Oktober 2026

[Hasil perangkat](face-capture-device-results.md): OPPO CPH2591 Android 15,
sepuluh capture/detection lulus pada debug dan profile. Profile mendeteksi satu
wajah pada 10/10 sampel, median capture + deteksi 611,5 ms; sampel pertama
1.245 ms. Operasi open/close kamera pada prototipe diserialkan, lalu buka ulang
diuji lewat event lifecycle yang disuntikkan. Target QA/driver lolos analisis statis.
Foto tidak diunggah atau disimpan sebagai screenshot bukti. Paket uji terpisah
ditutup setelah selesai. Snapshot memori startup bukan profil memori inferensi.

Pengguna memilih [cloud opsional dan server sendiri](design/face-verification-engine-decision.md).
Kandidat PoC: AWS untuk cloud, YuNet + SFace untuk matching server sendiri;
anti-spoofing server sendiri, lisensi/provenance bobot, enrollment, threshold dan
integrasi provider masih harus diuji. Belum ada koneksi/aktivasi verifier nyata.
Baseline streaming lama, profil CPU/puncak RAM dan pengujian banyak perangkat
masih terbuka. **Ini bukan penyelesaian M7 menyeluruh atau aktivasi Face produksi.**

## Implementasi pendaftaran dan PoC server wajah — 20 Oktober 2026

[Catatan implementasi](face-enrollment-implementation.md) menjadi acuan terkini.
Pendaftaran kamera/form mobile, API sendiri, HR review web privat, replacement,
revoke, event audit dan retensi tersedia. Supervisor tidak dapat menaikkan akses
atau mereset akun HR/admin untuk mengakses foto. Embedding lama tidak diterima.
Migrasi utama belum dijalankan; DB QA/tes memakai SQLite terisolasi.

Matching YuNet/SFace diuji melalui Laravel dan HTTP lokal. MiniFASNet NCNN dapat
menjalankan diagnostik eksperimental, tanpa otorisasi absensi. 74 tes backend,
57 tes mobile, 5 tes service, 8 smoke model, 13 HTTP lintas service dan 8 pemeriksaan
browser lulus. Screenshot web dan render widget light/dark ada pada catatan.
Kamera pendaftaran baru belum diuji native; perangkat tidak terhubung.

**Liveness operasional masih terbuka.** Integrasi Face masuk/pulang dan UI hasil
menunggu pengujian kamera/presentation attack dan proof server sekali pakai.
Face tetap tidak tersedia; QR mengikuti metode perusahaan. M7 menyeluruh tidak
berubah menjadi selesai.

## Evaluasi liveness — 21 Oktober 2026

[Protokol dan alat evaluasi](face-liveness-validation.md) tersedia. Evaluator
memisahkan development/holdout berdasarkan orang, menolak foto duplikat dan
melaporkan penerimaan per frame/sesi serta penolakan kualitas. 11 tes Python
lulus; hasil fixture bukan ukuran akurasi. Tidak ada threshold dipromosikan.

APK QA terpisah berhasil dibangun, dipasang dan dibuka pada OPPO CPH2591 yang
terhubung. Target memakai salinan halaman kamera pendaftaran baru, penyimpanan
sampel privat dengan persetujuan dan tombol hapus. Analisis target QA/kamera
tanpa temuan. Pengambilan kategori live/cetak/layar/video menunggu tindakan
relawan pada HP; belum ada hasil liveness atau kalibrasi yang dinyatakan lulus.
Absensi Face tetap tidak aktif, aplikasi utama dan database utama tidak diubah.

### Hasil sesi perangkat pada 21 Oktober

Empat sampel (satu per label live/cetak/layar/video) berhasil ditransfer lewat USB
dan dievaluasi dengan model nyata. Semua diterima pada threshold 0,8 maupun
sweep development 0,5–0,95. Operator mengonfirmasi penggunaan media fisik sesuai
kategori: ketiga sampel spoof lolos. Jalur eksperimen gagal uji awal dan tidak
boleh diintegrasikan ke absensi; audit praproses/adapter dan evaluasi alternatif
menjadi langkah berikutnya. Tidak ada holdout/kalibrasi produksi. Kamera pendaftaran baru berhasil
menghasilkan file pada HP, bukan bukti alur enrollment end-to-end selesai.
Foto pada HP/komputer telah dihapus dan paket QA dihentikan; hanya laporan
agregat disimpan. [Hasil dan batasan](face-liveness-validation.md).

### Audit adapter dan uji ulang — 21 Oktober

[Audit lengkap](face-liveness-adapter-audit.md): crop dikoreksi mengikuti batas
dan float32 referensi C++; 1.005 fixture cocok, tujuh pemeriksaan adapter/model
nyata serta 14 tes Python lulus. Format warna/ukuran/nilai input/kelas hidup
sudah sesuai. Uji ulang empat capture baru (satu per kategori) masih menerima
ketiga spoof pada 0,8 maupun sweep 0,5–0,95. Kandidat tetap gagal gerbang
integrasi. Foto HP/komputer dihapus, paket QA dihentikan, hanya agregat disimpan.
Shortlist berikutnya adalah PoC anti-spoof-mn3 server sendiri, dengan AWS tetap
opsional; belum mengunduh model baru atau memanggil layanan cloud.

### PoC MN3 — 21 Oktober

[Implementasi dan hasil](face-mn3-poc.md): model original ONNX dari storage
resmi diverifikasi SHA384 dan disimpan di luar checkout. Adapter RGB 128×128
dengan normalisasi/kelas MN3 terpisah berjalan pada OpenCV CPU. 20 tes Python
dan enam smoke HTTP model nyata lulus; produksi liveness tetap HTTP 501.

Empat sampel baru (satu per label, bukan tiga) tetap semuanya diterima MN3.
MiniFASNet pada foto yang sama juga menerima semua. Audit ONNX Runtime terhadap
OpenCV pada empat fixture sintetis dan empat foto kamera cocok dalam toleransi
1e-4, keputusan sama. Tidak ada threshold dipromosikan atau integrasi absensi.
Foto HP/komputer dihapus, paket QA/server sementara dihentikan. Tahap berikutnya
adalah evaluasi liveness sesi/video; AWS tetap opsional, belum dipanggil.

### Peralihan ke audit HRIS inti — 21 Oktober

Pengguna menunda liveness sesi/video dan melanjutkan audit fitur HRIS.
[Audit dan bukti](hris-core-audit.md) serta [milestone H0–H6](hris-core-timeline.md)
tersedia. Baseline 74 tes backend lulus; empat probe SQLite memory mengonfirmasi
gap yang dicatat. Tahap ini hanya audit/dokumentasi, tanpa perubahan aplikasi
atau database utama. Prioritas berikutnya H0, web/backend dahulu; Face tetap
tidak aktif dan pekerjaan biometrik tidak dilanjutkan otomatis.

## Pembaruan jadwal efektif H2 — 21 Oktober 2026

[Jadwal efektif dan checkout lintas malam](hris-h2-effective-shifts.md) tersedia dan diuji terisolasi. Dashboard memakai salinan jadwal catatan baru; Beranda mobile memakai tanggal kerja/sesi dari server. Sepuluh pemeriksaan Chrome light/dark desktop/sempit lulus. Migrasi database utama, review pengguna dan QA native perubahan H2 masih terbuka; QA OPPO H4 sebelumnya bukan bukti H2.

## QA shift emulator — 21 Oktober 2026

[Pengujian H2 pada Android 15 emulator lulus](hris-h2-mobile-emulator.md): 1 suite/10 pemeriksaan, 4 screenshot dan verifikasi SQLite terisolasi. Label jadwal efektif diperbaiki; 14 tes Beranda/shift lulus. Migrasi utama sebelumnya telah diterapkan. HP fisik, kamera QR dan akurasi GPS belum diuji untuk H2. Commit tetap menunggu perintah pengguna.
