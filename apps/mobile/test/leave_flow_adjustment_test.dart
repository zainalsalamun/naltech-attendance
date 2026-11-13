import 'dart:async';
import 'package:dartz/dartz.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:flutter_absensi_app/presentation/home/pages/submissions/submission_form_page.dart';
import 'package:flutter_absensi_app/data/models/response/submission_record.dart';
import 'leave_policy_mobile_test.dart' show LeaveFixture, policy;
import 'profile_session_test.dart' show app, controller;
import 'support/leave_preview_fixture.dart';

class QueuedPreview extends LeaveFixture {
  final requests = <Completer<Either<String, Map<String, dynamic>>>>[];
  @override
  Future<Either<String, Map<String, dynamic>>> calculateLeave(
      String start, String end, int? type) {
    final pending = Completer<Either<String, Map<String, dynamic>>>();
    requests.add(pending);
    return pending.future;
  }
}

Future<void> reveal(WidgetTester tester, Finder finder) async {
  await tester.scrollUntilVisible(finder, 150,
      scrollable: find
          .descendant(
              of: find.byType(ListView).first,
              matching: find.byType(Scrollable))
          .first);
  await tester.pump();
}

void main() {
  setUp(() => SharedPreferences.setMockInitialValues({}));
  testWidgets(
      'Latest date preview wins, review does not submit, confirmation submits once',
      (tester) async {
    tester.view.physicalSize = const Size(430, 1100);
    tester.view.devicePixelRatio = 1;
    addTearDown(tester.view.resetPhysicalSize);
    addTearDown(tester.view.resetDevicePixelRatio);
    final source = QueuedPreview()..catalog = Right([policy(required: false)]);
    var calls = 0;
    await tester.pumpWidget(app(
        await controller(),
        SubmissionFormPage(
            kind: SubmissionKind.permission,
            source: source,
            pickDate: () async => DateTime(2026, 10, 21 + calls++))));
    await tester.pumpAndSettle();
    await reveal(tester, find.widgetWithText(TextFormField, 'Tanggal mulai'));
    await tester.tap(find.widgetWithText(TextFormField, 'Tanggal mulai'));
    await tester.pump();
    await reveal(tester, find.widgetWithText(TextFormField, 'Tanggal selesai'));
    await tester.tap(find.widgetWithText(TextFormField, 'Tanggal selesai'));
    await tester.pump();
    expect(source.requests.length, 2);
    source.requests[1].complete(Right(leavePreview(days: 2)));
    await tester.pump();
    source.requests[0].complete(const Left('Outdated preview error'));
    await tester.pumpAndSettle();
    expect(find.text('Outdated preview error'), findsNothing);
    expect(find.text('2 Hari Kerja'), findsOneWidget);
    await reveal(tester, find.widgetWithText(TextFormField, 'Keperluan'));
    await tester.enterText(
        find.widgetWithText(TextFormField, 'Keperluan'), 'Urusan keluarga');
    await reveal(tester, find.text('Tinjau pengajuan'));
    await tester.tap(find.text('Tinjau pengajuan'));
    await tester.pumpAndSettle();
    expect(source.sends, 0);
    expect(find.textContaining('2 hari menjadi cadangan'), findsOneWidget);
    await tester.tap(find.text('Kembali mengedit'));
    await tester.pumpAndSettle();
    expect(source.sends, 0);
    await tester.tap(find.text('Tinjau pengajuan'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Kirim pengajuan'));
    await tester.pumpAndSettle();
    expect(source.sends, 1);
  });
  testWidgets('Failed preview blocks review until explicitly recalculated',
      (tester) async {
    final source = QueuedPreview();
    await tester.pumpWidget(app(
        await controller(),
        SubmissionFormPage(
            kind: SubmissionKind.permission,
            source: source,
            pickDate: () async => DateTime(2026, 10, 21))));
    await tester.pumpAndSettle();
    await reveal(tester, find.widgetWithText(TextFormField, 'Tanggal mulai'));
    await tester.tap(find.widgetWithText(TextFormField, 'Tanggal mulai'));
    await tester.pump();
    source.requests.single.complete(const Left('Koneksi terputus'));
    await tester.pumpAndSettle();
    await reveal(tester, find.text('Tinjau pengajuan'));
    expect(
        tester
            .widget<FilledButton>(
                find.widgetWithText(FilledButton, 'Tinjau pengajuan'))
            .onPressed,
        isNull);
    expect(source.sends, 0);
    await reveal(tester, find.text('Hitung ulang hari dan saldo'));
    await tester.tap(find.text('Hitung ulang hari dan saldo'));
    await tester.pump();
    source.requests.last.complete(Right(leavePreview()));
    await tester.pumpAndSettle();
    await reveal(tester, find.text('Tinjau pengajuan'));
    expect(
        tester
            .widget<FilledButton>(
                find.widgetWithText(FilledButton, 'Tinjau pengajuan'))
            .onPressed,
        isNotNull);
    expect(find.text('Koneksi terputus'), findsNothing);
  });
}
