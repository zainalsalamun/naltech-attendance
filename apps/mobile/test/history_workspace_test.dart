import 'dart:async';
import 'package:dartz/dartz.dart' show Either, Right, Left;
import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:flutter_absensi_app/core/theme/theme_controller.dart';
import 'package:flutter_absensi_app/core/theme/workspace_theme.dart';
import 'package:flutter_absensi_app/data/datasources/attendance_remote_datasource.dart';
import 'package:flutter_absensi_app/data/models/response/attendance_response_model.dart';
import 'package:flutter_absensi_app/presentation/home/bloc/get_attendance_by_date/get_attendance_by_date_bloc.dart';
import 'package:flutter_absensi_app/presentation/home/models/history_presentation.dart';
import 'package:flutter_absensi_app/presentation/home/pages/history_page.dart';
import 'package:flutter_absensi_app/presentation/home/pages/location_page.dart';

Attendance historyRecord([DateTime? date]) => Attendance(
    id: 1,
    userId: 1,
    date: date ?? DateTime(2026, 10, 20),
    timeIn: '08:03:21',
    timeOut: '17:04:35',
    latlonIn: '-6.2,106.8',
    latlonOut: 'bad-coordinates',
    overtimeMinutes: 90,
    overtimePay: '125000.50');

class HistorySource extends AttendanceRemoteDatasource {
  final calls = <String>[];
  bool fail = false;
  List<Attendance>? overrideRecords;
  @override
  Future<Either<String, AttendanceResponseModel>> getAttendance(
      String date) async {
    calls.add(date);
    if (fail) return const Left('Tidak dapat terhubung. Coba lagi.');
    return Right(AttendanceResponseModel(
        data: overrideRecords ??
            (date == '2026-10-20' ? [historyRecord()] : [])));
  }
}

class DelayedHistorySource extends AttendanceRemoteDatasource {
  final pending =
      <String, Completer<Either<String, AttendanceResponseModel>>>{};
  @override
  Future<Either<String, AttendanceResponseModel>> getAttendance(String date) =>
      pending
          .putIfAbsent(
              date, () => Completer<Either<String, AttendanceResponseModel>>())
          .future;
}

Future<ThemeController> historyTheme() async {
  SharedPreferences.setMockInitialValues({});
  return ThemeController(await SharedPreferences.getInstance());
}

Widget historyApp(GetAttendanceByDateBloc bloc, ThemeController controller,
        ThemeData theme, {double scale = 1}) =>
    ThemeScope(
        controller: controller,
        child: BlocProvider.value(
            value: bloc,
            child: MaterialApp(
                theme: theme,
                builder: (context, child) => MediaQuery(
                    data: MediaQuery.of(context)
                        .copyWith(textScaler: TextScaler.linear(scale)),
                    child: child!),
                home: HistoryPage(now: () => DateTime(2026, 10, 20)))));
