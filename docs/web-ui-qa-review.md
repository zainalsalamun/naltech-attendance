# Review UI web Workspace

Tanggal: 20 Oktober 2026. Status: implementasi dan QA lokal tersedia; review visual pengguna belum diberikan.

## Hasil pemeriksaan

- 54 tes lulus / 734 assertion pada SQLite terisolasi. Cakupan lintas halaman meliputi 28 layar daftar, form, detail dan profil; state kosong, teks panjang, role, scope perusahaan dan alur autentikasi.
- Browser in-app: 10 menu desktop 1440 px light serta layar 390×844 dark. Menu aktif benar, tema bertahan dan menu sempit menutup setelah navigasi. Tidak ditemukan overflow halaman; tabel lebar menggunakan scroll kontainer. Metadata: [browser-checks.json](screenshots/qa-web/browser-checks.json).
- Login salah menampilkan pesan dan mempertahankan email tanpa mengisi ulang password. Login berhasil, profil sendiri, logout serta redirect /home ke Login setelah keluar diperiksa melalui sesi fixture nyata. CSRF, throttle, input invalid dan batas role juga diuji server.
- Profil hanya membaca informasi yang tersedia. Perubahan profil melalui pengelola perusahaan; tidak ada tombol simpan semu. Pesan error 403/404/405/419/500 memakai gaya bersama. Halaman 419 juga terlihat selama pemeriksaan sesi QA; pemulihan lewat tautan Login berhasil.
- Pint, kompilasi Blade dan pemeriksaan whitespace dijalankan. Cache view dibersihkan setelah pemeriksaan. Pint berhasil dengan peringatan deprecation dari dependency bawaan pada versi PHP lokal.

## Bukti layar sempit lintas modul

| Modul | Screenshot dark 390 px |
| --- | --- |
| Ringkasan | [Lihat](screenshots/qa-web/01-mobile.png) |
| Kehadiran | [Lihat](screenshots/qa-web/02-mobile.png) |
| Karyawan | [Lihat](screenshots/qa-web/03-mobile.png) |
| Pengajuan izin | [Lihat](screenshots/qa-web/04-mobile.png) |
| Lembur | [Lihat](screenshots/qa-web/05-mobile.png) |
| Reimbursement | [Lihat](screenshots/qa-web/06-mobile.png) |
| Jadwal shift | [Lihat](screenshots/qa-web/07-mobile.png) |
| Lokasi kerja | [Lihat](screenshots/qa-web/08-mobile.png) |
| QR absensi | [Lihat](screenshots/qa-web/09-mobile.png) |
| Verifikasi pensiun | [Lihat](screenshots/qa-web/10-mobile.png) |

Bukti desktop light/dark dan form per modul ada pada [timeline](web-ui-timeline.md). Bukti akun: [Login light desktop](screenshots/login-light-desktop.png), [Login dark desktop](screenshots/login-dark-desktop.png), [Login salah](screenshots/login-error-mobile.png), [Profil light desktop](screenshots/profile-light-desktop.png), [Profil dark desktop](screenshots/profile-dark-desktop.png), [Profil dark sempit](screenshots/profile-dark-mobile.png), [Login setelah logout](screenshots/login-after-logout-mobile.png).

## Batas pemeriksaan dan langkah berikutnya

Browser memakai database/file sesi sementara dan data contoh lokal, bukan data produksi. Mutasi modul diuji server; browser lintas modul memakai GET, kecuali login/logout. Browser lain, audit aksesibilitas menyeluruh, uji semua kombinasi tema/viewport per layar, beban besar, perangkat lokasi/kamera, printer dan pengiriman email nyata belum diverifikasi. Pendaftaran/reset password/2FA tidak diubah atau dinyatakan selesai.

Aturan kalender, snapshot shift historis, shift lintas hari dan zona waktu perusahaan tetap mengikuti batas yang tercatat pada timeline. Verifikasi pensiun baru meninjau catatan kehadiran; proses keputusan pensiun bisnis belum tersedia. Absensi wajah tetap tidak tersedia.

Tahap berikutnya adalah review visual web oleh pengguna, perbaikan temuan jika ada, kemudian mobile sesuai urutan proyek. Tidak ada deployment, migrasi database produksi atau push pada pekerjaan ini.


## Review lanjutan W12 — 20 Oktober 2026

Review teknis interaksi menemukan dan memperbaiki tiga kekurangan pada komponen bersama:

1. Escape pada menu akun sebelumnya menutup panel tanpa memulihkan fokus dari isinya. Kini fokus kembali ke summary menu akun.
2. Pembungkus tabel tidak memiliki titik fokus keyboard. Seluruh sembilan tabel Workspace kini memiliki region berlabel dan tabindex, sehingga pengguna dapat menggeser tabel dengan tombol panah.
3. Selector fokus bersama belum mencakup select dan textarea. Keduanya sekarang memiliki outline fokus yang sama dengan input dan tombol, termasuk pada dark mode.

Pemeriksaan browser in-app memakai router GET sementara dengan database SQLite memory baru setiap request, akun fixture dan tanpa mutasi produksi. Pada viewport 390×844: Enter membuka menu akun/navigasi; Escape dari isinya menutup dan mengembalikan fokus. Enter membuka dialog hapus dengan fokus awal Batal; Escape membatalkan dan mengembalikan fokus ke pemicu. Tidak ada penghapusan dijalankan. ArrowRight pada region tabel benar-benar mengubah scrollLeft (35,5 px pada observasi) tanpa overflow halaman. Outline textarea light terukur 2 px, dan screenshot dark diperiksa secara visual. Konsol warning/error kosong dalam skenario ini.

Bukti: [fokus dan scroll tabel light](screenshots/qa-web/keyboard-table-mobile.png), [fokus form dark](screenshots/qa-web/keyboard-form-dark-mobile.png). Kedua screenshot diperiksa; tidak ada kontrol yang tertumpuk pada viewport tersebut. Label zona waktu UTC pada fixture review ini mengikuti konfigurasi aplikasi lokal, bukan perubahan zona waktu perusahaan.

Validasi setelah perbaikan: **54 tes / 734 assertion lulus**, kompilasi Blade berhasil dan diff whitespace bersih. Cache view dibersihkan. Review teknis W12 selesai untuk cakupan tercatat; audit aksesibilitas menyeluruh dan persetujuan visual pengguna tetap belum dinyatakan selesai. Mobile belum dimulai sesuai gerbang review pada instruksi proyek.
