import 'dart:async';
import 'dart:convert';
import 'package:dartz/dartz.dart' show Either, Right;
import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:flutter_absensi_app/core/theme/workspace_theme.dart';
import 'package:flutter_absensi_app/data/datasources/attendance_remote_datasource.dart';
import 'package:flutter_absensi_app/data/datasources/auth_local_datasource.dart';
import 'package:flutter_absensi_app/data/datasources/attendance_qr_proof.dart';
import 'package:flutter_absensi_app/data/models/request/checkinout_request_model.dart';
import 'package:flutter_absensi_app/data/models/response/auth_response_model.dart';
import 'package:flutter_absensi_app/data/models/response/checkinout_response_model.dart'
    as result;
import 'package:flutter_absensi_app/data/models/response/qr_absen_remote_datasource.dart';
import 'package:flutter_absensi_app/data/models/response/attendance_response_model.dart'
    as history;
import 'package:flutter_absensi_app/presentation/home/bloc/get_attendance_by_date/get_attendance_by_date_bloc.dart';
import 'package:flutter_absensi_app/presentation/home/bloc/qr_scan/qr_scan_cubit.dart';
import 'package:flutter_absensi_app/presentation/home/bloc/checkin_attendance/checkin_attendance_bloc.dart';
import 'package:flutter_absensi_app/presentation/home/bloc/checkout_attendance/checkout_attendance_bloc.dart';
import 'package:flutter_absensi_app/presentation/home/bloc/workspace_home/workspace_home_cubit.dart';
import 'package:flutter_absensi_app/presentation/home/pages/attandences/attendance_result_page.dart';
import 'package:flutter_absensi_app/presentation/home/pages/attendance_success_page.dart';

result.CheckInOutResponseModel response({bool checkout = false}) =>
    result.CheckInOutResponseModel(
        attendance: result.Attendance(
            id: 1,
            userId: 1,
            date: DateTime(2026, 10, 20),
            timeIn: '08:03:21',
            timeOut: checkout ? '17:04:35' : null));

class SubmitFixture extends AttendanceRemoteDatasource {
  int calls = 0;
  final pending = Completer<Either<String, result.CheckInOutResponseModel>>();
  @override
  Future<Either<String, result.CheckInOutResponseModel>> checkin(
      CheckInOutRequestModel data) {
    calls++;
    return pending.future;
  }

  @override
  Future<Either<String, result.CheckInOutResponseModel>> checkout(
      CheckInOutRequestModel data) {
    calls++;
    return pending.future;
  }
}

class GeoFixture extends HomeLocationService {
  LocationReading reading =
      const LocationReading(LocationState.inside, -6.2, 106.8);
  @override
  Future<LocationReading> read() async => reading;
}

class QrFixture extends QrAbsenRemoteDatasource {
  int calls = 0;
  final pending = Completer<Either<String, bool>>();
  @override
  Future<Either<String, bool>> checkQr(String code, String date, String type) {
    calls++;
    return pending.future;
  }
}

class HistoryFixture extends AttendanceRemoteDatasource {
  final dates = <String>[];
  @override
  Future<Either<String, history.AttendanceResponseModel>> getAttendance(
      String date) async {
    dates.add(date);
    return Right(history.AttendanceResponseModel(data: []));
  }
}

