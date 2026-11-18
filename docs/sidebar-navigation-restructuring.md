# Restrukturisasi Navigasi Sidebar Dashboard Web Naltech Attendance

Terakhir diperbarui: 21 Oktober 2026.

## Ringkasan Perubahan

Sidebar Dashboard web Naltech Attendance direstrukturisasi dari daftar panjang datar (flat list) menjadi navigasi bertingkat (maksimal 2 tingkat: grup utama dan submenu berindentasi) yang dikelompokkan ke dalam tepat **6 menu utama** logis, ditambah area akun tersemat di bagian bawah sidebar.

Struktur ini menyederhanakan penemuan fitur tanpa mengubah route, otorisasi, maupun proses bisnis yang sudah ada.

---

## Pemetaan Menu Lama → Grup Navigasi Baru

| Grup Navigasi Baru | Submenu | Route / URL | Pola Route Aktif | Hak Akses / Otorisasi | Keterangan & Catatan |
|---|---|---|---|---|---|
| **1. Ringkasan** | *(Langsung membuka Dashboard tanpa submenu)* | `route('home')` | `home` | Semua pengguna (karyawan & pengelola) | Menu tunggal tingkat teratas. |
| **2. Karyawan** | Data karyawan | `route('users.index')` | `users.*` | Pengelola (`$manager`) | CRUD karyawan & atasan. Halaman tambah/edit tetap mengaktifkan menu ini. |
| | Unit organisasi | `route('units.index')` | `units.*` | Pengelola (`$manager`) | Kelola departemen/unit kerja. |
| | Verifikasi pensiun | `route('verifikasi.index')` | `verifikasi.*` | Pengelola (`$manager`) | Verifikasi status pensiun kepegawaian. |
| **3. Kehadiran** | Data kehadiran | `route('attendances.index')` | `attendances.*` | Pengelola (`$manager`) | Rekap & log kehadiran. Koreksi absensi (`attendances.edit`) tetap menjadi aksi pada modul ini dan otomatis mengaktifkan menu ini. |
| | QR absensi | `route('qr_absens.index')` | `qr_absens.*` | Pengelola (`$manager`) | Buat & unduh token QR absensi. |
| | Pendaftaran wajah | `route('face-enrollments.index')` | `face-enrollments.*` | Admin, Super Admin, HRD | Pendaftaran foto referensi biometrik karyawan. |
| **4. Jadwal kerja** | Master shift | `route('shifts.index')` | `shifts.*` | Pengelola (`$manager`) | Master jam kerja dan shift malam. |
| | Jadwal efektif | `route('effective-shifts.index')` | `effective-shifts.*` | Pengelola (`$manager`) | Penetapan shift efektif per karyawan. |
| | Kalender kerja | `route('work-calendar.index')` | `work-calendar.*` | Pengelola (`$manager`) | Kalender perusahaan, hari libur, dan aturan kerja mingguan. |
| | Lokasi kerja | `route('companies.show', $company)` | `companies.show` | Pengelola (`$manager`) | Informasi kantor, koordinat, radius, dan metode absensi. |
| **5. Pengajuan** | Cuti / izin | `route('permissions.index')` | `permissions.*`, `leave-balances.*` | Pengelola (`$manager`) | Pengajuan cuti, izin, serta kelola saldo cuti & buku besar saldo (`leave-balances.*`). |
| | Lembur | `route('overtimes.index')` | `overtimes.*` | Pengelola (`$manager`) | Pengajuan dan persetujuan lembur. |
| | Reimbursement | `route('reimbursements.index')` | `reimbursements.*` | Pengelola (`$manager`) | Pengajuan dan persetujuan klaim biaya. |
| **6. Pengaturan** | Perusahaan | `route('companies.edit', $company)` | `companies.edit`, `companies.update` | Pengelola (`$manager`) | Formulir pengaturan kantor, profil, radius, dan tarif lembur. |
| | Jenis cuti | `route('leave-types.index')` | `leave-types.*` | Pengelola (`$manager`) | Master kebijakan cuti dan kuota tahunan. |
| | Alur persetujuan | `route('approval-workflows.index')` | `approval-workflows.*` | Pengelola (`$manager`) | Pengaturan tahap persetujuan berjenjang. |
| **Area Akun (Footer)** | Profil akun | `route('profile')` | `profile` | Pengguna aktif | Tersemat di bagian bawah sidebar. |
| | Keluar | `route('logout')` (POST) | - | Pengguna aktif | Tombol logout langsung dari footer sidebar. |

