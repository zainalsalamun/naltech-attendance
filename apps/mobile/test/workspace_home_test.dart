import 'package:flutter_absensi_app/data/models/response/attendance_context.dart';
import 'dart:async';
import 'package:dartz/dartz.dart' show Either, Left, Right;
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:flutter_absensi_app/core/theme/theme_controller.dart';
import 'package:flutter_absensi_app/core/theme/workspace_theme.dart';
import 'package:flutter_absensi_app/data/datasources/attendance_remote_datasource.dart';
import 'package:flutter_absensi_app/data/datasources/user_remote_datasource.dart';
import 'package:flutter_absensi_app/data/models/response/attendance_response_model.dart';
import 'package:flutter_absensi_app/data/models/response/auth_response_model.dart';
import 'package:flutter_absensi_app/data/models/response/company_response_model.dart';
import 'package:flutter_absensi_app/presentation/home/bloc/workspace_home/workspace_home_cubit.dart';
import 'package:flutter_absensi_app/presentation/home/pages/home_page.dart';
import 'package:flutter_absensi_app/presentation/home/pages/main_page.dart';

Company company({String method = 'None', String radius = '0.5'}) => Company(
    name: 'Perusahaan aktif dengan nama yang panjang untuk review',
    latitude: '-6.2',
    longitude: '106.8',
    radiusKm: radius,
    attendanceType: method,
    timeIn: '08:00',
    timeOut: '17:00');
HomeData data(
        {bool checkedIn = false,
        bool checkedOut = false,
        Attendance? attendance,
        String method = 'None'}) =>
    HomeData(
        company: company(method: method),
        user: User(
            id: 1, name: 'Nama karyawan panjang untuk pemeriksaan tata letak'),
        date: '2026-10-20',
        checkedIn: checkedIn,
        checkedOut: checkedOut,
        attendance: attendance);

class AttendanceFixture extends AttendanceRemoteDatasource {
  @override
  Future<Either<String, AttendanceContext>> attendanceState() async =>
      Right(AttendanceContext(checkedIn: status.$1, checkedOut: status.$2));
  Company? office = company();
  List<Attendance> records = [];
  (bool, bool) status = (false, false);
  bool fail = false;
  @override
  Future<Either<String, CompanyResponseModel>> getCompanyProfile() async => fail
      ? const Left('server')
      : Right(CompanyResponseModel(company: office));
  @override
  Future<Either<String, (bool, bool)>> isCheckedin() async => Right(status);
  @override
  Future<Either<String, AttendanceResponseModel>> getAttendance(
          String date) async =>
      Right(AttendanceResponseModel(data: records));
}

class UserFixture extends UserRemoteDatasource {
  @override
  Future<Either<String, User>> getUser() async =>
      Right(User(id: 1, name: 'Nama akun aktif'));
}

class LocationFixture extends HomeLocationService {
  LocationReading reading =
      const LocationReading(LocationState.inside, -6.2, 106.8);
  int calls = 0;
  @override
  Future<LocationReading> read() async {
    calls++;
    return reading;
  }
}

class QueuedLocation extends HomeLocationService {
  final readings = <Completer<LocationReading>>[];
  @override
  Future<LocationReading> read() {
    final request = Completer<LocationReading>();
    readings.add(request);
    return request.future;
  }
}

