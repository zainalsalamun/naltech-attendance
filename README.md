# Attendance Management System (Absensi Online)

Sistem Manajemen Absensi Karyawan berbasis Laravel (Backend/Dashboard) dan Flutter (Mobile App). Sistem ini dirancang untuk memudahkan pencatatan kehadiran dengan validasi lokasi (Geofencing), pendeteksi wajah, dan modul lembur terpisah.

## Fitur Utama

-   **Dashboard Admin & HRD**: Rekapitulasi absensi, statistik harian, dan manajemen data karyawan.
-   **Modul Lembur Terpisah (New)**: 
    -   Sistem pengajuan lembur yang terpisah dari record absensi regular.
    -   Status approval (Pending, Approved, Rejected) untuk setiap pengajuan.
    -   Kalkulasi durasi lembur (Menit) dan deskripsi pekerjaan lembur.
-   **Manajemen Perusahaan**: Pengaturan lokasi kantor (Latitude/Longitude), radius jangkauan absen (Geofencing), dan jam kerja operasional.
-   **Role-Based Access Control (RBAC)**:
    -   **Super Admin**: Akses penuh seluruh sistem.
    -   **Admin/HRD**: Mengelola karyawan, divisi, jadwal, dan laporan.
    -   **Employee (Staff)**: Melakukan absen via mobile dan mengajukan lembur.
-   **Geofencing & Security**:
    -   Validasi radius jarak karyawan dengan lokasi kantor sebelum absen.
    -   Toleransi GPS (50 meter) untuk akurasi yang lebih baik.
-   **Export Report**: Support ekspor data absensi ke format CSV.

## Teknologi yang Digunakan

### Backend (Web)
-   **Framework**: Laravel 11
-   **Database**: MySQL
-   **UI Template**: Stisla (Bootstrap 4)
-   **Auth**: Laravel Sanctum (untuk API Mobile)

### Mobile
-   **Framework**: Flutter
-   **State Management**: BLoC / Cubit

## Langkah Instalasi (Backend)

1.  Clone repository
2.  Install dependencies: `composer install` & `npm install`
3.  Konfigurasi `.env` & `php artisan key:generate`
4.  Jalankan migrasi: `php artisan migrate --seed`
5.  Jalankan server: `php artisan serve`

---
*Dokumentasi ini diperbarui terakhir pada Feb 2026.*
