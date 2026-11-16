import 'dart:async';
import 'dart:convert';
import 'dart:io';
import 'dart:typed_data';
import 'package:dartz/dartz.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:image_picker/image_picker.dart';
import 'package:shared_preferences/shared_preferences.dart';

import 'package:flutter_absensi_app/data/datasources/auth_local_datasource.dart';
import 'package:flutter_absensi_app/data/datasources/submission_remote_datasource.dart';
import 'package:flutter_absensi_app/data/models/response/auth_response_model.dart';
import 'package:flutter_absensi_app/data/models/response/leave_policy_record.dart';
import 'package:flutter_absensi_app/data/models/response/submission_record.dart';
import 'package:flutter_absensi_app/presentation/home/pages/submissions/submission_form_page.dart';
import 'package:flutter_absensi_app/presentation/home/pages/submissions/submission_list_page.dart';
import 'profile_session_test.dart' show app, controller;

const transparentPng = [
  0x89, 0x50, 0x4e, 0x47, 0x0d, 0x0a, 0x1a, 0x0a, 0x00, 0x00, 0x00, 0x0d,
  0x49, 0x48, 0x44, 0x52, 0x00, 0x00, 0x00, 0x01, 0x00, 0x00, 0x00, 0x01,
  0x08, 0x06, 0x00, 0x00, 0x00, 0x1f, 0x15, 0xc4, 0x89, 0x00, 0x00, 0x00,
  0x0a, 0x49, 0x44, 0x41, 0x54, 0x78, 0x9c, 0x63, 0x00, 0x01, 0x00, 0x00,
  0x05, 0x00, 0x01, 0x0d, 0x0a, 0x2d, 0xb4, 0x00, 0x00, 0x00, 0x00, 0x49,
  0x45, 0x4e, 0x44, 0xae, 0x42, 0x60, 0x82
];

