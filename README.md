# Attendance Management System (Absensi Online)

Sistem Manajemen Absensi Karyawan berbasis Laravel (Backend/Dashboard) dan Flutter (Mobile App). Sistem ini dirancang untuk memudahkan pencatatan kehadiran dengan validasi lokasi (Geofencing), pendeteksi wajah, dan perhitungan lembur otomatis.

## Fitur Utama

-   **Dashboard Admin & HRD**: Rekapitulasi absensi, statistik harian, dan manajemen data karyawan.
-   **Sistem Lembur Otomatis**: 
    -   Pengaturan tarif lembur per jam di level perusahaan.
    -   Perhitungan menit lembur otomatis jika karyawan checkout melebihi jam shift/perusahaan.
    -   Kalkulasi total pendapatan lembur harian.
-   **Manajemen Perusahaan**: Pengaturan lokasi kantor (Latitude/Longitude), radius jangkauan absen (Geofencing), dan jam kerja operasional.
-   **Role-Based Access Control (RBAC)**:
    -   **Super Admin**: Akses penuh seluruh sistem.
    -   **Admin/HRD**: Mengelola karyawan, divisi, jadwal, dan laporan (tanpa hapus data sensitif).
    -   **Employee (Staff)**: Melakukan absen via mobile dan melihat riwayat pribadi.
-   **Geofencing & Security**:
    -   Validasi radius jarak karyawan dengan lokasi kantor sebelum absen.
    -   Pendeteksi Mock Location (GPS Palsu) di sisi aplikasi mobile.
    -   Toleransi GPS (50 meter) untuk akurasi yang lebih baik.
-   **Export Report**:支持 (Support) ekspor data absensi ke format CSV untuk keperluan penggajian.

## Teknologi yang Digunakan

### Backend (Web)
-   **Framework**: Laravel 11
-   **Database**: MySQL / PostgreSQL
-   **UI Template**: Stisla (Bootstrap 4)
-   **Auth**: Laravel Sanctum (untuk API Mobile)

### Mobile
-   **Framework**: Flutter
-   **State Management**: BLoC / Cubit
-   **Maps/Location**: Geolocator & Google Maps API

## Langkah Instalasi (Backend)

1.  Clone repository:
    ```bash
    git clone [repository-url]
    ```
2.  Install dependencies:
    ```bash
    composer install
    npm install
    ```
3.  Konfigurasi `.env`:
    ```bash
    cp .env.example .env
    php artisan key:generate
    ```
4.  Jalankan migrasi & seeder:
    ```bash
    php artisan migrate --seed
    ```
5.  Jalankan server:
    ```bash
    php artisan serve
    ```

## Tim Pengembangan
-   **FIC (Flutter Intensive Club)** - [Your Name/Org]

---
*Dokumentasi ini diperbarui terakhir pada Feb 2026.*
