# Prompt Antigravity — Penutupan review Dashboard

Tanggal: 4 November 2026, Asia/Jakarta. Salin blok berikut.

```text
Siapkan penutupan review visual Dashboard H1, H2 dan H5 serta snapshot batas fitur pada repositori:
/Users/macbookpro/development/fic/absensi/naltech-attendance

PRD utama: docs/hris-dashboard-review-closure-prd.md

Baca PRD penuh, AGENTS.md beserta instruksi yang dirujuknya, agent.md, design.md, skills.md dan timeline. Semua shell command diawali rtk. Periksa working tree dan pertahankan perubahan lokal pihak lain.

Tujuan: hasil Dashboard yang konkret dan mudah ditinjau pengguna, perbaikan bug dalam lingkup bila ditemukan, dan dokumen cakupan fitur saat ini. Jangan menambah modul atau backlog. Detail pengajuan mobile sudah disetujui pengguna pada 4 November 2026; jangan meminta persetujuan ulang mobile. Dashboard web masih menunggu review pengguna.

Kerjakan:
1. Audit bukti H1/H2/H5 yang sudah ada sesuai daftar pada PRD. Periksa apakah screenshot dan laporan masih mewakili kode saat ini. Jangan mengubah hasil lama menjadi seolah rerun baru.
2. Review visual tema light/dark, desktop 1440x1000 dan sempit 390x844, keyboard/fokus, teks panjang, empty/error. Periksa khusus konflik update/arsip H1 dan pemulihan alasan untuk target yang tepat; baseline shift pada tanggal target termasuk revisi tanggal sama; koreksi diff H5, tanggal checkout, audit, rekap/drilldown/filter/CSV.
3. Bila ada celah konkret, reproduksi dan perbaiki hanya dalam lingkup. Pertahankan otorisasi, tenant, versi, snapshot sejarah, approval/saldo dan aturan kalender. Jangan redesign atau menambah dependency tanpa kebutuhan nyata.
4. Gunakan bukti lama bila masih relevan. Jalankan browser/tes ulang hanya untuk bukti yang kurang, usang atau terdampak perubahan. Klaim mutasi membutuhkan assertion request/DB, screenshot saja tidak cukup. Jangan menjalankan seluruh suite berulang tanpa alasan.
5. Buat paket review Markdown yang dapat dibuka pengguna, dengan gambar inline/tautan aktual per H1/H2/H5, keterangan halaman/state/tema/viewport, catatan yang perlu dilihat dan kolom keputusan pengguna. Tautkan URL lokal hanya bila server masih tersedia. Jangan berhenti pada kalimat 'silakan review' tanpa hasil konkret yang bisa dibuka.
6. Buat snapshot status fitur: selesai sesuai bukti, menunggu review pengguna, ditunda, backlog. Wajah produksi tetap ditunda; rotasi, bulk assignment, employee search, kalender visual dan split shift tetap backlog. Jangan menyatakan seluruh HRIS siap produksi.

Deliverable:
- docs/hris-dashboard-user-review.md
- docs/hris-dashboard-review-closure-report.md
- docs/hris-dashboard-review-closure-results.json (C01-C09: lulus/gagal/belum diuji/terkendala dan rujukan bukti)
- docs/hris-feature-scope-status.md
- Screenshot baru bila perlu: docs/screenshots/dashboard-review-closure/
- Update docs/hris-core-timeline.md sesuai fakta.

Pakai data sintetis serta DB/storage QA disposable jika perlu menjalankan app. Dilarang memutasi DB utama, migrate/seed/reset utama, mengedit .env/Firebase, mencetak rahasia, mereset Git, commit, push, deploy atau mengemas source code. Bersihkan hanya resource yang dibuat tugas ini dan catat status server.

Agen tidak berwenang menyatakan review pengguna disetujui tanpa jawaban pengguna. Selesaikan seluruh audit/perbaikan dan paket review yang dapat dikerjakan, lalu laporkan web menunggu review pengguna bila belum ada persetujuan. Tidak mengerjakan mobile atau backlog. Akhiri dengan tautan paket review, temuan/perbaikan, verifikasi aktual, batasan dan status cakupan.
```
