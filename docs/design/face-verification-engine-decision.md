# Pilihan mesin verifikasi wajah

Tanggal evaluasi: 20 Oktober 2026. Status: keputusan arah integrasi; belum aktivasi verifier produksi.

## Keputusan

Pengguna memilih **layanan cloud opsional dan jalur server sendiri**. Backend Laravel tetap menjadi pengambil keputusan absensi; Flutter tidak menjadi sumber bukti identitas tepercaya. Provider harus dapat diganti tanpa mengubah aturan absensi. Tidak ada langganan, provisioning cloud, model baru, atau pengiriman foto ke pihak ketiga pada tahap ini.

**Cloud kandidat pertama untuk proof of concept: AWS Rekognition CompareFaces + Face Liveness.** CompareFaces sendiri hanya matching, bukan liveness. Face Liveness memakai video singkat dengan tantangan, bukan satu JPEG. Karena itu prototipe selfie satu foto hanya menguji capture/detection; hasilnya tidak menjadi bukti kecepatan/verifikasi AWS. Dokumentasi AWS mencantumkan komponen React, Android dan Swift; Flutter memerlukan integrasi native yang harus diuji tersendiri. Jangan menjanjikan SDK Flutter siap pakai atau UI kamera bebas diubah sebelum spike integrasi native.

**Server sendiri: arah yang didukung, mesin/model belum dikunci.** DeepFace menjadi kandidat evaluasi wrapper layanan terpisah. Library berlisensi MIT dan menyediakan pilihan recognition serta anti-spoofing, tetapi setiap model/detector/bobot perlu diperiksa hak penggunaan dan redistribusinya secara terpisah. Jangan langsung menyalin model ke paket komersial. Model InsightFace publik tidak dipilih sebagai bawaan karena README menyatakan bobot yang disediakan hanya untuk riset nonkomersial. Lisensi kode yang permisif tidak menyelesaikan batas penggunaan bobot.

Kandidat PoC server sendiri yang lebih spesifik: **OpenCV YuNet untuk deteksi/alignment + SFace untuk matching**, sebagai alternatif menjalankan wrapper DeepFace yang lebih luas. README direktori YuNet menyatakan MIT dan direktori SFace menyatakan Apache-2.0. Ini dasar shortlist, bukan bukti bahwa seluruh provenance bobot dan hak redistribusi paket sudah diaudit. Pin versi/checksum bobot dan sertakan notice sebelum dibundel. Keduanya tidak menyediakan liveness dengan sendirinya. MiniFASNet/Silent-Face-Anti-Spoofing menjadi kandidat uji anti-spoofing tersendiri; dokumentasi pembuatnya menyebut hasil RGB bergantung pada kamera dan kondisi. Tidak mengklaim setara dengan liveness AWS atau siap produksi.

| Jalur | Nilai praktis | Syarat sebelum dipakai |
| --- | --- | --- |
| Cloud opsional | Infrastruktur model dikelola provider; tersedia matching dan liveness | Akun pembeli, region/kuota dan biaya, bridge native Flutter, uji jaringan dan data, kalibrasi threshold |
| Server sendiri | Pembeli mengendalikan lokasi layanan dan operasional | Model dengan hak yang sesuai, kebutuhan CPU/RAM terukur, anti-spoofing teruji, deployment dan monitoring |

Tidak ada klaim bahwa salah satu lebih akurat, lebih murah atau lebih cepat: belum dilakukan benchmark kedua verifier. Tidak memasukkan harga karena region, jumlah pengguna, frekuensi absensi dan retry belum ditentukan.

## Kontrak alur yang dituju

1. Pengguna login dan meminta sesi verifikasi sesuai perusahaan serta aksi masuk/pulang. Backend memeriksa metode yang diizinkan.
2. Backend mengikat sesi pada akun, perusahaan, aksi, enrollment aktif dan masa berlaku. Sesi cloud dimulai backend; akses mobile harus terbatas dan sementara, bukan AWS secret permanen di aplikasi.
3. Provider melakukan pemeriksaan. Untuk cloud, backend mengambil hasil liveness dan memakai reference image hasil sesi untuk matching terhadap enrollment yang disetujui. Jangan mencocokkan foto unggahan terpisah yang tidak terikat dengan bukti liveness.
4. Backend memeriksa hasil dan threshold yang dikalibrasi; callback mobile hanya berarti proses UI selesai, bukan verifikasi berhasil.
5. Absensi memakai bukti sekali pakai yang dikonsumsi secara atomik bersama pemeriksaan radius/duplikasi. Sesi akun/perusahaan/aksi lain, kedaluwarsa atau telah digunakan harus ditolak.
6. Jika provider gagal atau belum dikonfigurasi, jangan menerima absensi Face. QR hanya ditawarkan jika kebijakan perusahaan mengizinkan.

