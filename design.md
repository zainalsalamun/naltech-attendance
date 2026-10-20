# Panduan Desain Naltech Attendance

Terakhir diperbarui: 19 Oktober 2026.

## Arah dan urutan implementasi

Dashboard web menggunakan desain **Workspace** yang disetujui pengguna. Implementasi dan review Dashboard didahulukan, kemudian mobile. Daily Brief tidak digunakan. Preview yang disetujui adalah acuan visual, bukan bukti implementasi atau integrasi data telah selesai.

Tracking terperinci:

- [Dashboard web](docs/dashboard-ui-implementation-checklist.md)
- [Mobile](docs/mobile-ui-implementation-checklist.md)

## Identitas visual

Gunakan tampilan kerja yang tenang dan padat informasi: panel putih pada light mode, navy pada dark mode, aksen biru, ikon garis tanpa emoji. Hindari gradient dekoratif, bayangan berlebihan, kartu berulang tanpa fungsi, dan ilustrasi yang mengalihkan perhatian dari absensi.

| Token semantik | Light | Dark |
| --- | --- | --- |
| Latar | `#F5F6F8` | `#141B28` |
| Panel | `#FFFFFF` | `#1D2635` |
| Teks utama | `#202C40` | `#E8EDF6` |
| Teks sekunder | `#626F83` | `#A1AEC3` |
| Garis | `#E5E9EF` | `#344054` |
| Aksen | `#315BCE` | `#A6BCFF` |
| Teks pada aksen | `#FFFFFF` | `#162747` |
| Aksen lembut | `#EDF2FF` | `#283A5A` |
| Sukses / hadir | `#34725B` | `#9ED2B9` |
| Terlambat | `#B45309` | `#FBBF24` |
| Navy merek | `#192A48` | `#192A48` |

Teks sekunder light direvisi ke `#626F83` setelah pemeriksaan kontras agar memenuhi 4,5:1 pada latar halaman dan kontrol lembut.

Warna Terlambat sebelumnya `#A66620` telah direvisi. Definisikan token error, nonaktif, dan fokus saat implementasi serta periksa kontrasnya. Gunakan teks/ikon bersama warna status.

## Dashboard Workspace

- Sidebar navy: merek, Ringkasan, Kehadiran, Karyawan, Pengajuan, Rekap harian, dan Lokasi kerja sesuai route serta hak akses yang tersedia.
- Header: perusahaan aktif, pilihan Light/Dark, identitas pengguna dan akses akun.
- Konten: breadcrumb, judul, tanggal, ringkasan hadir/tepat waktu/terlambat/belum check-in.
- Area utama: tabel karyawan dengan pencarian, filter, jam masuk/pulang, status, dan detail.
- Panel pendukung: pengajuan menunggu serta informasi kantor, radius, dan metode absensi.
- Pada layar sempit, susun ulang panel dan navigasi; pertahankan semua aksi penting.

## Mobile — tahap setelah Dashboard

Pertahankan navigasi Beranda, Riwayat, Profil. Beranda memprioritaskan jadwal, status lokasi, jam masuk/pulang, dan satu aksi absensi sesuai state. Sediakan pintasan Izin dan Reimbursement. Selaraskan login, QR, formulir, hasil absensi, dan pengaturan dengan tema yang sama.

## Komponen dan perilaku

- Gunakan tipografi sans serif konsisten, hierarki jelas, serta angka jam/jumlah yang mudah dibandingkan.
- Gunakan ikon dari satu keluarga; ikon dekoratif tidak menggantikan label aksi.
- Gunakan token untuk jarak, radius, warna, dan ukuran ikon; detail skalanya ditetapkan saat fondasi implementasi.
- Pastikan kontrol dapat digunakan dengan keyboard, memiliki fokus terlihat, dan area sentuh yang memadai.
- Simpan preferensi tema dan pulihkan ketika aplikasi/halaman dibuka kembali.
- Bedakan loading, kosong, belum absen, gagal, dan tidak berwenang. Jangan mengubah kegagalan memuat menjadi angka nol.
- Semua nama, jumlah, tanggal, radius, metode, dan jadwal produksi berasal dari data aktif. Data ilustrasi tidak boleh menjadi fallback produksi.
- Gunakan bahasa Indonesia secara konsisten; zona waktu dan tanggal mengikuti aturan perusahaan yang diverifikasi.
- Strip palet/hex pada preview adalah alat review, bukan konten Dashboard produksi.

## Review

Periksa light/dark, desktop/layar sempit, teks panjang, data kosong, filter, dan state error. Simpan screenshot dan catat perbedaan dari rancangan. Tugas dinyatakan selesai setelah fungsi terkait diperiksa dan hasil visual direview.

## Perluasan lingkup web — 19 Oktober 2026

Sesuai instruksi pengguna, keselarasan Workspace mencakup seluruh halaman web, bukan hanya Home. Ikuti [timeline web menyeluruh](docs/web-ui-timeline.md): Kehadiran → Karyawan → Izin → Lembur → Reimbursement → Shift → Lokasi → QR → Verifikasi → Profil/Login → QA dan review web. Mobile menunggu review web menyeluruh. Checklist Dashboard tetap khusus Home/Ringkasan; statusnya tidak mewakili modul lain.