void main() {
  test('Coordinates, status and pay never invent location or lateness', () {
    for (final value in [
      null,
      '',
      'x,y',
      '-91,10',
      '0,181',
      'NaN,0',
      '1,2,3'
    ]) {
      expect(HistoryCoordinates.parse(value), isNull);
    }
    expect(HistoryCoordinates.parse('0,0')!.latitude, 0);
    expect(HistoryCoordinates.parse(' -6.2, 106.8 ')!.longitude, 106.8);
    expect(historyStatus(historyRecord()), 'Masuk & pulang tercatat');
    expect(historyStatus(Attendance(timeIn: '08:00')), 'Masuk tercatat');
    expect(historyStatus(Attendance(timeOut: '17:00')), 'Perlu tinjau');
    expect(historyStatus(Attendance(timeIn: '99:99', timeOut: '17:00')),
        'Perlu tinjau');
    expect(historyPay(null), 'Belum tersedia');
    expect(historyPay('NaN'), 'Belum tersedia');
    expect(historyPay('125000.50'), contains('125.000,50'));
  });
  test('Older response cannot replace the newer date state', () async {
    final source = DelayedHistorySource();
    final bloc = GetAttendanceByDateBloc(source);
    bloc.add(const GetAttendanceByDateEvent.getAttendanceByDate('2026-10-19'));
    await Future<void>.delayed(Duration.zero);
    bloc.add(const GetAttendanceByDateEvent.getAttendanceByDate('2026-10-20'));
    await Future<void>.delayed(Duration.zero);
    source.pending['2026-10-20']!
        .complete(Right(AttendanceResponseModel(data: [])));
    await Future<void>.delayed(Duration.zero);
    source.pending['2026-10-19']!.complete(Right(AttendanceResponseModel(
        data: [historyRecord(DateTime(2026, 10, 19))])));
    await Future<void>.delayed(Duration.zero);
    expect(bloc.selectedDate, '2026-10-20');
    expect(
        bloc.state.maybeWhen(empty: () => true, orElse: () => false), isTrue);
    await bloc.close();
  });
  test(
      'Invalid date, duplicate or wrong-date records and missing data are errors',
      () async {
    final source = HistorySource();
    final bloc = GetAttendanceByDateBloc(source);
    bloc.add(const GetAttendanceByDateEvent.getAttendanceByDate('2026-02-31'));
    await Future<void>.delayed(Duration.zero);
    expect(source.calls, isEmpty);
    expect(
        bloc.state.maybeWhen(error: (_) => true, orElse: () => false), isTrue);
    for (final records in [
      [historyRecord(), historyRecord()],
      [historyRecord(DateTime(2026, 10, 19))],
      [Attendance(timeIn: '08:00')]
    ]) {
      source.overrideRecords = records;
      bloc.add(
          const GetAttendanceByDateEvent.getAttendanceByDate('2026-10-20'));
      await Future<void>.delayed(Duration.zero);
      expect(bloc.state.maybeWhen(error: (_) => true, orElse: () => false),
          isTrue);
    }
    expect(AttendanceResponseModel.fromJson('{}').data, isNull);
    await bloc.close();
  });
  testWidgets(
      'History changes dates, clears old hours, retries errors and opens location details',
      (tester) async {
    final source = HistorySource();
    final bloc = GetAttendanceByDateBloc(source);
    addTearDown(bloc.close);
    final controller = await historyTheme();
    await tester.pumpWidget(historyApp(bloc, controller, WorkspaceTheme.light));
    await tester.pumpAndSettle();
    expect(find.text('08:03:21'), findsOneWidget);
    expect(find.text('17:04:35'), findsOneWidget);
    await tester.tap(find.text('Lokasi tercatat'));
    await tester.pumpAndSettle();
    expect(find.text('Lintang: -6.2'), findsOneWidget);
    expect(find.text('Bujur: 106.8'), findsOneWidget);
    Navigator.pop(tester.element(find.text('Lokasi masuk')));
    await tester.pumpAndSettle();
    await tester.tap(find.byTooltip('Hari sebelumnya'));
    await tester.pumpAndSettle();
    expect(find.text('Belum ada catatan'), findsOneWidget);
    expect(find.text('08:03:21'), findsNothing);
    expect(source.calls.last, '2026-10-19');
    source.fail = true;
    await tester.tap(find.text('Hari ini'));
    await tester.pumpAndSettle();
    expect(find.text('Riwayat belum dapat dimuat'), findsOneWidget);
    source.fail = false;
    await tester.tap(find.text('Coba lagi'));
    await tester.pumpAndSettle();
    expect(find.text('08:03:21'), findsOneWidget);
    await tester.tap(find.text('Selasa, 20 Oktober 2026'));
    await tester.pumpAndSettle();
    expect(find.text('Pilih tanggal'), findsOneWidget);
    expect(find.text('Oktober 2026'), findsOneWidget);
    await tester.tap(find.text('Oktober 2026'));
    await tester.pumpAndSettle();
    expect(find.text('Pilih tahun'), findsOneWidget);
    await tester.tap(find.text('2025'));
    await tester.pumpAndSettle();
    expect(find.text('Oktober 2025'), findsOneWidget);
    await tester.tap(find.text('Batal'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Selasa, 20 Oktober 2026'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('19'));
    await tester.pumpAndSettle();
    expect(source.calls.last, '2026-10-19');
    expect(find.text('Belum ada catatan'), findsOneWidget);
    expect(tester.takeException(), isNull);
  });
  testWidgets('History light and dark support small screens and enlarged text',
      (tester) async {
    tester.view.physicalSize = const Size(320, 640);
    tester.view.devicePixelRatio = 1;
    addTearDown(tester.view.resetPhysicalSize);
    addTearDown(tester.view.resetDevicePixelRatio);
    final controller = await historyTheme();
    for (final theme in [WorkspaceTheme.light, WorkspaceTheme.dark]) {
      final bloc = GetAttendanceByDateBloc(HistorySource());
      addTearDown(bloc.close);
      await tester.pumpWidget(const SizedBox());
      await tester.pumpWidget(historyApp(bloc, controller, theme, scale: 1.8));
      await tester.pumpAndSettle();
      await tester.scrollUntilVisible(
          find.textContaining('Nominal lembur:'), 150);
      await tester.pumpAndSettle();
      expect(find.textContaining('125.000,50'), findsOneWidget);
      expect(find.text('Sesuai spot Absensi'), findsNothing);
      expect(tester.takeException(), isNull);
      await tester.scrollUntilVisible(
          find.text('Selasa, 20 Oktober 2026'), -150);
      await tester.pumpAndSettle();
      await tester.tap(find.text('Selasa, 20 Oktober 2026'));
      await tester.pumpAndSettle();
      expect(find.text('Pilih tanggal'), findsOneWidget);
      expect(tester.takeException(), isNull);
      await tester.ensureVisible(find.text('Batal'));
      await tester.pumpAndSettle();
      await tester.tap(find.text('Batal'));
      await tester.pumpAndSettle();
      expect(find.text('Pilih tanggal'), findsNothing);
    }
  });
  testWidgets('Invalid map coordinates never create a map at a fallback point',
      (tester) async {
    await tester.pumpWidget(
        MaterialApp(theme: WorkspaceTheme.dark, home: const LocationPage()));
    expect(find.text('Koordinat catatan belum tersedia atau tidak valid.'),
        findsOneWidget);
    expect(tester.takeException(), isNull);
  });
}
