// Native QA only, copied to a marked disposable checkout with a random fixture password.
import 'dart:convert';
import 'dart:io';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:integration_test/integration_test.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:flutter_absensi_app/qa_main.dart' as entry;
import 'package:flutter_absensi_app/data/datasources/auth_local_datasource.dart';
import 'package:flutter_absensi_app/data/datasources/submission_remote_datasource.dart';
import 'package:flutter_absensi_app/data/models/response/submission_record.dart';
import 'package:flutter_absensi_app/presentation/auth/pages/login_page.dart';
import 'package:flutter_absensi_app/presentation/home/pages/main_page.dart';
import 'package:flutter_absensi_app/presentation/home/pages/submissions/submission_list_page.dart';
import 'package:flutter_absensi_app/presentation/home/pages/submissions/submission_form_page.dart';

const password = '__QA_PASSWORD__';
const cache = '/data/user/0/com.naltech.attendance.approvalqa/cache';
void main() {
  final binding = IntegrationTestWidgetsFlutterBinding.ensureInitialized();
  testWidgets(
    'Native submissions Dashboard decisions offline and session expiry',
    (tester) async {
      final checks = <String>[];
      final runTag = DateTime.now().millisecondsSinceEpoch;
      final source = SubmissionRemoteDatasource();
      Future<void> stage(
        String name, [
        Map<String, dynamic> data = const {},
      ]) async {
        await File(
          '$cache/approval-progress.json',
        ).writeAsString(jsonEncode({'stage': name, 'checks': checks, ...data}));
      }

      Future<void> waitFor(Finder finder, {int seconds = 40}) async {
        final until = DateTime.now().add(Duration(seconds: seconds));
        while (finder.evaluate().isEmpty && DateTime.now().isBefore(until)) {
          await tester.pump(const Duration(milliseconds: 250));
        }
        expect(finder, findsWidgets);
        await tester.pump(const Duration(milliseconds: 300));
      }

      Future<void> tap(String text) async {
        final f = find.text(text);
        await waitFor(f);
        await tester.ensureVisible(f.first);
        await tester.tap(f.first);
        await tester.pump(const Duration(milliseconds: 400));
      }

      Future<void> scroll(String text) async {
        await tester.scrollUntilVisible(
          find.text(text),
          160,
          scrollable: find
              .descendant(
                of: find.byType(ListView).first,
                matching: find.byType(Scrollable),
              )
              .first,
        );
        await tester.pump(const Duration(milliseconds: 300));
      }

      Future<void> screen(String name) async {
        await tester.pump(const Duration(milliseconds: 500));
        await File(
          '$cache/$name.png',
        ).writeAsBytes(await binding.takeScreenshot(name));
      }

      Future<void> signal(String name) async {
        final file = File('$cache/control-$name');
        final until = DateTime.now().add(const Duration(minutes: 8));
        while (!await file.exists() && DateTime.now().isBefore(until)) {
          await tester.pump(const Duration(milliseconds: 500));
        }
        expect(await file.exists(), true);
      }

      final prefs = await SharedPreferences.getInstance();
      await prefs.remove('auth_data');
      await prefs.setString('naltech.theme', 'light');
      entry.main();
      await tester.pump();
      await waitFor(find.byType(LoginPage));
      await tester.enterText(
        find.byType(TextFormField).first,
        'face@example.test',
      );
      await tester.enterText(find.byType(TextFormField).last, password);
      FocusManager.instance.primaryFocus?.unfocus();
      await tap('Masuk');
      await waitFor(find.byType(MainPage));
      checks.add('Native login');
      await stage('logged_in');
      await binding.convertFlutterSurfaceToImage();
      final records = <SubmissionKind, SubmissionRecord>{};
      for (final kind in SubmissionKind.values) {
        await stage('creating_${kind.apiKind}');
        Navigator.of(tester.element(find.byType(MainPage))).push(
          MaterialPageRoute<void>(
            builder: (_) => SubmissionListPage(kind: kind),
          ),
        );
        await waitFor(find.text('Ajukan ${kind.title.toLowerCase()}'));
        await tap('Ajukan ${kind.title.toLowerCase()}');
        await waitFor(find.byType(SubmissionFormPage));
        await tester.tap(find.byType(TextFormField).first);
        await waitFor(find.text('Pilih tanggal'));
        await tap('${DateTime.now().day}');
        if (!kind.isPermission)
          await tester.enterText(
            find.byType(TextFormField).at(1),
            kind.isOvertime ? '90' : '15.000,25',
          );
        await tester.enterText(
          find.byType(TextFormField).last,
          'Native QA ${kind.name} $runTag',
        );
        FocusManager.instance.primaryFocus?.unfocus();
        await scroll('Kirim pengajuan');
        await tap('Kirim pengajuan');
        await waitFor(find.text('Native QA ${kind.name} $runTag'));
        final result = await source.list(kind);
        expect(result.isRight(), true);
        records[kind] = result
            .getOrElse(() => throw StateError('List unavailable'))
            .records
            .firstWhere(
              (r) => r.description == 'Native QA ${kind.name} $runTag',
            );
        checks.add('Native ${kind.name} form and HTTP create');
        await screen('created-${kind.name}');
        await tester.pageBack();
        await tester.pump(const Duration(milliseconds: 500));
      }
      await stage('awaiting_dashboard', {
        'records': {
          for (final row in records.entries) row.key.apiKind: row.value.id,
        },
      });
      await signal('decisions');
      for (final row in records.entries) {
        Navigator.of(tester.element(find.byType(MainPage))).push(
          MaterialPageRoute<void>(
            builder: (_) => SubmissionDetailPage(record: row.value),
          ),
        );
        await waitFor(
          find.text(row.key.isReimbursement ? 'Ditolak' : 'Disetujui'),
        );
        await screen('decision-${row.key.apiKind}');
        final result = await source.detail(row.value);
        expect(result.isRight(), true);
        final fresh = result.getOrElse(
          () => throw StateError('Detail unavailable'),
        );
        expect(fresh.reviewerName, 'HR Penguji');
        expect(fresh.decisionReason, 'Ditinjau melalui Dashboard QA');
        checks.add('Dashboard decision reflected on native ${row.key.apiKind}');
        await tester.pageBack();
        await tester.pump(const Duration(milliseconds: 500));
      }
      // Create a second request through the real form for native owner cancellation.
      Navigator.of(tester.element(find.byType(MainPage))).push(
        MaterialPageRoute<void>(
          builder: (_) =>
              const SubmissionListPage(kind: SubmissionKind.permission),
        ),
      );
      await tap('Ajukan izin');
      await tester.tap(find.byType(TextFormField).first);
      await waitFor(find.text('Pilih tanggal'));
      await tap('${DateTime.now().day}');
      await tester.enterText(
        find.byType(TextFormField).last,
        'Native QA cancelled $runTag',
      );
      FocusManager.instance.primaryFocus?.unfocus();
      await scroll('Kirim pengajuan');
      await tap('Kirim pengajuan');
      await waitFor(find.text('Native QA cancelled $runTag'));
      final rows = (await source.list(
        SubmissionKind.permission,
      )).getOrElse(() => throw StateError('List unavailable')).records;
      final cancelRow = rows.firstWhere(
        (r) => r.description == 'Native QA cancelled $runTag',
      );
      // Open from a real list card, exercising parent refresh when returning.
      final card = find.ancestor(
        of: find.text('Native QA cancelled $runTag'),
        matching: find.byType(SubmissionCard),
      );
      await tester.tap(
        find.descendant(of: card, matching: find.text('Lihat detail')),
      );
      await waitFor(find.text('Batalkan pengajuan'));
      await scroll('Batalkan pengajuan');
      await tap('Batalkan pengajuan');
      await tester.enterText(
        find.byType(TextFormField),
        'Tidak jadi dari HP QA',
      );
      FocusManager.instance.primaryFocus?.unfocus();
      await tap('Konfirmasi pembatalan');
      await waitFor(find.text('Dibatalkan'));
      await screen('cancelled-permission');
      checks.add('Native reason and cancellation');
      await tester.pageBack();
      await waitFor(find.text('Native QA cancelled $runTag'));
      checks.add('List refreshed after cancellation');
      // Reopen for offline/recovery and session-expiry checks, without toggling phone networks.
      Navigator.of(tester.element(find.byType(SubmissionListPage))).push(
        MaterialPageRoute<void>(
          builder: (_) => SubmissionDetailPage(record: cancelRow),
        ),
      );
      await waitFor(find.text('Dibatalkan'));
      await stage('awaiting_offline');
      await signal('offline');
      await tester.tap(find.byTooltip('Muat ulang detail'));
      await tester.pump(const Duration(seconds: 1));
      await waitFor(
        find.text(
          'Detail belum terkonfirmasi. Muat ulang sebelum mengambil tindakan.',
        ),
        seconds: 50,
      );
      await screen('offline-detail');
      checks.add('Offline detail does not enable mutation');
      await stage('offline_verified');
      await signal('online');
      await tester.tap(find.byTooltip('Muat ulang detail'));
      await tester.pump(const Duration(seconds: 2));
      expect(
        find.text(
          'Detail belum terkonfirmasi. Muat ulang sebelum mengambil tindakan.',
        ),
        findsNothing,
      );
      checks.add('Connection recovery');
      await stage('awaiting_expiry');
      await signal('expiry');
      await tester.tap(find.byTooltip('Muat ulang detail'));
      await waitFor(find.byType(LoginPage));
      expect(await AuthLocalDatasource().isAuth(), false);
      checks.add('Expired session removes private routes');
      await screen('expired-session');
      await stage('complete');
      binding.reportData = {'checks': checks, 'passed': checks.length};
      await signal('finish');
    },
    timeout: const Timeout(Duration(minutes: 25)),
  );
}
