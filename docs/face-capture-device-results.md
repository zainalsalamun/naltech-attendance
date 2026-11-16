# Uji prototipe selfie pada perangkat fisik

Tanggal: 20 Oktober 2026. Perangkat: OPPO CPH2591, Android 15.

## Ruang lingkup

Aplikasi terpisah **Naltech Face QA** (`com.naltech.attendance.facespike`) dipasang pada HP yang disambungkan pengguna. Entry point hanya membuka kamera depan dengan preset medium dan menjalankan ML Kit fast setelah capture. Tidak ada login, API absensi, matching identitas, liveness, atau upload foto. Tidak mengambil screenshot wajah sebagai bukti. Foto sementara dihapus setelah deteksi.

Hasil ini mengukur capture dan deteksi lokal pada satu HP dengan pencahayaan tidak dikontrol. Jumlah wajah yang ditemukan bukan ukuran akurasi identitas. Resolusi file foto aktual, profil CPU/puncak RAM dan suhu perangkat belum diukur. Tidak ada pembanding streaming lama sehingga belum dapat disimpulkan berapa peningkatan performanya.

## Hasil debug awal

[Data numerik asli dan ringkasan](face-capture-debug-results.json).

10 capture/detection selesai; seluruhnya mendeteksi tepat satu wajah. Sampel pertama: capture 696 ms, deteksi 1.179 ms, total 1.875 ms. Total sampel berikutnya berkisar 607–661 ms. Median seluruh sampel: capture 443 ms, deteksi 195,5 ms, total 637,5 ms. Angka ini **mode debug**, bukan angka performa produksi.

Detector baru dibuat pada setiap sampel. Sampel pertama berarti pertama pada run ini, bukan bukti cold start model dari instalasi bersih. p95 memakai nearest-rank; dengan sepuluh sampel, p95 sama dengan nilai maksimum.

Suite integrasi native lulus. Log memperlihatkan konflik open/close kamera saat izin/lifecycle pertama; prototipe kemudian diubah agar operasi open/close diserialkan. Hasil debug awal mendahului perbaikan tersebut. Driver mencoba menghapus paket QA saat selesai tetapi Android mengembalikan DELETE_FAILED_INTERNAL_ERROR; paket QA kemudian di-force-stop. Tidak melakukan uninstall/clear pada aplikasi utama.

## Debug setelah perbaikan lifecycle

[Hasil numerik setelah perbaikan](face-capture-debug-lifecycle-results.json): 10/10 sampel mendeteksi satu wajah, median total 631,5 ms, sampel pertama 1.443 ms. Setelah sampel kelima, test menyuntikkan event inactive/resumed dan memastikan kamera bisa dipakai lagi. Ini pengujian event Flutter dengan pemanggilan kamera native nyata, bukan pengujian OS backgrounding. Suite lulus; bukan bukti perbaikan performa terhadap run awal karena kondisi tidak dikontrol.

## Mode profile

**Suite profile lulus pada HP fisik**, termasuk sepuluh capture/detection dan event lifecycle yang disuntikkan setelah sampel kelima. [Data numerik profile](face-capture-profile-results.json).

| Bagian | Sampel pertama | Median seluruh 10 sampel | Median 9 sampel berikutnya |
| --- | --- | --- | --- |
| Ambil foto | 522 ms | 415,5 ms | 413 ms |
| Deteksi wajah | 722 ms | 191 ms | 189 ms |
| Total capture + deteksi | 1.245 ms | 611,5 ms | 607 ms |

10/10 sampel mendeteksi tepat satu wajah. Total sembilan sampel berikutnya 584–645 ms; p95 total seluruh sampel 1.245 ms, sedangkan p95 sembilan sampel berikutnya 645 ms. Nilai ini tidak mencakup penutupan detector/pembersihan file setelah stopwatch dihentikan, upload, verifikasi server atau penyimpanan absensi. Total dibulatkan dari stopwatch tersendiri, sehingga dapat berbeda 1 ms dari penjumlahan kolom.

Build pertama gagal mengunduh artefak arm64 profile akibat TLS Gradle. Artefak diunduh melalui HTTPS langsung dari storage resmi Flutter dengan resume, ukuran dan MD5 dicocokkan dengan header Google. Fallback Maven diikat ke direktori root Android hanya pada checkout QA. Tidak mengubah dependency, SDK atau konfigurasi Gradle proyek utama. Profile APK 99,6 MB berhasil dibangun; ini bukan ukuran APK release yang sudah dioptimalkan.

Percobaan profile pertama berhenti pada kesiapan kamera karena izin belum aktif, tanpa mengambil sampel. Pemberian izin lewat USB ditolak kebijakan OS; pengguna kemudian mengaktifkan izin kamera pada HP. Percobaan berikutnya memakai APK profile yang sama dan lulus. File hasil disalin hanya dari run yang exit code-nya 0. Aplikasi Face QA di-force-stop setelah selesai; aplikasi utama dan datanya tidak diubah.

Satu [snapshot memori startup profile](face-capture-profile-memory-snapshot.json) pada percobaan profile pertama mencatat PSS 114.394 kB dan RSS 148.844 kB. Snapshot ini mendahului keberhasilan izin kamera dan tidak tersinkron dengan capture; bukan puncak, rata-rata, atau bukti kebutuhan RAM saat inferensi. Belum ada profil CPU/memori sepanjang alur. Log native juga mencatat peringatan `Surface.release`; suite tetap lulus, tetapi pengujian jangka panjang diperlukan sebelum menyatakan tidak ada kebocoran resource.

## Keputusan mesin verifikasi

[Evaluasi mesin](design/face-verification-engine-decision.md) mencatat pilihan pengguna: cloud opsional dan server sendiri. Kandidat cloud untuk PoC adalah AWS Rekognition; pilihan model server sendiri masih memerlukan evaluasi bobot/lisensi dan uji anti-spoofing. Tidak ada verifier eksternal yang dikonfigurasi/dipanggil pada pengujian ini. Face pada backend produksi tetap tidak tersedia.

## Validasi tooling

Analisis statis target prototipe dan driver dijalankan; formatter Dart dan pemeriksaan sintaks Python digunakan. Script agregasi diperiksa dengan fixture numerik untuk median/p95, pemisahan sampel pertama dan penolakan field sampel selain yang diizinkan. Fixture script tidak dimasukkan ke hasil perangkat.

## Pekerjaan berikutnya

- Ukur streaming lama dan CPU/RAM dengan kondisi yang sama; profile satu foto setelah perbaikan lifecycle sudah lulus.
- Uji lebih dari satu perangkat serta kondisi gelap, wajah jauh/tertutup, tanpa wajah dan lebih dari satu wajah.
- Uji enrollment, verifikasi identitas, liveness, retry dan replay menggunakan provider nyata sebelum mengaktifkan Face di server.
