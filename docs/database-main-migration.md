# Penerapan migrasi database utama

Dijalankan 21 Oktober 2026 atas instruksi pengguna. Hanya commit yang ditunda sampai ada perintah berikutnya.

- Koneksi aktif Laravel: MySQL, database `absensi`.
- Backup sebelum migrasi disimpan lokal di luar repositori: `/Users/macbookpro/.codex/backups/naltech-attendance/20261021-072715/before-migrate.sql`, izin file 0600. Backup tidak dimasukkan ke Git.
- `php artisan migrate --force` berhasil menerapkan delapan migrasi tertunda sebagai batch 8: pendaftaran wajah, lifecycle karyawan, organisasi, kalender kerja, approval dasar, workflow, jenis cuti dan jadwal efektif.
- `migrate:status` setelah penerapan: seluruh migrasi Ran, tidak ada Pending.
- Jumlah baris sebelum/sesudah sama: perusahaan 2, karyawan 18, absensi 29, izin 17, lembur 14, reimbursement 13.
- Izin lama dengan `is_approved=false` terpetakan ke `needs_review`; skema EffectiveShift siap.
- Tidak menjalankan seed/reset, tidak mengisi kebijakan kalender/cuti/approval secara rekaan dan tidak mengaktifkan Face. Pengujian native shift tetap tahap berikutnya.

Catatan “database utama belum dimigrasikan” dalam laporan sebelumnya adalah status saat pengujian tersebut berlangsung; dokumen ini memperbarui status penerapan aktual.
