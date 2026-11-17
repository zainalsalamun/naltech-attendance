# Prompt Antigravity — Review H5 dan penyempurnaan H1

Tanggal: 30 Oktober 2026, Asia/Jakarta. Salin blok berikut.

```text
Kerjakan review visual Dashboard H5 dan audit/perbaikan lifecycle karyawan H1 di:
/Users/macbookpro/development/fic/absensi/naltech-attendance

PRD utama: docs/hris-web-review-and-h1-refinement-prd.md
Baca seluruh PRD, AGENTS.md beserta RTK.md yang dirujuknya, agent.md, design.md, skills.md dan dokumentasi acuan PRD. Semua perintah shell wajib diawali rtk.

Periksa Git dan implementasi existing terlebih dahulu. Pertahankan seluruh perubahan lokal pihak lain. Jangan mengulang pembangunan modul yang sudah tersedia. Buat pemetaan kebutuhan A–E terhadap kode, tes, celah yang terbukti dan aturan yang ambigu.

Urutan:
1. Review form koreksi, preview before/after, audit, rekap dan drilldown H5 pada light/dark, 1440x1000 dan 390x844. Manfaatkan bukti lama secara jujur; perbaiki UX bila ada masalah. Jangan mengulang suite H5 yang sudah lulus tanpa perubahan atau kekurangan bukti terkait.
2. Audit H1: role/cakupan, nomor induk, masa kerja, transisi nonaktif/arsip/reaktivasi, alasan, preview, audit atomik, konflik stale form, self-deactivation dan perlindungan admin terakhir.
3. Audit atasan/unit dan dampak pada pengajuan existing vs baru. Hubungan organisasi tidak sama dengan otoritas reviewer. Pertahankan kontrak snapshot/resolver, fallback sah, isolasi tenant dan larangan self-approval. Jangan rewrite massal snapshot atau memindahkan pengajuan secara diam-diam.
4. Buktikan pencabutan akses lama dan pelestarian riwayat setelah transisi, serta reaktivasi yang tidak menghidupkan token lama. Jangan menyimpulkan riwayat penempatan/jeda kerja yang tidak tersedia.
5. Perbaiki hanya celah yang terbukti dan jelas aturannya. Bila aturan akses ambigu, dokumentasikan dan minta keputusan sebelum memperluas akses; lanjutkan pekerjaan independen yang tidak bergantung jawaban.

Ikuti acceptance criteria dan matriks R01–R11 PRD. Untuk klaim submit tepat sekali, periksa request serta DB/event. Untuk race admin terakhir/concurrency gunakan worker dan koneksi MySQL QA independen; SQLite atau dua panggilan berurutan bukan bukti locking. Uji scoped terlebih dahulu, lalu regresi backend penuh setelah perubahan bisnis selesai bila tersedia. Jangan mengklaim tes yang belum dijalankan.

Gunakan DB/storage QA disposable dan fixture sintetis. Baca tools/mysql-qa/README.md sebelum MySQL QA. Dilarang memutasi database utama, migrate/seed/reset utama, mengedit .env atau Firebase, mencetak token/password, reset Git, commit, push, deploy dan pengemasan. Migrasi aditif bila diperlukan hanya diuji di QA dan dicatat belum diterapkan utama.

Keluaran wajib:
- Perubahan kode/tes dalam lingkup bila memang ditemukan celah.
- docs/hris-web-review-and-h1-refinement-report.md: baseline, pemetaan A–E, temuan/perbaikan, R01–R11 beserta bukti aktual, hasil lama vs rerun baru, batasan, kebutuhan keputusan dan cleanup.
- docs/hris-web-review-and-h1-refinement-results.json: status lulus/gagal/belum diuji/terkendala per skenario.
- Screenshot aktual di docs/screenshots/web-review-h1-refinement/ tanpa data pribadi/rahasia.
- Update docs/hris-core-timeline.md hanya berdasarkan hasil nyata.

Review visual oleh agen tidak menggantikan persetujuan pengguna: tandai hasil Dashboard yang menunggu review pengguna. Audit H1 boleh berjalan sambil menunggu. Jangan lanjut implementasi H2/mobile; catat kebutuhan lanjut sebagai backlog saja. Hentikan dan bersihkan hanya server/resource QA yang dibuat tugas ini. Akhiri dengan perubahan, tes aktual, temuan tersisa dan daftar tampilan yang siap direview pengguna.
```
