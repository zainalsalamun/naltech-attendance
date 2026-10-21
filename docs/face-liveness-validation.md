# Validasi liveness — 21 Oktober 2026

Status: empat capture melalui halaman kamera pendaftaran pada HP selesai dan
dievaluasi lokal. Operator mengonfirmasi media fisik sesuai kategori. Jalur
eksperimen gagal menolak tiga sampel spoof awal. Absensi Face tetap tidak aktif.

## Hasil sesi awal pada HP

[Laporan agregat](face-liveness-device-evaluation.json) mencatat OPPO CPH2591,
satu relawan, satu perangkat dan empat sampel (satu per label, bukan tiga).
Seluruh foto lolos batas kualitas server dan menghasilkan diagnostik model nyata.

| Label yang dipilih pada HP | Sampel | Diterima pada threshold 0,8 |
| --- | --- | --- |
| Wajah langsung | 1 | 1 |
| Cetak | 1 | 1 |
| Layar | 1 | 1 |
| Video | 1 | 1 |

Semua sampel juga diterima pada sweep development 0,5–0,95. Karena itu tidak ada
threshold dari sweep yang memisahkan label pada sesi ini. Operator mengonfirmasi
kamera benar-benar menghadapi cetakan, layar lain dan video yang diputar.
Ketiga sampel serangan lolos jalur eksperimen saat ini. Keputusan: jangan
mengintegrasikannya ke absensi. Audit adapter/praproses dan evaluasi kandidat
alternatif diperlukan sebelum pengujian ulang; sesi ini belum mengisolasi apakah
penyebabnya adapter, model, atau keterbatasan kondisi pengambilan.
Tidak ada holdout dan tidak ada klaim tingkat akurasi atau kalibrasi produksi.

Median inferensi lokal 38,32 ms, maksimum 73,21 ms untuk empat sampel; tidak
mencakup capture, USB, startup model atau jaringan. Foto sementara di HP dan
komputer telah dihapus setelah laporan agregat tersimpan; paket QA dihentikan.
Tidak ada foto/embedding relawan yang disimpan ke repository. Capture ini
membuktikan jalur kamera → deteksi → pengembalian file dipakai pada HP, bukan
kelulusan keseluruhan enrollment, persetujuan HR atau absensi end-to-end.

## Pengambilan sampel awal

Pembaruan: [audit adapter dan uji ulang](face-liveness-adapter-audit.md) menemukan
dan memperbaiki selisih crop terhadap C++ resmi. Empat sampel baru tetap semuanya
diterima termasuk tiga label spoof. Kandidat tetap diblokir; bukan masalah yang
terbukti selesai dengan menaikkan threshold. Suite Python kini 14 tes; angka 11
di bagian pemeriksaan awal adalah baseline sebelum koreksi crop.

Target `tools/mobile-qa/liveness_capture_qa.dart` dijalankan hanya pada checkout
Android disposable yang disiapkan `prepare_face.py`, dengan application ID
`com.naltech.attendance.facespike`. Target ini menggunakan salinan persis
`FaceCapturePage` aplikasi, bukan prototipe kamera sebelumnya. Tidak ada login,
API absensi atau unggahan cloud. Persetujuan pada layar wajib sebelum capture.

Persiapan/build dapat diulang dengan perintah berikut. Jalankan build dari folder
`mobile` di root QA yang dicetak, bukan dari checkout aplikasi utama.

```sh
rtk proxy python3 tools/mobile-qa/prepare_face.py --liveness
rtk proxy flutter pub get
rtk proxy flutter build apk --debug --target lib/liveness_capture_qa.dart --target-platform android-arm64
```

Ambil tiga foto per kategori pada HP/kondisi pencahayaan yang sama:

| Kategori | Yang dihadapkan ke kamera depan |
| --- | --- |
| `live` | Wajah orang secara langsung |
| `print` | Foto cetak wajah orang yang sama |
| `screen` | Foto wajah orang yang sama pada perangkat lain |
| `video` | Video wajah orang yang sama yang sedang diputar pada perangkat lain |

Sampel harus merupakan hasil kamera menghadapi media fisik; file digital atau
screenshot langsung bukan pengganti presentasi cetak/layar/video. Kategori yang
tidak tersedia dilewati dan dicatat sebagai cakupan yang belum diuji.
Klasifikasi berasal dari operator; alat belum membuktikan label/origin kamera.

