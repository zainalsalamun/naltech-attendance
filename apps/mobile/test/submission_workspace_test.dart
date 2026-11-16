import 'support/leave_preview_fixture.dart';
import 'dart:async';
import 'dart:convert';
import 'dart:typed_data';
import 'package:dartz/dartz.dart';
import 'package:flutter/material.dart';
import 'package:flutter_absensi_app/data/datasources/submission_remote_datasource.dart';
import 'package:flutter_absensi_app/data/datasources/auth_local_datasource.dart';
import 'package:flutter_absensi_app/data/models/response/auth_response_model.dart';
import 'package:flutter_absensi_app/data/models/response/submission_record.dart';
import 'package:flutter_absensi_app/presentation/home/bloc/submissions/submission_cubit.dart';
import 'package:flutter_absensi_app/presentation/home/pages/submissions/submission_list_page.dart';
import 'package:flutter_absensi_app/presentation/home/pages/submissions/submission_form_page.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:image_picker/image_picker.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:flutter_absensi_app/data/models/response/leave_policy_record.dart';
import 'profile_session_test.dart' show app, controller;

SubmissionRecord record(
        {int id = 1,
        SubmissionKind kind = SubmissionKind.permission,
        String? status}) =>
    SubmissionRecord(
        id: id,
        userId: 7,
        date: '2026-10-20',
        description: 'Keperluan keluarga',
        kind: kind,
        amount: kind.isPermission ? null : '150000.50',
        status: status ?? (kind.isPermission ? 'unapproved' : 'pending'));
Map<String, dynamic> row(SubmissionKind kind, {int id = 1, int userId = 7}) => {
      'id': id,
      'user_id': userId,
      kind.isPermission ? 'date_permission' : 'date': '2026-10-20',
      kind.descriptionKey: 'Keperluan keluarga',
      'amount': '150000.50',
      'is_approved': 0,
      'status': 'pending',
    };

class SubmissionFixture extends SubmissionRemoteDatasource {
  @override
  Future<Either<String, Map<String, dynamic>>> calculateLeave(
          String start, String end, int? type) async =>
      Right(leavePreview());

  @override
  Future<Either<String, List<LeavePolicyRecord>>> leaveTypes() async =>
      const Right([]);
  int sends = 0, lists = 0;
  String? amount;
  int? duration;
  final sent = Completer<Either<String, SubmissionRecord>>();
  List<SubmissionRecord> rows = [];
  String? error;
  @override
  Future<Either<String, SubmissionRecord>> detail(
          SubmissionRecord record) async =>
      Right(record);

  @override
  Future<Either<String, SubmissionBatch>> list(SubmissionKind kind,
      {int page = 1}) async {
    lists++;
    return error != null
        ? Left(error!)
        : Right(SubmissionBatch(rows, page: page, lastPage: 1));
  }

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
    this.amount = amount;
    this.duration = duration;
    return sent.future;
  }
}

class PagedFixture extends SubmissionRemoteDatasource {
  final requests = <(int, Completer<Either<String, SubmissionBatch>>)>[];
  @override
  Future<Either<String, SubmissionBatch>> list(SubmissionKind kind,
      {int page = 1}) {
    final pending = Completer<Either<String, SubmissionBatch>>();
    requests.add((page, pending));
    return pending.future;
  }
}