Untuk AWS, sesi liveness berlaku tiga menit dan satu sesi digunakan untuk satu pemeriksaan. Laravel perlu menyimpan status/binding miliknya sendiri dan memproses hasil sebelum masa berlaku habis. Implementasi ini belum dibuat pada tahap evaluasi.

## Enrollment dan data

Pendaftaran wajah harus mengikat identitas karyawan yang benar melalui prosedur HR/admin. Perubahan foto profil tidak otomatis mengganti enrollment. Server menyimpan referensi secara privat dengan akses terbatas; tidak memakai public storage foto profil. Definisikan retensi, penghapusan saat nonaktif, pencabutan enrollment dan audit perubahan. Jangan menaruh foto, embedding, session credential atau hasil mentah provider di log.

## Kriteria lolos sebelum integrasi absensi

- Karyawan yang sama dan berbeda diuji dalam pencahayaan/perangkat yang beragam; tetapkan threshold dari data uji, bukan angka contoh dokumentasi.
- Uji foto cetak, layar ponsel, video replay, frame kosong dan lebih dari satu wajah. Jangan menamai kedipan sederhana sebagai liveness yang sudah andal.
- Uji sesi kedaluwarsa, replay, pergantian akun/perusahaan/aksi, submit bersamaan, timeout dan provider tidak tersedia.
- Ukur upload, proses provider, total respons, retry, kebutuhan server dan penggunaan biaya; pisahkan dari waktu deteksi lokal.
- Uji Android dan iOS dengan bridge provider yang nyata. Tidak ada klaim kesetaraan dengan Talenta.

## Batas hasil tahap ini

Yang selesai: eksplorasi sumber primer, pilihan arsitektur dua provider dan kandidat cloud untuk percobaan berikutnya. Belum selesai: model self-host dengan hak redistribusi terverifikasi, layanan cloud yang dikonfigurasi, enrollment, matching/liveness nyata, threshold dan integrasi absensi. Face produksi tetap diblokir.

## Evaluasi setelah audit — 21 Oktober 2026

[Audit adapter](../face-liveness-adapter-audit.md) menyamakan geometri dengan
referensi C++ pada 1.005 fixture; format BGR, input 80×80 tanpa normalisasi dan
kelas hidup sudah sesuai. Empat capture baru setelah koreksi tetap menerima
wajah langsung dan ketiga sampel berlabel cetak/layar/video. Satu sampel per
kategori bukan benchmark populasi, tetapi sudah cukup untuk menahan integrasi
kandidat ini. Memakai wrapper lain atas bobot MiniFASNet yang sama belum menjadi
alternatif independen atau bukti masalah selesai.

| Kandidat | Bentuk pemeriksaan | Status dan pekerjaan berikutnya |
| --- | --- | --- |
| MiniFASNet NCNN saat ini | Diagnostik satu gambar, dua skala crop | Gagal uji awal dan uji ulang lokal. Simpan sebagai eksperimen; jangan dipakai sebagai proof |
| anti-spoof-mn3 | Klasifikasi satu gambar berbasis MobileNetV3; keluarga model berbeda | Shortlist PoC server sendiri berikutnya. Belum diunduh/dijalankan; perlu pin artefak, audit hak model, verifikasi runtime dan uji kamera yang sama |
| AWS Face Liveness opsional | Sesi video dengan challenge, hasil diambil backend | Tetap kandidat cloud sesuai pilihan pengguna; belum ada akun/region/biaya/bridge native yang dikonfigurasi atau panggilan layanan |