Foto disimpan sementara dalam cache privat paket QA; OS dapat menghapus cache.
Tombol hapus membersihkan direktori sampel QA. `pull_liveness_samples.py` menyalin
sampel lewat USB ke folder temp privat baru (direktori 0700/file 0600), tanpa
mencetak foto/embedding. Setelah evaluasi selesai, hapus foto di HP dan folder
privat di komputer; simpan hanya laporan agregat. Jangan memasukkan manifest/foto
relawan ke Git.

```sh
rtk proxy python3 tools/mobile-qa/pull_liveness_samples.py
```

Sesi awal satu relawan diberi split `development`, perangkat `android-qa-phone-1`
dan pencahayaan `uncontrolled`. Ini pemeriksaan awal, bukan dataset kalibrasi atau
bukti ketahanan spoof. Jumlah frame tidak sama dengan percobaan independen.

## Evaluator

`services/face-verifier/evaluate_liveness.py` memakai model eksperimental lokal
yang sama dengan endpoint preview. Setup environment/hash/model mengikuti
[README verifier](../services/face-verifier/README.md). Tidak memerlukan service
HTTP, kredensial cloud atau koneksi jaringan saat inferensi.

```sh
rtk proxy python3 services/face-verifier/evaluate_liveness.py --manifest /private/dataset/manifest.json --output /private/dataset/validation.json --validate-only
rtk proxy /path/to/face-poc/venv/bin/python services/face-verifier/evaluate_liveness.py --manifest /private/dataset/manifest.json --output /private/dataset/run-001.json --threshold 0.8
```

Manifest berisi `version: 1` dan array `samples`. Setiap sampel memiliki tepat
`path`, `subject_id`, `session_id`, `device_id`, `lighting`, `kind`, `split`.
`path` relatif pada direktori manifest, hanya JPG/PNG maksimal 2 MB.
Nilai split adalah `development` atau `holdout`.

Evaluator menolak file keluar direktori termasuk symlink, konten foto duplikat,
orang yang sama pada dua split dan metadata sesi tidak konsisten. Gunakan ID
pseudonim yang konsisten untuk orang yang sama. Jangan mengubah ID untuk meloloskan
pemisahan data. Foto yang ditolak detektor dilaporkan terpisah, tidak diam-diam
dihilangkan dari denominator. Kegagalan model/hasil tidak valid membatalkan run.

Laporan hanya agregat: jumlah sampel/sesi/perangkat/kondisi, kategori yang hilang,
penerimaan per kategori, penolakan kualitas dan latency inferensi. Foto, path,
identitas serta embedding tidak disalin ke laporan. Untuk serangan, peluang
setidaknya satu frame diterima per sesi dilaporkan agar retry tidak tersembunyi
di balik rata-rata frame. Ini simulasi keputusan diagnostik satu frame, bukan
verifikasi sesi liveness produksi.

Sweep threshold hanya pada development; holdout hanya dihitung pada threshold
yang dipilih sebelum pengujian. Jangan berulang kali memilih threshold berdasarkan
holdout. Laporan selalu `production_ready=false` dan `attendance_eligible=false`.
Tidak ada threshold yang otomatis diterapkan ke Laravel atau Flutter.

## Gerbang berikutnya

1. Selesaikan kontrol wajah langsung dan ketiga presentasi spoof pada HP nyata.
2. Tambah relawan/perangkat/pencahayaan dan sesi terpisah. Siapkan holdout dengan
   orang berbeda sebelum memilih threshold. Tetapkan target penerimaan serangan
   dan penolakan pengguna sesuai kebutuhan operasional; hasil kohort kecil
   tidak membuktikan target tersebut tercapai pada populasi lain.
3. Bila kandidat gagal memisahkan sampel, evaluasi mesin lain atau jalur cloud
   opsional; jangan menurunkan gerbang sekadar agar demo tampak berhasil.
4. Setelah liveness andal tersedia, implementasikan proof sekali pakai yang
   terikat akun/perusahaan/aksi/referensi dan dikonsumsi atomik bersama aturan
   radius, tanggal serta duplikasi. Integrasi masuk/pulang menunggu tahap ini.

## Pemeriksaan alat

11 tes Python lulus (5 protokol yang sudah ada dan 6 evaluasi baru). Tes baru
menggunakan fixture untuk memeriksa leakage, duplikasi, path, retry, penolakan
kualitas, agregasi, holdout, respons palsu dan CLI yang tidak menimpa laporan;
bukan pengujian akurasi model.
