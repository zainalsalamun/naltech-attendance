# Timeline UI mobile Workspace

Diperbarui: 20 Oktober 2026. Dimulai atas arahan pengguna setelah review teknis web W12. Milestone berurutan, tanpa tenggat kalender baru.

| Tahap | Lingkup | Status | Berikutnya / batas |
| --- | --- | --- | --- |
| M0 | Audit SDK, layar dan kontrak data | Berjalan | Audit kode tersedia; baseline visual perangkat belum |
| M1 | Fondasi tema dan Login | Implementasi awal tersedia | 6 tes lulus; review visual/perangkat dan sesi API nyata belum |
| M2 | Navigasi dan Beranda | Berikutnya | Beranda/Riwayat/Profil, data pengguna/perusahaan, jadwal, status lokasi, satu aksi absensi sesuai state |
| M3 | Absensi manual/QR dan hasil | Belum dimulai | Respons server, izin perangkat, submit tunggal, refresh Beranda; Face tetap tidak tersedia |
| M4 | Riwayat | Belum dimulai | Tanggal, data kosong/error, jam asli, batas aturan shift |
| M5 | Profil, pengaturan dan sesi | Belum dimulai | Tema di dalam akun, edit sesuai API, logout, splash/sesi kedaluwarsa |
| M6 | Izin dan Reimbursement | Belum dimulai | Kontrak API, form/lampiran/status dan feedback |
| M7 | QA mobile menyeluruh | Belum dimulai | Light/dark, perangkat, keyboard, lokasi/kamera, offline dan sesi |

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

Home saat ini masih memiliki warna literal, beberapa aksi absensi dan parsing radius yang perlu penanganan data invalid saat M2. Tidak menambah fallback koordinat/radius produksi. Jadwal perusahaan tidak boleh dilabeli shift pribadi; zona waktu/kalender/riwayat shift yang belum tersedia perlu dinyatakan jelas.

## Validasi M1

Detail hasil dan batas pada [checklist mobile](mobile-ui-implementation-checklist.md#log-irisan-m1--fondasi-tema-dan-login--20-oktober-2026). Belum menjalankan login terhadap data produksi, memasang aplikasi pada iPhone, atau menguji kamera/lokasi. Tidak ada deployment atau publikasi store.