void main() {
  test('Successful-flow refresh preserves the currently selected history date',
      () async {
    final source = HistoryFixture();
    final bloc = GetAttendanceByDateBloc(source);
    bloc.add(const GetAttendanceByDateEvent.getAttendanceByDate('2026-10-19'));
    await Future<void>.delayed(Duration.zero);
    bloc.refreshCurrent();
    await Future<void>.delayed(Duration.zero);
    expect(source.dates, ['2026-10-19', '2026-10-19']);
    await bloc.close();
  });
  setUp(() async {
    SharedPreferences.setMockInitialValues({});
    AttendanceQrProof.clear();
    await AuthLocalDatasource().saveAuthData(
        AuthResponseModel(user: User(id: 1), token: 'fixture-session'));
  });
  tearDown(AttendanceQrProof.clear);
  test(
      'Submission HTTP preserves action-bound QR, parses checkout time and clears evidence',
      () async {
    final client = MockClient((request) async {
      expect(request.url.path, '/api/checkout');
      final body = jsonDecode(request.body);
      expect(body['qr_code'], 'fixture-qr');
      expect(body['latitude'], '-6.2');
      return http.Response(response(checkout: true).toJson(), 200);
    });
    final validationClient = MockClient((request) async {
      expect(jsonDecode(request.body)['type_qr'], 'qr_checkout');
      return http.Response('{"is_valid":true}', 200);
    });
    expect(
        (await QrAbsenRemoteDatasource(client: validationClient)
                .checkQr('fixture-qr', '2026-10-20', 'qr_checkout'))
            .fold((_) => false, (valid) => valid),
        isTrue);
    validationClient.close();
    final source = AttendanceRemoteDatasource(client: client);
    final value = await source
        .checkout(CheckInOutRequestModel(latitude: '-6.2', longitude: '106.8'));
    expect(value.fold((_) => null, (r) => r.attendance!.timeOut), '17:04:35');
    expect(AttendanceQrProof.codeFor('fixture-session', 'qr_checkout'), isNull);
    client.close();
  });
  test(
      'Server rejection, session expiry, malformed and incomplete responses never become success',
      () async {
    for (final pair in [
      (422, '{"message":"Anda di luar radius"}'),
      (409, '{"message":"Anda sudah absen masuk hari ini"}'),
      (422, '{"message":"QR tidak valid atau kedaluwarsa"}'),
      (401, '{}'),
      (500, '<html>failed</html>'),
      (200, '{}'),
      (200, 'not-json')
    ]) {
      final client = MockClient((_) async => http.Response(pair.$2, pair.$1));
      final value = await AttendanceRemoteDatasource(client: client).checkin(
          CheckInOutRequestModel(latitude: '-6.2', longitude: '106.8'));
      expect(value.isLeft(), isTrue);
      client.close();
    }
    final client = MockClient((_) async => throw TimeoutException('fixture'));
    final value = await AttendanceRemoteDatasource(client: client)
        .checkin(CheckInOutRequestModel());
    expect(
        value.fold((e) => e.contains('belum dapat dipastikan'), (_) => false),
        isTrue);
    client.close();
  });
  test(
      'Duplicate checkin and checkout events send one request and transport failures recover',
      () async {
    final source = SubmitFixture();
    final checkin = CheckinAttendanceBloc(source);
    checkin.add(const CheckinAttendanceEvent.checkin('-6.2', '106.8'));
    checkin.add(const CheckinAttendanceEvent.checkin('-6.2', '106.8'));
    await Future<void>.delayed(Duration.zero);
    expect(source.calls, 1);
    source.pending.completeError(Exception('offline'));
    await Future<void>.delayed(Duration.zero);
    expect(checkin.state.maybeWhen(error: (_) => true, orElse: () => false),
        isTrue);
    await checkin.close();
    final source2 = SubmitFixture();
    final checkout = CheckoutAttendanceBloc(source2);
    checkout.add(const CheckoutAttendanceEvent.checkout('-6.2', '106.8'));
    checkout.add(const CheckoutAttendanceEvent.checkout('-6.2', '106.8'));
    await Future<void>.delayed(Duration.zero);
    expect(source2.calls, 1);
    source2.pending.complete(Right(response(checkout: true)));
    await Future<void>.delayed(Duration.zero);
    expect(
        checkout.state.maybeWhen(
            loaded: (r) => r.attendance!.timeOut == '17:04:35',
            orElse: () => false),
        isTrue);
    await checkout.close();
  });
  test('False QR validation remains a failure and duplicate scans do not race',
      () async {
    final source = QrFixture();
    final scan = QrScanCubit(source);
    final first = scan.scan('fixture-qr', true);
    expect(await scan.scan('fixture-qr', true), isFalse);
    expect(source.calls, 1);
    source.pending.complete(const Right(false));
    expect(await first, isFalse);
    expect(scan.state.valid, isFalse);
    expect(scan.state.error, isNotNull);
    scan.retry();
    expect(scan.state.error, isNull);
    await scan.close();
  });
  test(
      'QR evidence cannot be restored by a validation response after cancellation',
      () async {
    final pending = Completer<http.Response>();
    final client = MockClient((_) => pending.future);
    final future = QrAbsenRemoteDatasource(client: client)
        .checkQr('fixture-qr', '2026-10-20', 'qr_checkin');
    await Future<void>.delayed(Duration.zero);
    AttendanceQrProof.clear();
    pending.complete(http.Response('{"is_valid":true}', 200));
    expect((await future).isLeft(), isTrue);
    expect(AttendanceQrProof.codeFor('fixture-session', 'qr_checkin'), isNull);
    client.close();
  });
  testWidgets(
      'Manual confirmation blocks repeated taps and shows server time only after success',
      (tester) async {
    final source = SubmitFixture();
    final checkin = CheckinAttendanceBloc(source);
    final checkout = CheckoutAttendanceBloc(source);
    addTearDown(checkin.close);
    addTearDown(checkout.close);
    await tester.pumpWidget(MultiBlocProvider(
        providers: [
          BlocProvider.value(value: checkin),
          BlocProvider.value(value: checkout)
        ],
        child: MaterialApp(
            theme: WorkspaceTheme.light,
            home: AttendanceResultPage(
                isCheckin: true,
                isMatch: true,
                attendanceType: 'None',
                locationService: GeoFixture()))));
    await tester.pumpAndSettle();
    expect(find.text('Kehadiran tercatat'), findsNothing);
    await tester.ensureVisible(find.text('Konfirmasi Absen masuk'));
    await tester.tap(find.text('Konfirmasi Absen masuk'));
    await tester.pump();
    await tester.pump();
    expect(source.calls, 1);
    expect(tester.widget<FilledButton>(find.byType(FilledButton)).onPressed,
        isNull);
    source.pending.complete(Right(response()));
    await tester.pumpAndSettle();
    expect(find.text('Kehadiran tercatat'), findsOneWidget);
    expect(find.text('08:03:21'), findsOneWidget);
    expect(find.text('20-10-2026'), findsOneWidget);
    await tester.pumpWidget(const SizedBox());
  });
  testWidgets('Denied location and unsupported Face cannot submit',
      (tester) async {
    final source = SubmitFixture();
    final checkin = CheckinAttendanceBloc(source);
    final checkout = CheckoutAttendanceBloc(source);
    addTearDown(checkin.close);
    addTearDown(checkout.close);
    final geo = GeoFixture()
      ..reading = const LocationReading(LocationState.denied);
    for (final method in ['None', 'Face']) {
      await tester.pumpWidget(const SizedBox());
      await tester.pumpWidget(MultiBlocProvider(
          providers: [
            BlocProvider.value(value: checkin),
            BlocProvider.value(value: checkout)
          ],
          child: MaterialApp(
              theme: WorkspaceTheme.dark,
              home: AttendanceResultPage(
                  isCheckin: true,
                  isMatch: true,
                  attendanceType: method,
                  locationService: geo))));
      await tester.pumpAndSettle();
      if (method == 'None') {
        await tester.ensureVisible(find.text('Konfirmasi Absen masuk'));
        await tester.tap(find.text('Konfirmasi Absen masuk'));
        await tester.pumpAndSettle();
        expect(find.text('Izin lokasi ditolak'), findsOneWidget);
      } else {
        expect(tester.widget<FilledButton>(find.byType(FilledButton)).onPressed,
            isNull);
      }
      expect(source.calls, 0);
    }
    await tester.pumpWidget(const SizedBox());
  });
  testWidgets(
      'Checkout result uses actual server date and time at large text in both themes',
      (tester) async {
    tester.view.physicalSize = const Size(320, 640);
    tester.view.devicePixelRatio = 1;
    addTearDown(tester.view.resetPhysicalSize);
    addTearDown(tester.view.resetDevicePixelRatio);
    for (final theme in [WorkspaceTheme.light, WorkspaceTheme.dark]) {
      await tester.pumpWidget(MaterialApp(
          theme: theme,
          builder: (context, child) => MediaQuery(
              data: MediaQuery.of(context)
                  .copyWith(textScaler: const TextScaler.linear(1.8)),
              child: child!),
          home: AttendanceSuccessPage(
              status: 'Absen pulang',
              isCheckin: false,
              response: response(checkout: true))));
      expect(find.text('17:04:35'), findsOneWidget);
      expect(find.text('20-10-2026'), findsOneWidget);
      expect(tester.takeException(), isNull);
    }
  });
}
