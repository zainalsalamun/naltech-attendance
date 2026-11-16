import 'dart:async';
import 'dart:convert';
import 'package:dartz/dartz.dart' show Either, Left, Right;
import 'package:flutter/material.dart';
import 'package:flutter_absensi_app/data/datasources/auth_local_datasource.dart';
import 'package:flutter_absensi_app/data/datasources/submission_remote_datasource.dart';
import 'package:flutter_absensi_app/data/models/response/auth_response_model.dart';
import 'package:flutter_absensi_app/data/models/response/submission_record.dart';
import 'package:flutter_absensi_app/presentation/home/pages/submissions/submission_list_page.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'profile_session_test.dart' show app, controller;

Map<String, dynamic> payload(
        {String status = 'pending', int owner = 7, int version = 0}) =>
    {
      'id': 1,
      'user_id': owner,
      'date_permission': '2026-10-21',
      'date': '2026-10-21',
      'reason': 'Keluarga',
      'description': 'Keluarga',
      'duration': 90,
      'status': status,
      'is_approved': false,
      'approval_version': version,
      'reviewer': {'id': 2, 'name': 'HR Penguji'},
      'decision_time': '2026-10-21T15:00:00+07:00',
      'decision_reason': 'Bukti diperiksa'
    };
SubmissionRecord item(
        {String status = 'pending',
        int version = 0,
        SubmissionKind kind = SubmissionKind.permission}) =>
    SubmissionRecord.parse(payload(status: status, version: version), kind, 7);

class DecisionFixture extends SubmissionRemoteDatasource {
  SubmissionRecord current = item();
  final pending = Completer<Either<String, SubmissionRecord>>();
  int cancellations = 0, loads = 0;
  @override
  Future<Either<String, SubmissionRecord>> detail(
      SubmissionRecord record) async {
    loads++;
    return Right(current);
  }

  @override
  Future<Either<String, SubmissionRecord>> cancel(
      SubmissionRecord record, String reason,
      {bool requestCancellation = false}) {
    cancellations++;
    return pending.future.then((result) {
      result.fold((_) {}, (row) => current = row);
      return result;
    });
  }
}

