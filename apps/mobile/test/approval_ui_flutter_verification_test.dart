import 'dart:async';
import 'dart:convert';
import 'package:dartz/dartz.dart' show Either, Left, Right;
import 'package:flutter/material.dart';
import 'package:flutter_absensi_app/core/theme/workspace_theme.dart';
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

Map<String, dynamic> samplePayload({
  int id = 101,
  String status = 'pending',
  int version = 0,
  int owner = 7,
  List<Map<String, dynamic>>? history,
  Map<String, dynamic>? progress,
  String? currentReviewer,
}) =>
    {
      'id': id,
      'user_id': owner,
      'date_permission': '2026-11-20',
      'start_date': '2026-11-20',
      'end_date': '2026-11-22',
      'duration_days': 3,
      'date': '2026-11-20',
      'reason': 'Cuti libur tahunan keluarga',
      'description': 'Cuti libur tahunan keluarga',
      'status': status,
      'is_approved': status == 'approved',
      'approval_version': version,
      'reviewer': {'id': 2, 'name': 'QA Supervisor Direct'},
      'decision_time': '2026-11-20T10:00:00+07:00',
      'decision_reason': 'Diverifikasi sesuai kebijakan.',
      'approval_current_reviewer': currentReviewer,
      'approval_progress': progress,
      'approval_history': history ?? [],
    };

SubmissionRecord makeRecord({
  int id = 101,
  String status = 'pending',
  int version = 0,
  int owner = 7,
  SubmissionKind kind = SubmissionKind.permission,
  List<Map<String, dynamic>>? history,
  Map<String, dynamic>? progress,
  String? currentReviewer,
}) =>
    SubmissionRecord.parse(
        samplePayload(
          id: id,
          status: status,
          version: version,
          owner: owner,
          history: history,
          progress: progress,
          currentReviewer: currentReviewer,
        ),
        kind,
        owner);

class TestDecisionFixture extends SubmissionRemoteDatasource {
  SubmissionRecord current = makeRecord();
  Completer<Either<String, SubmissionRecord>>? pendingCancel;
  int cancelCount = 0;
  int detailLoads = 0;
  int listLoads = 0;
  List<SubmissionRecord> listRecords = [];

  @override
  Future<Either<String, SubmissionBatch>> list(SubmissionKind kind,
      {int page = 1}) async {
    listLoads++;
    return Right(SubmissionBatch(List.of(listRecords), page: 1, lastPage: 1));
  }

  @override
  Future<Either<String, SubmissionRecord>> detail(
      SubmissionRecord record) async {
    detailLoads++;
    return Right(current);
  }

  @override
  Future<Either<String, SubmissionRecord>> cancel(
      SubmissionRecord record, String reason,
      {bool requestCancellation = false}) {
    cancelCount++;
    if (pendingCancel != null) {
      return pendingCancel!.future.then((res) {
        res.fold((_) {}, (r) => current = r);
        return res;
      });
    }
    return Future.value(Right(current));
  }
}

Widget testApp(Widget page, {ThemeMode mode = ThemeMode.light}) => MaterialApp(
      theme: WorkspaceTheme.light,
      darkTheme: WorkspaceTheme.dark,
      themeMode: mode,
      home: page,
    );

