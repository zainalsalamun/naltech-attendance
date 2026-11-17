# PRD — Penutupan review Dashboard dan pembekuan cakupan fitur

Tanggal: 4 November 2026, Asia/Jakarta.  
Status: Rencana pengerjaan; bukan persetujuan visual atau bukti pengujian baru.

## Tujuan

Siapkan hasil Dashboard H1, H2 dan H5 yang konkret untuk ditinjau pengguna, perbaiki temuan dalam lingkup, dan catat batas fitur saat ini. Jangan membuka siklus penambahan fitur baru hanya untuk menutup review.

Detail pengajuan mobile Izin, Lembur dan Reimbursement sudah disetujui pengguna pada 4 November 2026. Persetujuan tersebut tidak otomatis mencakup Dashboard web. Agen boleh menyelesaikan review teknis dan menyiapkan bukti; persetujuan visual pengguna harus berasal dari pengguna.

## Acuan

Baca AGENTS.md dan instruksi yang dirujuknya, agent.md, design.md, skills.md serta docs/hris-core-timeline.md. Gunakan laporan dan bukti existing berikut sebagai awal:

- docs/hris-web-review-and-h1-refinement-report.md
- docs/hris-h2-shift-assignment-refinement-prd.md
- docs/hris-h5-flow-refinement-report.md
- docs/web-visual-review-report.json
- docs/screenshots/web-visual-review/
- docs/screenshots/web-review-closure/
- docs/screenshots/effective-shifts/

Periksa keberadaan dan relevansi setiap artefak terhadap kode saat ini. Hasil lama tetap diberi tanggal dan lingkungan aslinya. Angka tes yang dilaporkan pengguna tidak boleh disebut rerun agen jika tidak dijalankan.

## Lingkup review

| Area | Halaman/state yang harus dapat ditinjau | Kriteria |
| --- | --- | --- |
| H1 Karyawan | Daftar/filter status, create/edit, unit/atasan nonaktif lama, preview status, arsip, konflik update/arsip | Aktif/nonaktif/arsip jelas; arsip dapat direaktivasi; alasan dan input pulih pada target yang tepat; fokus/modal benar; pesan versi dapat dipahami |
| H2 Jadwal | Pemilih karyawan, riwayat, preview target mendatang dan revisi tanggal sama, konflik, tanpa shift/shift malam | Pembanding tepat pada tanggal target; zona waktu dan tanggal berlaku jelas; tanpa shift tidak dianggap hari libur; copy tanggal kerja mengikuti server; input konflik terpelihara |
| H5 Kehadiran | Daftar, edit koreksi, preview diff, audit, rekap, drilldown dan kembali, ekspor | Field berubah terbaca; tanggal kerja asal jelas; libur/cuti konsisten; filter tetap; angka dapat ditelusuri; label data provisional/historis jujur |

Tema terang dan gelap, desktop 1440×1000 dan sempit 390×844. Periksa teks panjang, kosong, error, keyboard, fokus dan dialog. Tabel dapat bergulir dalam kontainer; body tidak meluap. Jangan mengklaim rasio kontras atau ukuran target sentuh terukur hanya dari pengamatan screenshot.

## Alur pelaksanaan

1. Periksa working tree dan artefak, buat daftar halaman/state serta status bukti: relevan, usang atau belum tersedia.
2. Tinjau screenshot aktual dan interaksi yang penting. Pakai bukti yang ada bila masih mewakili implementasi. Buat ulang hanya bila usang, kurang lengkap, atau ada perubahan terkait.
3. Jika perlu browser live, gunakan server/storage/database QA disposable dan data sintetis. Siapkan navigasi dan fixture agar pengguna dapat melihat hasil tanpa memakai data/kredensial produksi.
4. Catat temuan dengan halaman, langkah reproduksi, dampak, screenshot dan prioritas. Perbaiki bug konkret dalam lingkup; jangan redesign menyeluruh, menambah framework atau modul.
5. Jalankan verifikasi yang terdampak perubahan saja. Perubahan bisnis/otorisasi memerlukan regresi terkait; ulangi suite penuh hanya bila perubahan atau kegagalan membenarkannya. Tidak perlu mengulang seluruh suite untuk perubahan label sederhana.
6. Siapkan paket review berupa dokumen Markdown dengan gambar inline atau tautan yang dapat dibuka, dikelompokkan per H1/H2/H5. Sertakan tampilan sebelum/sesudah bila ada perbaikan, dan URL lokal hanya jika server benar-benar masih tersedia. Jangan meninggalkan server tanpa mencatat status/cara menghentikannya.
7. Tandai hasil teknis selesai dan review pengguna menunggu keputusan apabila belum ada jawaban. Jangan meminta persetujuan ulang detail mobile yang sudah disetujui. Status menunggu review bukan alasan meninggalkan audit/perbaikan independen yang masih bisa dikerjakan.

