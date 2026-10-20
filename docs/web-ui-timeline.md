# Timeline UI Web — Naltech Attendance

Diperbarui: 20 Oktober 2026. Acuan utama urutan kerja setelah perluasan lingkup oleh pengguna.

## Koreksi lingkup

Home/Ringkasan sudah menggunakan Workspace. Halaman lain sebelumnya masih menggunakan Stisla; kesiapan Home tidak berarti seluruh web selesai. Semua halaman web di bawah perlu diselaraskan, termasuk daftar, formulir, detail, state kosong/error, tema, dan navigasi. Mobile dimulai setelah review web menyeluruh.

Timeline memakai urutan milestone; belum ada tenggat kalender yang disepakati. Satu tahap selesai setelah implementasi, pemeriksaan fungsi/visual, dan review yang relevan. Tidak menandai halaman selesai hanya karena sidebar sudah terhubung.

| Urutan | Area dan halaman | Status aktual | Kriteria / langkah berikutnya |
| --- | --- | --- | --- |
| W0 | Home / Ringkasan | Implementasi tersedia; siap review | Detail bukti di checklist Dashboard; aturan kalender/shift tetap terbatas sesuai skema |
| W1 | Layout Workspace lintas halaman | Implementasi tersedia; siap review | Layout bersama seluruh modul aktif, judul/menu dinamis dan tema tersimpan |
| W2 | Kehadiran: daftar, edit, ekspor CSV, konfirmasi hapus | Implementasi tersedia; siap review | Pencarian, tanggal, pagination, form error, dialog batal hapus, tema dan layar sempit diperiksa |
| W3 | Karyawan: daftar, tambah, edit | Implementasi tersedia; siap review | Workspace diterapkan; pencarian, pagination, validasi dan isolasi perusahaan diperiksa |
| W4 | Pengajuan izin: daftar, detail, edit/persetujuan | Implementasi tersedia; siap review | Workspace, filter, detail dan persetujuan diperiksa; batas status boolean tetap berlaku |
| W5 | Lembur: daftar, tambah, detail, edit/persetujuan | Implementasi tersedia; siap review | Workspace, filter, validasi, persetujuan dan pembatasan pemilik diperiksa |
| W6 | Reimbursement: daftar, tambah, detail, edit/persetujuan | Implementasi tersedia; siap review | Nominal, bukti, filter, validasi dan scope perusahaan diperiksa |
| W7 | Shift: daftar, tambah, detail, edit | Implementasi tersedia; siap review | Jam kerja, lintas hari, penugasan karyawan, validasi dan scope diperiksa |
| W8 | Lokasi kerja/perusahaan: detail, edit | Implementasi tersedia; siap review | Koordinat, radius, jam, metode, tarif dan scope perusahaan diperiksa |
| W9 | QR absensi: daftar, tambah, detail, unduh PDF | Implementasi tersedia; siap review | Workspace, pasangan QR, filter, pembuatan ulang dan PDF diperiksa |
| W10 | Verifikasi pensiun / rekap terkait | UI peninjauan tersedia; siap review dengan batas data | Catatan kehadiran, filter/detail/CSV tersedia; keputusan pensiun belum didukung backend |
| W11 | Profil akun dan login web | Implementasi tersedia; siap review | Profil akun sendiri, login/logout nyata, tema dan error sesi diperiksa |
| W12 | QA lintas halaman dan review web | QA dan review teknis lokal selesai; review pengguna menunggu | [Laporan dan bukti QA](web-ui-qa-review.md); persetujuan visual belum diberikan |
| M0 | Implementasi mobile | Dimulai atas arahan pengguna setelah W12 | [Timeline mobile](mobile-ui-timeline.md): fondasi tema dan Login tersedia; berikutnya Beranda |

## Checklist wajib per modul

- [ ] Daftar/detail/form menggunakan layout Workspace dan judul bahasa Indonesia.
- [ ] Tema light/dark tersimpan ketika berpindah halaman; menu aktif benar.
- [ ] Semua aksi tersedia tetap terhubung ke route/controller sebenarnya.
- [ ] Pencarian, filter, pagination dan ekspor (bila ada) memakai definisi yang sama.
- [ ] State kosong, validasi, feedback sukses/gagal dan konfirmasi aksi destruktif jelas.
- [ ] Perusahaan aktif, role, CSRF dan otorisasi server dipertahankan.
- [ ] Light/dark serta desktop/layar sempit diperiksa; catat yang belum diuji.
- [ ] Bukti dan status aktual diperbarui tanpa menyamakan implementasi dengan persetujuan pengguna.