---

## Perilaku Teknis dan Aksesibilitas

1. **Struktur Accordion Independen & Minimize**:
   - Membuka satu grup accordion tidak menutup grup lainnya, dan masing-masing grup dapat diminimize/ditutup secara mandiri.
   - Menggunakan elemen semantik `<button type="button">` untuk accordion toggle, bukan elemen tautan semu.
   - Atribut ARIA `aria-expanded="true/false"` dan `aria-controls="[groupId]"` diperbarui secara dinamis.
   - Submenu kontainer menggunakan `display: none` secara default dan saat diminimize (`[hidden]`), serta `display: flex` hanya ketika grup terbuka (`.sidebar-group.is-open`).
   - Kontainer submenu menggunakan `role="region"` dan `aria-labelledby="btn-[groupId]"`.

2. **Pembukaan Otomatis Halaman Aktif**:
   - Jika route saat ini (termasuk aksi create, edit, show, ledger) berada di dalam suatu grup, grup tersebut **otomatis terbuka** baik saat server render awal maupun setelah hidrasi JavaScript.
   - Submenu aktif mendapatkan kelas `.active`, atribut `aria-current="page"`, dan indikator garis aksen Naltech (`border-left: 3px solid var(--accent)`).

3. **Isolasi Preferensi Antar-Akun & Antar-Perusahaan**:
   - Status buka/tutup grup disimpan di `localStorage` dengan kunci berlingkup akun dan perusahaan: `naltech.sidebar.groups.{userId}.{companyId}`.
   - Preferensi grup dipulihkan saat kembali ke Dashboard, dengan aturan bahwa grup halaman aktif selalu diprioritaskan terbuka.

4. **Scroll Layout dengan Pinned Header & Footer**:
   - Header merek (`.sidebar-header`) dan profil akun (`.sidebar-footer`) disematkan (*pinned/sticky*).
   - Area navigasi (`.sidebar-body`) dapat digulir secara mandiri dengan scrollbar navy halus, memastikan tombol profil dan logout selalu terlihat dan mudah diakses tanpa harus menggulir sampai akhir daftar.

5. **Drawer Mobile Responsif**:
   - Pada layar ≤ 800px, sidebar beralih menjadi drawer off-canvas yang meluncur dari kiri.
   - Dilengkapi tombol penutup (`#sidebar-toggle-btn` dengan ikon silang pada mobile), tombol pemicu menu (`.menu-toggle`), dan lapisan latar (`.sidebar-backdrop`).
   - Drawer dapat ditutup melalui:
     - Tombol silang / tutup.
     - Klik pada lapisan latar (*backdrop overlay*).
     - Tombol keyboard `Escape` (fokus otomatis dikembalikan ke pemicu menu).
     - Mengklik tautan navigasi manapun di dalam drawer.
   - Memenuhi standar target sentuh minimum 44px pada layar sentuh (`pointer: coarse`).

6. **Penyaringan Otorisasi Server-Driven**:
   - Jika pengguna tidak memiliki izin untuk seluruh submenu dalam suatu grup (misalnya akun karyawan non-manager), grup tersebut disembunyikan sepenuhnya dari render HTML.
   - Penyembunyian antarmuka tidak menggantikan otorisasi server (middleware Laravel tetap memverifikasi setiap request).

7. **Fitur Minimize / Collapse Sidebar Desktop**:
   - Pengguna desktop dapat meminimize seluruh panel sidebar untuk memberikan ruang penuh pada tabel data dan formulir.
   - Pemicu minimize: tombol panah kiri (`#sidebar-toggle-btn`) pada header sidebar dan tombol hamburger (`.menu-toggle`) pada header halaman.
   - Saat diminimize, layout grid kolom sidebar bertransisi halus ke lebar 0 dan sidebar bergeser keluar layar secara tersembunyi.
   - Tombol `.menu-toggle` tetap dapat diakses di kiri atas header dengan label aksesibilitas untuk membuka kembali sidebar dalam satu klik.
   - Preferensi minimize desktop disimpan di `localStorage` per akun dan perusahaan (`naltech.sidebar.minimized.{userId}.{companyId}`).

---

## Status Pengujian dan Batasan

- **Status Pengujian**: Belum diuji (*testing and QA deferred per user instruction*).
- **Status Commit**: Ditunda (*commit, push, and deployment deferred*).
- **Pemeriksaan Sintaks**: Verifikasi kompilasi Blade template berhasil (`view:cache` dan `view:clear` selesai tanpa kesalahan).
