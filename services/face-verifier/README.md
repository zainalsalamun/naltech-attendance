# Verifier wajah server sendiri — PoC

Layanan lokal untuk matching 1:1 memakai OpenCV YuNet dan SFace. **Tidak melakukan liveness dan tidak boleh digunakan sebagai otorisasi absensi.** Laravel tetap menolak absensi Face; preview pencocokan adalah endpoint terpisah yang default-nya nonaktif.

Diagnostik anti-spoofing NCNN tersedia sebagai PoC opsional di bawah. Ini bukan liveness produksi dan tidak mengubah larangan tersebut.

## Menjalankan percobaan

Gunakan lingkungan virtual dan direktori model di luar checkout. Persyaratan pada `requirements.txt` dipin; tidak mengubah Python global. Contoh perintah menggunakan placeholder path milik operator:

```sh
rtk proxy python3 -m venv /path/to/face-poc/venv
rtk proxy /path/to/face-poc/venv/bin/python -m pip install -r services/face-verifier/requirements.txt
rtk proxy python3 services/face-verifier/download_models.py --directory /path/to/face-poc/models
```

Downloader memakai commit OpenCV Zoo yang dipin, memeriksa SHA256 terhadap pointer Git LFS resmi, menyimpan `manifest.json` dan lisensi upstream. Tidak mengunduh model secara diam-diam saat service dimulai dan tidak membundel bobot model ke repository produk. Audit provenance/hak redistribusi bobot tetap perlu diselesaikan sebelum paket komersial dibundel; lisensi direktori upstream bukan hasil audit hukum seluruh dataset pelatihan.

Siapkan environment service pada lingkungan lokal, jangan menyimpan secret ke Git atau log:

- `FACE_SERVICE_KEY`: secret acak minimal 32 karakter.
- `FACE_YUNET_MODEL`, `FACE_SFACE_MODEL`: path ke file model hasil unduhan.
- `FACE_YUNET_SHA256`, `FACE_SFACE_SHA256`: hash dari manifest yang diverifikasi.
- `FACE_SERVICE_PORT`: default 8021.

```sh
rtk proxy /path/to/face-poc/venv/bin/python services/face-verifier/server.py
```

Bind selalu `127.0.0.1`. Ini server HTTP standar Python yang memproses satu request pada satu waktu untuk percobaan, bukan deployment produksi. Untuk produksi perlu worker terbatas, reverse proxy/network policy, timeout, pengukuran kapasitas dan perlindungan akses. Jangan membuka port ini ke internet.

## API internal

Bearer authentication wajib, termasuk `/health`. Secret hanya berada di Laravel dan service, tidak dikirim ke Flutter.

- `GET /health`: engine dan batas kemampuan, tanpa data pengguna.
- `POST /v1/compare`: JSON `reference` dan `probe` berisi base64 JPEG/PNG. Masing-masing maksimal 2 MB, dimensi 320–4096, tepat satu wajah dan ukuran wajah minimum. Foto diproses di memori dan tidak ditulis ke disk. Respons berisi cosine similarity, jumlah wajah, `liveness: not_configured` dan `attendance_eligible: false`. Similarity bukan probabilitas atau sertifikasi identitas.
- `POST /v1/liveness`: HTTP 501, `liveness_not_implemented`. Jangan mengubahnya menjadi `passed` berdasarkan matching atau kedipan sederhana.

Layanan tidak mencetak header, foto, template/embedding atau body request. Output numerik similarity hanya dipakai Laravel untuk preview; tidak dikembalikan sebagai proof absensi. Request selain dua field yang ditetapkan ditolak. File model dimuat sekali, hash diverifikasi, dan komputasi detector dibatasi ukuran gambar.

## Diagnostik anti-spoofing eksperimental

Jalur opsional memakai MiniFASNet NCNN pada commit upstream yang dipin. Install core dahulu, lalu bindings dengan **`--no-deps`** agar dependency model-zoo NCNN tidak menarik distribusi GUI OpenCV kedua. PoC ini tidak memakai model-zoo/utility downloader NCNN.

```sh
rtk proxy /path/to/face-poc/venv/bin/python -m pip install --no-deps -r services/face-verifier/requirements-liveness.txt
rtk proxy python3 services/face-verifier/download_liveness_models.py --directory /path/to/face-poc/liveness-models
```

