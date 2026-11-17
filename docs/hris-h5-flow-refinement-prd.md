# PRD — Penyempurnaan koreksi absensi dan rekap kehadiran H5

Tanggal: 29 Oktober 2026, Asia/Jakarta.  
Status: **Rencana pengerjaan, belum diimplementasikan atau diuji melalui dokumen ini.**  
Prioritas: Dashboard web. Mobile menunggu review hasil Dashboard. Commit, push, deployment, dan pengemasan ditunda.

## 1. Tujuan

HR dapat mengoreksi absensi dengan jejak perubahan yang jelas dan menelusuri angka rekap sampai data sumber. Keputusan izin/cuti, koreksi jam, kalender kerja, dan ekspor harus menghasilkan perhitungan yang konsisten untuk cakupan dan waktu pengambilan data yang sama.

Ini penyempurnaan H5 yang sudah tersedia, bukan pembangunan ulang. Audit implementasi dan tes terlebih dahulu; pertahankan perilaku yang sudah benar.

## 2. Acuan dan kondisi awal

- Baca `agent.md`, `design.md`, `skills.md`, dan instruksi AGENTS yang berlaku.
- Acuan bisnis: `hris-h5-attendance-corrections-and-recap.md`, `hris-review-fixes-and-mysql-testing.md`, `hris-h4-leave-balances-and-date-range.md`, `hris-approval-ui-verification-results.md`, dan `hris-core-timeline.md` di folder docs.
- `AttendanceController::update()` sudah menggunakan transaksi, alasan koreksi, konversi zona waktu, dan snapshot audit; periksa ulang detailnya sebelum perubahan.
- `AttendanceCorrectionController` sudah memiliki daftar audit berpagination dan filter nama/tanggal.
- `AttendanceRecapController` menghitung rekap serta CSV dengan validasi periode bersama, maksimal 90 hari inklusif. Logika masih berada di controller; jangan mengasumsikan service rekap terpisah sudah ada.
- Pada pemeriksaan awal, query izin rekap hanya memilih `status = approved`. Ini kandidat ketidaksesuaian dengan aturan pembatalan cuti; buktikan melalui tes sebelum memperbaiki.
- Riwayat karyawan nonaktif/arsip, batas joined_on/left_on, deduplikasi tanggal izin, dan persentase hadir pada hari terjadwal sudah pernah diperbaiki. Pertahankan regresinya.

## 3. Batas lingkup

Termasuk: koreksi absensi web, audit, rekap periode, tautan detail, filter, ekspor CSV, serta penyesuaian Ringkasan hanya untuk aturan bersama yang terbukti tidak konsisten. Gunakan pola Workspace dan token tema yang ada.

Di luar lingkup: payroll, sanksi otomatis, approval koreksi baru bertingkat, notifikasi eksternal, face/liveness, desain ulang mobile, rekonstruksi roster historis menyeluruh, pengemasan komersial. Jangan menambah framework/dependency tanpa kebutuhan nyata.

## 4. Aturan bisnis dan kriteria penerimaan

### A. Koreksi absensi dan integritas audit

1. Hanya pengelola yang berwenang dalam perusahaan aktif dapat mengoreksi; UI dan endpoint menegakkan aturan yang sama. Supervisor mengikuti cakupan bawahan yang berlaku, bukan otomatis seluruh perusahaan.
2. Tampilkan tanggal kerja asal, zona waktu absensi, tanggal/jam masuk, dan tanggal/jam pulang secara eksplisit. Alasan wajib setelah trim, dengan batas panjang yang tervalidasi server.
3. Preview perubahan sebelum simpan menampilkan nilai sebelum/sesudah dan alasan; kembali mengedit mempertahankan isian tanpa mutasi. Konfirmasi mencegah pengiriman berulang.
4. Validasi urutan waktu dan aturan rentang sesi yang sudah ada. Checkout lintas tengah malam sah sesuai aturan shift. Jangan mengubah tanggal kerja asal hanya karena checkout jatuh besok.
5. Update absensi dan audit tersimpan atomik. Audit mencatat aktor, alasan, waktu, serta before/after field relevan. Tampilan waktu menyebut zona waktunya dan snapshot tidak diubah oleh edit berikutnya.
6. Submit tanpa perubahan efektif tidak menghasilkan event koreksi palsu; tampilkan pesan tidak ada perubahan. Kegagalan validasi/transaksi tidak mengubah absensi maupun audit.
7. Dua editor dengan data awal sama tidak boleh saling menimpa diam-diam. Gunakan mekanisme versi yang tersedia; bila belum ada, pilih concurrency token/versi yang andal dan validasi di dalam transaksi. Konflik memberi pesan muat ulang, mempertahankan input untuk ditinjau, serta tidak menambah audit. Dokumentasikan strategi dan kebutuhan migrasi aditif bila ada.
8. Preview memakai textContent/escaping yang aman. Tidak ada interpolasi HTML mentah dari nama, alasan, atau snapshot.

### B. Definisi status harian dan sumber angka