## Log W1–W2 — 19 Oktober 2026

- Daftar/edit Kehadiran dipindah ke `layouts.workspace`; modul lain belum dipindah agar tidak dianggap sudah selesai.
- View composer menyuplai perusahaan akun aktif dan role pengelola ke layout bersama; menu menandai route aktif pada index maupun edit. Navigasi Reimbursement dan Verifikasi pensiun turut tersedia sesuai route yang ada.
- Pencarian nama dan filter tanggal tervalidasi digunakan bersama daftar dan CSV; pagination mempertahankan query. Shift dimuat eager dan dibatasi pada perusahaan aktif.
- Form edit menampilkan input lama saat validasi gagal, label serta pesan field. Aksi hapus memakai dialog konfirmasi dengan Batal; tidak ada catatan produksi dihapus saat pemeriksaan.
- Tes memakai SQLite memory terisolasi: filter, pagination, tanggal, ekspor, data kosong, validasi, render edit, update, hapus serta akses lintas perusahaan/staf.
- Browser in-app memakai fixture 14 karyawan di SQLite memory melalui router GET sementara: desktop 1440×1000 light/dark, halaman kedua 11–14/14, edit, layar 390×844, dialog hapus dan Batal, hasil pencarian kosong. Tidak ada overflow halaman pada 390 px atau warning/error konsol pada skenario tersebut.
- Bukti: [daftar light](screenshots/attendance-light-desktop.png), [daftar dark](screenshots/attendance-dark-desktop.png), [edit layar sempit](screenshots/attendance-edit-mobile.png).
- Keterbatasan: browser tidak mengirim update/hapus karena router review hanya GET; mutasi diperiksa lewat tes terisolasi. Beban ekspor besar, browser lain, dan persetujuan visual pengguna belum diperiksa. Aturan status tetap shift saat ini tanpa kalender/toleransi; tidak mengklaim jadwal historis.

- Pemeriksaan akhir W2: `php artisan test` **27 lulus, 129 assertion**; `view:cache` berhasil lalu cache dibersihkan; `git diff --check` bersih.

## Log W3 — Karyawan — 19 Oktober 2026

- Daftar, tambah dan edit Karyawan memakai layout Workspace, tema tersimpan, menu aktif serta komponen feedback/pagination yang sama. Form tambah/edit berbagi partial agar label dan pesan validasi konsisten.
- Daftar memuat kontak, jabatan/departemen, shift dan peran asli. Pencarian nama tervalidasi, tetap tersimpan pada pagination, tersedia keadaan tanpa hasil dan dialog konfirmasi hapus dengan Batal.
- Shift dimuat eager untuk daftar dan dibatasi perusahaan aktif. Pilihan shift tambah/edit tetap melalui scope perusahaan dan validasi server terhadap `company_id` akun aktif. Payload perusahaan diabaikan; keanggotaan mengikuti context server yang sudah ada.
- Validasi ditambahkan untuk tipe/panjang nama dan profil, email unik sesuai constraint global users, panjang kata sandi, dan enum peran yang sudah didukung. Nomor telepon memakai input `tel`; tanda + dan nol awal dipertahankan.
- Tambah akun memakai default Staf jika peran tidak dikirim. Pilihan peran yang tersedia tetap mengikuti kemampuan pengelola sebelumnya; tidak menambahkan role/otorisasi baru. Kata sandi pada edit boleh kosong untuk mempertahankan hash; isian kata sandi tidak diisi kembali setelah error. Field lain memulihkan `old()` beserta error field yang berlabel.
- **30 tes lulus, 178 assertion** pada SQLite `:memory:`. Tiga tes baru memeriksa daftar/search/pagination, pilihan shift, penolakan akun perusahaan lain/staf, tambah/edit/hapus, keanggotaan perusahaan, hash kata sandi kosong/baru, email duplikat, payload invalid dan pemulihan isian setelah validasi gagal.
- Browser in-app dengan fixture 15 akun dan database memory terisolasi: daftar light/dark 1440×1000, halaman kedua 11–15/15, navigasi tambah/edit/Batal, form 390×844, dialog hapus/Batal, pencarian tanpa hasil. Tidak ada overflow halaman pada form 390 px dan tidak ada warning/error konsol pada skenario yang diperiksa. Data produksi tidak diubah.
- Bukti: [daftar light](screenshots/users-light-desktop.png), [daftar dark](screenshots/users-dark-desktop.png), [tambah dark](screenshots/users-create-dark.png), [tambah layar sempit](screenshots/users-create-mobile.png), [edit layar sempit](screenshots/users-edit-mobile.png).
- `view:cache` berhasil lalu cache dibersihkan; Pint pada controller/tes dan `git diff --check` berhasil. Aset dilayani langsung sehingga build Vite tidak diperlukan untuk irisan ini.
- Batas pemeriksaan: mutasi diverifikasi melalui tes server terisolasi; router browser review hanya GET. Belum benchmark perusahaan besar, pengujian browser lain, atau persetujuan visual pengguna. Modul Izin/Lembur/Reimbursement dan seterusnya tetap belum diselaraskan.