void main() {
  setUp(() {
    SharedPreferences.setMockInitialValues({});
  });
  Future<void> login() => AuthLocalDatasource().saveAuthData(
      AuthResponseModel(user: User(id: 7), token: 'fixture-token'));

  test(
      'Private attachment uses a fixed endpoint and never authorizes an untrusted image URL',
      () async {
    await login();
    final item = SubmissionRecord.parse({
      ...row(SubmissionKind.permission),
      'attachment_url': 'https://evil.invalid/proof.png'
    }, SubmissionKind.permission, 7);
    final source =
        SubmissionRemoteDatasource(client: MockClient((request) async {
      expect(request.url.path, '/api/submission-attachments/permission/1');
      expect(request.url.host, isNot('evil.invalid'));
      expect(request.followRedirects, isFalse);
      expect(request.headers['Authorization'], 'Bearer fixture-token');
      return http.Response.bytes([1, 2, 3], 200,
          headers: {'content-type': 'image/png'});
    }));
    final result = await source.attachment(item);
    expect(result.isRight(), isTrue);
    result.fold(
        (_) => fail('Expected bytes'), (bytes) => expect(bytes, [1, 2, 3]));
  });

  test(
      'Attachment rejects redirects wrong MIME oversized data and expired login',
      () async {
    for (final response in [
      http.Response('', 302,
          headers: {'location': 'https://evil.invalid/proof.png'}),
      http.Response('not an image', 200,
          headers: {'content-type': 'text/html'}),
      http.Response.bytes(Uint8List(2 * 1024 * 1024 + 1), 200,
          headers: {'content-type': 'image/png'}),
      http.Response('', 401),
    ]) {
      await login();
      final source =
          SubmissionRemoteDatasource(client: MockClient((_) async => response));
      expect((await source.attachment(record())).isLeft(), isTrue);
      if (response.statusCode == 401) {
        expect(await AuthLocalDatasource().getAuthData(), isNull);
      }
    }
  });

  test('Attachment response cannot cross sessions', () async {
    await login();
    final pending = Completer<http.Response>(), started = Completer<void>();
    final source = SubmissionRemoteDatasource(client: MockClient((_) {
      started.complete();
      return pending.future;
    }));
    final result = source.attachment(record());
    await started.future;
    await AuthLocalDatasource().saveAuthData(
        AuthResponseModel(user: User(id: 88), token: 'replacement-fixture'));
    pending.complete(http.Response.bytes([1, 2, 3], 200,
        headers: {'content-type': 'image/png'}));
    expect((await result).isLeft(), isTrue);
    expect((await AuthLocalDatasource().getAuthData())?.user?.id, 88);
  });
  test(
      'Rupiah parsing is exact and ambiguous or nonpositive values are rejected',
      () {
    expect(submissionAmount('150.000,50'), '150000.50');
    expect(submissionAmount('1.234'), '1234.00');
    expect(submissionAmount('0,01'), '0.01');
    expect(submissionAmount('9.999.999.999,99'), '9999999999.99');
    for (final value in [
      '0',
      '-1',
      '1.23',
      '1,234',
      '10.000.000.000',
      'NaN',
      '1e3',
      'Rp 100',
      ''
    ]) {
      expect(submissionAmount(value), isNull);
    }
    expect(submissionMoney('150000.50'), 'Rp 150.000,50');
    expect(submissionMoney('bad'), 'Nominal belum tersedia');
    expect(submissionDate('2026-02-30'), isNull);
    expect(record().statusLabel, 'Belum disetujui');
    expect(
        record(kind: SubmissionKind.reimbursement, status: 'other').statusLabel,
        'Status belum tersedia');
  });

  test(
      'Submit sends optional attachment safely and canonical amount; parses server record',
      () async {
    await login();
    for (final kind in [
      SubmissionKind.permission,
      SubmissionKind.reimbursement
    ]) {
      final source =
          SubmissionRemoteDatasource(client: MockClient((request) async {
        expect(request.url.path, '/api/${kind.endpoint}');
        expect(
            request.body,
            contains(
                'name="${kind.descriptionKey}"\r\n\r\nKeperluan keluarga'));
        expect(request.body, isNot(contains('name="user_id"')));
        if (!kind.isPermission) {
          expect(request.body, contains('name="amount"\r\n\r\n150000.50'));
          expect(request.body, contains('name="image"; filename="proof.png"'));
        } else {
          expect(request.body, isNot(contains('filename=')));
        }
        return http.Response(jsonEncode({'data': row(kind)}), 201);
      }));
      final result = await source.submit(kind,
          date: '2026-10-20',
          description: ' Keperluan keluarga ',
          amount: kind.isPermission ? null : '150000.50',
          image: kind.isPermission
              ? null
              : XFile.fromData(Uint8List(3),
                  path: 'proof.png', name: 'proof.png'));
      expect(result.isRight(), isTrue);
      expect(result.getOrElse(() => record()).id, 1);
    }
  });

  test('Invalid file, amount and date never send HTTP', () async {
    await login();
    var calls = 0;
    final source = SubmissionRemoteDatasource(client: MockClient((_) async {
      calls++;
      return http.Response('{}', 500);
    }));
    expect(
        (await source.submit(SubmissionKind.permission,
                date: 'bad', description: 'Reason'))
            .isLeft(),
        isTrue);
    expect(
        (await source.submit(SubmissionKind.reimbursement,
                date: '2026-10-20', description: 'Reason', amount: '-1'))
            .isLeft(),
        isTrue);
    for (final file in [
      XFile.fromData(Uint8List(3), path: 'proof.svg'),
      XFile.fromData(Uint8List(2097153), path: 'proof.png')
    ]) {
      expect(
          (await source.submit(SubmissionKind.permission,
                  date: '2026-10-20', description: 'Reason', image: file))
              .isLeft(),
          isTrue);
    }
    expect(calls, 0);
  });

  test(
      'List rejects malformed, foreign and duplicate rows and accepts pagination',
      () async {
    await login();
    var rows = [row(SubmissionKind.permission)];
    final source = SubmissionRemoteDatasource(
        client: MockClient((_) async => http.Response(
            jsonEncode({
              'data': rows,
              'meta': {'current_page': 1, 'last_page': 2}
            }),
            200)));
    expect(
        (await source.list(SubmissionKind.permission))
            .getOrElse(() => const SubmissionBatch([], page: 0, lastPage: 0))
            .lastPage,
        2);
    rows = [row(SubmissionKind.permission, userId: 99)];
    expect((await source.list(SubmissionKind.permission)).isLeft(), isTrue);
    rows = [row(SubmissionKind.permission), row(SubmissionKind.permission)];
    expect((await source.list(SubmissionKind.permission)).isLeft(), isTrue);
    rows = [
      {'id': 1, 'user_id': 7}
    ];
    expect((await source.list(SubmissionKind.permission)).isLeft(), isTrue);

    final overtimeSource = SubmissionRemoteDatasource(
        client: MockClient((request) async {
      expect(request.url.path, '/api/api-overtimes');
      final page = int.parse(request.url.queryParameters['page'] ?? '1');
      return http.Response(
          jsonEncode({
            'message': 'Success',
            'data': [row(SubmissionKind.overtime, id: page == 1 ? 1 : 2)],
            'meta': {'current_page': page, 'last_page': 2}
          }),
          200);
    }));
    final page1 = await overtimeSource.list(SubmissionKind.overtime, page: 1);
    expect(page1.isRight(), isTrue);
    expect(
        page1
            .getOrElse(() => const SubmissionBatch([], page: 0, lastPage: 0))
            .records
            .first
            .id,
        1);
    final page2 = await overtimeSource.list(SubmissionKind.overtime, page: 2);
    expect(page2.isRight(), isTrue);
    expect(
        page2
            .getOrElse(() => const SubmissionBatch([], page: 0, lastPage: 0))
            .records
            .first
            .id,
        2);
  });

  test(
      'SubmissionRecord dateRangeLabel formats according to kind without leaking leave workdays',
      () {
    final perm = SubmissionRecord(
        id: 1,
        userId: 7,
        date: '2026-10-20',
        description: 'Cuti',
        kind: SubmissionKind.permission,
        durationDays: 2,
        startDate: '2026-10-20',
        endDate: '2026-10-21');
    expect(perm.dateRangeLabel, '2026-10-20 s/d 2026-10-21 (2 hari kerja)');

    final permSingle = SubmissionRecord(
        id: 2,
        userId: 7,
        date: '2026-10-20',
        description: 'Izin 1 hari',
        kind: SubmissionKind.permission);
    expect(permSingle.dateRangeLabel, '2026-10-20 (1 hari kerja)');

    final overtime = SubmissionRecord(
        id: 3,
        userId: 7,
        date: '2026-10-20',
        description: 'Lembur',
        kind: SubmissionKind.overtime,
        duration: 120);
    expect(overtime.dateRangeLabel, '2026-10-20');

    final reimb = SubmissionRecord(
        id: 4,
        userId: 7,
        date: '2026-10-20',
        description: 'Reimbursement',
        kind: SubmissionKind.reimbursement,
        amount: '50000.00');
    expect(reimb.dateRangeLabel, '2026-10-20');
  });

  test(
      'Errors, invalid success and offline never report a saved submission; 401 clears session',
      () async {
    for (final status in [200, 422, 500, 401, 201]) {
      await login();
      final source = SubmissionRemoteDatasource(
          client: MockClient((_) async => http.Response(
              jsonEncode({
                'errors': {
                  'reason': ['Keterangan wajib diisi.']
                }
              }),
              status)));
      final result = await source.submit(SubmissionKind.permission,
          date: '2026-10-20', description: 'Reason');
      expect(result.isLeft(), isTrue);
      expect(await AuthLocalDatasource().isAuth(), status != 401);
    }
    final source = SubmissionRemoteDatasource(
        client: MockClient((_) async => throw Exception('offline')));
    expect((await source.list(SubmissionKind.permission)).isLeft(), isTrue);
    expect(
        (await source.submit(SubmissionKind.permission,
                date: '2026-10-20', description: 'Reason'))
            .isLeft(),
        isTrue);
  });

  test(
      'Pagination ignores old responses, deduplicates boundaries and retains rows on error',
      () async {
    final source = PagedFixture();
    final cubit = SubmissionListCubit(source, SubmissionKind.permission);
    final old = cubit.refresh();
    final current = cubit.refresh();
    source.requests[1].$2
        .complete(Right(SubmissionBatch([record()], page: 1, lastPage: 3)));
    await current;
    source.requests[0].$2.complete(const Left('Old error'));
    await old;
    expect(cubit.state.records.length, 1);
    final more = cubit.more();
    await cubit.more();
    expect(source.requests.length, 3);
    source.requests[2].$2.complete(Right(
        SubmissionBatch([record(), record(id: 2)], page: 2, lastPage: 3)));
    await more;
    expect(cubit.state.records.length, 2);
    final failed = cubit.more();
    source.requests[3].$2.complete(const Left('Offline'));
    await failed;
    expect(cubit.state.records.length, 2);
    expect(cubit.state.moreError, 'Offline');
    await cubit.close();
  });

  test('Sender blocks duplicate submissions and leaves loading on failure',
      () async {
    final source = SubmissionFixture();
    final cubit = SubmissionSendCubit(source, SubmissionKind.permission);
    final pending = cubit.send(date: '2026-10-20', description: 'Reason');
    await cubit.send(date: '2026-10-20', description: 'Reason');
    expect(source.sends, 1);
    source.sent.complete(const Left('Offline'));
    await pending;
    expect(cubit.state.loading, isFalse);
    expect(cubit.state.error, 'Offline');
    await cubit.close();
  });

  test(
      'Late submission response cannot be shown under a new session and attachments use protected endpoints',
      () async {
    await login();
    final pending = Completer<http.Response>(), started = Completer<void>();
    final source = SubmissionRemoteDatasource(client: MockClient((_) {
      started.complete();
      return pending.future;
    }));
    final result = source.submit(SubmissionKind.permission,
        date: '2026-10-20', description: 'Keperluan keluarga');
    await started.future;
    await AuthLocalDatasource().saveAuthData(
        AuthResponseModel(user: User(id: 88), token: 'new-fixture-token'));
    pending.complete(http.Response(
        jsonEncode({'data': row(SubmissionKind.permission)}), 201));
    expect((await result).isLeft(), isTrue);
    expect((await AuthLocalDatasource().getAuthData())?.user?.id, 88);
    expect(
        submissionImageUrl(const SubmissionRecord(
            id: 1,
            userId: 7,
            date: '2026-10-20',
            description: 'Proof',
            kind: SubmissionKind.reimbursement,
            image: 'assets/reimbursements/proof.png')),
        endsWith('/api/submission-attachments/reimbursement/1'));
    expect(
        submissionImageUrl(const SubmissionRecord(
            id: 1,
            userId: 7,
            date: '2026-10-20',
            description: 'Proof',
            kind: SubmissionKind.permission,
            image: 'proof.png')),
        endsWith('/api/submission-attachments/permission/1'));
  });

  testWidgets(
      'Form validates, cancels attachment, sends exact amount once and keeps fields on error',
      (tester) async {
    tester.view.physicalSize = const Size(390, 1000);
    tester.view.devicePixelRatio = 1;
    addTearDown(tester.view.resetPhysicalSize);
    addTearDown(tester.view.resetDevicePixelRatio);
    final source = SubmissionFixture();
    await tester.pumpWidget(app(
        await controller(),
        SubmissionFormPage(
            kind: SubmissionKind.reimbursement,
            source: source,
            pickDate: () async => DateTime(2026, 10, 20),
            pickImage: () async => null)));
    await tester.ensureVisible(find.text('Kirim pengajuan'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Kirim pengajuan'));
    await tester.pumpAndSettle();
    expect(find.text('Pilih tanggal pengajuan.'), findsOneWidget);
    expect(source.sends, 0);
    await tester.ensureVisible(find.byType(TextFormField).first);
    await tester.pumpAndSettle();
    await tester.tap(find.byType(TextFormField).first);
    await tester.pumpAndSettle();
    await tester.enterText(find.byType(TextFormField).at(1), '150.000,50');
    await tester.enterText(find.byType(TextFormField).at(2), 'Transportasi');
    await tester.ensureVisible(find.text('Pilih lampiran'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Pilih lampiran'));
    await tester.pumpAndSettle();
    expect(find.text('Hapus lampiran'), findsNothing);
    await tester.ensureVisible(find.text('Kirim pengajuan'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Kirim pengajuan'));
    await tester.pump();
    expect(source.sends, 1);
    expect(source.amount, '150000.50');
    expect(tester.widget<FilledButton>(find.byType(FilledButton)).onPressed,
        isNull);
    source.sent.complete(const Left('Server menolak pengajuan.'));
    await tester.pumpAndSettle();
    expect(find.text('Server menolak pengajuan.'), findsOneWidget);
    expect(find.text('Transportasi'), findsOneWidget);
    await tester.pumpWidget(const SizedBox());
  });

  testWidgets(
      'List supports empty, error retry, detail and a small viewport in both themes',
      (tester) async {
    tester.view.physicalSize = const Size(320, 640);
    tester.view.devicePixelRatio = 1;
    addTearDown(tester.view.resetPhysicalSize);
    addTearDown(tester.view.resetDevicePixelRatio);
    final source = SubmissionFixture();
    final theme = await controller();
    await tester.pumpWidget(app(
        theme,
        MediaQuery(
            data: const MediaQueryData(
                size: Size(320, 640), textScaler: TextScaler.linear(1.8)),
            child: SubmissionListPage(
                kind: SubmissionKind.permission, source: source))));
    await tester.pumpAndSettle();
    await tester.scrollUntilVisible(find.text('Belum ada pengajuan'), 150);
    await tester.pumpAndSettle();
    expect(find.text('Belum ada pengajuan'), findsOneWidget);
    source.error = 'Offline';
    await tester.tap(find.byTooltip('Muat ulang pengajuan'));
    await tester.pumpAndSettle();
    await tester.scrollUntilVisible(
        find.text('Daftar belum dapat dimuat'), 150);
    await tester.pumpAndSettle();
    expect(find.text('Daftar belum dapat dimuat'), findsOneWidget);
    source.error = null;
    source.rows = [record()];
    await tester.ensureVisible(find.text('Coba lagi'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Coba lagi'));
    await tester.pumpAndSettle();
    await theme.setMode(ThemeMode.dark);
    await tester.pumpAndSettle();
    await tester.scrollUntilVisible(find.text('Lihat detail'), 200);
    await tester.pumpAndSettle();
    await tester.tap(find.text('Lihat detail'));
    await tester.pumpAndSettle();
    expect(find.text('Pengajuan #1'), findsOneWidget);
    await tester.scrollUntilVisible(find.text('Tidak ada lampiran.'), 150,
        scrollable: find
            .descendant(
                of: find.byType(ListView), matching: find.byType(Scrollable))
            .first);
    await tester.pumpAndSettle();
    expect(find.text('Tidak ada lampiran.'), findsOneWidget);
    expect(tester.takeException(), isNull);
    await tester.pumpWidget(const SizedBox());
  });

  testWidgets(
      'Overtime form sends minutes through the sender without money or attachment',
      (tester) async {
    tester.view.physicalSize = const Size(390, 1000);
    tester.view.devicePixelRatio = 1;
    addTearDown(tester.view.resetPhysicalSize);
    addTearDown(tester.view.resetDevicePixelRatio);
    final source = SubmissionFixture();
    await tester.pumpWidget(app(
        await controller(),
        SubmissionFormPage(
            kind: SubmissionKind.overtime,
            source: source,
            pickDate: () async => DateTime(2026, 10, 21))));
    await tester.tap(find.widgetWithText(TextFormField, 'Tanggal'));
    await tester.pumpAndSettle();

    // Verify hint text is '120'
    final field = tester.widget<TextField>(find.descendant(
        of: find.widgetWithText(TextFormField, 'Durasi (menit)'),
        matching: find.byType(TextField)));
    expect(field.decoration?.hintText, '120');

    // Duration > 1440 is rejected
    await tester.enterText(
        find.widgetWithText(TextFormField, 'Durasi (menit)'), '1441');
    await tester.ensureVisible(find.text('Kirim pengajuan'));
    await tester.tap(find.text('Kirim pengajuan'));
    await tester.pumpAndSettle();
    expect(find.text('Isi durasi 1–1.440 menit (maks. 24 jam).'), findsOneWidget);
    expect(source.sends, 0);

    // Valid duration 1440 is accepted
    await tester.enterText(
        find.widgetWithText(TextFormField, 'Durasi (menit)'), '1440');
    await tester.enterText(
        find.widgetWithText(TextFormField, 'Catatan pekerjaan'),
        'Penyelesaian laporan');
    expect(find.text('Pilih lampiran'), findsNothing);
    await tester.ensureVisible(find.text('Kirim pengajuan'));
    await tester.tap(find.text('Kirim pengajuan'));
    await tester.pump();
    expect(source.sends, 1);
    expect(source.duration, 1440);
    expect(source.amount, isNull);
    source.sent.complete(const Left('Fixture ends here'));
    await tester.pumpAndSettle();
    expect(tester.takeException(), isNull);
  });

  testWidgets(
      'Successful form returns server record without replacing the parent route',
      (tester) async {
    tester.view.physicalSize = const Size(390, 1000);
    tester.view.devicePixelRatio = 1;
    addTearDown(tester.view.resetPhysicalSize);
    addTearDown(tester.view.resetDevicePixelRatio);
    final source = SubmissionFixture();
    SubmissionRecord? saved;
    await tester.pumpWidget(app(
        await controller(),
        Scaffold(
            body: Builder(
                builder: (context) => FilledButton(
                    onPressed: () async {
                      saved = await Navigator.of(context)
                          .push<SubmissionRecord>(MaterialPageRoute(
                              builder: (_) => SubmissionFormPage(
                                  kind: SubmissionKind.permission,
                                  source: source,
                                  pickDate: () async =>
                                      DateTime(2026, 10, 20))));
                    },
                    child: const Text('Open form'))))));
    await tester.tap(find.text('Open form'));
    await tester.pumpAndSettle();
    await tester.tap(find.byType(TextFormField).first);
    await tester.pumpAndSettle();
    await tester.enterText(
        find.byType(TextFormField).last, 'Keperluan keluarga');
    await tester.scrollUntilVisible(find.text('Tinjau pengajuan'), 150,
        scrollable: find
            .descendant(
                of: find.byType(ListView), matching: find.byType(Scrollable))
            .first);
    await tester.pumpAndSettle();
    await tester.tap(find.text('Tinjau pengajuan'));
    await tester.pump();
    expect(source.sends, 0);
    await tester.pumpAndSettle();
    await tester.tap(find.text('Kirim pengajuan'));
    await tester.pump();
    source.sent.complete(Right(record()));
    await tester.pumpAndSettle();
    expect(saved?.id, 1);
    expect(find.text('Open form'), findsOneWidget);
    await tester.pumpWidget(const SizedBox());
  });

  testWidgets(
      'Forms and date cancellation work with keyboard, large text and both themes',
      (tester) async {
    tester.view.physicalSize = const Size(320, 640);
    tester.view.devicePixelRatio = 1;
    addTearDown(tester.view.resetPhysicalSize);
    addTearDown(tester.view.resetDevicePixelRatio);
    final theme = await controller();
    for (final kind in [
      SubmissionKind.permission,
      SubmissionKind.reimbursement
    ]) {
      await tester.pumpWidget(app(
          theme,
          MediaQuery(
              data: const MediaQueryData(
                  size: Size(320, 640),
                  textScaler: TextScaler.linear(1.8),
                  viewInsets: EdgeInsets.only(bottom: 240)),
              child: SubmissionFormPage(
                  key: ValueKey(kind),
                  kind: kind,
                  source: SubmissionFixture(),
                  pickImage: () async => throw Exception('denied')))));
      await tester.scrollUntilVisible(
          find.widgetWithText(
              TextFormField, kind.isPermission ? 'Tanggal mulai' : 'Tanggal'),
          120,
          scrollable: find
              .descendant(
                  of: find.byType(ListView), matching: find.byType(Scrollable))
              .first);
      await tester.pumpAndSettle();
      await tester.tap(find.widgetWithText(
          TextFormField, kind.isPermission ? 'Tanggal mulai' : 'Tanggal'));
      await tester.pumpAndSettle();
      expect(find.text('Pilih tanggal'), findsOneWidget);
      await tester.tap(find.text('Batal').last);
      await tester.pumpAndSettle();
      await tester.scrollUntilVisible(find.text('Pilih lampiran'), 200,
          scrollable: find
              .descendant(
                  of: find.byType(ListView), matching: find.byType(Scrollable))
              .first);
      await tester.pumpAndSettle();
      await tester.tap(find.text('Pilih lampiran'));
      await tester.pumpAndSettle();
      expect(
          find.text(
              'Galeri belum dapat dibuka. Periksa izin foto lalu coba lagi.'),
          findsOneWidget);
      await theme.setMode(ThemeMode.dark);
      await tester.pumpAndSettle();
      await tester.scrollUntilVisible(
          find.text(kind.isPermission ? 'Tinjau pengajuan' : 'Kirim pengajuan'),
          200,
          scrollable: find
              .descendant(
                  of: find.byType(ListView), matching: find.byType(Scrollable))
              .first);
      await tester.pumpAndSettle();
      expect(tester.takeException(), isNull);
    }
    await tester.pumpWidget(const SizedBox());
  });

  testWidgets(
      'Overtime list loads page 1 and fetches page 2 on tapping load more',
      (tester) async {
    tester.view.physicalSize = const Size(390, 1000);
    tester.view.devicePixelRatio = 1;
    addTearDown(tester.view.resetPhysicalSize);
    addTearDown(tester.view.resetDevicePixelRatio);

    final source = PagedFixture();
    await tester.pumpWidget(app(
        await controller(),
        SubmissionListPage(kind: SubmissionKind.overtime, source: source)));
    expect(source.requests.length, 1);
    expect(source.requests.first.$1, 1);

    // Page 1 returns 20 records with lastPage = 2
    final page1Records = List.generate(
        20,
        (i) => SubmissionRecord(
            id: i + 1,
            userId: 7,
            date: '2026-10-20',
            description: 'Lembur nomor ${i + 1}',
            kind: SubmissionKind.overtime,
            duration: 60 + i,
            status: 'pending'));
    source.requests.first.$2.complete(
        Right(SubmissionBatch(page1Records, page: 1, lastPage: 2)));
    await tester.pumpAndSettle();

    expect(find.text('#1'), findsOneWidget);

    // Scroll until 'Muat lebih banyak' button is visible and tap it
    await tester.scrollUntilVisible(find.text('Muat lebih banyak'), 300);
    expect(find.text('Muat lebih banyak'), findsOneWidget);
    await tester.tap(find.text('Muat lebih banyak'));
    await tester.pump();

    // Verify page 2 was requested
    expect(source.requests.length, 2);
    expect(source.requests[1].$1, 2);

    // Page 2 returns 5 records with lastPage = 2
    final page2Records = List.generate(
        5,
        (i) => SubmissionRecord(
            id: 21 + i,
            userId: 7,
            date: '2026-10-19',
            description: 'Lembur nomor ${21 + i}',
            kind: SubmissionKind.overtime,
            duration: 90,
            status: 'approved'));
    source.requests[1].$2.complete(
        Right(SubmissionBatch(page2Records, page: 2, lastPage: 2)));
    await tester.pumpAndSettle();

    // Verify page 2 record is now displayed, and button is gone (reached last page)
    await tester.scrollUntilVisible(find.text('#25'), 200);
    expect(find.text('#25'), findsOneWidget);
    expect(find.text('Muat lebih banyak'), findsNothing);
    await tester.pumpWidget(const SizedBox());
  });
}