1. Petakan aturan yang digunakan Ringkasan, daftar absensi, detail, rekap, dan CSV. Satu kategori dengan arti sama harus memakai aturan sama; angka berbeda karena periode/populasi berbeda harus dijelaskan.
2. Hadir tepat waktu/terlambat/perlu tinjau mengikuti snapshot jadwal dan zona waktu absensi yang valid. Perubahan shift saat ini tidak boleh menulis ulang sejarah absensi yang memiliki snapshot.
3. Jangan menyimpulkan jadwal historis yang tidak tersedia. Pertahankan penanda keterbatasan roster/kalender dan data legacy; jangan menyajikannya sebagai hasil payroll final.
4. Kehadiran pada hari libur tetap dapat tampil sebagai hadir dan jam kerja, tetapi tidak menaikkan pembilang persentase hadir hari terjadwal.
5. Bedakan belum hadir pada hari berjalan, tanggal mendatang, dan ketidakhadiran pada hari kerja yang sudah selesai. Tanggal mendatang tidak dihitung alpa. Untuk hari berjalan, gunakan akhir jadwal yang andal bila tersedia; jika tidak tersedia tampilkan belum terkonfirmasi, bukan alpa final.
6. Karyawan nonaktif/arsip tetap tercakup dalam periode historis sesuai tanggal masuk/keluar; jangan memakai status aktif saat ini untuk menghapus riwayat. Tanggal keluar tetap inklusif sesuai aturan sebelumnya.

### C. Sinkronisasi izin/cuti dengan rekap

| Status pengajuan | Efektif sebagai izin/cuti di rekap? |
| --- | --- |
| pending, rejected, cancelled, needs_review | Tidak |
| approved | Ya, pada hari kerja yang relevan |
| cancellation_requested | Ya, persetujuan sebelumnya masih berlaku |
| pembatalan ditolak → approved | Tetap ya |
| pembatalan disetujui → cancelled | Tidak lagi |

1. Gunakan status kanonis dan aturan server; flag legacy `is_approved` tidak boleh mengalahkan status kanonis tanpa aturan rekonsiliasi eksplisit.
2. Izin/cuti rentang tanggal dipotong pada periode laporan, hari kerja, dan masa kerja karyawan. Tanggal tumpang tindih dihitung sekali.
3. Kehadiran dan izin pada tanggal yang sama tidak menggandakan jumlah hari terjadwal: pertahankan prioritas hadir untuk agregat eksklusif, tampilkan konteks izin pada detail bila tersedia.
4. Izin non-saldo tetap bisa mengesahkan ketidakhadiran bila tipenya memang izin kerja; jangan menyamakan tidak memotong saldo dengan tidak berlaku di rekap. Lembur/reimbursement tidak dihitung sebagai cuti.
5. Approval dan pembatalan tercermin saat rekap dimuat ulang; jangan membuat cache angka yang tetap usang setelah transaksi terkait.
6. Membaca rekap, detail, atau CSV tidak boleh menulis ledger, mengubah saldo, atau mengubah status pengajuan.

### D. Rekap yang dapat ditelusuri

1. UI menjelaskan periode, zona waktu, populasi, hari terjadwal, hadir pada hari terjadwal, cuti/izin efektif, ketidakhadiran, dan data perlu tinjau. Persentase memakai pembilang/penyebut eksplisit; penyebut nol tampil sebagai tidak tersedia, bukan angka menyesatkan.
2. Sediakan detail per karyawan/per tanggal dari baris rekap: kategori hari, jadwal/sumbernya, absensi, izin efektif, dan tautan sumber yang diizinkan. Gunakan route/pola yang ada atau tambahan minimal dengan otorisasi server.
3. Kembali dari detail mempertahankan periode dan filter. Return URL hanya internal dan tervalidasi; jangan memperkenalkan open redirect.
4. Jam kerja menggunakan pasangan timestamp valid, dikonversi dengan benar; sesi belum checkout tidak diberi durasi final rekaan. Jangan menyamakan jam kerja dengan lembur/payroll.

### E. Filter dan ekspor

1. Pertahankan maksimal 90 hari inklusif, urutan tanggal valid, default zona waktu perusahaan, dan validasi bersama halaman/CSV.
2. Filter: periode, karyawan, unit organisasi yang memang tersedia pada model. Audit relasi aktual terlebih dahulu; jangan menciptakan master unit baru. Filter ID harus tervalidasi dan dibatasi perusahaan serta cakupan aktor.
3. Semua filter aktif dipertahankan pada pagination, detail/kembali, dan ekspor. Filter diterapkan di SQL sebelum pagination, bukan menyaring koleksi sesudahnya.
4. CSV menggunakan cakupan dan definisi angka yang sama dengan halaman. Ekspor mencakup seluruh hasil filter, bukan hanya halaman aktif. Bandingkan pada fixture yang tidak berubah selama dua pembacaan.
5. Pertahankan encoding yang benar dan proteksi formula injection untuk field teks. Hindari query per karyawan/per tanggal; batch/eager load dan batasi memori ekspor. Jangan memuat seluruh riwayat di luar periode.
6. Unit organisasi saat ini belum tentu unit historis. Jika tidak ada riwayat penempatan, jelaskan filter berdasarkan unit saat ini; jangan mengklaim laporan unit historis.