## Log W4 — Pengajuan Izin — 19 Oktober 2026

- Daftar, detail dan persetujuan memakai Workspace; label Indonesia, tema, menu aktif, feedback dan pagination seragam dengan Home/Kehadiran/Karyawan.
- Pencarian nama, filter tanggal dan status tervalidasi, bekerja bersama dan tersimpan pada pagination. Status hanya Disetujui/Belum disetujui sesuai boolean yang tersedia; tidak mengarang kategori menunggu/ditolak.
- Detail dan form memakai partial informasi yang sama, alasan lengkap dengan pembungkusan teks, fallback data kosong, serta tautan bukti pada `storage/permissions/` yang sesuai lokasi upload API. Halaman edit lama sebelumnya memakai path singular yang tidak sesuai.
- Tombol hapus lama tidak ditampilkan karena controller tidak menyediakan `destroy`; tidak menambahkan endpoint mutasi baru. Modul web mengelola izin yang diajukan melalui alur yang sudah ada.
- Persetujuan tetap memeriksa CSRF, middleware pengelola dan scope perusahaan. Form memiliki label, status saat ini, `old()` dan pesan validasi. Keputusan tersimpan meskipun email gagal: feedback warning menjelaskan email belum terkirim tanpa membocorkan error penyedia. Email hanya dicoba ketika status berubah dari belum disetujui ke disetujui; menyimpan ulang status yang sama tidak mengirim ulang.
- **32 tes lulus, 207 assertion** pada SQLite memory. Tes baru meliputi kombinasi filter/pagination, detail/edit/bukti, data kosong, input invalid, scope perusahaan/staf, kegagalan email setelah keputusan tersimpan, penyimpanan ulang tanpa email ganda, serta pengembalian status ke belum disetujui. Tes sebelumnya tetap memeriksa persetujuan sukses, perubahan ringkasan, dan CSRF. Semua pengiriman email pada tes dimock; tidak ada email nyata dikirim.
- Browser in-app dengan fixture 14 pengajuan dan router GET memory terisolasi: daftar desktop 1440×1000 light/dark, halaman kedua 11–14/14, filter disetujui (4 hasil), detail/alasan panjang, form status terpilih sesuai data, Batal/Kembali, pencarian kosong, dan persetujuan pada 390×844. Tidak ada overflow halaman pada viewport 390 px dan tidak ada warning/error konsol pada skenario yang diperiksa.
- Bukti: [daftar light](screenshots/permissions-light-desktop.png), [daftar dark](screenshots/permissions-dark-desktop.png), [persetujuan layar sempit](screenshots/permissions-approval-mobile.png). `view:cache` berhasil lalu cache dibersihkan; Pint dan `git diff --check` berhasil.
- Belum diuji: pengiriman email nyata, berkas bukti produksi, benchmark data besar, browser lain, serta persetujuan visual pengguna. Mutasi diperiksa lewat tes server; browser review hanya GET. Mobile tetap menunggu QA/review seluruh web.

## Log W5 — Lembur — 19 Oktober 2026