void main() {
  TestWidgetsFlutterBinding.ensureInitialized();

  setUp(() {
    SharedPreferences.setMockInitialValues({});
  });

  Future<void> loginAs({int id = 7, String token = 'fixture-token-7'}) =>
      AuthLocalDatasource().saveAuthData(
          AuthResponseModel(user: User(id: id), token: token));

  group('1. Validasi Batas Ukuran File & Preservasi Lampiran', () {
    testWidgets(
        'Tolak file > 2.048 KiB (2.097.152 byte) dan pertahankan pilihan valid sebelumnya',
        (tester) async {
      tester.view.physicalSize = const Size(390, 1000);
      tester.view.devicePixelRatio = 1;
      addTearDown(tester.view.resetPhysicalSize);
      addTearDown(tester.view.resetDevicePixelRatio);

      await loginAs();
      final valid2048KiB =
          XFile.fromData(Uint8List(2097152), name: 'valid_exact_2mb.png', path: 'valid_exact_2mb.png');
      final oversized2048KiBPlus1 =
          XFile.fromData(Uint8List(2097153), name: 'too_large.png', path: 'too_large.png');
      final invalidExt =
          XFile.fromData(Uint8List(100), name: 'document.pdf', path: 'document.pdf');

      XFile? nextImage = valid2048KiB;

      final formSource = _TestSubmissionFixture();

      await tester.pumpWidget(app(
        await controller(),
        SubmissionFormPage(
          kind: SubmissionKind.reimbursement,
          source: formSource,
          pickDate: () async => DateTime(2026, 10, 20),
          pickImage: () async => nextImage,
        ),
      ));

      // 1. Pilih lampiran valid tepat 2.048 KiB (2.097.152 byte)
      await tester.ensureVisible(find.text('Pilih lampiran'));
      await tester.pump();
      await tester.pump(const Duration(milliseconds: 100));
      await tester.tap(find.text('Pilih lampiran'));
      await tester.pump();
      await tester.pump(const Duration(milliseconds: 100));

      expect(find.text('valid_exact_2mb.png'), findsOneWidget);
      expect(find.text('Ganti lampiran'), findsOneWidget);
      expect(
          find.text(
              'Ukuran file melebihi 2.048 KiB (2.097.152 byte). Gunakan file maksimal 2 MB.'),
          findsNothing);

      // 2. Ganti lampiran dengan file melebihi 2.048 KiB (2.097.153 byte)
      nextImage = oversized2048KiBPlus1;
      await tester.tap(find.text('Ganti lampiran'));
      await tester.pump();
      await tester.pump(const Duration(milliseconds: 100));

      // Pesan langsung harus muncul
      expect(
          find.text(
              'Ukuran file melebihi 2.048 KiB (2.097.152 byte). Gunakan file maksimal 2 MB.'),
          findsOneWidget);
      // Pilihan valid sebelumnya tetap dipertahankan
      expect(find.text('valid_exact_2mb.png'), findsOneWidget);

      // 3. Ganti lampiran dengan format tidak valid (PDF)
      nextImage = invalidExt;
      await tester.tap(find.text('Ganti lampiran'));
      await tester.pump();
      await tester.pump(const Duration(milliseconds: 100));

      expect(find.text('Gunakan lampiran dengan format JPG atau PNG.'),
          findsOneWidget);
      // Pilihan valid sebelumnya tetap dipertahankan
      expect(find.text('valid_exact_2mb.png'), findsOneWidget);

      // 4. Kirim form dan pastikan file valid sebelumnya yang dikirim
      await tester.tap(find.byType(TextFormField).first);
      await tester.pump();
      await tester.pump(const Duration(milliseconds: 100));
      await tester.enterText(find.byType(TextFormField).at(1), '150.000,00');
      await tester.enterText(find.byType(TextFormField).at(2), 'Biaya operasional');
      await tester.ensureVisible(find.text('Kirim pengajuan'));
      await tester.pump();
      await tester.pump(const Duration(milliseconds: 100));
      await tester.tap(find.text('Kirim pengajuan'));
      await tester.pump();

      expect(formSource.sends, 1);
      expect(formSource.sentImage?.name, 'valid_exact_2mb.png');
      formSource.sent.complete(Right(SubmissionRecord(
        id: 10,
        userId: 7,
        date: '2026-10-20',
        description: 'Biaya operasional',
        kind: SubmissionKind.reimbursement,
        amount: '150000.00',
        status: 'pending',
      )));
      await tester.pump();
      await tester.pump(const Duration(milliseconds: 100));
      await tester.pumpWidget(const SizedBox());
    });
  });

  group('2. Fallback Catatan Keputusan (decision_reason vs note)', () {
    test('Utamakan decision_reason di atas note', () {
      final data = {
        'id': 1,
        'user_id': 7,
        'date': '2026-10-20',
        'description': 'Lembur server down',
        'status': 'approved',
        'decision_reason': 'Disetujui oleh Direktur Operasional',
        'note': 'Catatan reviewer lama',
      };
      final record =
          SubmissionRecord.parse(data, SubmissionKind.overtime, 7);
      expect(record.decisionReason, 'Disetujui oleh Direktur Operasional');
    });

    test(
        'Gunakan note hanya pada kontrak payload lama yang terkonfirmasi dari peninjau',
        () {
      final data = {
        'id': 2,
        'user_id': 7,
        'date': '2026-10-20',
        'description': 'Lembur rilis fitur',
        'status': 'approved',
        'reviewed_by': 3,
        'decision_reason': null,
        'note': 'Disetujui kabag dengan catatan lembur efisien',
      };
      final record =
          SubmissionRecord.parse(data, SubmissionKind.overtime, 7);
      expect(record.decisionReason,
          'Disetujui kabag dengan catatan lembur efisien');
    });

    test('Gunakan note ketika decision_reason hanya berisi spasi kosong', () {
      final data = {
        'id': 3,
        'user_id': 7,
        'date': '2026-10-20',
        'description': 'Penggantian uang bensin',
        'status': 'approved',
        'reviewed_by': 3,
        'decision_reason': '   ',
        'note': 'Kuitansi lengkap dan disetujui',
      };
      final record =
          SubmissionRecord.parse(data, SubmissionKind.reimbursement, 7);
      expect(record.decisionReason, 'Kuitansi lengkap dan disetujui');
    });

    test(
        'Tolak note jika identik dengan catatan/alasan pemohon (bukan catatan peninjau)',
        () {
      final data = {
        'id': 4,
        'user_id': 7,
        'date': '2026-10-20',
        'description': 'Keperluan keluarga',
        'status': 'approved',
        'decision_reason': null,
        'note': 'Keperluan keluarga',
      };
      final record =
          SubmissionRecord.parse(data, SubmissionKind.reimbursement, 7);
      expect(record.decisionReason, isNull);
    });

    test('Tolak note pada pengajuan pending yang belum ditinjau', () {
      final data = {
        'id': 5,
        'user_id': 7,
        'date': '2026-10-20',
        'description': 'Lembur perbaikan bug',
        'status': 'pending',
        'decision_reason': null,
        'note': 'Catatan pengajuan oleh staf',
      };
      final record =
          SubmissionRecord.parse(data, SubmissionKind.overtime, 7);
      expect(record.decisionReason, isNull);
    });

    test('Tangani nilai kosong atau whitespace pada kedua field', () {
      final data = {
        'id': 6,
        'user_id': 7,
        'date': '2026-10-20',
        'description': 'Lembur maintenance',
        'status': 'approved',
        'decision_reason': '   ',
        'note': '  ',
      };
      final record =
          SubmissionRecord.parse(data, SubmissionKind.overtime, 7);
      expect(record.decisionReason, isNull);
    });

    test(
        'Regresi: Tolak note pada pengajuan pending dengan is_approved: false (tidak memakai is_approved sebagai bukti reviewer)',
        () {
      final data = {
        'id': 7,
        'user_id': 7,
        'date_permission': '2026-10-20',
        'reason': 'Izin urusan mendesak',
        'status': 'pending',
        'is_approved': false,
        'decision_reason': null,
        'note': 'Mohon segera diproses ya pak',
      };
      final record =
          SubmissionRecord.parse(data, SubmissionKind.permission, 7);
      expect(record.decisionReason, isNull);
    });

    test(
        'Catatan sah pada approval tahap antara yang statusnya masih pending (modern contract decision_reason)',
        () {
      final data = {
        'id': 8,
        'user_id': 7,
        'date_permission': '2026-10-20',
        'reason': 'Pelatihan sertifikasi',
        'status': 'pending',
        'is_approved': false,
        'approval_version': 1,
        'approval_current_reviewer': 'HR Manager',
        'approval_progress': {
          'current': 2,
          'total': 2,
          'label': 'HR Manager',
          'steps': ['Team Lead', 'HR Manager']
        },
        'approval_history': [
          {
            'actor': 'Team Lead Budi',
            'action': 'approved',
            'stage': 1,
            'reason': 'Disetujui tahap 1, relevan dengan project Q4',
            'time': '2026-10-20T10:00:00+07:00'
          }
        ],
        'reviewer': {'id': 3, 'name': 'Team Lead Budi'},
        'decision_time': '2026-10-20T10:00:00+07:00',
        'decision_reason': 'Disetujui tahap 1, relevan dengan project Q4',
      };
      final record =
          SubmissionRecord.parse(data, SubmissionKind.permission, 7);
      expect(record.decisionReason,
          'Disetujui tahap 1, relevan dengan project Q4');
      expect(record.status, 'pending');
    });

    test(
        'Catatan sah pada approval tahap antara yang statusnya masih pending (fallback ke riwayat tahap 1)',
        () {
      final data = {
        'id': 9,
        'user_id': 7,
        'date': '2026-10-20',
        'description': 'Lembur audit sistem',
        'status': 'pending',
        'is_approved': false,
        'approval_version': 1,
        'approval_progress': {
          'current': 2,
          'total': 2,
          'steps': ['Supervisor', 'Manager']
        },
        'approval_history': [
          {
            'actor': 'Supervisor Dani',
            'action': 'approved',
            'stage': 1,
            'reason': 'Pekerjaan audit mendesak dan disetujui supervisor',
            'time': '2026-10-20T11:00:00+07:00'
          }
        ],
        'decision_reason': null,
      };
      final record =
          SubmissionRecord.parse(data, SubmissionKind.overtime, 7);
      expect(record.decisionReason,
          'Pekerjaan audit mendesak dan disetujui supervisor');
      expect(record.status, 'pending');
    });

    test(
        'Catatan sah pada approval tahap antara dengan fallback note dan reviewer terkonfirmasi',
        () {
      final data = {
        'id': 10,
        'user_id': 7,
        'date': '2026-10-20',
        'description': 'Lembur deployment',
        'status': 'pending',
        'is_approved': false,
        'approval_version': 1,
        'reviewed_by': 4,
        'reviewer': {'id': 4, 'name': 'Supervisor Dani'},
        'decision_time': '2026-10-20T11:00:00+07:00',
        'approval_progress': {
          'current': 2,
          'total': 2,
          'steps': ['Supervisor', 'Manager']
        },
        'decision_reason': null,
        'note': 'Disetujui tahap 1 oleh supervisor',
      };
      final record =
          SubmissionRecord.parse(data, SubmissionKind.overtime, 7);
      expect(record.decisionReason, 'Disetujui tahap 1 oleh supervisor');
      expect(record.status, 'pending');
    });
  });

  group('3. Coba Lagi Lampiran Privat & Penanganan Error Terpisah', () {
    testWidgets(
        'Kegagalan jaringan memunculkan tombol Muat ulang lampiran, retry berhasil tanpa reload detail penuh',
        (tester) async {
      tester.view.physicalSize = const Size(390, 1200);
      tester.view.devicePixelRatio = 1;
      addTearDown(tester.view.resetPhysicalSize);
      addTearDown(tester.view.resetDevicePixelRatio);

      AuthLocalDatasource.resetForTesting();
      await loginAs();

      int detailCalls = 0;
      int attachmentCalls = 0;
      bool failAttachment = true;
      Completer<http.Response>? retryCompleter;

      final client = MockClient((request) async {
        if (request.url.path.contains('reimbursements/1') &&
            !request.url.path.contains('submission-attachments')) {
          detailCalls++;
          return http.Response(
              jsonEncode({
                'data': {
                  'id': 1,
                  'user_id': 7,
                  'date': '2026-10-20',
                  'description': 'Transportasi dinas',
                  'amount': '250000.00',
                  'status': 'approved',
                  'attachment_url':
                      'http://localhost/api/submission-attachments/reimbursement/1',
                }
              }),
              200);
        }
        if (request.url.path
            .contains('/api/submission-attachments/reimbursement/1')) {
          attachmentCalls++;
          if (failAttachment) {
            throw const SocketException('Network unreachable');
          }
          if (retryCompleter != null) {
            return retryCompleter.future;
          }
          return http.Response.bytes(transparentPng, 200,
              headers: {'content-type': 'image/png'});
        }
        return http.Response('Not found', 404);
      });

      final source = SubmissionRemoteDatasource(client: client);
      final initialRecord = SubmissionRecord(
        id: 1,
        userId: 7,
        date: '2026-10-20',
        description: 'Transportasi dinas',
        kind: SubmissionKind.reimbursement,
        amount: '250000.00',
        status: 'approved',
        image: 'http://localhost/api/submission-attachments/reimbursement/1',
      );

      await tester.pumpWidget(app(
        await controller(),
        SubmissionDetailPage(record: initialRecord, source: source),
      ));

      // Detail pertama dimuat, attachment gagal jaringan
      await tester.pump();
      await tester.pump(const Duration(milliseconds: 100));
      expect(detailCalls, 1);
      expect(attachmentCalls, 1);

      // Teks error jaringan dan tombol "Muat ulang lampiran" tampil
      expect(
          find.text(
              'Gagal terhubung ke server. Periksa koneksi internet Anda.'),
          findsOneWidget);
      expect(find.text('Muat ulang lampiran'), findsOneWidget);

      // Sekarang perbaiki koneksi dan tap retry
      failAttachment = false;
      retryCompleter = Completer<http.Response>();
      await tester.tap(find.text('Muat ulang lampiran'));
      await tester.pump();

      // Loading indikator khusus lampiran tampil saat request in-flight
      expect(find.text('Memuat lampiran privat…'), findsOneWidget);

      // Selesaikan request retry
      retryCompleter.complete(http.Response.bytes(transparentPng, 200,
          headers: {'content-type': 'image/png'}));
      await tester.pump();
      await tester.pump(const Duration(milliseconds: 100));

      // Request attachment bertambah menjadi 2, sedangkan detailCalls TETAP 1 (tidak reload detail penuh)
      expect(attachmentCalls, 2);
      expect(detailCalls, 1);

      // Error dan tombol retry telah hilang
      expect(
          find.text(
              'Gagal terhubung ke server. Periksa koneksi internet Anda.'),
          findsNothing);
      expect(find.text('Muat ulang lampiran'), findsNothing);
      await tester.pumpWidget(const SizedBox());
    });

    test('Membedakan 401 Sesi berakhir dan 403 Akses ditolak', () async {
      AuthLocalDatasource.resetForTesting();
      for (final status in [401, 403]) {
        await loginAs();
        final client = MockClient((request) async =>
            http.Response('Denied or expired', status));
        final source = SubmissionRemoteDatasource(client: client);
        final rec = SubmissionRecord(
          id: 1,
          userId: 7,
          date: '2026-10-20',
          description: 'Bukti',
          kind: SubmissionKind.reimbursement,
          image: 'http://localhost/api/submission-attachments/reimbursement/1',
        );

        final result = await source.attachment(rec);
        expect(result.isLeft(), isTrue);
        result.fold((err) {
          if (status == 401) {
            expect(err, 'Sesi berakhir. Silakan masuk kembali.');
          } else {
            expect(err, 'Akses lampiran ditolak. Hubungi pengelola.');
          }
        }, (_) => fail('Expected Left'));
      }
    });
  });

  group('4. Pergantian Akun Saat Unduhan Berlangsung', () {
    testWidgets(
        'Respons unduhan terlambat setelah pergantian akun tidak menampilkan gambar akun sebelumnya',
        (tester) async {
      tester.view.physicalSize = const Size(390, 1200);
      tester.view.devicePixelRatio = 1;
      addTearDown(tester.view.resetPhysicalSize);
      addTearDown(tester.view.resetDevicePixelRatio);

      AuthLocalDatasource.resetForTesting();
      await loginAs(id: 7, token: 'user-7-token');

      final pendingAttachment = Completer<http.Response>();
      final attachmentStarted = Completer<void>();

      final client = MockClient((request) async {
        if (request.url.path.contains('reimbursements/1')) {
          return http.Response(
              jsonEncode({
                'data': {
                  'id': 1,
                  'user_id': 7,
                  'date': '2026-10-20',
                  'description': 'Kuitansi User 7',
                  'amount': '300000.00',
                  'status': 'approved',
                  'attachment_url':
                      'http://localhost/api/submission-attachments/reimbursement/1',
                }
              }),
              200);
        }
        if (request.url.path
            .contains('/api/submission-attachments/reimbursement/1')) {
          attachmentStarted.complete();
          return pendingAttachment.future;
        }
        return http.Response('Not found', 404);
      });

      final source = SubmissionRemoteDatasource(client: client);
      final recordUser7 = SubmissionRecord(
        id: 1,
        userId: 7,
        date: '2026-10-20',
        description: 'Kuitansi User 7',
        kind: SubmissionKind.reimbursement,
        amount: '300000.00',
        status: 'approved',
        image: 'http://localhost/api/submission-attachments/reimbursement/1',
      );

      await tester.pumpWidget(app(
        await controller(),
        SubmissionDetailPage(record: recordUser7, source: source),
      ));

      // Tunggu hingga request attachment mulai berjalan
      await tester.pump();
      await attachmentStarted.future;

      // Sementara unduhan sedang in-flight, akun di perangkat berganti ke User 99
      await loginAs(id: 99, token: 'user-99-token');

      // Respons terlambat akhirnya tiba membawa byte gambar User 7
      pendingAttachment.complete(http.Response.bytes(transparentPng, 200,
          headers: {'content-type': 'image/png'}));

      // Pompa frame sampai unduhan selesai diproses
      for (int i = 0; i < 20; i++) {
        await tester.pump(const Duration(milliseconds: 100));
        if (!tester.any(find.byType(CircularProgressIndicator))) {
          break;
        }
      }

      // Gambar dari User 7 TIDAK BOLEH dirender
      expect(find.byType(Image), findsNothing);
      expect(
          find.text('Sesi akun telah berganti. Buka ulang pengajuan akun aktif.'),
          findsOneWidget);
      await tester.pumpWidget(const SizedBox());
    });
  });

  group(
      '5. Diferensiasi Visual Catatan Pemohon, Keputusan Terakhir, dan Riwayat Peninjauan',
      () {
    testWidgets(
        'Memastikan catatan pemohon, keputusan terakhir, dan riwayat per tahap terpisah dengan label dan kontainer jelas',
        (tester) async {
      tester.view.physicalSize = const Size(390, 1600);
      tester.view.devicePixelRatio = 1;
      addTearDown(tester.view.resetPhysicalSize);
      addTearDown(tester.view.resetDevicePixelRatio);

      AuthLocalDatasource.resetForTesting();
      await loginAs(id: 7);

      final multiStageData = {
        'id': 101,
        'user_id': 7,
        'date_permission': '2026-10-25',
        'date': '2026-10-25',
        'reason': 'Keperluan keluarga mendesak',
        'description': 'Keperluan keluarga mendesak',
        'status': 'approved',
        'reviewer': {'id': 5, 'name': 'HR Naltech'},
        'decision_time': '2026-10-25T15:30:00+07:00',
        'decision_reason': 'Disetujui penuh oleh HR setelah evaluasi atasan',
        'approval_progress': {
          'current': 2,
          'total': 2,
          'label': 'HR',
          'steps': ['Atasan Langsung', 'HR'],
        },
        'approval_history': [
          {
            'actor': 'Atasan Langsung',
            'action': 'approved',
            'stage': 1,
            'reason': 'Disetujui oleh atasan langsung',
            'time': '2026-10-25T09:00:00+07:00',
          },
          {
            'actor': 'HR Naltech',
            'action': 'approved',
            'stage': 2,
            'reason': 'Disetujui penuh oleh HR setelah evaluasi atasan',
            'time': '2026-10-25T15:30:00+07:00',
          },
        ],
      };
      final record = SubmissionRecord.parse(
          multiStageData, SubmissionKind.permission, 7);

      final fixture = _TestSubmissionFixture();

      await tester.pumpWidget(app(
        await controller(),
        SubmissionDetailPage(record: record, source: fixture),
      ));
      await tester.pumpAndSettle();

      // 1. Bagian Catatan Pemohon
      expect(find.text('Alasan pengajuan'), findsOneWidget);
      expect(find.text('Keperluan keluarga mendesak'), findsOneWidget);

      // 2. Bagian Keputusan Terakhir (Keputusan Pengajuan)
      expect(find.text('Keputusan pengajuan'), findsOneWidget);
      expect(find.text('Peninjau: HR Naltech'), findsOneWidget);
      expect(
          find.text('Waktu keputusan: 2026-10-25 15:30:00 WIB'), findsOneWidget);
      expect(find.textContaining('UTC'), findsNothing);
      expect(find.text('Catatan keputusan:'), findsOneWidget);
      expect(
          find.text('Disetujui penuh oleh HR setelah evaluasi atasan'),
          findsNWidgets(2)); // Muncul di ringkasan keputusan dan di riwayat tahap 2

      // 3. Bagian Riwayat Peninjauan per Tahap
      expect(find.text('Riwayat peninjauan'), findsOneWidget);
      expect(find.text('Tahap 1 · Disetujui'), findsOneWidget);
      expect(find.text('Atasan Langsung'), findsOneWidget);
      expect(find.text('2026-10-25 09:00:00 WIB'), findsOneWidget);
      expect(find.text('Tahap 2 · Disetujui'), findsOneWidget);
      expect(find.text('2026-10-25 15:30:00 WIB'), findsOneWidget);
      expect(find.text('Catatan tahap ini:'), findsNWidgets(2));
      expect(
          find.text('Disetujui oleh atasan langsung'), findsOneWidget);

      await tester.pumpWidget(const SizedBox());
    });

    testWidgets(
        'Pengajuan pending tanpa peninjau menampilkan placeholder keputusan dan menyembunyikan riwayat tahap',
        (tester) async {
      tester.view.physicalSize = const Size(390, 1200);
      tester.view.devicePixelRatio = 1;
      addTearDown(tester.view.resetPhysicalSize);
      addTearDown(tester.view.resetDevicePixelRatio);

      AuthLocalDatasource.resetForTesting();
      await loginAs(id: 7);

      final pendingOvertimeData = {
        'id': 102,
        'user_id': 7,
        'date': '2026-10-26',
        'duration': 120,
        'description': 'Lembur perbaikan server produksi',
        'status': 'pending',
      };
      final pendingRecord = SubmissionRecord.parse(
          pendingOvertimeData, SubmissionKind.overtime, 7);

      final fixture = _TestSubmissionFixture();

      await tester.pumpWidget(app(
        await controller(),
        SubmissionDetailPage(record: pendingRecord, source: fixture),
      ));
      await tester.pumpAndSettle();

      // 1. Catatan Pemohon untuk lembur berlabel "Keterangan pengajuan"
      expect(find.text('Keterangan pengajuan'), findsOneWidget);
      expect(find.text('Lembur perbaikan server produksi'), findsOneWidget);
      expect(find.text('Durasi: 120 menit'), findsOneWidget);

      // 2. Keputusan pengajuan berstatus belum tersedia
      expect(find.text('Keputusan pengajuan'), findsOneWidget);
      expect(find.text('Peninjau: Belum tersedia'), findsOneWidget);
      expect(find.text('Waktu keputusan belum tersedia'), findsOneWidget);
      expect(find.text('Catatan keputusan:'), findsNothing);

      // 3. Riwayat peninjauan belum ada sehingga tidak dirender
      expect(find.text('Riwayat peninjauan'), findsNothing);
      expect(find.text('Catatan tahap ini:'), findsNothing);

      await tester.pumpWidget(const SizedBox());
    });

    test(
        'Format waktu keputusan dan riwayat dalam zona waktu perusahaan dengan label yang jelas',
        () {
      final utcTime = DateTime.utc(2026, 10, 25, 8, 30, 0);

      // Default atau Asia/Jakarta menghasilkan WIB (UTC+7)
      expect(formatSubmissionTime(utcTime), '2026-10-25 15:30:00 WIB');
      expect(
          formatSubmissionTime(utcTime, timezone: 'Asia/Jakarta'),
          '2026-10-25 15:30:00 WIB');
      expect(
          formatSubmissionTime(utcTime, timezone: 'WIB'),
          '2026-10-25 15:30:00 WIB');

      // Asia/Makassar menghasilkan WITA (+8)
      expect(
          formatSubmissionTime(utcTime, timezone: 'Asia/Makassar'),
          '2026-10-25 16:30:00 WITA');

      // Asia/Jayapura menghasilkan WIT (+9)
      expect(
          formatSubmissionTime(utcTime, timezone: 'Asia/Jayapura'),
          '2026-10-25 17:30:00 WIT');

      // UTC menghasilkan UTC (+0)
      expect(
          formatSubmissionTime(utcTime, timezone: 'UTC'),
          '2026-10-25 08:30:00 UTC');

      // Penanganan null aman
      expect(formatSubmissionTime(null), 'Waktu belum tersedia');
    });

    test(
        'Format waktu dengan ISO string server menjaga offset zona IANA umum dan DST, serta menolak menganggap UTC diam-diam sebagai waktu lokal',
        () {
      // 1. Zona IANA umum dengan offset dari server (misal America/New_York saat Daylight Saving Time EDT UTC-4)
      expect(
          formatSubmissionTime(
            null,
            timezone: 'America/New_York',
            rawTime: '2026-10-21T04:00:00-04:00',
          ),
          '2026-10-21 04:00:00 America/New_York (UTC-04:00)');

      // 2. Zona IANA umum Eropa dengan offset BST (UTC+1)
      expect(
          formatSubmissionTime(
            null,
            timezone: 'Europe/London',
            rawTime: '2026-10-21T09:00:00+01:00',
          ),
          '2026-10-21 09:00:00 Europe/London (UTC+01:00)');

      // 3. Zona IANA tidak dikenal yang HANYA menerima raw UTC (Z) tanpa offset server:
      // Tidak boleh diam-diam dianggap waktu lokal perusahaan!
      expect(
          formatSubmissionTime(
            null,
            timezone: 'America/New_York',
            rawTime: '2026-10-21T08:00:00Z',
          ),
          '2026-10-21 08:00:00 UTC (Zona: America/New_York)');

      // 4. Offset eksplisit tanpa nama zona IANA
      expect(
          formatSubmissionTime(
            DateTime.utc(2026, 10, 21, 8, 0, 0),
            timezone: '+08:00',
          ),
          '2026-10-21 16:00:00 UTC+08:00');
    });
  });

  group(
      '6. Verifikasi Visual Detail Izin, Lembur, dan Reimbursement (Light/Dark & Edge Cases)',
      () {
    const longApplicantText =
        'Permohonan izin mendesak dikarenakan ada keperluan keluarga besar di luar kota yang membutuhkan kehadiran langsung selama beberapa hari ke depan, termasuk pengurusan dokumen penting di instansi terkait yang tidak dapat diwakilkan oleh pihak lain. Mohon kebijaksanaan atasan dan HRD untuk memberikan persetujuan cuti ini.';
    const longDecisionReason =
        'Setelah melakukan evaluasi mendalam terhadap beban kerja tim dan mempertimbangkan kuota cuti tahunan yang masih tersedia serta koordinasi pelimpahan tugas kepada rekan kerja piket, permohonan ini disetujui penuh oleh manajemen.';
    const longHistoryReason =
        'Pemeriksaan awal oleh supervisor unit telah selesai dilakukan; pekerjaan operasional sementara telah dialihkan dengan aman ke anggota tim lain.';

    for (final mode in [ThemeMode.light, ThemeMode.dark]) {
      final modeName = mode == ThemeMode.light ? 'Light' : 'Dark';

      testWidgets(
          'Detail Izin - Tema $modeName dengan teks panjang dan riwayat multi-tahap render tanpa overflow',
          (tester) async {
        tester.view.physicalSize = const Size(360, 2400);
        tester.view.devicePixelRatio = 1;
        addTearDown(tester.view.resetPhysicalSize);
        addTearDown(tester.view.resetDevicePixelRatio);

        AuthLocalDatasource.resetForTesting();
        await loginAs(id: 7);

        final theme = await controller();
        await theme.setMode(mode);

        final permissionData = {
          'id': 201,
          'user_id': 7,
          'date_permission': '2026-11-04',
          'date': '2026-11-04',
          'reason': longApplicantText,
          'description': longApplicantText,
          'status': 'approved',
          'reviewer': {'id': 10, 'name': 'HR Operations Lead'},
          'decision_time': '2026-11-04T14:30:00+07:00',
          'decision_reason': longDecisionReason,
          'timezone': 'Asia/Jakarta',
          'approval_history': [
            {
              'actor': 'Direct Supervisor',
              'action': 'approved',
              'stage': 1,
              'reason': longHistoryReason,
              'time': '2026-11-04T09:15:00+07:00',
            },
            {
              'actor': 'HR Operations Lead',
              'action': 'approved',
              'stage': 2,
              'reason': longDecisionReason,
              'time': '2026-11-04T14:30:00+07:00',
            },
          ],
        };
        final record = SubmissionRecord.parse(
            permissionData, SubmissionKind.permission, 7);

        final fixture = _TestSubmissionFixture();

        await tester.pumpWidget(app(
          theme,
          SubmissionDetailPage(record: record, source: fixture),
        ));
        await tester.pumpAndSettle();

        expect(find.text('Alasan pengajuan'), findsOneWidget);
        expect(find.text(longApplicantText), findsOneWidget);
        expect(find.text('Keputusan pengajuan'), findsOneWidget);
        expect(find.text('Peninjau: HR Operations Lead'), findsOneWidget);
        expect(find.text('Waktu keputusan: 2026-11-04 14:30:00 WIB'),
            findsOneWidget);
        expect(find.text('Catatan keputusan:'), findsOneWidget);
        expect(find.text('Riwayat peninjauan'), findsOneWidget);
        expect(find.text('Tahap 1 · Disetujui'), findsOneWidget);
        expect(find.text('2026-11-04 09:15:00 WIB'), findsOneWidget);
        expect(find.text('Catatan tahap ini:'), findsNWidgets(2));
        expect(tester.takeException(), isNull);
      });

      testWidgets(
          'Detail Lembur - Tema $modeName dengan teks panjang render tanpa overflow',
          (tester) async {
        tester.view.physicalSize = const Size(360, 1400);
        tester.view.devicePixelRatio = 1;
        addTearDown(tester.view.resetPhysicalSize);
        addTearDown(tester.view.resetDevicePixelRatio);

        AuthLocalDatasource.resetForTesting();
        await loginAs(id: 7);

        final theme = await controller();
        await theme.setMode(mode);

        final overtimeData = {
          'id': 202,
          'user_id': 7,
          'date': '2026-11-04',
          'duration': 180,
          'description': longApplicantText,
          'status': 'approved',
          'reviewer': {'id': 12, 'name': 'Engineering Manager'},
          'decision_time': '2026-11-04T18:00:00+07:00',
          'decision_reason': longDecisionReason,
          'timezone': 'Asia/Jakarta',
        };
        final record =
            SubmissionRecord.parse(overtimeData, SubmissionKind.overtime, 7);

        final fixture = _TestSubmissionFixture();

        await tester.pumpWidget(app(
          theme,
          SubmissionDetailPage(record: record, source: fixture),
        ));
        await tester.pumpAndSettle();

        expect(find.text('Keterangan pengajuan'), findsOneWidget);
        expect(find.text(longApplicantText), findsOneWidget);
        expect(find.text('Durasi: 180 menit'), findsOneWidget);
        expect(find.text('Keputusan pengajuan'), findsOneWidget);
        expect(find.text('Waktu keputusan: 2026-11-04 18:00:00 WIB'),
            findsOneWidget);
        expect(find.text('Lampiran'), findsNothing);
        expect(tester.takeException(), isNull);
      });

      testWidgets(
          'Detail Reimbursement - Tema $modeName dengan lampiran gagal dimuat memunculkan pesan error dan tombol coba lagi',
          (tester) async {
        tester.view.physicalSize = const Size(360, 2400);
        tester.view.devicePixelRatio = 1;
        addTearDown(tester.view.resetPhysicalSize);
        addTearDown(tester.view.resetDevicePixelRatio);

        AuthLocalDatasource.resetForTesting();
        await loginAs(id: 7);

        final theme = await controller();
        await theme.setMode(mode);

        final reimbursementData = {
          'id': 203,
          'user_id': 7,
          'date': '2026-11-04',
          'amount': '1750000.00',
          'description': longApplicantText,
          'status': 'approved',
          'reviewer': {'id': 15, 'name': 'Finance Controller'},
          'decision_time': '2026-11-04T16:45:00+07:00',
          'decision_reason': longDecisionReason,
          'timezone': 'Asia/Jakarta',
          'image':
              'http://localhost/api/submission-attachments/reimbursement/203',
        };
        final record = SubmissionRecord.parse(
            reimbursementData, SubmissionKind.reimbursement, 7);

        final fixture = _TestSubmissionFixture()
          ..attachmentResult = const Left(
              'Gagal terhubung ke server. Periksa koneksi internet Anda.');

        await tester.pumpWidget(app(
          theme,
          SubmissionDetailPage(record: record, source: fixture),
        ));
        await tester.pumpAndSettle();

        expect(find.text('Keterangan pengajuan'), findsOneWidget);
        expect(find.text('Lampiran'), findsOneWidget);
        expect(
            find.text(
                'Gagal terhubung ke server. Periksa koneksi internet Anda.'),
            findsOneWidget);
        expect(find.text('Muat ulang lampiran'), findsOneWidget);
        expect(find.byIcon(Icons.refresh), findsWidgets);
        expect(tester.takeException(), isNull);
      });
    }
  });
}

class _TestSubmissionFixture extends SubmissionRemoteDatasource {
  int sends = 0;
  XFile? sentImage;
  final sent = Completer<Either<String, SubmissionRecord>>();
  Either<String, Uint8List>? attachmentResult;

  @override
  Future<Either<String, Uint8List>> attachment(
          SubmissionRecord record) async =>
      attachmentResult ??
      const Left('Gagal terhubung ke server. Periksa koneksi internet Anda.');

  @override
  Future<Either<String, SubmissionRecord>> detail(
          SubmissionRecord record) async =>
      Right(record);

  @override
  Future<Either<String, Map<String, dynamic>>> calculateLeave(
          String start, String end, int? type) async =>
      const Right({});

  @override
  Future<Either<String, List<LeavePolicyRecord>>> leaveTypes() async =>
      const Right([]);

  @override
  Future<Either<String, SubmissionRecord>> submit(SubmissionKind kind,
      {required String date,
      required String description,
      String? startDate,
      String? endDate,
      String? amount,
      int? duration,
      LeavePolicyRecord? leaveType,
      XFile? image}) {
    sends++;
    sentImage = image;
    return sent.future;
  }
}