void main() {
  testWidgets(
      'Approved design opens useful location and schedule details and service actions',
      (tester) async {
    SharedPreferences.setMockInitialValues({});
    final controller = ThemeController(await SharedPreferences.getInstance());
    tester.view.physicalSize = const Size(390, 844);
    tester.view.devicePixelRatio = 1;
    addTearDown(tester.view.resetPhysicalSize);
    addTearDown(tester.view.resetDevicePixelRatio);
    var located = 0;
    var permission = 0;
    var reimbursement = 0;
    var profile = 0;
    await tester.pumpWidget(ThemeScope(
        controller: controller,
        child: MaterialApp(
            theme: WorkspaceTheme.light,
            home: HomeDashboard(
                state: WorkspaceHomeState(
                    data: data(),
                    location: LocationState.inside,
                    distanceMeters: 42),
                onRefresh: () async {},
                onLocate: () => located++,
                onAttend: () {},
                onPermission: () => permission++,
                onReimbursement: () => reimbursement++,
                onProfile: () => profile++))));
    await tester.tap(find.byTooltip('Profil akun'));
    await tester.pump();
    expect(profile, 1);
    await tester.tap(find.text('Jam kantor'));
    await tester.pumpAndSettle();
    expect(find.textContaining('Jam kantor bukan jadwal shift pribadi.'),
        findsOneWidget);
    await tester.tap(find.text('Mengerti'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Dalam radius kantor'));
    await tester.pumpAndSettle();
    expect(find.text('Lokasi absensi'), findsOneWidget);
    await tester.tap(find.text('Periksa lokasi lagi'));
    await tester.pumpAndSettle();
    expect(located, 1);
    await tester.scrollUntilVisible(find.text('Ajukan izin'), 150);
    await tester.ensureVisible(find.text('Ajukan izin'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Ajukan izin'));
    expect(permission, 1);
    await tester.ensureVisible(find.text('Reimbursement'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Reimbursement'));
    expect(reimbursement, 1);
    expect(tester.takeException(), isNull);
  });
  testWidgets(
      'Unavailable location never enables the redesigned attendance button',
      (tester) async {
    SharedPreferences.setMockInitialValues({});
    final controller = ThemeController(await SharedPreferences.getInstance());
    await tester.pumpWidget(ThemeScope(
        controller: controller,
        child: MaterialApp(
            theme: WorkspaceTheme.dark,
            home: HomeDashboard(
                state: WorkspaceHomeState(
                    data: data(method: 'QR'), location: LocationState.denied),
                onRefresh: () async {},
                onLocate: () {},
                onAttend: () {},
                onPermission: () {},
                onReimbursement: () {}))));
    await tester.scrollUntilVisible(find.text('Absen masuk'), 150);
    await tester.pumpAndSettle();
    expect(tester.widget<FilledButton>(find.byType(FilledButton)).onPressed,
        isNull);
    expect(find.text('Belum absen'), findsOneWidget);
    expect(tester.takeException(), isNull);
  });
  test(
      'An older location result cannot enable attendance after a newer check fails',
      () async {
    final location = QueuedLocation();
    final cubit = WorkspaceHomeCubit(
        AttendanceFixture(), UserFixture(), location,
        now: () => DateTime(2026, 10, 20));
    final initial = cubit.load();
    await Future<void>.delayed(Duration.zero);
    location.readings[0]
        .complete(const LocationReading(LocationState.inside, -6.2, 106.8));
    await initial;
    final older = cubit.locate();
    final newer = cubit.locate();
    location.readings[2].complete(const LocationReading(LocationState.denied));
    await newer;
    location.readings[1]
        .complete(const LocationReading(LocationState.inside, -6.2, 106.8));
    await older;
    expect(cubit.state.location, LocationState.denied);
    expect(cubit.state.canAttend, isFalse);
    await cubit.close();
  });
  test('Attendance action uses both server status and actual records', () {
    expect(data().action, HomeAction.checkin);
    final entry = Attendance(timeIn: '08:07:00');
    expect(
        data(checkedIn: true, attendance: entry).action, HomeAction.checkout);
    expect(
        data(
                checkedIn: true,
                checkedOut: true,
                attendance: Attendance(timeIn: '08:07:00', timeOut: '17:03:00'))
            .action,
        HomeAction.complete);
    expect(data(checkedIn: true).action, HomeAction.unavailable);
    expect(data(attendance: entry).action, HomeAction.unavailable);
    expect(data(checkedOut: true).action, HomeAction.unavailable);
  });
  test(
      'Home loads real records, blocks outside/denied/Face and rejects invalid configuration',
      () async {
    final source = AttendanceFixture();
    final location = LocationFixture();
    final cubit = WorkspaceHomeCubit(source, UserFixture(), location,
        now: () => DateTime(2026, 10, 20));
    await cubit.load();
    expect(cubit.state.canAttend, isTrue);
    expect(cubit.state.data!.user.name, 'Nama akun aktif');
    source.status = (true, false);
    source.records = [
      Attendance(userId: 1, date: DateTime(2026, 10, 20), timeIn: '08:07:00')
    ];
    await cubit.load();
    expect(cubit.state.data!.action, HomeAction.checkout);
    expect(cubit.state.data!.attendance!.timeIn, '08:07:00');
    location.reading = const LocationReading(LocationState.inside, -6.3, 106.8);
    await cubit.locate();
    expect(cubit.state.location, LocationState.outside);
    expect(cubit.state.canAttend, isFalse);
    for (final state in [
      LocationState.denied,
      LocationState.deniedForever,
      LocationState.disabled,
      LocationState.failed
    ]) {
      location.reading = LocationReading(state);
      await cubit.locate();
      expect(cubit.state.location, state);
      expect(cubit.state.canAttend, isFalse);
    }
    location.reading = const LocationReading(LocationState.inside, -6.2, 106.8);
    source.office = company(method: 'Face');
    await cubit.load();
    expect(cubit.state.canAttend, isFalse);
    for (final radius in ['NaN', '0', '-1', 'invalid']) {
      source.office = company(radius: radius);
      await cubit.load();
      expect(cubit.state.location, LocationState.invalidOffice);
      expect(cubit.state.canAttend, isFalse);
    }
    source.fail = true;
    await cubit.load();
    expect(cubit.state.error, isNotNull);
    expect(cubit.state.data, isNull);
    expect(cubit.state.canAttend, isFalse);
    source.fail = false;
    source.office = null;
    await cubit.load();
    expect(cubit.state.error, isNotNull);
    await cubit.close();
  });
  test(
      'Home never uses another user or date record and rejects duplicate records',
      () async {
    final source = AttendanceFixture()..status = (true, false);
    final cubit = WorkspaceHomeCubit(source, UserFixture(), LocationFixture(),
        now: () => DateTime(2026, 10, 20));
    source.records = [
      Attendance(userId: 2, date: DateTime(2026, 10, 20), timeIn: '08:00'),
      Attendance(userId: 1, date: DateTime(2026, 10, 19), timeIn: '08:00')
    ];
    await cubit.load();
    expect(cubit.state.data!.attendance, isNull);
    expect(cubit.state.canAttend, isFalse);
    source.records = List.generate(
        2,
        (_) => Attendance(
            userId: 1, date: DateTime(2026, 10, 20), timeIn: '08:00'));
    await cubit.load();
    expect(cubit.state.error, isNotNull);
    await cubit.close();
  });
  testWidgets('Home themes and large text render one action and actual hours',
      (tester) async {
    SharedPreferences.setMockInitialValues({});
    final controller = ThemeController(await SharedPreferences.getInstance());
    tester.view.physicalSize = const Size(320, 640);
    tester.view.devicePixelRatio = 1;
    addTearDown(tester.view.resetPhysicalSize);
    addTearDown(tester.view.resetDevicePixelRatio);
    var attended = 0;
    for (final theme in [WorkspaceTheme.light, WorkspaceTheme.dark]) {
      await tester.pumpWidget(const SizedBox.shrink());
      await tester.pumpWidget(ThemeScope(
          controller: controller,
          child: MaterialApp(
              theme: theme,
              builder: (context, child) => MediaQuery(
                  data: MediaQuery.of(context)
                      .copyWith(textScaler: const TextScaler.linear(1.8)),
                  child: child!),
              home: HomeDashboard(
                  state: WorkspaceHomeState(
                      data: data(
                          checkedIn: true,
                          attendance: Attendance(timeIn: '08:07:00')),
                      location: LocationState.inside),
                  onRefresh: () async {},
                  onLocate: () {},
                  onAttend: () {
                    attended++;
                  },
                  onPermission: () {},
                  onReimbursement: () {}))));
      await tester.scrollUntilVisible(find.text('08:07:00'), 150);
      expect(find.text('08:07:00'), findsOneWidget);
      expect(find.text('Absen masuk'), findsNothing);
      await tester.scrollUntilVisible(find.text('Absen pulang'), 150);
      await tester.ensureVisible(find.text('Absen pulang'));
      await tester.pumpAndSettle();
      await tester.tap(find.text('Absen pulang'));
      await tester.pump();
      expect(tester.takeException(), isNull);
    }
    expect(attended, 2);
  });
  testWidgets('Network error renders cloud off and retry action without showing attendance card',
      (tester) async {
    SharedPreferences.setMockInitialValues({});
    final controller = ThemeController(await SharedPreferences.getInstance());
    var refreshed = 0;
    await tester.pumpWidget(ThemeScope(
        controller: controller,
        child: MaterialApp(
            theme: WorkspaceTheme.light,
            home: HomeDashboard(
                state: const WorkspaceHomeState(
                    error: 'Data Beranda belum dapat dimuat. Periksa koneksi dan hubungan akun dengan perusahaan, lalu coba lagi.'),
                onRefresh: () async {
                  refreshed++;
                },
                onLocate: () {},
                onAttend: () {},
                onPermission: () {},
                onReimbursement: () {}))));
    expect(find.byIcon(Icons.cloud_off_outlined), findsOneWidget);
    expect(find.text('Data Beranda belum dapat dimuat. Periksa koneksi dan hubungan akun dengan perusahaan, lalu coba lagi.'), findsOneWidget);
    expect(find.text('Coba lagi'), findsOneWidget);
    expect(find.text('Kehadiran hari ini'), findsNothing);
    expect(find.text('Kehadiran sesi ini'), findsNothing);
    expect(find.text('Belum check-in'), findsNothing);
    await tester.tap(find.text('Coba lagi'));
    await tester.pump();
    expect(refreshed, 1);
  });
  testWidgets('Navigation preserves tab state and returns to Home on back',
      (tester) async {
    await tester.pumpWidget(MaterialApp(
        theme: WorkspaceTheme.dark,
        home: const MainPage(pages: [
          Text('Konten Beranda'),
          _CounterPage(),
          Text('Konten Profil')
        ])));
    await tester.tap(find.text('Riwayat'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Tambah 0'));
    await tester.pump();
    await tester.tap(find.text('Profil'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Riwayat'));
    await tester.pumpAndSettle();
    expect(find.text('Tambah 1'), findsOneWidget);
    await tester.binding.handlePopRoute();
    await tester.pumpAndSettle();
    expect(find.text('Konten Beranda').hitTestable(), findsOneWidget);
  });
}

class _CounterPage extends StatefulWidget {
  const _CounterPage();
  @override
  State<_CounterPage> createState() => _CounterPageState();
}

class _CounterPageState extends State<_CounterPage> {
  int value = 0;
  @override
  Widget build(BuildContext context) => Center(
      child: TextButton(
          onPressed: () => setState(() => value++),
          child: Text('Tambah $value')));
}