- Daftar, tambah, edit/persetujuan dan detail lembur menggunakan Workspace, tema, navigasi aktif, feedback dan pagination bersama. View detail ditambahkan karena controller sebelumnya menunjuk view yang belum tersedia.
- Daftar memiliki pencarian nama, tanggal dan status Menunggu/Disetujui/Ditolak; filter tervalidasi dan ikut pagination. Durasi ditampilkan dalam menit, catatan lengkap tersedia pada detail, tersedia hasil kosong dan dialog konfirmasi hapus dengan Batal.
- Form tambah/edit berbagi partial, memakai label, `old()`, feedback field, tanggal aplikasi dan input menit bilangan bulat positif. Batas durasi mengikuti kapasitas integer database, bukan aturan maksimum jam bisnis baru. Catatan dibatasi 10.000 karakter.
- Store/update hanya menyimpan field tervalidasi. Pengajuan baru dipaksa pending; payload status tambahan tidak dapat meloloskan persetujuan awal. Pemilik pengajuan tetap saat update meskipun payload menyertakan user_id. Pemilihan karyawan pada tambah harus berasal dari perusahaan aktif; middleware pengelola, scope model dan CSRF dipertahankan.
- **35 tes lulus, 257 assertion** pada SQLite memory terisolasi. Tes baru memeriksa filter/search/tanggal/pagination, detail/edit/create, akses lintas perusahaan/staf, durasi nol/negatif/pecahan/di luar kapasitas integer, tanggal/status invalid, pemulihan isian, status awal pending, transisi approved/rejected, pencegahan reassignment lewat edit, serta hapus. Tes CSRF dan ringkasan Dashboard sebelumnya tetap lulus.
- Browser in-app dengan 14 pengajuan fixture pada router GET sementara: daftar light/dark 1440×1000, halaman kedua 11–14/14, filter Menunggu 4 hasil, detail, status edit sesuai data, tambah/edit 390×844, Batal, dialog hapus/Batal serta pencarian kosong. Tidak ada overflow halaman terukur pada desktop/form sempit atau warning/error konsol pada skenario tersebut.
- Bukti: [daftar light](screenshots/overtime-light-desktop.png), [daftar dark](screenshots/overtime-dark-desktop.png), [tambah layar sempit](screenshots/overtime-create-mobile.png), [edit layar sempit](screenshots/overtime-edit-mobile.png).
- Pint, `view:cache` dan `git diff --check` berhasil; cache view dibersihkan. Tidak ada perubahan database produksi, migrasi atau pengiriman notifikasi selama pemeriksaan.
- Batas: mutasi diperiksa lewat tes server, browser review hanya GET. Beban besar, browser lain dan persetujuan visual pengguna belum diperiksa. Penetapan batas jam bisnis/nominal pembayaran bukan lingkup UI lembur ini. Berikutnya W6 Reimbursement; mobile tetap menunggu review web menyeluruh.

## Log W6 — Reimbursement — 19 Oktober 2026

- Daftar, tambah, detail serta edit/persetujuan menggunakan Workspace. View detail ditambahkan karena controller sebelumnya menunjuk view yang belum tersedia. Form memakai partial bersama, label Indonesia, feedback field, `old()` dan status Menunggu/Disetujui/Ditolak.
- Pencarian nama diperbaiki agar benar-benar memfilter data (form lama mengirim name, controller hanya membaca user_id). Tanggal/status dan user_id lama tetap didukung serta tersimpan pada pagination. Semua data/pilihan karyawan mengikuti perusahaan aktif.
- Nominal Rupiah mengikuti tampilan mobile yang sudah ada, memakai dua angka desimal; validasi web menolak nol/negatif, lebih dari dua desimal dan nilai di luar decimal(12,2). Maksimum 9.999.999.999,99 mengikuti kapasitas skema, bukan plafon pengeluaran bisnis baru. Keterangan dibatasi 10.000 karakter.
- Upload web menerima JPG/PNG/GIF maksimal 2 MB; SVG tidak ditawarkan/diterima. Bukti disimpan pada `assets/reimbursements` disk public mengikuti alur lama, tersedia tautan buka bukti dan keadaan tanpa bukti. Tanpa upload pengganti, berkas lama dipertahankan. Pengguna diberi petunjuk memilih ulang berkas setelah validasi gagal. Pemilihan ulang karyawan pada edit tetap tersedia sesuai kemampuan lama, dibatasi perusahaan aktif.
- Store/update memakai field tervalidasi dan status awal dipaksa pending. Tidak ada endpoint pembayaran atau pengiriman dana; persetujuan hanya memperbarui status pengajuan. API/mobile tidak diubah pada irisan UI web ini.
- **38 tes lulus, 323 assertion** pada SQLite memory; upload menggunakan Storage fake. Tes baru mencakup filter/search/tanggal/status/pagination, scope daftar/detail/edit/staf, render nominal/bukti, upload/penggantian/preservasi berkas, nominal invalid, tipe/ukuran berkas invalid, karyawan lintas perusahaan, status invalid, pemulihan input, persetujuan dan hapus.
- Browser in-app dengan 14 fixture dan router GET memory: light/dark desktop 1440×1000, nominal Rp 125.000,50, halaman kedua 11–14/14, filter Menunggu 4 hasil, detail, status edit sesuai data, tambah/edit 390×844, Batal, dialog hapus/Batal, pencarian kosong. Tidak ada overflow halaman desktop/form 390 px atau warning/error konsol pada skenario yang diperiksa.
- Bukti: [daftar light](screenshots/reimbursement-light-desktop.png), [daftar dark](screenshots/reimbursement-dark-desktop.png), [tambah layar sempit](screenshots/reimbursement-create-mobile.png), [edit layar sempit](screenshots/reimbursement-edit-mobile.png). Pint, `view:cache` dan `git diff --check` berhasil; cache view dibersihkan.
- Batas: mutasi/upload dibuktikan lewat tes server terisolasi, browser review hanya GET. Berkas produksi, kapasitas besar, browser lain dan review visual pengguna belum diperiksa. Berkas lama tidak dihapus otomatis ketika bukti diganti/pengajuan dihapus, mengikuti perilaku penyimpanan lama; kebijakan retensi terpisah belum ditetapkan. Berikutnya W7 Shift; mobile menunggu review seluruh web.