void main() {
  setUp(() => SharedPreferences.setMockInitialValues({}));
  Future<void> login() => AuthLocalDatasource().saveAuthData(
      AuthResponseModel(user: User(id: 7), token: 'fixture-only-token'));
  test(
      'Canonical status overrides boolean and unknown or legacy data cannot cancel',
      () {
    for (final status in [
      'pending',
      'approved',
      'rejected',
      'cancelled',
      'needs_review'
    ]) {
      final record = item(status: status);
      expect(record.status, status);
      expect(record.canCancel, status == 'pending');
    }
    final old = payload()
      ..remove('status')
      ..remove('approval_version');
    expect(
        SubmissionRecord.parse(old, SubmissionKind.permission, 7).statusLabel,
        'Belum disetujui');
    final unknown = payload()..['status'] = 'future-state';
    expect(
        SubmissionRecord.parse(unknown, SubmissionKind.permission, 7).canCancel,
        false);
    expect(item().decisionTime!.toUtc(), DateTime.utc(2026, 10, 21, 8));
  });
  test(
      'Cancellation uses own fixed endpoint version reason and confirms response',
      () async {
    await login();
    var calls = 0;
    final source =
        SubmissionRemoteDatasource(client: MockClient((request) async {
      calls++;
      expect(request.url.path, '/api/submission-cancellations/permission/1');
      expect(request.followRedirects, false);
      expect(jsonDecode(request.body),
          {'approval_version': 0, 'decision_reason': 'Tidak jadi'});
      return http.Response(
          jsonEncode({'data': payload(status: 'cancelled', version: 1)}), 200);
    }));
    expect((await source.cancel(item(), ' Tidak jadi ')).isRight(), true);
    expect(calls, 1);
    expect(
        (await source.cancel(item(status: 'approved'), 'No')).isLeft(), true);
    expect(calls, 1);
    expect((await source.cancel(item(), ' ')).isLeft(), true);
    expect(calls, 1);
  });
  test(
      'Conflict, malformed success and account switching never claim cancellation',
      () async {
    await login();
    for (final status in [409, 422, 503, 200]) {
      final source = SubmissionRemoteDatasource(
          client: MockClient((_) async => http.Response('{}', status)));
      expect((await source.cancel(item(), 'Reason')).isLeft(), true);
    }
    final started = Completer<void>(), pending = Completer<http.Response>();
    final source = SubmissionRemoteDatasource(client: MockClient((_) {
      started.complete();
      return pending.future;
    }));
    final request = source.cancel(item(), 'Reason');
    await started.future;
    await AuthLocalDatasource().saveAuthData(
        AuthResponseModel(user: User(id: 8), token: 'new-fixture-only-token'));
    pending.complete(http.Response(
        jsonEncode({'data': payload(status: 'cancelled', version: 1)}), 200));
    expect((await request).isLeft(), true);
    expect((await AuthLocalDatasource().getAuthData())!.user!.id, 8);
  });
  test('Overtime accepts nullable old notes and sends duration without money',
      () async {
    await login();
    final data = payload()..['description'] = '';
    expect(SubmissionRecord.parse(data, SubmissionKind.overtime, 7).description,
        'Tanpa catatan');
    data['description'] = null;
    expect(SubmissionRecord.parse(data, SubmissionKind.overtime, 7).description,
        'Tanpa catatan');
    final source =
        SubmissionRemoteDatasource(client: MockClient((request) async {
      expect(request.body, contains('name="duration"\r\n\r\n90'));
      expect(request.body, isNot(contains('name="amount"')));
      return http.Response(jsonEncode({'data': payload()}), 201);
    }));
    expect(
        (await source.submit(SubmissionKind.overtime,
                date: '2026-10-21', description: 'Keluarga', duration: 90))
            .isRight(),
        true);
  });
  test(
      'Workflow progress and stage decisions parse without changing pending cancellation',
      () {
    final data = payload(version: 1)
      ..addAll({
        'approval_progress': {
          'current': 2,
          'total': 2,
          'label': 'HR',
          'steps': ['Atasan', 'HR']
        },
        'approval_current_reviewer': 'HR Naltech',
        'approval_history': [
          {
            'actor': 'Atasan Naltech',
            'action': 'approved',
            'stage': 1,
            'reason': 'Diperiksa atasan',
            'time': '2026-10-21T08:00:00+07:00'
          }
        ],
      });
    final record = SubmissionRecord.parse(data, SubmissionKind.permission, 7);
    expect(record.progress!.current, 2);
    expect(record.progress!.steps, ['Atasan', 'HR']);
    expect(record.currentReviewer, 'HR Naltech');
    expect(record.approvalHistory.single.label, 'Disetujui');
    expect(record.approvalHistory.single.stage, 1);
    expect(record.canCancel, true);
    expect(
        SubmissionProgress.parse({
          'current': 5,
          'total': 2,
          'steps': ['A', 'B']
        }),
        isNull);
    expect(
        SubmissionApprovalEvent.parse([
          null,
          {'action': 'approved'}
        ]),
        isEmpty);
  });
  testWidgets(
      'Active stage and prior decision render on narrow screen with large text',
      (tester) async {
    tester.view.physicalSize = const Size(320, 640);
    tester.view.devicePixelRatio = 1;
    addTearDown(tester.view.resetPhysicalSize);
    addTearDown(tester.view.resetDevicePixelRatio);
    final fixture = DecisionFixture()
      ..current = SubmissionRecord.parse(
          payload(version: 1)
            ..addAll({
              'approval_progress': {
                'current': 2,
                'total': 2,
                'label': 'HR',
                'steps': ['Atasan', 'HR']
              },
              'approval_current_reviewer': 'HR Naltech',
              'approval_history': [
                {
                  'actor': 'Atasan Naltech',
                  'action': 'approved',
                  'stage': 1,
                  'reason': 'Diperiksa atasan',
                  'time': '2026-10-21T08:00:00+07:00'
                }
              ],
            }),
          SubmissionKind.permission,
          7);
    await tester.pumpWidget(app(
        await controller(),
        MediaQuery(
            data: const MediaQueryData(
                size: Size(320, 640), textScaler: TextScaler.linear(1.8)),
            child: SubmissionDetailPage(
                record: fixture.current, source: fixture))));
    await tester.pumpAndSettle();
    expect(find.text('Tahap 2 dari 2: HR'), findsOneWidget);
    await tester.scrollUntilVisible(find.text('Diperiksa atasan'), 150,
        scrollable: find
            .descendant(
                of: find.byType(ListView), matching: find.byType(Scrollable))
            .first);
    expect(find.text('Tahap 1 · Disetujui'), findsOneWidget);
    expect(tester.takeException(), isNull);
  });
  for (final conflict in [false, true]) {
    testWidgets(
        'Cancellation refreshes detail and blocks repeat taps; conflict=$conflict',
        (tester) async {
      tester.view.physicalSize = const Size(390, 1000);
      tester.view.devicePixelRatio = 1;
      addTearDown(tester.view.resetPhysicalSize);
      addTearDown(tester.view.resetDevicePixelRatio);
      final fixture = DecisionFixture();
      await tester.pumpWidget(app(await controller(),
          SubmissionDetailPage(record: item(), source: fixture)));
      await tester.pumpAndSettle();
      await tester.scrollUntilVisible(find.text('Batalkan pengajuan'), 200,
          scrollable: find
              .descendant(
                  of: find.byType(ListView), matching: find.byType(Scrollable))
              .first);
      await tester.tap(find.text('Batalkan pengajuan'));
      await tester.pumpAndSettle();
      await tester.enterText(find.byType(TextFormField), 'Tidak jadi');
      await tester.tap(find.text('Konfirmasi pembatalan'));
      await tester.pump();
      expect(fixture.cancellations, 1);
      if (conflict) {
        fixture.current = item(status: 'approved', version: 1);
        fixture.pending.complete(const Left('Pengajuan sudah berubah.'));
      } else {
        fixture.pending.complete(Right(item(status: 'cancelled', version: 1)));
      }
      await tester.pumpAndSettle();
      expect(fixture.loads, 2);
      expect(find.text('Batalkan pengajuan'), findsNothing);
      expect(find.text(conflict ? 'Disetujui' : 'Dibatalkan'), findsOneWidget);
      expect(tester.takeException(), isNull);
    });
  }
}
