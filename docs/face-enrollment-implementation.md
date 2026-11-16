# Pendaftaran dan prototipe verifikasi wajah

Tanggal: 20 Oktober 2026. Urutan: pendaftaran → matching → anti-spoofing → integrasi absensi → penyelesaian UI.

| Tahap | Hasil aktual |
| --- | --- |
| 1. Pendaftaran | API sendiri, kamera/form mobile, peninjauan HR/admin web, penggantian, pencabutan, event audit dan retensi tersedia; diuji lokal |
| 2. Matching | YuNet + SFace berjalan dengan model nyata melalui HTTP Laravel; preview opt-in, bukan proof absensi |
| 3. Anti-spoofing | MiniFASNet/NCNN dan MN3 berjalan sebagai eksperimen; keduanya gagal menolak tiga sampel spoof awal, integrasi tetap ditahan. [Hasil MN3](face-mn3-poc.md) |
| 4. Integrasi Face | Tetap diblokir. Hasil cocok/diagnostik eksperimental tidak mencatat kehadiran |
| 5. UI lengkap | UI pendaftaran/peninjauan diterapkan; masuk/pulang wajah dan hasil absensi menunggu tahap 3–4 tervalidasi |

## Alur

Karyawan membuka **Profil → Pendaftaran wajah**, mengambil satu foto dengan kamera depan, memeriksa preview, menyetujui penggunaan foto lalu mengajukan ke HR/admin. ML Kit hanya berjalan setelah foto diambil, tanpa stream deteksi/pencocokan kontinu. Tidak ada pilihan galeri pada alur baru. Status/error berasal dari API; kegagalan tidak dianggap sukses.

HR/admin membuka **Pendaftaran wajah** pada Workspace. Foto disajikan melalui route yang memeriksa login, role dan perusahaan, bukan URL storage publik. HR/admin wajib mengonfirmasi pemeriksaan identitas; pengaju tidak dapat meninjau dirinya sendiri. Supervisor tidak mendapat akses ke referensi biometrik. Jalur lama untuk menaikkan role sendiri atau mereset/menghapus akun HR/admin melalui CRUD karyawan ikut dibatasi.

Satu pengajuan pending diizinkan per akun. Referensi lama tetap approved selama pengganti pending, lalu menjadi superseded ketika pengganti disetujui. Foto ditolak/dicabut/digantikan dibersihkan; metadata/event perubahan disimpan. Karyawan dapat mencabut pendaftaran sendiri. Foto profil dan embedding lama tidak menjadi referensi tepercaya. `/api/update-profile` lama mengembalikan HTTP 410; `face_embedding` tidak lagi diserialisasi pada respons pengguna.

Persetujuan ini adalah pemeriksaan manusia, bukan liveness atau pemeriksaan identitas otomatis. HR harus memeriksa orang yang benar; centang formulir bukan bukti teknis bahwa selfie hidup.

## Pemasangan dan data

Migrasi baru `2026_10_20_000001_create_face_enrollments_table.php` membuat `face_enrollments` dan `face_enrollment_events`. **Migrasi hanya dijalankan pada SQLite QA terisolasi dan database tes dalam memori; database utama tidak diubah.** Setelah operator memilih lingkungan dan meninjau migrasi, jalankan dari backend:

```sh
rtk proxy php artisan migrate --path=database/migrations/2026_10_20_000001_create_face_enrollments_table.php
```

Jika tabel belum tersedia, middleware mengembalikan HTTP 503 sebelum route binding mengakses tabel. Tidak ada backfill otomatis dari embedding/foto profil lama.

Disk `face_private` memakai root `storage/app/private/face-enrollments`; jangan membuat symlink ke public atau membukanya lewat web server. Privat bukan enkripsi biometrik tingkat aplikasi; kontrol filesystem, backup, retensi dan akses operator masih perlu ditetapkan sebelum operasional.

Command berikut mengakhiri pending lebih dari 30 hari, mencoba ulang penghapusan foto nonaktif dan menghapus orphan lebih dari satu hari. Operator perlu menjadwalkannya; tidak ada cron/automation komputer pengguna dibuat. Penghapusan akun melalui CRUD menghapus enrollment terkait dan membersihkan file setelah transaksi berhasil. Jika filesystem gagal, maintenance diperlukan.

```sh
rtk proxy php artisan face:purge-photos
```

## API

Semua endpoint memerlukan Sanctum, perusahaan dan schema siap. Payload tidak dapat memilih akun/perusahaan, status persetujuan atau reference orang lain.