## Log W7 — Shift — 19 Oktober 2026

- Daftar, tambah, edit serta detail memakai Workspace. Detail ditambahkan untuk route show yang sebelumnya belum memiliki view. Pencarian nama kini terhubung backend, tervalidasi dan dipertahankan pada pagination; tersedia hasil kosong dan feedback form.
- Relasi users/count memuat jumlah karyawan terhubung per shift tanpa query per baris, mengikuti perusahaan aktif. Dialog hapus menampilkan jumlah dan menjelaskan efek FK `ON DELETE SET NULL`: karyawan menjadi tanpa shift. Tidak mengubah aturan penghapusan database.
- Store/update hanya menyimpan field tervalidasi. Nama wajib string maksimal 255 karakter, jam menerima HH:mm atau HH:mm:ss dan dinormalisasi HH:mm:ss. Jam tidak valid dan pasangan jam sama ditolak; shift lintas hari tetap diperbolehkan. Tidak menambah asumsi toleransi atau panjang shift bisnis.
- Form/detail menampilkan zona waktu konfigurasi aplikasi, catatan keterbatasan rekap lintas hari, dan dampak edit pada rekap yang memakai shift saat ini. Snapshot shift historis/kalender kerja tidak ditambahkan.
- **41 tes lulus, 361 assertion** pada SQLite memory. Tes baru mencakup pencarian/pagination/count/detail/form, isolasi perusahaan pada baca/edit/update/hapus, staf ditolak, tambah/edit shift biasa/lintas hari, normalisasi jam dengan detik, jam invalid/sama, pemulihan isian, payload perusahaan diabaikan, dan efek penghapusan pada penugasan karyawan.
- Browser in-app dengan fixture 14 shift termasuk malam dan satu karyawan per shift, router GET terisolasi: daftar desktop 1440×1000 light/dark, halaman kedua 11–14/14, pencarian Malam 4 hasil, detail/count, tambah/edit 390×844, Batal, dialog hapus/Batal, hasil pencarian kosong. Form sempit tidak overflow horizontal dan konsol tidak mengandung warning/error pada skenario tersebut.
- Bukti: [daftar light](screenshots/shifts-light-desktop.png), [daftar dark](screenshots/shifts-dark-desktop.png), [tambah layar sempit](screenshots/shifts-create-mobile.png), [edit layar sempit](screenshots/shifts-edit-mobile.png).
- Pint, `view:cache` dan `git diff --check` berhasil; cache view dibersihkan. Mutasi diverifikasi melalui tes server; browser review hanya GET. Database produksi tidak dimigrasikan/diubah.
- Batas: beban besar, browser lain dan review visual pengguna belum diperiksa. Rekap lintas hari masih berstatus perlu tinjau sesuai implementasi sebelumnya. Berikutnya W8 Lokasi Kerja/Perusahaan; mobile tetap menunggu review web menyeluruh.

