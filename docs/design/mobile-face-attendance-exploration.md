# Eksplorasi absensi wajah Naltech

Tanggal: 20 Oktober 2026. Status: konsep untuk review, belum menjadi UI produksi.

## Preview dan keputusan

![Konsep tiga layar](mobile-face-attendance-concept-v1.png)

Beranda → kamera depan → ambil foto → pemeriksaan → hasil dari server.
Wajah adalah metode utama yang dituju. QR adalah alternatif yang hanya ditampilkan jika diaktifkan perusahaan; tidak menjadi jalan otomatis untuk melewati verifikasi wajah yang gagal.

Konsep memakai biru Naltech #315BCE, navy #192A48, latar #F5F6F8 dan panel putih. Tampilan mengutamakan jadwal, status masuk/pulang, lokasi dan satu aksi utama. Preview adalah gambar AI dengan data serta orang fiktif, bukan bukti aplikasi berjalan. Pintasan izin/cuti dan jadwal menggambarkan arah produk, bukan klaim kelengkapan modul. Light mode dieksplor terlebih dahulu; dark mode masih mengikuti token desain proyek dan perlu preview tersendiri.

Layar hasil pada gambar menggambarkan kondisi sukses setelah server menerima absensi. Implementasi harus menyediakan state lokasi belum didapat, di luar radius, izin kamera ditolak, wajah tidak ditemukan/lebih dari satu, identitas tidak cocok, layanan tidak tersedia, unggahan gagal, dan duplikasi. Sediakan ambil ulang sebelum pengiriman serta cegah submit ganda. Jangan menampilkan sukses sebelum konfirmasi server.

## Temuan kode dan hipotesis performa

Alur lama pada `apps/mobile/lib/presentation/home/pages/attendance_checkin_page.dart` memakai kamera resolusi tinggi dan memproses stream secara berurutan dengan flag busy. Setiap siklus mencakup ML Kit, konversi piksel Dart, rotasi, crop, MobileFaceNet dan pembacaan data akun lokal. `Recognizer` juga memuat model secara asynchronous dari konstruktor dan menjalankan inference secara sinkron. Ini kandidat sumber beban; belum ada profiling perangkat yang membuktikan penyebab dominan.

Hipotesis: preview kamera tanpa image stream, kemudian deteksi pada foto yang diambil, dapat mengurangi frekuensi kerja ML selama menunggu. Tidak berarti pengiriman dan verifikasi server otomatis lebih cepat. Resolusi medium adalah kandidat eksperimen, bukan keputusan produksi; kualitas wajah perlu diuji.

## Prototipe teknis terpisah

Target: `tools/mobile-qa/face_capture_spike.dart`. Memakai dependency yang sudah tersedia, kamera depan, resolusi medium, tanpa image stream; ML Kit fast dipanggil saat tombol ditekan. Layar mencatat durasi capture, deteksi, total dan jumlah wajah. Foto sementara dihapus setelah proses; tidak ada unggahan, API absensi, login, Firebase initializer, face embedding, matching atau liveness. Menampilkan satu wajah tidak berarti identitas terverifikasi. Prototipe sengaja tidak menyerupai layar sukses produksi.

Jalankan hanya pada salinan QA dengan application ID terpisah seperti panduan `tools/mobile-qa/README.md`. Setelah menyiapkan salinan, salin target ini ke `lib/face_capture_spike.dart` dalam salinan tersebut dan jalankan dari sana:

```sh
rtk proxy flutter run --profile -t lib/face_capture_spike.dart
```

Jangan mengganti entry point aplikasi produksi atau mengaktifkan ulang Face pada server untuk mencoba prototipe. Gunakan relawan/data uji. Jika pembersihan foto gagal, hapus data aplikasi QA sebelum digunakan kembali.

### Protokol pengukuran

