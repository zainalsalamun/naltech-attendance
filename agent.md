# Panduan Pengerjaan Agen

Terakhir diperbarui: 19 Oktober 2026.

## Konteks

Monorepo Naltech Attendance berisi `apps/backend` (Laravel) dan `apps/mobile` (Flutter). Prioritas saat ini adalah **implementasi UI Dashboard web**, dilanjutkan mobile setelah review Dashboard. Dokumen ini merupakan panduan proyek; instruksi terbaru pengguna tetap menjadi acuan utama.

Baca sebelum bekerja:

1. [Keputusan desain](design.md).
2. [Checklist Dashboard](docs/dashboard-ui-implementation-checklist.md).
3. [Panduan teknis](skills.md).
4. [Checklist mobile](docs/mobile-ui-implementation-checklist.md) saat tahap mobile dimulai.

## Alur kerja

- Periksa status Git, instruksi repositori yang berlaku, kode terkait, dan baseline sebelum mengedit.
- Gunakan `rtk` sebagai awalan perintah shell sesuai instruksi workspace; gunakan `rtk proxy` untuk perintah yang tidak didukung langsung.
- Kerjakan per irisan: tema/layout, data ringkasan/tabel, panel pendukung, lalu pemeriksaan dan review.
- Gunakan struktur dan alur Laravel/Flutter yang ada; perubahan framework atau dependency memerlukan kebutuhan nyata dari pekerjaan.
- Pertahankan perubahan lokal pengguna dan perubahan di luar lingkup. Jangan melakukan reset atau menimpa perubahan tersebut.
- Perbarui checklist, tanggal, dan log setelah pekerjaan benar-benar selesai serta diperiksa. Catat hambatan dan pemeriksaan yang belum dijalankan.
- Laporkan hasil, validasi, dan batasan secara ringkas dalam bahasa Indonesia.

## Batasan implementasi

- Pertahankan isolasi perusahaan berdasarkan akun aktif dan otorisasi server; jangan memakai perusahaan pertama atau ID perusahaan tetap sebagai fallback.
- Pertahankan pemeriksaan radius, duplikasi absensi, konfigurasi metode, dan validasi server. UI tidak menggantikan pemeriksaan server.
- Absensi wajah tetap tidak tersedia sampai verifikasi server yang andal dikonfigurasi.
- Jangan mencetak token, kredensial, header autentikasi, atau isi konfigurasi rahasia ke log.
- Jangan mengedit `vendor`, `node_modules`, atau file hasil generator sebagai solusi permanen.
- Jangan menyimpan `.env`, konfigurasi Firebase lokal, backup database, atau dependency terpasang ke Git; periksa aturan ignore sebelum commit.
- Revisi UI tidak mencakup migrasi destruktif, rewrite riwayat Git, deployment, publikasi portfolio, atau push tanpa instruksi terkait.

## Definisi selesai

UI mengikuti `design.md`, data berasal dari perusahaan aktif, interaksi berfungsi, state kosong/error jelas, tema dan responsivitas diperiksa, serta checklist memiliki bukti pemeriksaan. Jangan menyatakan aplikasi sudah diuji pada perangkat/browser yang belum digunakan.

## Penemuan instruksi

Nama `agent.md` dipertahankan sesuai permintaan pengguna. Untuk alat yang menemukan instruksi melalui `AGENTS.md`, repositori menyediakan file pengarah terpisah. `skills.md` adalah panduan proyek, bukan instalasi skill Codex.