## Log W8 — Lokasi Kerja/Perusahaan — 19 Oktober 2026

- Detail dan edit perusahaan memakai Workspace. Detail menampilkan profil, alamat/email, koordinat, radius km/meter, jam perusahaan, zona waktu aplikasi, metode absensi dan tarif lembur. Tidak menampilkan data contoh sebagai fallback produksi.
- Form memakai label field, nilai tersimpan/`old()`, pesan validasi, petunjuk satuan radius, serta dampak perubahan pada absensi. Manual + lokasi dan QR + lokasi adalah pilihan aktif; Face tetap ditolak server dan ditandai belum tersedia pada detail bila merupakan konfigurasi lama. Konfigurasi Face/tidak dikenal memerlukan pilihan metode yang didukung sebelum disimpan.
- Validasi web mencakup tipe/panjang profil, rentang lintang -90…90 dan bujur -180…180, radius positif/finite, format jam HH:mm atau HH:mm:ss, metode None/QR, serta tarif lembur integer tidak negatif sesuai kolom integer. Tidak menambahkan aturan zona waktu perusahaan atau mekanisme wajah baru.
- Update hanya memakai data tervalidasi. Scope perusahaan aktif dan middleware pengelola dipertahankan. Pemeriksaan radius, QR, duplikasi dan wajah pada endpoint absensi tidak diubah.
- **44 tes lulus, 417 assertion** pada SQLite memory. Tes baru mencakup detail/edit dan akses perusahaan lain/staf, update perusahaan aktif, konsistensi radius/metode di Home, penolakan koordinat/radius/jam/metode/tarif invalid, pemulihan input, dan label konfigurasi Face lama. Semua tes integritas absensi yang sudah ada tetap lulus.
- Browser in-app dengan fixture kantor bernama/alamat panjang dan router GET terisolasi: detail light/dark 1440×1000, navigasi edit/Batal, metode QR terpilih sesuai konfigurasi, detail/edit 390×844. Form tidak overflow horizontal, konsol tidak menunjukkan warning/error pada skenario tersebut.
- Bukti: [detail light](screenshots/company-light-desktop.png), [detail dark](screenshots/company-dark-desktop.png), [detail layar sempit](screenshots/company-detail-mobile.png), [edit layar sempit](screenshots/company-edit-mobile.png).
- Pint, `view:cache` dan `git diff --check` berhasil; cache view dibersihkan. Tidak ada migrasi/update database produksi. Mutasi diverifikasi melalui tes server, browser review hanya GET.
- Batas: perangkat lokasi nyata, browser lain dan review visual pengguna belum diperiksa. Zona waktu masih konfigurasi aplikasi, bukan setting per perusahaan. Berikutnya W9 QR Absensi; mobile menunggu review web menyeluruh.

## Log W9 — QR Absensi — 19 Oktober 2026