1. Gunakan perangkat Android fisik kelas bawah dan menengah, mode profile, pencahayaan/jarak yang sama. Catat model perangkat, OS, ukuran gambar aktual dan suhu/kondisi awal.
2. Pisahkan percobaan pertama (cold) dari pengulangan (warm); ukur setidaknya 10 foto per kondisi, laporkan median/p95 dan kegagalan deteksi.
3. Pada salinan QA alur lama, ukur waktu ML Kit, konversi/rotasi/crop, inference dan baca cache secara terpisah dengan stopwatch; jangan mencatat gambar, embedding atau identitas. Pengukuran baseline ini belum ditambahkan/dijalankan.
4. Rekam CPU, memori, kelancaran preview dan respons tombol dengan DevTools pada kedua alur. Bandingkan durasi kamera terbuka yang sama, bukan hanya waktu satu inference.
5. Saat verifier server dipilih, ukur upload dan verifikasi secara terpisah dalam kondisi jaringan lambat. Uji ketidakcocokan, foto layar/cetak, lebih dari satu wajah dan gangguan layanan.

Pembaruan native 20 Oktober 2026: [uji HP fisik](../face-capture-device-results.md) berhasil menjalankan sepuluh capture/detection di mode debug dan profile. Pada profile OPPO CPH2591 Android 15, median total 611,5 ms dan 10/10 sampel mendeteksi satu wajah. Angka hanya mencakup capture/detection; belum membuktikan kecepatan total absensi, akurasi recognition, ketahanan spoofing atau peningkatan terhadap streaming lama. Snapshot startup memori bukan profil memori inferensi. Tidak ada klaim penghematan persentase.

[Keputusan mesin](face-verification-engine-decision.md): pengguna memilih cloud opsional dan server sendiri. AWS Rekognition menjadi kandidat PoC cloud; YuNet + SFace menjadi shortlist matching server sendiri dengan anti-spoofing yang dievaluasi terpisah. Provider nyata belum dikonfigurasi/dipanggil.

## Batas arsitektur produksi

- Mobile memberi panduan posisi dan kualitas; keputusan identitas harus berasal dari verifier server tepercaya.
- Matching wajah dan liveness adalah kemampuan berbeda. Mesin verifikasi belum dipilih. Evaluasi akurasi, performa, lisensi redistribusi model, kebutuhan server dan biaya sebelum menjanjikan fitur di paket source code.
- Enrollment perlu pengikatan ke akun yang benar dan prosedur persetujuan/perubahan wajah oleh admin. Foto profil biasa tidak otomatis menjadi enrollment tepercaya.
- Backend tetap memeriksa perusahaan, akun, metode, radius, duplikasi dan waktu server. Verifikasi harus terikat pada permintaan absensi yang sama, memiliki masa berlaku singkat dan tidak bisa dipakai ulang.
- Batasi akses foto/template biometrik, hindari log sensitif, tetapkan retensi dan penghapusan. Jangan menerima embedding atau boolean `verified` dari klien sebagai bukti identitas.
- Face produksi tetap tidak tersedia sampai verifier dikonfigurasi dan diuji. QR mengikuti kebijakan perusahaan, bukan fallback tersembunyi.

## Urutan lanjutan

1. Review konsep tiga layar dan revisi bila perlu.
2. Jalankan prototipe di perangkat fisik dan lengkapi baseline lama.
3. Pilih verifier; uji enrollment, matching dan liveness termasuk kegagalan/spoofing.
4. Implementasikan satu irisan masuk/pulang end-to-end, lalu light/dark dan QA perangkat.

## Referensi eksplorasi

- [Alur liveness Talenta Mobile](https://help-center.talenta.co/hc/id/articles/13574927936281-Bagaimana-Cara-Menggunakan-Liveness-Validation-pada-Live-Attendance-Talenta-Mobile): referensi urutan selfie, pemeriksaan dan hasil, bukan bukti teknologi internal Talenta.
- [ML Kit Face Detection](https://developers.google.com/ml-kit/vision/face-detection/): deteksi wajah berbeda dari identitas.
- [Performa ML Kit Android](https://developers.google.com/ml-kit/vision/face-detection/android): panduan mode cepat dan pemrosesan gambar.

Gambar dibuat dengan tool imagegen bawaan. Prompt lengkap disimpan di `mobile-face-attendance-concept-v1.prompt.txt`.