Dokumentasi resmi [anti-spoof-mn3](https://docs.openvino.ai/2023.3/omz_models_model_anti_spoof_mn3.html)
mencatat input original RGB 128×128 dengan mean/scale tertentu, sedangkan model
hasil konversi mengharapkan BGR; kelas 0 hidup, kelas 1 spoof. Karena berbeda dari
MiniFASNet, praproses dan indeks kelas tidak boleh dipakai ulang. Angka benchmark
upstream tidak menjadi estimasi hasil aplikasi ini. Dukungan runtime/server CPU
dan checksum harus diperiksa pada artefak yang benar sebelum implementasi.

[AWS Face Liveness](https://docs.aws.amazon.com/rekognition/latest/dg/face-liveness.html)
memakai sesi video singkat, challenge, SDK klien dan hasil confidence/reference
yang diambil server. Integrasi Flutter memerlukan evaluasi bridge native; ini
bukan mengganti URL endpoint JPEG. Provider tetap perlu diuji pada kondisi
pengguna dan threshold yang dipilih, tanpa janji selalu menolak spoof.

Urutan: pertahankan matching YuNet/SFace yang terpisah; siapkan PoC anti-spoof-mn3
terisolasi dan bandingkan pada media fisik yang sama. Jalur AWS dipertahankan
opsional dengan konfigurasi milik pembeli. Pemilihan ini adalah prioritas riset,
bukan klaim kandidat baru lebih akurat atau siap dijual. Jangan mengaktifkan
Face, mengganti metode perusahaan atau mengirim foto ke cloud secara otomatis.

## Pembaruan PoC MN3 — 21 Oktober 2026

[PoC MN3](../face-mn3-poc.md) kini diimplementasikan dan diuji lokal, menggantikan
status shortlist MN3 di tabel evaluasi sebelumnya. Bobot original ONNX dipin
melalui SHA384 resmi, tetap di luar checkout; adapter dan lisensi sumber dicatat.
Empat capture baru menerima seluruh label hidup/cetak/layar/video. Kontrol
MiniFASNet pada foto identik juga menerima semua. Dua runtime MN3 memberikan
keputusan sama; tidak ada bukti perbaikan dengan sekadar mengganti runtime.

Keputusan: kedua kandidat satu-gambar ini belum memenuhi gerbang absensi pada
kondisi uji. Jangan menghabiskan kalibrasi populasi pada kandidat yang belum
menolak serangan dasar sesi ini. Evaluasi sesi/video menjadi prioritas berikut,
dengan AWS tetap opsi yang memerlukan konfigurasi pembeli dan uji nyata, bukan
dianggap otomatis lolos. Server sendiri tetap didukung sebagai arah arsitektur,
belum memiliki kandidat liveness yang layak dari dua PoC tersebut.

## Sumber primer awal

- [AWS Face Liveness](https://docs.aws.amazon.com/rekognition/latest/dg/face-liveness.html): video, reference image dan komponen integrasi.
- [AWS CompareFaces](https://docs.aws.amazon.com/rekognition/latest/APIReference/API_CompareFaces.html): matching wajah.
- [AWS CreateFaceLivenessSession](https://docs.aws.amazon.com/rekognition/latest/APIReference/API_CreateFaceLivenessSession.html): satu sesi per pemeriksaan dan masa berlaku.
- [Amplify Android Liveness](https://ui.docs.amplify.aws/android/connected-components/liveness): integrasi native dan kredensial.
- [DeepFace README](https://github.com/serengil/deepface/blob/master/README.md), [lisensi library](https://github.com/serengil/deepface/blob/master/LICENSE): kandidat wrapper, bukan persetujuan seluruh model.
- [InsightFace Python README](https://github.com/deepinsight/insightface/blob/master/python-package/README.md): perbedaan lisensi kode dan pretrained weights.
- [OpenCV Zoo SFace](https://github.com/opencv/opencv_zoo/blob/main/models/face_recognition_sface/README.md): recognition, alignment dan pernyataan lisensi direktori.
- [OpenCV Zoo YuNet](https://github.com/opencv/opencv_zoo/blob/main/models/face_detection_yunet/README.md): detector dan pernyataan lisensi direktori.
- [Silent-Face-Anti-Spoofing](https://github.com/minivision-ai/Silent-Face-Anti-Spoofing/blob/master/README_EN.md): kandidat anti-spoofing dan keterbatasan kondisi kamera.