- Daftar, buat bulanan dan detail memakai Workspace. Filter bulan/tanggal tervalidasi bekerja bersama dan ikut pagination; hasil kosong jelas. Daftar menampilkan ketersediaan QR, detail menampilkan gambar Masuk/Pulang, perusahaan dan tanggal. QR tidak lengkap mendapat petunjuk pelengkapan; unduh PDF tidak ditawarkan dan endpoint mengembalikan 422.
- Preview/PDF memakai Endroid PNG yang sama dengan latar putih serta quiet zone. PDF A4 kini beridentitas Naltech Attendance, perusahaan aktif, tanggal, zona waktu dan petunjuk masuk/pulang; identitas contoh FIC16 lama dihapus. Tata letak memakai tabel yang didukung DomPDF.
- Parsing bulan dimulai dari hari pertama (`!Y-m`), memperbaiki potensi overflow saat dijalankan pada akhir bulan. Pembuatan berjalan dalam transaksi dengan lock baris perusahaan: hanya tanggal belum ada/pasangan belum lengkap yang disimpan. QR lengkap lama tidak diganti. Tidak ada pembersihan/migrasi destruktif atas duplikasi legacy; data tersebut memerlukan audit terpisah bila ada.
- Kode baru memakai random kriptografis 32 karakter, unik terhadap kedua kolom dalam perusahaan dan dibedakan antara masuk/pulang. Endpoint absensi tetap memverifikasi kode, aksi, perusahaan, tanggal dan radius seperti sebelumnya; format payload masih string yang kompatibel dengan validasi API maksimal 255 karakter. Tidak mencetak kode produksi ke log.
- **46 tes lulus, 448 assertion** pada SQLite memory. Tes baru mencakup generasi Februari saat tanggal sistem 31 Januari, jumlah hari, pembuatan ulang tanpa perubahan kode/jumlah, pelengkapan parsial, pasangan berbeda, filter/pagination, detail PNG, response PDF valid, penolakan lintas perusahaan/staf, input invalid dan PDF pasangan belum lengkap. Tes integritas QR absensi lama tetap lulus.
- Browser in-app dengan 14 tanggal fixture: daftar desktop 1440×1000 light/dark, halaman kedua 11–14/14, detail pasangan lengkap, status parsial tanpa PDF, navigasi pelengkapan bulanan/Batal, filter gabungan bulan/tanggal (1 hasil), filter tanpa hasil, detail/form 390×844. Tidak ada overflow halaman pada detail sempit atau warning/error konsol dalam skenario yang diperiksa.
- PDF dari response tes dirender lewat Poppler dan gambar satu halaman diperiksa: nama perusahaan/tanggal, dua QR, label dan catatan tidak terpotong/bertumpuk. [Contoh PDF fixture](../output/pdf/qr-absensi-contoh-review.pdf), [render PDF](screenshots/qr-pdf-review.png).
- Bukti browser: [daftar light](screenshots/qr-light-desktop.png), [daftar dark](screenshots/qr-dark-desktop.png), [detail layar sempit](screenshots/qr-detail-mobile.png), [buat layar sempit](screenshots/qr-create-mobile.png).
- Pint, `view:cache` dan `git diff --check` berhasil; cache view dibersihkan. Tidak ada perubahan database produksi. Browser memakai router GET terisolasi; generasi diuji melalui tes server.
- Batas: scan kamera/perangkat nyata, printer fisik, benchmark dan uji request bersamaan terhadap database produksi belum dilakukan. Lock diuji secara alur transaksi, bukan benchmark konkurensi; tidak menambahkan constraint unik baru pada data legacy. Persetujuan visual pengguna belum diberikan. Berikutnya W10 Verifikasi Pensiun; mobile menunggu review seluruh web.

## Log W10 — Verifikasi Pensiun — 19 Oktober 2026

- Audit route/controller/model: route aktif `verifikasi` membaca tabel attendances dan relasi user; tidak ada model, status, dokumen kelayakan atau alur keputusan pensiun. Implementasi UI dibatasi peninjauan catatan kehadiran dan secara eksplisit menjelaskan bahwa keputusan verifikasi pensiun belum tersedia. Tidak mengarang status kelayakan atau hasil verifikasi.
- Halaman aktif `pages.verifikasi.index` memakai Workspace dengan tema/navigasi/feedback/pagination bersama. View lama `pages.verifikasi-pensiun.index` menjadi alias ke halaman aktif agar tidak mempertahankan dua implementasi yang berbeda.
- Tombol edit/hapus lama mengarah ke method controller yang tidak ada. Aksi tersebut diganti detail read-only berupa dialog; resource route dibatasi index, ekspor CSV tetap route terpisah. Tidak menambah mutasi atau keputusan baru pada data kehadiran.
- Filter nama/tanggal tervalidasi, dipertahankan pada pagination dan digunakan query yang sama untuk CSV. Detail menampilkan jam/koordinat, shift saat ini dan zona waktu; data yang belum tercatat diberi label jelas. Relasi shift dimuat eager dan dibatasi perusahaan aktif.
- Ekspor memakai stream dan lazy batch 500 dengan header bahasa Indonesia; sel teks yang berpotensi dibaca sebagai formula spreadsheet diberi prefix apostrof. Filter dan scope perusahaan tetap berlaku, termasuk pada ekspor. Koordinat negatif diekspor sebagai teks berprefix apostrof agar tidak ditafsirkan formula.
- **48 tes lulus, 472 assertion** pada SQLite memory. Tes baru mencakup pencarian/tanggal/pagination, konsistensi hasil CSV, hasil kosong, input invalid, data perusahaan lain tidak tampil/terekspor, staf ditolak, route edit/hapus tidak tersedia tanpa perubahan data, serta ekspor 501 catatan melintasi batch dan escape formula.
- Browser in-app dengan 14 fixture dan router GET memory: desktop 1440×1000 light/dark, halaman kedua 11–14/14, detail dalam dialog pada 390×844, Tutup, gabungan nama/tanggal 1 hasil, URL ekspor membawa filter yang sama, dan pencarian kosong. Tidak ada overflow halaman pada viewport sempit atau warning/error konsol pada skenario tersebut.
- Bukti: [daftar light](screenshots/pension-light-desktop.png), [daftar dark](screenshots/pension-dark-desktop.png), [detail layar sempit](screenshots/pension-detail-mobile.png). Pint, `view:cache` dan `git diff --check` berhasil; cache view dibersihkan. Database produksi tidak diubah.
- Batas: hasil ini bukan implementasi proses verifikasi pensiun bisnis. Kebutuhan sumber data, aturan keputusan dan hak akses proses tersebut perlu ditetapkan terpisah. Browser lain, beban produksi besar dan review visual pengguna belum diperiksa. Berikutnya W11 Profil/Login; mobile tetap menunggu review seluruh web.


