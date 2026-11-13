import 'support/leave_preview_fixture.dart';
import 'dart:async';
import 'dart:convert';
import 'package:dartz/dartz.dart' show Either, Left, Right;
import 'package:flutter/material.dart';
import 'package:flutter_absensi_app/data/datasources/auth_local_datasource.dart';
import 'package:flutter_absensi_app/data/datasources/submission_remote_datasource.dart';
import 'package:flutter_absensi_app/data/models/response/auth_response_model.dart';
import 'package:flutter_absensi_app/data/models/response/leave_policy_record.dart';
import 'package:flutter_absensi_app/data/models/response/submission_record.dart';
import 'package:flutter_absensi_app/presentation/home/pages/submissions/submission_form_page.dart';
import 'package:flutter_absensi_app/presentation/home/pages/submissions/submission_list_page.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:image_picker/image_picker.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'profile_session_test.dart' show app, controller;

Map<String, dynamic> policyData({int id = 4, bool required = true}) => {
      'id': id,
      'code': 'TAHUNAN',
      'name': 'Cuti tahunan',
      'version': 1,
      'deducts_balance': true,
      'annual_quota_days': 12,
      'requires_attachment': required,
      'requirements': 'Koordinasikan jadwal dengan atasan.'
    };
LeavePolicyRecord policy({bool required = true}) =>
    LeavePolicyRecord.parse(policyData(required: required));
Map<String, dynamic> leaveRow() => {
      'id': 1,
      'user_id': 7,
      'date_permission': '2026-10-21',
      'reason': 'Keluarga',
      'status': 'pending',
      'approval_version': 0,
      'leave_type_id': 4,
      'leave_policy_snapshot': policyData(),
      'approval_progress': {
        'current': 1,
        'total': 2,
        'label': 'Atasan',
        'steps': ['Atasan', 'HR']
      }
    };

class LeaveFixture extends SubmissionRemoteDatasource {
  @override
  Future<Either<String, Map<String, dynamic>>> calculateLeave(
          String start, String end, int? type) async =>
      Right(leavePreview());

  Either<String, List<LeavePolicyRecord>> catalog = Right([policy()]);
  int sends = 0, loads = 0;
  LeavePolicyRecord? submitted;
  XFile? proof;
  @override
  Future<Either<String, List<LeavePolicyRecord>>> leaveTypes() async {
    loads++;
    return catalog;
  }

  @override
  Future<Either<String, SubmissionRecord>> detail(
          SubmissionRecord record) async =>
      Right(record);
  @override
  Future<Either<String, SubmissionRecord>> submit(SubmissionKind kind,
      {required String date,
      required String description,
      String? startDate,
      String? endDate,
      String? amount,
      int? duration,
      LeavePolicyRecord? leaveType,
      XFile? image}) async {
    sends++;
    submitted = leaveType;
    proof = image;
    return const Left('Jenis cuti ditolak server.');
  }
}