### F. UX dan keamanan

Light/dark, desktop 1440×1000, sempit 390×844, keyboard, fokus dialog, teks panjang, empty state dan error harus dapat digunakan. Staff, supervisor di luar cakupan, aktor nonaktif, serta aktor perusahaan lain tidak dapat melewati larangan melalui URL atau POST langsung. Jangan bocorkan isi pengajuan/audit perusahaan lain dalam pesan penolakan.

## 5. Urutan pengerjaan

1. Audit kode, route, policy, query, dan tes; buat tabel kondisi awal vs target A–F beserta file terkait.
2. Reproduksi celah dengan tes bermakna, prioritaskan cancellation_requested, tanggal mendatang, shift malam, serta lost update koreksi.
3. Perbaiki aturan bisnis dan otorisasi dahulu; ekstrak logika bersama hanya bila menghilangkan duplikasi nyata.
4. Rapikan form/preview/audit, detail rekap, filter dan CSV.
5. Jalankan regresi terarah dan browser pada fixture terisolasi; perbaiki kegagalan dan uji ulang.
6. Perbarui laporan serta timeline berdasarkan hasil nyata. Hentikan lingkup pada Dashboard untuk review; mobile dicatat sebagai tindak lanjut bila diperlukan.

## 6. Matriks bukti wajib

| ID | Skenario | Bukti minimum |
| --- | --- | --- |
| H5-01 | Koreksi sah, alasan, preview/back, konfirmasi | Browser: back mengirim 0 mutasi; simpan 1 update + 1 audit, before/after tepat |
| H5-02 | Alasan kosong, waktu terbalik, transaksi gagal, no-op | Respons dan DB absensi/audit tetap sesuai invariant |
| H5-03 | Dua editor / submit berulang | Satu perubahan versi diterima, stale ditolak, audit tidak ganda; gunakan MySQL terisolasi untuk bukti race/locking |
| H5-04 | Shift 22:00–06:00 dengan koreksi checkout besok | Tanggal kerja asal tetap, timestamp UTC dan durasi benar |
| H5-05 | Pergantian konfigurasi shift setelah absensi | Snapshot valid mempertahankan klasifikasi historis |
| H5-06 | Siklus approved → cancellation_requested → approved/cancelled | Rekap tetap mengakui izin saat menunggu/ditolak, melepas saat dibatalkan; baca tidak mengubah ledger |
| H5-07 | Cuti rentang, libur, overlap dan hadir bersamaan | Himpunan tanggal dan kategori eksklusif sesuai aturan C |
| H5-08 | Hari lalu/berjalan/mendatang, masa kerja, arsip | Tidak ada alpa mendatang; roster historis dan label provisional jelas |
| H5-09 | Filter periode/karyawan/unit, pagination dan kembali | Fixture >1 halaman, ID asing ditolak, filter tetap |
| H5-10 | CSV vs halaman, formula dan rentang 90/91 hari | Angka/populasi sama, formula aman, 90 diterima/91 ditolak |
| H5-11 | Tenant, role, cakupan supervisor dan akun nonaktif | GET serta mutasi langsung tidak bocor/tidak mengubah DB |
| H5-12 | Tema, sempit, keyboard, XSS, kosong/error | Screenshot aktual dan assertion interaksi, tanpa overflow atau eksekusi HTML |

Setiap ID diberi status lulus/gagal/belum diuji/terkendala. Stub `check(true)` bukan bukti. Tes SQLite tidak membuktikan perilaku locking MySQL. Tes concurrency harus memakai koneksi/worker independen, bukan dua panggilan berurutan yang diberi label bersamaan.

## 7. Lingkungan, keluaran dan definisi selesai

- Gunakan database QA disposable dan fixture sintetis. Jangan migrate/seed/reset database utama, mengedit `.env`, mencetak kredensial, atau mengubah konfigurasi Firebase.
- Semua shell command diawali `rtk`. Baca `tools/mysql-qa/README.md` sebelum menyiapkan MySQL QA; hentikan dan bersihkan hanya resource yang dibuat untuk tugas ini.
- Jika perlu migrasi, buat aditif dan uji pada QA; penerapan ke database utama bukan bagian tugas ini.
- Deliverable implementasi: kode dalam lingkup, tes regresi, `docs/hris-h5-flow-refinement-report.md`, dan bukti terstruktur `docs/hris-h5-flow-refinement-results.json`. Screenshot di `docs/screenshots/h5-flow-refinement/`.
- Laporan memuat pemetaan A–F/H5-01–12, file yang berubah, hasil aktual, perintah, versi DB/browser, keterbatasan, dan cleanup. Bedakan regresi penuh vs terarah dan hasil lama vs rerun baru.
- Selesai bila kriteria dalam lingkup terimplementasi dan memiliki bukti aktual; hambatan disebutkan tanpa klaim lulus. Tidak menjalankan commit/push/deploy.