## Log W11–W12 — 20 Oktober 2026

- Profil dan Login memakai Workspace, aset lokal serta tema tersimpan. Profil menampilkan data akun/perusahaan aktif secara read-only dengan petunjuk hubungi pengelola; tombol simpan semu dihapus. Login/logout tetap memakai Fortify, sesi dan CSRF. Root mengarahkan tamu ke Login dan akun masuk ke Home.
- Halaman 403/404/405/419/500 memiliki pesan Indonesia dan tautan pemulihan. Script tema mendukung halaman tanpa sidebar. Input username non-string tidak lagi menyebabkan error pada pembentukan kunci throttle; batas login tetap dipertahankan.
- Route resource dibatasi pada handler yang tersedia, menghilangkan tautan endpoint semu. Tidak menambah pendaftaran, reset password, 2FA atau kemampuan edit profil baru.
- **54 tes lulus, 734 assertion**, termasuk 28 layar web, state kosong, role pengelola/staf, isolasi perusahaan, login salah/throttle, CSRF, profil sendiri dan logout. Pint dan kompilasi Blade diperiksa; cache view dibersihkan.
- Browser in-app terisolasi: navigasi 10 modul desktop 1440 px light dan 390 px dark, menu aktif, menu sempit menutup, tidak ada overflow halaman pada skenario tersebut. Login salah/berhasil, Profil, logout dan redirect halaman terlindungi diperiksa dengan sesi nyata fixture. Tidak ada warning/error konsol pada pemeriksaan akhir. Data produksi tidak diubah.
- [Laporan review dan screenshot](web-ui-qa-review.md). QA lokal bukan persetujuan visual pengguna. Berikutnya review web dan perbaikan temuan; mobile tetap menunggu hasil review.


### Review lanjutan W12 — 20 Oktober 2026

- Memperbaiki pemulihan fokus menu akun saat Escape, region tabel yang dapat difokuskan/digeser keyboard, serta outline select/textarea pada kedua tema.
- Browser GET terisolasi memverifikasi Enter/Escape menu akun dan navigasi, pembatalan dialog hapus tanpa mutasi, scroll tabel ArrowRight, serta fokus form light/dark pada 390×844. Tidak ada overflow halaman atau warning/error konsol dalam skenario ini.
- 54 tes / 734 assertion lulus; kompilasi Blade dan pemeriksaan whitespace berhasil. [Bukti dan batas review](web-ui-qa-review.md#review-lanjutan-w12--20-oktober-2026).
- Review teknis lokal selesai. Persetujuan visual pengguna belum dicatat; M0 mobile tetap menunggu review web sesuai instruksi proyek.


### Transisi ke mobile — 20 Oktober 2026

Pengguna mengarahkan “Oke lanjutkan nextnya” setelah hasil review teknis W12. Pengerjaan mobile dimulai: fondasi tema/Login dan audit awal, dengan batas pemeriksaan web yang sudah tercatat tetap berlaku. Ini mencatat izin melanjutkan tahap, bukan mengklaim persetujuan atas fitur bisnis yang belum tersedia. [Status dan urutan mobile](mobile-ui-timeline.md).