## Matriks penerimaan

| ID | Bukti minimum |
| --- | --- |
| C01 | H1 daftar/edit/status/arsip pada kedua tema dan ukuran, termasuk retensi organisasi lama |
| C02 | H1 konflik update dan arsip: notice, input alasan target benar, data tidak termutasi pada penolakan |
| C03 | H2 preview tanggal mendatang dan revisi tanggal sama: baseline sesuai resolver, bukan sekadar jadwal hari ini |
| C04 | H2 back tanpa mutasi, konfirmasi satu kali, konflik dengan input pulih, copy malam/tanpa shift benar |
| C05 | H5 preview diff, bantuan tanggal, audit before/after, dan state gagal/kosong |
| C06 | H5 rekap/drilldown: hari libur tetap status utama, izin menunggu pembatalan tetap efektif, filter/kembali/CSV konsisten |
| C07 | Keyboard, fokus, teks panjang dan overflow untuk area yang ditinjau |
| C08 | Paket review dapat dibuka pengguna dan setiap gambar/state diberi konteks |
| C09 | Dokumen batas cakupan membedakan selesai, menunggu review, ditunda dan backlog |

Status per ID: lulus/gagal/belum diuji/terkendala. Screenshot membuktikan tampilan, bukan secara otomatis otorisasi atau mutasi DB. Untuk klaim interaksi/mutasi, tautkan assertion aktual. Dilarang stub selalu lulus.

## Pembekuan cakupan

Buat snapshot status fitur dari bukti yang tersedia, tanpa klaim seluruh HRIS siap produksi:

- Detail pengajuan mobile: disetujui pengguna 4 November 2026; sertakan cakupannya.
- H1/H2/H5 web: implementasi dan bukti teknis dirujuk, status review pengguna dicatat terpisah.
- Approval, cuti/saldo, lembur/reimbursement dan mobile jadwal: catat status serta batas pengujian dari laporan, bukan menguji ulang semuanya.
- Absensi wajah produksi: ditunda sampai verifikasi server andal; jangan menyalakannya untuk menutup milestone.
- Rotasi otomatis, bulk assignment, employee search dropdown, kalender visual dan split shift: backlog opsional, bukan blocker penutupan cakupan sekarang.
- Commit, push, deployment, pengemasan dan instalasi pembeli: tetap ditunda. Pembekuan cakupan bukan izin melakukan aksi tersebut.

## Keluaran dan batas lingkungan

- docs/hris-dashboard-user-review.md: paket visual dengan gambar/tautan, skenario, catatan perubahan dan kolom keputusan pengguna per area.
- docs/hris-dashboard-review-closure-report.md: audit, temuan/perbaikan, C01–C09, perintah/hasil aktual, batasan dan cleanup.
- docs/hris-dashboard-review-closure-results.json: status terstruktur dan rujukan bukti.
- docs/hris-feature-scope-status.md: snapshot cakupan selesai/menunggu review/ditunda/backlog, tanggal dan sumber bukti.
- Screenshot baru bila perlu di docs/screenshots/dashboard-review-closure/. Pertahankan bukti lama.
- Perbarui timeline sesuai fakta; bila pengguna belum menyetujui web, tetap tulis menunggu review pengguna.

Semua shell command diawali rtk. Jangan mereset/menimpa perubahan pihak lain. Jangan memutasi DB utama, migrate/seed/reset utama, mengedit .env/Firebase, atau mengekspos token/password. Semua pengujian memakai QA terisolasi. Bersihkan hanya resource buatan tugas. Tidak mengerjakan mobile atau backlog baru. Tidak commit/push/deploy/package.

Definisi selesai tugas agen: paket review siap dibuka, temuan konkret tertangani atau hambatan dijelaskan, verifikasi relevan selesai, dan status fitur jujur. Persetujuan pengguna tetap dicatat menunggu sampai diterima; jangan menyatakan tugas menghasilkan persetujuan yang belum diberikan.