void main() {
  setUp(() {
    SharedPreferences.setMockInitialValues({});
    AuthLocalDatasource.resetForTesting();
  });

  Future<void> loginUser([int userId = 7]) async {
    final pref = await SharedPreferences.getInstance();
    await pref.setString(
      'auth_data',
      AuthResponseModel(
        user: User(id: userId),
        token: 'qa-mock-token-$userId',
      ).toJson(),
    );
  }

  // -------------------------------------------------------------
  // F01: Payload API lama tanpa action_label/event_type
  // -------------------------------------------------------------
  test('F01: Legacy API payload without action_label/event_type fallback gracefully', () {
    final legacyActions = {
      'stage_approved': 'Tahap disetujui',
      'cancellation_approved': 'Pembatalan disetujui',
      'cancellation_rejected': 'Pembatalan ditolak',
      'cancellation_requested': 'Menunggu pembatalan',
      'approved': 'Disetujui',
      'rejected': 'Ditolak',
      'cancelled': 'Dibatalkan',
      'updated': 'Informasi diperbarui',
      'unknown_event': 'Perubahan pengajuan',
    };

    for (final entry in legacyActions.entries) {
      final events = SubmissionApprovalEvent.parse([
        {
          'action': entry.key,
          'reason': 'Catatan tanpa metadata baru',
          'actor': 'Atasan Lama',
          'time': '2026-10-20T08:00:00+07:00'
        }
      ]);
      expect(events.length, 1);
      expect(events.first.label, entry.value,
          reason: 'Action ${entry.key} should produce fallback label ${entry.value}');
      expect(events.first.actionLabel, isNull);
      expect(events.first.eventType, isNull);
    }
  });

  // -------------------------------------------------------------
  // F02: Payload baru untuk seluruh event
  // -------------------------------------------------------------
  test('F02: New payload with explicit action_label and event_type parses correctly', () {
    final explicitEvents = [
      {
        'action': 'stage_approved',
        'action_label': 'Tahap 1 disetujui atasan',
        'event_type': 'stage_approved',
        'stage': 1,
        'actor': 'QA Supervisor Direct',
        'reason': 'Pemeriksaan awal valid',
        'time': '2026-10-28T08:00:00+07:00'
      },
      {
        'action': 'approved',
        'action_label': 'Persetujuan final HR',
        'event_type': 'final_approved',
        'stage': 2,
        'actor': 'QA HR Manager',
        'reason': 'Kuota cuti diverifikasi',
        'time': '2026-10-28T09:00:00+07:00'
      },
      {
        'action': 'rejected',
        'action_label': 'Pengajuan ditolak HR',
        'event_type': 'rejected',
        'actor': 'QA HR Manager',
        'reason': 'Dokumen pendukung kurang',
        'time': '2026-10-28T10:00:00+07:00'
      },
      {
        'action': 'cancellation_approved',
        'action_label': 'Pembatalan disetujui HR',
        'event_type': 'cancellation_approved',
        'actor': 'QA HR Manager',
        'reason': 'Pengembalian saldo disetujui',
        'time': '2026-10-28T11:00:00+07:00'
      },
      {
        'action': 'cancellation_rejected',
        'action_label': 'Permintaan pembatalan ditolak HR',
        'event_type': 'cancellation_rejected',
        'actor': 'QA HR Manager',
        'reason': 'Jadwal operasional sudah ditetapkan',
        'time': '2026-10-28T12:00:00+07:00'
      },
    ];

    final parsed = SubmissionApprovalEvent.parse(explicitEvents);
    expect(parsed.length, 5);
    expect(parsed[0].label, 'Tahap 1 disetujui atasan');
    expect(parsed[0].eventType, 'stage_approved');
    expect(parsed[0].stage, 1);
    expect(parsed[1].label, 'Persetujuan final HR');
    expect(parsed[1].eventType, 'final_approved');
    expect(parsed[2].label, 'Pengajuan ditolak HR');
    expect(parsed[3].label, 'Pembatalan disetujui HR');
    expect(parsed[4].label, 'Permintaan pembatalan ditolak HR');
    expect(parsed[4].eventType, 'cancellation_rejected');
  });

  // -------------------------------------------------------------
  // F03: Pembatalan ditolak lalu status approved
  // -------------------------------------------------------------
  testWidgets('F03: Rejected cancellation returns to approved state without resetting lifecycle',
      (tester) async {
    tester.view.physicalSize = const Size(430, 1200);
    tester.view.devicePixelRatio = 1;
    addTearDown(tester.view.resetPhysicalSize);
    addTearDown(tester.view.resetDevicePixelRatio);

    await loginUser();
    final fixture = TestDecisionFixture()
      ..current = makeRecord(
        status: 'approved',
        version: 2,
        history: [
          {
            'action': 'cancellation_rejected',
            'action_label': 'Permintaan pembatalan ditolak HR',
            'actor': 'QA HR Manager',
            'reason': 'Operasional mendesak',
            'time': '2026-10-28T10:00:00+07:00'
          }
        ],
      );

    await tester.pumpWidget(testApp(
        SubmissionDetailPage(record: fixture.current, source: fixture)));
    await tester.pumpAndSettle();

    // Renders approved status
    expect(find.text('Disetujui'), findsOneWidget);
    // User can still request cancellation again on active approved leave
    expect(find.text('Ajukan pembatalan ke HR'), findsOneWidget);
    // Not a pending direct cancel
    expect(find.text('Batalkan pengajuan'), findsNothing);
    expect(find.textContaining('Permintaan pembatalan ditolak HR'), findsOneWidget);
    expect(tester.takeException(), isNull);
  });

  // -------------------------------------------------------------
  // F04: Pending tahap 1 -> tahap 2 -> final melalui Dashboard
  // -------------------------------------------------------------
  testWidgets('F04: Mobile detail displays updated multi-stage progress and reviewer',
      (tester) async {
    tester.view.physicalSize = const Size(430, 1200);
    tester.view.devicePixelRatio = 1;
    addTearDown(tester.view.resetPhysicalSize);
    addTearDown(tester.view.resetDevicePixelRatio);

    await loginUser();
    final fixture = TestDecisionFixture()
      ..current = makeRecord(
        status: 'pending',
        version: 1,
        currentReviewer: 'QA HR Manager',
        progress: {
          'current': 2,
          'total': 2,
          'label': 'HR Approval',
          'steps': ['Atasan Langsung', 'HR Approval']
        },
        history: [
          {
            'action': 'stage_approved',
            'action_label': 'Tahap 1 disetujui atasan',
            'stage': 1,
            'actor': 'QA Supervisor Direct',
            'reason': 'Disetujui tahap 1',
            'time': '2026-10-28T09:00:00+07:00'
          }
        ],
      );

    await tester.pumpWidget(testApp(
        SubmissionDetailPage(record: fixture.current, source: fixture)));
    await tester.pumpAndSettle();

    expect(find.text('Tahap 2 dari 2: HR Approval'), findsOneWidget);
    expect(find.textContaining('QA HR Manager'), findsOneWidget);
    expect(find.textContaining('Tahap 1 disetujui atasan'), findsOneWidget);
    expect(tester.takeException(), isNull);
  });

  // -------------------------------------------------------------
  // F05: Ajukan pembatalan approved dari mobile
  // -------------------------------------------------------------
  testWidgets('F05: Submitting cancellation request for approved leave transitions status and blocks double tap',
      (tester) async {
    tester.view.physicalSize = const Size(430, 1200);
    tester.view.devicePixelRatio = 1;
    addTearDown(tester.view.resetPhysicalSize);
    addTearDown(tester.view.resetDevicePixelRatio);

    await loginUser();
    final fixture = TestDecisionFixture()
      ..current = makeRecord(status: 'approved', version: 1)
      ..pendingCancel = Completer<Either<String, SubmissionRecord>>();

    await tester.pumpWidget(testApp(
        SubmissionDetailPage(record: fixture.current, source: fixture)));
    await tester.pumpAndSettle();

    final btnFinder = find.text('Ajukan pembatalan ke HR');
    expect(btnFinder, findsOneWidget);
    await tester.tap(btnFinder);
    await tester.pumpAndSettle();

    // Dialog appears with informative text
    expect(find.text('Ajukan pembatalan cuti ke HR?'), findsOneWidget);
    expect(find.textContaining('Pengajuan tetap berlaku selama permohonan pembatalan ditinjau'), findsOneWidget);

    // Enter reason and submit
    await tester.enterText(find.byType(TextFormField), 'Rencana perjalanan dibatalkan mendadak');
    await tester.tap(find.text('Kirim permohonan'));
    await tester.pump();

    // Button disabled / shows processing state
    expect(fixture.cancelCount, 1);
    expect(find.text('Memproses…'), findsWidgets);

    // Complete async request
    fixture.current = makeRecord(
      status: 'cancellation_requested',
      version: 2,
    );
    fixture.pendingCancel!.complete(Right(fixture.current));
    await tester.pumpAndSettle();

    expect(find.text('Menunggu pembatalan'), findsOneWidget);
    expect(find.text('Ajukan pembatalan ke HR'), findsNothing);
    expect(tester.takeException(), isNull);
  });

  // -------------------------------------------------------------
  // F06: Keputusan pembatalan dari web
  // -------------------------------------------------------------
  testWidgets('F06: Refreshing detail after web cancellation reflects cancelled or approved status',
      (tester) async {
    tester.view.physicalSize = const Size(430, 1200);
    tester.view.devicePixelRatio = 1;
    addTearDown(tester.view.resetPhysicalSize);
    addTearDown(tester.view.resetDevicePixelRatio);

    await loginUser();
    final fixture = TestDecisionFixture()
      ..current = makeRecord(status: 'cancellation_requested', version: 2);

    await tester.pumpWidget(testApp(
        SubmissionDetailPage(record: fixture.current, source: fixture)));
    await tester.pumpAndSettle();
    expect(find.text('Menunggu pembatalan'), findsOneWidget);

    // Web HR approved cancellation
    fixture.current = makeRecord(status: 'cancelled', version: 3);
    await tester.tap(find.byIcon(Icons.refresh));
    await tester.pumpAndSettle();
    expect(find.text('Dibatalkan'), findsOneWidget);
    expect(find.text('Ajukan pembatalan ke HR'), findsNothing);

    // If web HR had rejected cancellation instead
    fixture.current = makeRecord(status: 'approved', version: 4);
    await tester.tap(find.byIcon(Icons.refresh));
    await tester.pumpAndSettle();
    expect(find.text('Disetujui'), findsOneWidget);
    expect(find.text('Ajukan pembatalan ke HR'), findsOneWidget);
    expect(tester.takeException(), isNull);
  });

  // -------------------------------------------------------------
  // F07: Kembali dari detail & refresh otomatis daftar
  // -------------------------------------------------------------
  testWidgets('F07: Returning from detail automatically refreshes submission list with updated status without route duplication',
      (tester) async {
    tester.view.physicalSize = const Size(430, 1200);
    tester.view.devicePixelRatio = 1;
    addTearDown(tester.view.resetPhysicalSize);
    addTearDown(tester.view.resetDevicePixelRatio);

    await loginUser();
    final fixture = TestDecisionFixture();
    final initialApproved = makeRecord(status: 'approved', version: 1);
    fixture.current = initialApproved;
    fixture.listRecords = [initialApproved];

    await tester.pumpWidget(testApp(
        SubmissionListPage(kind: SubmissionKind.permission, source: fixture)));
    await tester.pumpAndSettle();

    // 1. Initial list displays single approved item
    expect(find.byType(SubmissionListPage), findsOneWidget);
    expect(find.text('Disetujui'), findsOneWidget);
    expect(find.text('Lihat detail'), findsOneWidget);
    expect(fixture.listLoads, 1, reason: 'Initial list load must execute once');

    // 2. Open detail page
    await tester.tap(find.text('Lihat detail'));
    await tester.pumpAndSettle();

    expect(find.byType(SubmissionDetailPage), findsOneWidget);
    expect(find.text('Detail izin'), findsOneWidget);
    expect(find.text('Ajukan pembatalan ke HR'), findsOneWidget);

    // 3. User submits cancellation request on detail page
    await tester.tap(find.text('Ajukan pembatalan ke HR'));
    await tester.pumpAndSettle();
    expect(find.text('Ajukan pembatalan cuti ke HR?'), findsOneWidget);

    await tester.enterText(
        find.byType(TextFormField), 'Permohonan pembatalan dari detail list');

    // Prepare updated state returned by backend
    final updatedRecord =
        makeRecord(status: 'cancellation_requested', version: 2);
    fixture.current = updatedRecord;
    fixture.listRecords = [updatedRecord];

    await tester.tap(find.text('Kirim permohonan'));
    await tester.pumpAndSettle();

    expect(find.text('Menunggu pembatalan'), findsOneWidget);
    expect(fixture.cancelCount, 1);

    // 4. Back navigation pops detail cleanly
    await tester.tap(find.byType(BackButton));
    await tester.pumpAndSettle();

    // 5. Back on SubmissionListPage: detail popped, list automatically refreshed
    expect(find.byType(SubmissionDetailPage), findsNothing);
    expect(find.byType(SubmissionListPage), findsOneWidget);
    expect(fixture.listLoads, 2,
        reason: 'Returning from detail must automatically trigger list refresh');
    expect(find.text('Menunggu pembatalan'), findsOneWidget,
        reason: 'List view must reflect newly refreshed cancellation_requested status');
    expect(find.text('Disetujui'), findsNothing,
        reason: 'Stale approved status must no longer be present on the list');
    expect(tester.takeException(), isNull);
  });

  // -------------------------------------------------------------
  // F08: Error jaringan, 409 atau sesi berakhir
  // -------------------------------------------------------------
  test('F08: Network error, 409 conflict and 401 session expiry provide recovery messages',
      () async {
    await loginUser();

    // 1. 409 Stale version conflict
    final client409 = MockClient((_) async => http.Response(
        jsonEncode({'message': 'Pengajuan berubah. Muat ulang sebelum mengambil keputusan.'}),
        409));
    final source409 = SubmissionRemoteDatasource(client: client409);
    final result409 = await source409.cancel(makeRecord(), 'Alasan');
    expect(result409.isLeft(), true);
    result409.fold((msg) => expect(msg, contains('Pengajuan sudah berubah')), (_) => fail('Expected failure'));

    // 2. 401 Session expired
    final client401 = MockClient((_) async => http.Response(
        jsonEncode({'message': 'Akun nonaktif. Hubungi HR/admin.'}), 401));
    final source401 = SubmissionRemoteDatasource(client: client401);
    final result401 = await source401.cancel(makeRecord(), 'Alasan');
    expect(result401.isLeft(), true);
    result401.fold((msg) => expect(msg, contains('Sesi berakhir')), (_) => fail('Expected failure'));

    // 3. 503 Server / network error
    final client503 = MockClient((_) async => http.Response('Service Unavailable', 503));
    final source503 = SubmissionRemoteDatasource(client: client503);
    final result503 = await source503.cancel(makeRecord(), 'Alasan');
    expect(result503.isLeft(), true);
  });

  // -------------------------------------------------------------
  // F09: Detail dibuka lalu respons terlambat / akun berubah
  // -------------------------------------------------------------
  test('F09: Stale delayed response does not overwrite state if user switched',
      () async {
    await loginUser(7);
    final started = Completer<void>();
    final delayedPending = Completer<http.Response>();

    final source = SubmissionRemoteDatasource(client: MockClient((_) {
      started.complete();
      return delayedPending.future;
    }));

    final cancelFuture = source.cancel(makeRecord(owner: 7), 'Alasan');
    await started.future;

    // User switches to different account (ID: 99) before response arrives
    await loginUser(99);

    delayedPending.complete(http.Response(
        jsonEncode({'data': samplePayload(status: 'cancelled', version: 1)}),
        200));

    final res = await cancelFuture;
    expect(res.isLeft(), true,
        reason: 'Delayed response must be discarded when active user has switched');
  });

  // -------------------------------------------------------------
  // F10: Tema, sempit, teks besar, alasan panjang
  // -------------------------------------------------------------
  testWidgets('F10: Dark and light themes, narrow screen and large text scale without RenderFlex overflow',
      (tester) async {
    await loginUser();
    tester.view.physicalSize = const Size(320, 640);
    tester.view.devicePixelRatio = 1;
    addTearDown(tester.view.resetPhysicalSize);
    addTearDown(tester.view.resetDevicePixelRatio);

    final xssAndLongReason =
        '<script>alert("xss")</script> & <b>bold</b>. ' +
        'Alasan permohonan yang sengaja dibuat sangat panjang untuk menguji teks melipat tanpa meluap ke luar batas layar atau card.';

    final fixture = TestDecisionFixture()
      ..current = makeRecord(
        status: 'pending',
        version: 1,
        currentReviewer: 'Supervisor Sangat Panjang Sekali Namanya Dari Tim Engineering Naltech',
        progress: {
          'current': 1,
          'total': 2,
          'label': 'Pemeriksaan Supervisor',
          'steps': ['Pemeriksaan Supervisor', 'HR Manager']
        },
        history: [
          {
            'action': 'stage_approved',
            'action_label': 'Tahap 1 disetujui atasan',
            'actor': 'Supervisor Peninjau Utama',
            'reason': xssAndLongReason,
            'time': '2026-10-28T08:00:00+07:00'
          }
        ],
      );

    final theme = await controller();
    await tester.pumpWidget(app(
      theme,
      MediaQuery(
        data: const MediaQueryData(
          size: Size(320, 640),
          textScaler: TextScaler.linear(1.8),
        ),
        child: SubmissionDetailPage(
          record: fixture.current,
          source: fixture,
        ),
      ),
    ));
    await tester.pumpAndSettle();

    expect(find.text('Tahap 1 dari 2: Pemeriksaan Supervisor'), findsOneWidget);
    expect(tester.takeException(), isNull,
        reason: 'No overflow exception on light theme at 320x640 with textScaler 1.8x');

    await theme.setMode(ThemeMode.dark);
    await tester.pumpAndSettle();
    expect(find.text('Tahap 1 dari 2: Pemeriksaan Supervisor'), findsOneWidget);
    expect(tester.takeException(), isNull,
        reason: 'No overflow exception on dark theme at 320x640 with textScaler 1.8x');
  });
}