Set `FACE_LIVENESS_EXPERIMENTAL_ENABLED=true` dan `FACE_LIVENESS_MODEL_DIR` ke direktori unduhan, lalu restart service. Downloader memverifikasi Git blob pada commit `83329c97ba1153e2bbdd374dee1b5d33a7367dc7` dan menyimpan SHA256 artefak/konfigurasi/lisensi; bobot di luar repository.

`POST /v1/liveness-preview` menerima JSON `probe` base64 dengan auth/limit yang sama. Hasil selalu `liveness: experimental` dan `attendance_eligible: false`. Threshold diagnostik 0,8 belum dikalibrasi. Endpoint tidak dihubungkan ke UI karyawan atau otorisasi absensi; `/v1/liveness` tetap HTTP 501. Adapter Laravel matching tetap menolak respons yang mengklaim proof/liveness, termasuk output eksperimental ini.

Praproses/scoring mengacu implementasi Minivision; [lisensi upstream](notices/Minivision-LICENSE) disertakan untuk port kode. Audit hak/provenance bobot tetap diperlukan sebelum redistribusi komersial. Uji file digital bukan uji cetak/layar/video atau injection kamera. Belum ada dataset presentation attack, positive control kamera, kalibrasi threshold atau pengukuran FPR/FNR.

## Kandidat MN3 terpisah

[PoC anti-spoof-mn3 dan hasil perangkat](../../docs/face-mn3-poc.md) tersedia.
Model original ONNX diunduh dengan SHA384 resmi yang dipin, di luar checkout:

```sh
rtk proxy python3 services/face-verifier/download_mn3.py --directory /path/to/face-poc/mn3-models
```

Dengan core requirements yang sama, pilih `FACE_LIVENESS_EXPERIMENTAL_ENGINE=mn3`
dan `FACE_MN3_MODEL_DIR`, serta flag eksperimen yang sudah disebutkan di atas.
Tidak perlu NCNN untuk MN3. OpenCV CPU memakai praproses original RGB, bukan
kontrak BGR model IR. Unknown engine menggagalkan startup; tidak ada auto-fallback.

Audit opsional dengan ONNX Runtime memakai `requirements-mn3-audit.txt` di venv
privat dan `tools/mobile-qa/audit_mn3_runtime.py`. MN3 **gagal uji spoof awal pada
HP**; adapter ini tetap riset, bukan konfigurasi absensi produksi. Semua hasil
tetap `experimental` / `attendance_eligible=false`.

## Menghubungkan Laravel untuk preview

Setelah migrasi pendaftaran wajah diterapkan pada lingkungan yang dipilih operator, siapkan konfigurasi lokal:

```dotenv
FACE_MATCHING_PREVIEW_ENABLED=true
FACE_PROVIDER=self_hosted
FACE_SELF_HOSTED_URL=http://127.0.0.1:8021
FACE_SELF_HOSTED_KEY=<secret yang sama dengan service>
```

Jangan menyalin placeholder sebagai secret. Panggilan backend memakai reference privat yang telah disetujui untuk akun/perusahaan aktif; request Flutter tidak bisa memilih reference orang lain. URL provider hanya berasal dari konfigurasi server dan redirect HTTP tidak diikuti. Threshold 0,5 di `config/face.php` hanya parameter PoC, belum dikalibrasi untuk operasional. Respons yang mengklaim liveness/otorisasi absensi ditolak oleh adapter preview ini.

Provider cloud tetap arah opsional pada [keputusan arsitektur](../../docs/design/face-verification-engine-decision.md); belum ada AWS credential, provisioning atau API cloud yang dipanggil. Kontrak preview ini bukan pengganti integrasi sesi liveness cloud.

## Pengujian

Alat pengambilan sampel privat dan evaluator development/holdout dijelaskan di
[protokol validasi liveness](../../docs/face-liveness-validation.md).
Evaluator tidak mengaktifkan Face atau mempromosikan threshold secara otomatis.

```sh
rtk proxy python3 -m unittest discover -s services/face-verifier -p 'test_*.py' -v
```

Tes HTTP menggunakan verifier fixture, bukan bukti akurasi model. Pengujian model nyata dicatat terpisah. Sebelum produksi, wajib menguji identitas berbeda, foto cetak, layar/video replay, perangkat/pencahayaan beragam dan kegagalan layanan. Matching tidak akan membedakan selfie hidup dengan salinan foto yang sama; karena itu Face tetap tidak aktif sampai liveness yang andal tersedia.
