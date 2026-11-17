# Penyesuaian alur cuti dan saldo — 21 Oktober 2026

Fokus: fitur operasional yang sudah ada, dari pengisian → perhitungan → tinjauan → kirim → status/pembatalan. Tidak melanjutkan pengemasan atau uji instalasi pembeli. Tidak ada migrasi/schema baru, seed/transaksi pada database utama, commit, push atau deployment.

## Perubahan web

- Tombol **Tinjau pengajuan** membuka ringkasan jenis, rentang, hari kerja/libur, alasan, lampiran dan dampak saldo. **Kirim pengajuan** baru mengirim form setelah konfirmasi. Kembali mengedit mempertahankan isian.
- Preview menampilkan alokasi, tersedia, cadangan dan terpakai; hari yang diajukan menjadi cadangan saat dikirim, belum menjadi terpakai. Periode preview mengikuti tanggal pilihan. Panel saldo awal dijelaskan sebagai saldo periode yang tertulis di panel.
- Preview lama langsung dibuang saat tanggal/jenis berubah. Request sebelumnya dibatalkan dan respons hanya diterima bila revisi serta pilihan masih cocok.
- Gagal jaringan/perhitungan menahan tindakan, menampilkan pesan dan tombol **Hitung ulang**. Jenis tidak aktif/tidak sah, kalender tidak lengkap, semua tanggal libur dan saldo tidak cukup tidak dianggap siap dikirim.
- Label kalender dan pesan perhitungan dirender sebagai teks, bukan HTML hasil interpolasi.
- Submit ganda ditahan pada UI; validasi/locking server tetap berlaku.
- Detail membedakan saldo terbaru agregat periode dengan data historis pengajuan. Panel saldo menyesuaikan layar sempit.
- Status diberi label “Status pengajuan”, tidak menyebut pending sebagai status akhir. Permohonan pembatalan cuti approved tidak lagi ditawarkan pada modul lembur/reimbursement.
- Pesan permintaan pembatalan menjelaskan pengajuan tetap berlaku dan saldo terpakai belum kembali selama keputusan belum ada.

## Backend

`LeaveBalanceService::preview` menjadi sumber perhitungan bersama endpoint web dan API. Format luar respons lama tetap dipertahankan (web `success/calculation/balance`, API `data`). Metadata periode ditambahkan. Jenis cuti harus aktif dan berasal dari perusahaan pengguna; preview tidak lagi memperlakukan jenis invalid sebagai pengajuan non-saldo.

Preview tidak menulis reservasi atau ledger. Saat submit, server tetap menghitung ulang kalender, mengecek saldo dan overlap, lalu menyimpan secara transaksi seperti sebelumnya. Tidak mengubah kuota, aturan hari penuh, lintas tahun, alur approval atau kebijakan saldo.

## Mobile

- Instruksi memakai rentang tanggal, bukan pengajuan satu tanggal.
- Tanggal disusun vertikal dan ringkasan hari memakai Wrap agar tidak overflow pada layar sempit/teks besar.
- Ada tinjauan sebelum kirim, beserta keterangan bahwa preview belum mengubah saldo.
- Request kalkulasi memiliki nomor revisi; respons lama tidak dapat menimpa hasil terbaru. Data sebelumnya dibersihkan selama menghitung, error dapat dihitung ulang, dan tinjauan ditahan sampai kalkulasi layak.
- Respons perhitungan diperiksa strukturnya dan diabaikan bila sesi berubah. Pesan validasi field server ditampilkan sebagai teks.
- Pesan saldo/periode dan dampak pembatalan diselaraskan dengan web.
- Fixture tes lama yang belum mengikuti parameter start/end date diperbarui; bukan menghapus validasi fitur.

## Bukti pemeriksaan

| Pemeriksaan | Hasil |
|---|---|
| Regresi backend SQLite terisolasi | **141 tes, 1.833 assertions lulus** |
| Regresi Flutter | **85 tes lulus**; setelah perapian teks/periode terakhir, 10 tes alur/approval terkait diulang dan lulus |
| Tes baru backend | Preview web/API sama, periode mengikuti tanggal pilihan, tidak menulis ledger, jenis nonaktif ditolak |
| Tes baru widget | Respons lama diabaikan; kembali mengedit tidak mengirim; konfirmasi mengirim sekali; error menahan review sampai hitung ulang |
| Browser Chrome nyata headless | **14 checks lulus**, fixture disposable; 1440×1000 dan 390×844, light/dark |
| Analisis Dart | 0 error proyek; [ringkasan](leave-flow-mobile-analysis.json), hint deprecated tema tetap dicatat |
| Blade | Kompilasi dan parsing hasil PHP di cache disposable lulus; cache dibersihkan |

[Laporan browser](leave-flow-browser-qa.json), [ringkasan light sempit](screenshots/leave-flow-adjustment/review-light-mobile.png), [ringkasan dark desktop](screenshots/leave-flow-adjustment/review-dark-desktop.png). Screenshot telah diperiksa visual.

Uji browser mencakup saldo 7 tersedia/2 cadangan/1 terpakai, 2 hari kerja dan 1 libur dilewati, label kalender literal, kegagalan jaringan yang membatalkan preview lama, dialog tinjauan, kembali mengedit dan pengiriman form sebenarnya. Transaksi hanya pada fixture SQLite.

## Batasan

Tidak mengklaim pengujian native Android/iOS terbaru, galeri, GPS/kamera atau regresi MySQL ulang pada irisan ini. Pengujian konkurensi MySQL sebelumnya tetap dirujuk pada [laporan review](hris-review-fixes-and-mysql-testing.md), bukan dihitung sebagai hasil baru. Masih ada hint deprecated Flutter dari API tema yang sudah digunakan; bukan error kompilasi.

Saldo detail adalah total terbaru pada periode, bukan saldo yang dibekukan pada saat pengajuan. Saldo saat submit dapat berubah setelah preview; server tetap menjadi penentu. Perbaikan tidak menambahkan payroll, prorata, carry-over atau fitur pengemasan.