| Endpoint | Tujuan |
| --- | --- |
| GET `/api/face-enrollments` | Sepuluh pengajuan terbaru beserta referensi aktif, tanpa path/embedding |
| POST `/api/face-enrollments` | Multipart `photo`, `consent=1`; JPG/PNG maksimal 2 MB, dimensi 320–4096 |
| DELETE `/api/face-enrollments/{id}` | Cabut pending/approved sendiri |
| POST `/api/face-matching-preview` | Preview matching terhadap reference approved sendiri, default nonaktif |

Preview selalu menghasilkan `attendance_eligible=false`, tanpa score mentah ke mobile. Timeout, respons provider salah, reference dicabut/berubah dan foto tidak valid tidak dianggap sukses. Setup ada di [README verifier](../services/face-verifier/README.md).

## Validasi

- Backend: **74 tes, 962 assertions**, SQLite dalam memori. Batas akun/perusahaan/role, larangan persetujuan sendiri, consent/upload, replacement/revoke, retensi, provider tidak tepercaya, role escalation dan schema belum diterapkan tercakup.
- Mobile: **57 tes**; consent, submit tunggal, pembersihan foto pada alur sukses, status tidak dikenal, proof palsu, dark/teks besar. Analisis statis berkas yang berubah lulus.
- [Browser/HTTP enrollment](face-workflow-browser-qa.json): **8** pemeriksaan nyata pada DB/storage disposable dengan gambar sintetis, termasuk approval session/CSRF dan akses foto setelah revoke.
- [Model nyata](face-verifier-model-smoke.json): **8** smoke checks memakai sampel publik OpenCV, bukan dataset akurasi orang berbeda.
- [HTTP Laravel → verifier](face-verifier-http-smoke.json): **13** pemeriksaan, termasuk matching, diagnostik eksperimental dan Face tetap menolak absensi.
- Service Python: **5** tes protokol dengan verifier fixture; terpisah dari inferensi model nyata.
- [NCNN eksperimental](face-liveness-experimental-smoke.json): model nyata dimuat dan diagnostik selesai; bukan validasi liveness operasional.

| Bukti visual | Light | Dark |
| --- | --- | --- |
| HR desktop | [light](screenshots/face-enrollment/review-light-desktop.png) | [dark](screenshots/face-enrollment/review-dark-desktop.png) |
| HR mobile | [light](screenshots/face-enrollment/review-light-mobile.png) | [dark](screenshots/face-enrollment/review-dark-mobile.png) |
| Aksi HR setelah geser tabel | [light](screenshots/face-enrollment/review-light-mobile-actions.png) | [dark](screenshots/face-enrollment/review-dark-mobile-actions.png) |
| Mobile — render widget | [light](screenshots/face-enrollment/mobile-enrollment-light-widget.png) | [dark](screenshots/face-enrollment/mobile-enrollment-dark-widget.png) |

Gambar web memakai ilustrasi sintetis. Gambar mobile adalah render widget/state fixture dengan Arial sebagai substitusi font QA, bukan screenshot HP. Tidak ada foto pengguna pada bukti repository. Kamera pendaftaran baru belum diuji native karena tidak ada HP/emulator terhubung saat diperiksa. [Uji kamera sebelumnya](face-capture-device-results.md) memakai prototipe terpisah dan bukan bukti pendaftaran baru sudah lulus native.

## Sisa pekerjaan sesuai urutan

Per 21 Oktober, [alat dan protokol evaluasi liveness](face-liveness-validation.md)
tersedia; APK QA dipasang/dibuka pada HP. Ini belum membuktikan kamera baru atau
presentasi spoof lulus. Suite Python kini 11 tes; jumlah 5 di atas adalah baseline
sebelum penambahan evaluator. Empat capture pada HP telah dievaluasi; seluruh
label diterima model eksperimen. Operator mengonfirmasi media fisik sesuai
kategori: jalur ini gagal menolak tiga sampel spoof awal dan tidak boleh
diintegrasikan ke absensi. Kalibrasi belum selesai. Foto sementara
telah dihapus; laporan agregat dan batasannya ada pada protokol tersebut.

Diperlukan live-camera positive control, foto cetak, layar/video replay, identitas berbeda, perangkat/pencahayaan beragam, kalibrasi threshold dan audit hak model. Output NCNN tetap `experimental`; satu score tinggi tidak boleh dijadikan green light produksi.

Sesudah liveness andal tersedia, buat sesi proof sekali pakai yang terikat akun/perusahaan/aksi/enrollment dan dikonsumsi atomik bersama pemeriksaan radius, tanggal dan duplikasi. Baru sambungkan masuk/pulang wajah serta UI hasil. Jangan melompat dengan boolean/embedding Flutter. Cloud opsional belum dikonfigurasi/dipanggil; tidak ada provisioning atau deploy dibuat.
