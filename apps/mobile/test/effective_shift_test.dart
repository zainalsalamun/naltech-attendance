import 'package:flutter/material.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:flutter_absensi_app/core/theme/theme_controller.dart';
import 'package:flutter_absensi_app/core/theme/workspace_theme.dart';
import 'package:flutter_absensi_app/data/models/response/auth_response_model.dart';
import 'package:flutter_absensi_app/presentation/home/pages/home_page.dart';
import 'package:dartz/dartz.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:flutter_absensi_app/data/models/response/attendance_context.dart';
import 'package:flutter_absensi_app/data/models/response/attendance_response_model.dart';
import 'package:flutter_absensi_app/presentation/home/bloc/workspace_home/workspace_home_cubit.dart';
import 'package:flutter_absensi_app/data/models/response/qr_absen_remote_datasource.dart';
import 'package:flutter_absensi_app/presentation/home/bloc/qr_scan/qr_scan_cubit.dart';
import 'workspace_home_test.dart'
    show AttendanceFixture, UserFixture, LocationFixture, company;

Map<String, dynamic> payload() => {
      'checkedin': true,
      'checkedout': false,
      'actionable': true,
      'attendance_date': '2026-10-21',
      'qr_date': '2026-10-22',
      'attendance': {
        'id': 1,
        'user_id': 1,
        'date': '2026-10-21',
        'time_in': '22:05:00'
      },
      'schedule': {
        'scheduled_name': 'Malam',
        'scheduled_in': '22:00:00',
        'scheduled_out': '06:00:00',
        'attendance_timezone': 'Asia/Jakarta'
      }
    };

class NightFixture extends AttendanceFixture {
  NightFixture(this.context);
  final AttendanceContext context;
  int historyCalls = 0;
  @override
  Future<Either<String, AttendanceContext>> attendanceState() async =>
      Right(context);
  @override
  Future<Either<String, AttendanceResponseModel>> getAttendance(
      String date) async {
    historyCalls++;
    throw StateError('Modern status must use server attendance');
  }
}

class ServerDateQr extends QrAbsenRemoteDatasource {
  String? date;
  @override
  Future<Either<String, bool>> checkQr(
      String code, String date, String type) async {
    this.date = date;
    return const Right(true);
  }
}

void main() {
  testWidgets(
      'Effective shift labels distinguish personal schedule from office hours',
      (tester) async {
    SharedPreferences.setMockInitialValues({});
    final controller = ThemeController(await SharedPreferences.getInstance());
    final context = AttendanceContext.parse(payload(), 1);
    await tester.pumpWidget(ThemeScope(
        controller: controller,
        child: MaterialApp(
            theme: WorkspaceTheme.light,
            home: HomeDashboard(
              state: WorkspaceHomeState(
                  data: HomeData(
                      company: company(),
                      user: User(id: 1, name: 'Penguji'),
                      date: context.date!,
                      checkedIn: true,
                      checkedOut: false,
                      attendance: context.attendance,
                      schedule: context.schedule),
                  location: LocationState.inside),
              onRefresh: () async {},
              onLocate: () {},
              onAttend: () {},
              onPermission: () {},
              onReimbursement: () {},
              onProfile: () {},
            ))));
    expect(find.text('Jadwal shift'), findsOneWidget);
    expect(find.text('Malam'), findsOneWidget);
    expect(find.text('Kehadiran sesi ini'), findsOneWidget);
    expect(find.text('Jam kantor'), findsNothing);
  });

  test(
      'QR scanner uses server current date rather than workday or device clock',
      () async {
    final source = ServerDateQr();
    final cubit = QrScanCubit(source,
        serverDate: '2026-10-22', now: () => DateTime(2026, 10, 23));
    addTearDown(cubit.close);
    expect(await cubit.scan('synthetic-qr', false), isTrue);
    expect(source.date, '2026-10-22');
  });
  test('server workday survives midnight and a different device date',
      () async {
    final source = NightFixture(AttendanceContext.parse(payload(), 1));
    final cubit = WorkspaceHomeCubit(source, UserFixture(), LocationFixture(),
        now: () => DateTime(2026, 10, 23));
    addTearDown(cubit.close);
    await cubit.load();
    expect(cubit.state.error, isNull);
    expect(cubit.state.data!.date, '2026-10-21');
    expect(cubit.state.data!.qrDate, '2026-10-22');
    expect(cubit.state.data!.action, HomeAction.checkout);
    expect(cubit.state.canAttend, isTrue);
    expect(source.historyCalls, 0);
  });
  test('expired open session disables attendance despite valid location',
      () async {
    final raw = payload()..['actionable'] = false;
    final cubit = WorkspaceHomeCubit(
        NightFixture(AttendanceContext.parse(raw, 1)),
        UserFixture(),
        LocationFixture());
    addTearDown(cubit.close);
    await cubit.load();
    expect(cubit.state.data!.action, HomeAction.unavailable);
    expect(cubit.state.canAttend, isFalse);
  });
  test('foreign attendance and wrong workday fail closed', () {
    final foreign = payload();
    (foreign['attendance'] as Map)['user_id'] = 2;
    expect(() => AttendanceContext.parse(foreign, 1), throwsFormatException);
    final wrongDate = payload();
    (wrongDate['attendance'] as Map)['date'] = '2026-10-22';
    expect(() => AttendanceContext.parse(wrongDate, 1), throwsFormatException);
  });
  test('overflow calendar dates and invalid QR date are rejected', () {
    for (final key in ['attendance_date', 'qr_date']) {
      final raw = payload()..[key] = '2026-02-30';
      expect(() => AttendanceContext.parse(raw, 1), throwsFormatException);
    }
  });
}