void main() {
  setUp(() => SharedPreferences.setMockInitialValues({}));
  Future<void> login() => AuthLocalDatasource().saveAuthData(
      AuthResponseModel(user: User(id: 7), token: 'fixture-only-token'));
  test(
      'Policy parsing preserves historical rules and refuses mismatched snapshots',
      () {
    final record =
        SubmissionRecord.parse(leaveRow(), SubmissionKind.permission, 7);
    expect(record.leavePolicy!.name, 'Cuti tahunan');
    expect(record.leavePolicy!.annualQuotaDays, 12);
    expect(record.progress!.total, 2);
    expect(
        () => SubmissionRecord.parse(
            leaveRow()..['leave_type_id'] = 5, SubmissionKind.permission, 7),
        throwsFormatException);
    expect(
        () =>
            LeavePolicyRecord.parse(policyData()..['deducts_balance'] = 'true'),
        throwsFormatException);
    expect(
        () =>
            LeavePolicyRecord.parse(policyData()..['annual_quota_days'] = null),
        throwsFormatException);
    final general = leaveRow()
      ..remove('leave_type_id')
      ..remove('leave_policy_snapshot');
    expect(
        SubmissionRecord.parse(general, SubmissionKind.permission, 7)
            .leavePolicy,
        isNull);
  });
  test(
      'Catalog follows fixed authenticated pages and retains no partial list on malformed pages',
      () async {
    await login();
    var calls = 0;
    final source =
        SubmissionRemoteDatasource(client: MockClient((request) async {
      calls++;
      expect(request.url.path, '/api/leave-types');
      expect(request.followRedirects, false);
      expect(request.headers['Authorization'], 'Bearer fixture-only-token');
      final page = int.parse(request.url.queryParameters['page']!);
      return http.Response(
          jsonEncode({
            'data': [policyData(id: page)],
            'meta': {'current_page': page, 'last_page': 2}
          }),
          200);
    }));
    final result = await source.leaveTypes();
    expect(result.isRight(), true);
    result.fold((_) => fail('Expected policies'),
        (rows) => expect(rows.map((e) => e.id), [1, 2]));
    expect(calls, 2);
    final broken = SubmissionRemoteDatasource(
        client: MockClient((_) async => http.Response(
            jsonEncode({
              'data': [policyData()],
              'meta': {'current_page': 1, 'last_page': 0}
            }),
            200)));
    expect((await broken.leaveTypes()).isLeft(), true);
  });
  test('Catalog handles unavailable service expiry and late account changes',
      () async {
    await login();
    for (final code in [503, 302, 200]) {
      final source = SubmissionRemoteDatasource(
          client: MockClient((_) async => http.Response('{}', code)));
      expect((await source.leaveTypes()).isLeft(), true);
    }
    final pending = Completer<http.Response>(), started = Completer<void>();
    final source = SubmissionRemoteDatasource(client: MockClient((_) {
      started.complete();
      return pending.future;
    }));
    final result = source.leaveTypes();
    await started.future;
    await AuthLocalDatasource().saveAuthData(
        AuthResponseModel(user: User(id: 8), token: 'other-fixture-token'));
    pending.complete(http.Response(
        jsonEncode({
          'data': [policyData()],
          'meta': {'current_page': 1, 'last_page': 1}
        }),
        200));
    expect((await result).isLeft(), true);
    await login();
    final expired = SubmissionRemoteDatasource(
        client: MockClient((_) async => http.Response('{}', 401)));
    expect((await expired.leaveTypes()).isLeft(), true);
    expect(await AuthLocalDatasource().getAuthData(), isNull);
  });
  test(
      'Typed submission requires attachment and sends only type id then checks returned policy',
      () async {
    await login();
    var calls = 0;
    final source =
        SubmissionRemoteDatasource(client: MockClient((request) async {
      calls++;
      expect(request.followRedirects, false);
      expect(latin1.decode(request.bodyBytes),
          contains('name="leave_type_id"\r\n\r\n4'));
      expect(latin1.decode(request.bodyBytes),
          isNot(contains('name="leave_policy_snapshot"')));
      return http.Response(jsonEncode({'data': leaveRow()}), 201);
    }));
    expect(
        (await source.submit(SubmissionKind.permission,
                date: '2026-10-21',
                description: 'Keluarga',
                leaveType: policy()))
            .isLeft(),
        true);
    expect(calls, 0);
    final image = XFile.fromData(
        base64Decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+j0ioAAAAASUVORK5CYII='),
        name: 'proof.png',
        path: 'proof.png');
    expect(
        (await source.submit(SubmissionKind.permission,
                date: '2026-10-21',
                description: 'Keluarga',
                leaveType: policy(),
                image: image))
            .isRight(),
        true);
    expect(calls, 1);
    final mismatch = SubmissionRemoteDatasource(
        client: MockClient((_) async => http.Response(
            jsonEncode({'data': leaveRow()..['leave_type_id'] = 5}), 201)));
    expect(
        (await mismatch.submit(SubmissionKind.permission,
                date: '2026-10-21',
                description: 'Keluarga',
                leaveType: policy(required: false)))
            .isLeft(),
        true);
  });
  Future<void> select(WidgetTester tester) async {
    await tester.ensureVisible(find.byType(DropdownButtonFormField<int>));
    await tester.tap(find.byType(DropdownButtonFormField<int>));
    await tester.pumpAndSettle();
    await tester.tap(find.text('TAHUNAN · Cuti tahunan').last);
    await tester.pumpAndSettle();
  }

  Future<void> visible(WidgetTester tester, Finder target) async {
    await tester.scrollUntilVisible(target, 150,
        scrollable: find
            .descendant(
                of: find.byType(ListView), matching: find.byType(Scrollable))
            .first);
    await tester.pumpAndSettle();
  }

  testWidgets(
      'Form validates required proof and retains selected type and input after server rejection',
      (tester) async {
    tester.view.physicalSize = const Size(390, 1000);
    tester.view.devicePixelRatio = 1;
    addTearDown(tester.view.resetPhysicalSize);
    addTearDown(tester.view.resetDevicePixelRatio);
    final source = LeaveFixture();
    await tester.pumpWidget(app(
        await controller(),
        SubmissionFormPage(
            kind: SubmissionKind.permission,
            source: source,
            pickDate: () async => DateTime(2026, 10, 21),
            pickImage: () async => XFile.fromData(
                base64Decode(
                    'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+j0ioAAAAASUVORK5CYII='),
                name: 'proof.png',
                path: 'proof.png'))));
    await tester.pumpAndSettle();
    await select(tester);
    await visible(tester, find.widgetWithText(TextFormField, 'Tanggal mulai'));
    await tester.tap(find.widgetWithText(TextFormField, 'Tanggal mulai'));
    await tester.pumpAndSettle();
    await tester.enterText(
        find.widgetWithText(TextFormField, 'Keperluan'), 'Keluarga');
    await visible(tester, find.text('Tinjau pengajuan'));
    await tester.tap(find.text('Tinjau pengajuan'));
    await tester.pumpAndSettle();
    expect(source.sends, 0);
    expect(find.text('Jenis cuti ini memerlukan lampiran.'), findsOneWidget);
    await visible(tester, find.text('Pilih lampiran'));
    await tester.tap(find.text('Pilih lampiran'));
    await tester.pumpAndSettle();
    await visible(tester, find.text('Tinjau pengajuan'));
    await tester.tap(find.text('Tinjau pengajuan'));
    await tester.pumpAndSettle();
    expect(source.sends, 0);
    expect(find.text('Tinjau pengajuan'), findsWidgets);
    await tester.tap(find.text('Kirim pengajuan'));
    await tester.pumpAndSettle();
    expect(source.sends, 1);
    expect(source.submitted!.id, 4);
    expect(source.proof, isNotNull);
    expect(find.text('Jenis cuti ditolak server.'), findsOneWidget);
    expect(find.text('Keluarga'), findsOneWidget);
    expect(tester.takeException(), isNull);
  });
  testWidgets(
      'Catalog retry preserves unavailable selection instead of changing to general permission',
      (tester) async {
    tester.view.physicalSize = const Size(390, 1000);
    tester.view.devicePixelRatio = 1;
    addTearDown(tester.view.resetPhysicalSize);
    addTearDown(tester.view.resetDevicePixelRatio);
    final source = LeaveFixture()
      ..catalog = const Left('Koneksi jenis cuti gagal');
    await tester.pumpWidget(app(await controller(),
        SubmissionFormPage(kind: SubmissionKind.permission, source: source)));
    await tester.pumpAndSettle();
    expect(find.text('Koneksi jenis cuti gagal'), findsOneWidget);
    expect(find.text('Belum ada jenis cuti aktif. Izin umum tetap tersedia.'),
        findsNothing);
    source.catalog = Right([policy()]);
    await tester.tap(find.text('Muat ulang jenis cuti'));
    await tester.pumpAndSettle();
    await select(tester);
    source.catalog = const Right([]);
    await tester.tap(find.text('Muat ulang jenis cuti'));
    await tester.pumpAndSettle();
    expect(
        find.text(
            'Pilihan cuti belum tersedia. Muat ulang atau pilih jenis lain; pilihan Anda belum diubah.'),
        findsOneWidget);
    await visible(tester, find.text('Tinjau pengajuan'));
    expect(tester.widget<FilledButton>(find.byType(FilledButton)).onPressed,
        isNull);
    expect(source.sends, 0);
    expect(tester.takeException(), isNull);
  });
  testWidgets(
      'Historical policy and approval stages support both themes narrow viewport and large text',
      (tester) async {
    tester.view.physicalSize = const Size(320, 640);
    tester.view.devicePixelRatio = 1;
    addTearDown(tester.view.resetPhysicalSize);
    addTearDown(tester.view.resetDevicePixelRatio);
    final theme = await controller();
    for (final mode in [ThemeMode.light, ThemeMode.dark]) {
      await theme.setMode(mode);
      await tester.pumpWidget(app(
          theme,
          MediaQuery(
              data: const MediaQueryData(
                  size: Size(320, 640), textScaler: TextScaler.linear(1.8)),
              child: SubmissionDetailPage(
                  key: ValueKey(mode),
                  record: SubmissionRecord.parse(
                      leaveRow(), SubmissionKind.permission, 7),
                  source: LeaveFixture()))));
      await tester.pumpAndSettle();
      expect(find.text('Tahap 1 dari 2: Atasan'), findsOneWidget);
      await visible(tester, find.text('Kuota kebijakan: 12 hari / tahun.'));
      expect(find.text('Kebijakan cuti saat diajukan'), findsOneWidget);
      expect(find.text('Saldo karyawan belum dihitung.'), findsOneWidget);
      expect(tester.takeException(), isNull);
    }
  });
  testWidgets(
      'Leave picker and required-proof form handle long names with large text in both themes',
      (tester) async {
    tester.view.physicalSize = const Size(320, 640);
    tester.view.devicePixelRatio = 1;
    addTearDown(tester.view.resetPhysicalSize);
    addTearDown(tester.view.resetDevicePixelRatio);
    final theme = await controller();
    const name =
        'Cuti tahunan untuk keperluan keluarga dengan nama kebijakan perusahaan yang panjang';
    for (final mode in [ThemeMode.light, ThemeMode.dark]) {
      final source = LeaveFixture()
        ..catalog =
            Right([LeavePolicyRecord.parse(policyData()..['name'] = name)]);
      await theme.setMode(mode);
      await tester.pumpWidget(app(
          theme,
          MediaQuery(
              data: const MediaQueryData(
                  size: Size(320, 640), textScaler: TextScaler.linear(1.8)),
              child: SubmissionFormPage(
                  key: ValueKey(mode),
                  kind: SubmissionKind.permission,
                  source: source))));
      await tester.pumpAndSettle();
      await visible(tester, find.byType(DropdownButtonFormField<int>));
      await tester.tap(find.byType(DropdownButtonFormField<int>));
      await tester.pumpAndSettle();
      await tester.tap(find.text('TAHUNAN · $name').last);
      await tester.pumpAndSettle();
      await visible(tester, find.text('Lampiran (wajib)'));
      expect(tester.takeException(), isNull);
    }
  });
}
