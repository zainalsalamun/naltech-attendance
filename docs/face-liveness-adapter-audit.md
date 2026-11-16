# Audit adapter liveness — 21 Oktober 2026

Hasil: ditemukan selisih geometri crop, diperbaiki dan diperiksa terhadap oracle
C++ dari referensi yang dipin. Format warna, ukuran, rentang input dan pembacaan
kelas hidup sebelumnya sudah sesuai. **Belum terbukti bahwa koreksi crop mengatasi
tiga sampel spoof yang lolos. Face tetap tidak aktif.**

## Acuan resmi

Audit memakai commit `83329c97ba1153e2bbdd374dee1b5d33a7367dc7`:

- [Live::CalculateBox dan inferensi](https://github.com/minivision-ai/Silent-Face-Anti-Spoofing-APK/blob/83329c97ba1153e2bbdd374dee1b5d33a7367dc7/engine/src/main/cpp/live/live.cpp).
- [Konfigurasi dua model](https://github.com/minivision-ai/Silent-Face-Anti-Spoofing-APK/blob/83329c97ba1153e2bbdd374dee1b5d33a7367dc7/engine/src/main/assets/live/config.json).
- [Konversi warna/orientasi Android](https://github.com/minivision-ai/Silent-Face-Anti-Spoofing-APK/blob/83329c97ba1153e2bbdd374dee1b5d33a7367dc7/engine/src/main/cpp/img_process.cpp).
- [Nama input/output](https://github.com/minivision-ai/Silent-Face-Anti-Spoofing-APK/blob/83329c97ba1153e2bbdd374dee1b5d33a7367dc7/engine/src/main/cpp/live/live.h).

Sumber diambil langsung dari raw GitHub pada commit tersebut. Tidak memakai
artikel pihak ketiga atau mengubah bobot model. Lisensi port tetap ada pada
`services/face-verifier/notices/Minivision-LICENSE`.

## Temuan dan perubahan

| Bagian | Temuan/tindakan |
| --- | --- |
| Warna | BGR sesuai referensi; tensor sintetis kanal berbeda membuktikan urutan dipertahankan |
| Nilai piksel | 0–255 tanpa pembagian 255 atau mean/std, sesuai referensi |
| Ukuran | 80×80 mengikuti config resmi kedua model; tidak mengganti berdasarkan dugaan dari deklarasi shape param |
| Resize | OpenCV INTER_LINEAR sesuai default cv::resize referensi |
| Model | Dua model skala 2,7 dan 4; konfigurasi skala/ukuran/shift diperiksa dan hash seluruh artefak wajib tersedia |
| Kelas hidup | Indeks 1 dari softmax, dirata-rata atas dua model; sudah sesuai |
| Crop | Clamp lama memotong terlalu jauh satu piksel untuk ukuran crop ganjil di tepi kanan/bawah. Kini mengikuti penyesuaian right/bottom referensi dan aritmetika float32 C++ |
| Geometri | YuNet xywh dipetakan ke xyxy inklusif (`x2=x+width-1`), sehingga lebar referensi `int(x2-x1+1)` sama dengan `int(width)` |
| Output tidak valid | Kini setiap probabilitas harus finite dan dalam [0,1], bukan hanya jumlah mendekati satu |
| Otorisasi | Hasil tetap experimental dan attendance_eligible=false; endpoint liveness produksi tetap 501 |

Contoh fixture frame 640×480, kotak `[600,400,82,82]`, skala 2,7:
crop lama `(418,258,221,221)`, referensi/koreksi `(419,259,221,221)`.
Koreksi satu piksel bukan bukti penyebab seluruh kegagalan anti-spoofing.

## Bukti pemeriksaan

[Laporan mesin](face-liveness-adapter-audit.json) mencatat tujuh pemeriksaan dan
1.005 fixture geometri deterministik, termasuk posisi tengah/tepi, ukuran ganjil,
dimensi frame berbeda, dan kedua skala. Oracle C++ dikompilasi sementara dari
adaptasi fungsi resmi dan dibandingkan dengan port Python.

Kedua model NCNN nyata dengan hash terverifikasi dieksekusi pada gambar sintetis
di memori. Tidak ada foto relawan, embedding, atau skor biometrik mentah yang
ditulis. Pemeriksaan ini membuktikan geometri/tensor dan eksekusi model, bukan
akurasi liveness atau kesetaraan seluruh pipeline Android.

14 tes Python lulus: suite protokol/evaluasi yang sudah ada ditambah tiga uji
regresi geometri dan probabilitas. Tidak ada perubahan Laravel, database utama,
URL mobile, dependency terpasang, atau aktivasi Face dari audit ini.

## Batas dan pengujian ulang

Pipeline server tetap berbeda pada sumber input (JPEG dengan EXIF vs NV21
Android), detector (YuNet vs pipeline Android upstream), dan kondisi pengambilan.
Oracle C++ hanya independen pada geometri crop, bukan implementasi inferensi
NCNN kedua yang independen.

Foto sesi sebelumnya sudah dihapus sesuai retensi QA, sehingga tidak dilakukan
perbandingan skor sebelum/sesudah pada foto yang identik. Pengujian ulang harus
mengambil sampel kamera baru untuk wajah langsung, cetakan, layar lain, dan video
yang diputar. Aplikasi QA dibuka kembali pada OPPO CPH2591 dan sampel baru
berhasil diambil, dipindahkan lewat USB, lalu dievaluasi.
Hasil awal tetap tercatat sebagai kegagalan jalur versi sebelumnya, tidak
ditimpa atau dianggap lulus oleh audit ini.

## Hasil pengujian ulang setelah koreksi

[Laporan uji ulang](face-liveness-device-retest.json) berisi empat sampel baru,
masing-masing satu per kategori, walau instruksi meminta tiga. Relawan menyatakan
selesai setelah instruksi penggunaan media fisik yang sama. Wajah langsung,
cetakan, layar dan video semuanya diterima pada ambang 0,8 dan seluruh sweep
0,5–0,95. Tidak ada penolakan kualitas atau kategori yang hilang.

Median inferensi 32,55 ms, maksimum 81,36 ms untuk empat sampel, tanpa waktu
capture/transfer/startup. Ini bukan perbandingan performa terkontrol dengan sesi
pertama. Tidak ada holdout atau estimasi akurasi populasi.

**Keputusan: koreksi adapter belum mengatasi kegagalan spoof. Kandidat ini tidak
diintegrasikan ke absensi.** Perbandingan kandidat berikutnya ada pada
[keputusan mesin](design/face-verification-engine-decision.md#evaluasi-setelah-audit--21-oktober-2026).
Foto sementara di HP dan komputer sudah dihapus, paket QA dihentikan, dan hanya
laporan agregat dipertahankan. Tidak ada aktivasi Face atau panggilan cloud.
