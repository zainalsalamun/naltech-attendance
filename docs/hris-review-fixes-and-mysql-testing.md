# Perbaikan review H5/H6 dan pengujian MySQL

Tanggal: 21 Oktober 2026, Asia/Jakarta. Dilaksanakan atas instruksi pengguna untuk memperbaiki empat temuan dan menguji saldo/approval pada MySQL terpisah. **Tidak menjalankan commit, migrasi, seed atau perubahan kredensial pada database utama.**

## Perubahan

1. **Setup admin tanpa password bawaan**
   - Konfigurasi `initial_setup.admin_password` membaca `SETUP_ADMIN_PASSWORD`.
   - Pembuatan admin baru ditolak sebelum perusahaan/akun ditulis bila password kosong atau kurang dari 12 karakter. Tidak mencetak password ke log.
   - Setup ulang mempertahankan password admin yang sudah ada. Akun lama tidak dirotasi otomatis; operator harus menggantinya lewat pengelolaan akun.
   - `.env.example` hanya berisi placeholder kosong. Seeder demo juga memerlukan konfigurasi admin saat pertama kali membuat fondasi; akun staf contoh tetap khusus demo terisolasi.

2. **Persentase kehadiran konsisten**
   - Pembilang: hadir pada hari kerja karyawan dalam periode. Penyebut: jumlah hari kerja seluruh karyawan yang masuk cakupan periode.
   - Kehadiran pada libur tetap tampil pada total hadir dan jam kerja, tetapi tidak menaikkan persentase hari kerja.
   - UI menjelaskan pembilang/penyebut. Tidak menggunakan pembatasan angka 100% untuk menutupi kesalahan perhitungan.

3. **Riwayat karyawan nonaktif/arsip dipertahankan**
   - Status aktif saat ini tidak menghapus karyawan dari periode lama.
   - Roster dan hari terjadwal dibatasi `joined_on` / `left_on` yang tercatat; tanggal keluar inklusif.
   - Jika tanggal kepegawaian lama kosong, cakupan historis belum dapat dipastikan. UI menyatakan keterbatasan tersebut dan bahwa laporan bukan penetapan alpa/payroll final. Jeda kerja/reaktivasi berulang belum dapat direkonstruksi hanya dari dua tanggal tersebut.
   - Hari izin yang tumpang tindih dihitung sebagai himpunan tanggal agar tidak terhitung ganda.

4. **Batas rekap 90 hari inklusif**
   - Halaman dan ekspor memakai validasi bersama, sebelum pembuatan daftar tanggal/query rekap.
   - Rentang terbalik atau 91 hari ditolak 422 dengan pesan jelas; 90 hari diterima.
   - Tanggal default mengikuti zona waktu perusahaan.

## Pengujian aktual

| Pemeriksaan | Hasil |
|---|---|
| Regresi backend SQLite memory | **140 tes, 1.819 assertions lulus** |
| Suite MySQL terpisah | **18 tes, 194 assertions lulus**, tanpa error/failure |
| MySQL aktual | **9.3.0, InnoDB**; tidak mengklaim sudah diuji pada MySQL 8/MariaDB |
| Browser Chrome nyata headless | **10 checks lulus**, light/dark, desktop 1440×1000 dan sempit 390×844 |
| Blade | Kompilasi dan parsing PHP hasil kompilasi di cache disposable lulus; cache dibersihkan |

[Bukti hasil](hris-review-fixes-mysql-results.json), [browser](recap-review-browser-qa.json), [light desktop](screenshots/recap-review-fixes/light-desktop.png), [dark sempit](screenshots/recap-review-fixes/dark-mobile.png). Screenshot diperiksa visual.

MySQL memakai schema acak `naltech_qa_*` dan akun terpisah yang hanya memiliki hak pada schema tersebut. Kredensial temporary file 0600, folder 0700, tidak masuk Git. Suite MySQL dijalankan terpisah dari suite SQLite default.

### Skenario multiproses

Dua worker PHP terpisah menunggu barrier. Koneksi ketiga menahan row lock perusahaan untuk memastikan keduanya bersaing sebelum lock dilepas.

- Dua submit pada tanggal berbeda bersaing untuk saldo satu hari: satu sukses, satu 422; hanya satu pengajuan/reservasi; saldo tidak negatif.
- Dua approval versi sama: satu sukses, satu 409; pemakaian saldo (`use`) hanya sekali.
- Dua keputusan persetujuan pembatalan versi sama: satu sukses, satu 409; pengembalian (`restore_used`) hanya sekali dan invariant saldo tetap benar.

Tes fungsional MySQL juga memakai skenario saldo, seeder dan rekap yang sama dengan suite dasar. Ini bukti skenario yang disebutkan, bukan benchmark beban atau jaminan untuk seluruh kemungkinan race.

### Temuan tambahan saat MySQL

Rollback migration menemukan InnoDB memakai indeks unik komposit untuk mendukung foreign key. Penghapusan indeks unik kemudian gagal. Jalur `down()` migrasi unique daily attendance dan lifecycle kini mempertahankan indeks nonunik pendukung sebelum menghapus indeks unik. Jalur maju tidak diubah dan database utama tidak di-rollback.

Percobaan awal juga memperbaiki fixture guarded model serta reset skema antar metode tes. Hasil di tabel adalah rerun akhir setelah perbaikan, bukan hasil percobaan gagal.

## Menjalankan ulang

Lihat [panduan MySQL QA](../tools/mysql-qa/README.md). Perintah prepare membuat database/akun baru, suite menggunakan marker dan konfigurasi QA tersebut, cleanup menghapus hanya database/akun QA dan file kredensial. Jangan memakai koneksi utama sebagai database suite.

Seeder baru memerlukan `SETUP_ADMIN_PASSWORD` unik minimal 12 karakter pada environment yang disiapkan operator. Jangan meletakkan nilai sebenarnya pada dokumentasi, command yang tercatat atau Git. Password contoh akun demo tidak boleh digunakan untuk operasional.

## Status kesiapan

Empat temuan review telah diperbaiki dan diverifikasi sesuai cakupan di atas. Klaim “semua 100% siap produksi” diganti menjadi implementasi tersedia dengan cakupan pengujian dan batas yang eksplisit. Instalasi pembeli dari checkout bersih, audit dependency/lisensi/secret menyeluruh, pengujian mobile terbaru, iOS/release dan reliabilitas GPS/kamera belum menjadi hasil pekerjaan ini. Kalender/kuota bawaan seeder tetap perlu disesuaikan dan disetujui perusahaan pembeli.

`git diff --check` keseluruhan masih menemukan dua whitespace lama pada view edit absensi di luar perubahan irisan ini; tidak menimpa perubahan tersebut. Tidak ada commit/push/deployment.
