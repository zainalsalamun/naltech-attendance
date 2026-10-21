# PoC anti-spoof-mn3 — 21 Oktober 2026

PoC selesai dibuat dan diuji. **Kandidat gagal gerbang awal spoof: pada ambang
diagnostik 0,8, satu wajah langsung dan tiga sampel berlabel cetak/layar/video
semuanya diterima. Face tetap tidak aktif.** Tidak ada threshold dipromosikan,
perubahan database utama, atau pengiriman foto ke cloud.

## Sumber, artefak dan lisensi

Model original ONNX diambil dari storage resmi Open Model Zoo, 12.270.179 byte.
Downloader memverifikasi SHA384 yang dicantumkan pada
[model.yml resmi](https://github.com/openvinotoolkit/open_model_zoo/blob/2c9b5c563ab3a202d085a42e2bbfc48f77961f4b/models/public/anti-spoof-mn3/model.yml),
commit `2c9b5c563ab3a202d085a42e2bbfc48f77961f4b`. Model, metadata dan manifest
SHA256 disimpan di luar checkout; downloader menolak penempatan di checkout,
artefak rusak, atau penimpaan file berbeda. Tidak ada bobot dibundel ke Git.

[Dokumentasi model](https://docs.openvino.ai/2023.3/omz_models_model_anti_spoof_mn3.html)
menyebut lisensi original MIT. Lisensi repository upstream diambil pada commit
`82a2c26bacff5462c6f9320c34ae0363ccbed497` dan notice dipertahankan di
`services/face-verifier/notices/MN3-upstream-LICENSE`. Ini pemeriksaan awal sumber
dan notice, bukan audit hak seluruh dataset pelatihan atau persetujuan bundling
komersial. Dataset pelatihan CelebA-Spoof tidak diunduh/disertakan.

## Adapter dan runtime

`experimental_mn3.py` terpisah dari MiniFASNet. Kontrak original ONNX: RGB
128×128, NCHW float32, normalisasi per kanal dengan mean
`[151.2405,119.5950,107.8395]` dan scale `[63.0105,56.4570,55.0035]`.
Resize CUBIC mengikuti accuracy config resmi; kelas hidup indeks 0, spoof indeks
1. Jangan memakai adapter MiniFASNet atau praproses model IR hasil konversi.

Crop memakai ekspansi horizontal 1,1 dan vertikal 1,05 dari
[demo upstream](https://github.com/kprokofi/light-weight-face-anti-spoofing/blob/82a2c26bacff5462c6f9320c34ae0363ccbed497/demo_tools/wrapers.py),
dengan clipping tepi. Detektor tetap YuNet, sehingga keseluruhan pipeline tidak
diklaim identik dengan demo upstream. JPG/PNG, batas ukuran, tepat satu wajah
dan kualitas minimum diperiksa oleh verifier yang sudah ada.

Inferensi berjalan pada CPU macOS ARM64 melalui OpenCV 4.12.0. Tidak memasang
runtime baru ke Python global atau menambah dependency mobile/Laravel.
ONNX Runtime 1.23.2 dipasang hanya pada venv PoC untuk pemeriksaan pembanding.
Server Linux/Windows dan profil kapasitas multi-pengguna belum diuji.

Opt-in memakai `FACE_LIVENESS_EXPERIMENTAL_ENABLED=true`,
`FACE_LIVENESS_EXPERIMENTAL_ENGINE=mn3` dan `FACE_MN3_MODEL_DIR` ke direktori model.
Default engine tetap MiniFASNet dan flag eksperimen default tidak aktif.
Nama engine tidak dikenal menggagalkan startup. Tidak ada fallback otomatis
ke provider lain jika model gagal. `/v1/liveness-preview` tetap diagnostik,
`/v1/liveness` tetap HTTP 501 dan matching tidak menerbitkan proof.

## Hasil kamera dan pembanding berpasangan

Relawan menyatakan selesai mengikuti instruksi menghadapkan media fisik sesuai
label. Terkumpul empat sampel baru, satu per kategori (instruksi meminta tiga),
pada satu OPPO CPH2591 dan satu relawan. Tidak ada holdout atau kondisi terkontrol.

| Label sampel | MN3 diterima / diuji | MiniFASNet pada foto yang sama |
| --- | --- | --- |
| Wajah langsung | 1 / 1 | 1 / 1 |
| Foto cetak | 1 / 1 | 1 / 1 |
| Foto pada layar lain | 1 / 1 | 1 / 1 |
| Video pada layar lain | 1 / 1 | 1 / 1 |

Kedua model menerima semua sampel pada sweep development 0,5–0,95. Label media
berasal dari operator dan tidak dibuktikan secara independen. Hasil ini cukup
untuk menahan integrasi kandidat, bukan estimasi akurasi populasi.

- [Laporan MN3](face-mn3-device-evaluation.json).
- [Kontrol MiniFASNet berpasangan](face-mn3-minifasnet-paired-control.json).
- [Audit dua runtime](face-mn3-runtime-audit.json): empat tensor sintetis dan
  empat sampel kamera yang sama; selisih maksimum probabilitas antar-runtime
  `1,0728836e-6`, di bawah toleransi `1e-4`. Keputusan keduanya sama.

Paritas runtime menunjukkan hasil bukan perbedaan eksekusi OpenCV/ONNX Runtime
pada input yang diuji. Keduanya menerima praproses yang sama; pemeriksaan ini
tidak membuktikan crop atau label pengambilan benar pada setiap kondisi.

Median pemrosesan lokal MN3 43,56 ms, maksimum 100,49 ms untuk empat sampel pada
run akhir. Ini mencakup pembacaan/base64, decode, deteksi dan inferensi, tanpa
capture, USB, startup model atau jaringan. Tidak dipakai sebagai benchmark
kecepatan produksi atau perbandingan performa terkontrol.

Foto sementara pada HP dan komputer telah dihapus setelah laporan tersimpan.
Paket QA dan server HTTP sementara dihentikan. Hanya laporan agregat, provenance
model dan kode alat yang dipertahankan; tidak ada foto/embedding relawan di Git.

## Pemeriksaan dan reproduksi

20 tes Python lulus di venv dengan core requirements, termasuk RGB/normalisasi,
indeks kelas, geometri, checksum, manifest, serta hasil yang tidak menerbitkan
proof. [Enam smoke HTTP model nyata](face-mn3-http-smoke.json) lulus memakai
fixture publik OpenCV: auth, preview, matching tanpa proof, liveness produksi
tetap 501, foto rusak dan field proof klien ditolak. Bukan uji akurasi spoof.

```sh
rtk proxy python3 services/face-verifier/download_mn3.py --directory /path/outside/repo/mn3-models
rtk proxy /path/to/venv/bin/python -m unittest discover -s services/face-verifier -p 'test_*.py'
```

Untuk audit pembanding, install `requirements-mn3-audit.txt` pada venv yang sama,
lalu jalankan `tools/mobile-qa/audit_mn3_runtime.py` dengan direktori model,
manifest QA privat dan output laporan baru. Gunakan data kamera baru dengan
persetujuan; foto pada laporan sesi ini sudah dihapus dan tidak bisa diputar ulang.

## Keputusan berikutnya

Jangan menghubungkan MN3 atau MiniFASNet saat ini ke absensi atau menganggap
kenaikan threshold sebagai perbaikan yang terbukti. PoC berikutnya sebaiknya
liveness berbasis sesi/video dengan provider yang dapat dievaluasi, termasuk
jalur AWS opsional yang sudah dipilih. Server sendiri tetap arah yang didukung,
tetapi belum ada mesin yang lolos pada kondisi uji ini. Tidak ada provisioning,
biaya cloud, kredensial AWS atau API cloud yang dipakai pada PoC MN3.
