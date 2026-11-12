import 'dart:async';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:geolocator/geolocator.dart';
import 'package:intl/intl.dart';
import '../../../../data/datasources/attendance_remote_datasource.dart';
import '../../../../data/datasources/user_remote_datasource.dart';
import '../../../../data/models/response/auth_response_model.dart';
import '../../../../data/models/response/company_response_model.dart';
import '../../../../data/models/response/attendance_response_model.dart';
import '../../../../data/models/response/attendance_context.dart';

enum LocationState {
  idle,
  loading,
  disabled,
  denied,
  deniedForever,
  failed,
  inside,
  outside,
  invalidOffice
}

enum HomeAction { checkin, checkout, complete, unavailable }

class LocationReading {
  const LocationReading(this.state, [this.latitude, this.longitude]);
  final LocationState state;
  final double? latitude;
  final double? longitude;
}

class HomeLocationService {
  Future<LocationReading> read() async {
    if (!await Geolocator.isLocationServiceEnabled()) {
      return const LocationReading(LocationState.disabled);
    }
    var permission = await Geolocator.checkPermission();
    if (permission == LocationPermission.denied) {
      permission = await Geolocator.requestPermission();
    }
    if (permission == LocationPermission.deniedForever) {
      return const LocationReading(LocationState.deniedForever);
    }
    if (permission != LocationPermission.always &&
        permission != LocationPermission.whileInUse) {
      return const LocationReading(LocationState.denied);
    }
    final position = await Geolocator.getCurrentPosition(
        desiredAccuracy: LocationAccuracy.high,
        timeLimit: const Duration(seconds: 15));
    return LocationReading(
        LocationState.inside, position.latitude, position.longitude);
  }
}

class HomeData {
  const HomeData(
      {required this.company,
      required this.user,
      required this.date,
      required this.checkedIn,
      required this.checkedOut,
      this.attendance,
      this.refreshedAt,
      this.schedule,
      this.qrDate,
      this.actionable = true});
  final Map<String, dynamic>? schedule;
  final String? qrDate;
  final bool actionable;
  final Company company;
  final User user;
  final String date;
  final bool checkedIn;
  final bool checkedOut;
  final Attendance? attendance;
  final DateTime? refreshedAt;
  String get method => company.attendanceType?.trim().toLowerCase() ?? '';
  HomeAction get action {
    if (!actionable) return HomeAction.unavailable;
    // Never infer success or permit another action when sources disagree.
    final hasIn = attendance?.timeIn?.isNotEmpty ?? false;
    final hasOut = attendance?.timeOut?.isNotEmpty ?? false;
    if (checkedIn != hasIn ||
        checkedOut != hasOut ||
        (checkedOut && !checkedIn)) {
      return HomeAction.unavailable;
    }
    if (checkedOut) {
      return HomeAction.complete;
    }
    return checkedIn ? HomeAction.checkout : HomeAction.checkin;
  }
}

class WorkspaceHomeState {
  const WorkspaceHomeState(
      {this.loading = false,
      this.data,
      this.error,
      this.location = LocationState.idle,
      this.distanceMeters});
  final bool loading;
  final HomeData? data;
  final String? error;
  final LocationState location;
  final double? distanceMeters;
  bool get canAttend =>
      !loading &&
      error == null &&
      location == LocationState.inside &&
      data != null &&
      ['none', 'qr'].contains(data!.method) &&
      [HomeAction.checkin, HomeAction.checkout].contains(data!.action);
}

class WorkspaceHomeCubit extends Cubit<WorkspaceHomeState> {
  WorkspaceHomeCubit(
      this.attendanceSource, this.userSource, this.locationService,
      {DateTime Function()? now})
      : now = now ?? DateTime.now,
        super(const WorkspaceHomeState());
  final AttendanceRemoteDatasource attendanceSource;
  final UserRemoteDatasource userSource;
  final HomeLocationService locationService;
  final DateTime Function() now;
  int _generation = 0;
  int _locationGeneration = 0;

  Future<void> load() async {
    final generation = ++_generation;
    emit(const WorkspaceHomeState(
        loading: true, location: LocationState.loading));
    final date = DateFormat('yyyy-MM-dd').format(now());
    try {
      final results = await (
        attendanceSource.getCompanyProfile(),
        userSource.getUser(),
        attendanceSource.attendanceState(),
      ).wait.timeout(const Duration(seconds: 20));
      final Company? company =
          results.$1.fold<Company?>((_) => null, (value) => value.company);
      if (company == null) {
        throw StateError('company');
      }
      final User? user = results.$2.fold<User?>((_) => null, (value) => value);
      final AttendanceContext? status =
          results.$3.fold<AttendanceContext?>((_) => null, (value) => value);
      if (user?.id == null || status == null) throw StateError('data');
      final workDate = status.date ?? date;
      Attendance? attendance = status.attendance;
      if (status.date == null) {
        final records = (await attendanceSource.getAttendance(date))
            .getOrElse(() => throw StateError('records'));
        final matches = (records.data ?? [])
            .where((r) =>
                r.userId == user!.id &&
                r.date != null &&
                DateFormat('yyyy-MM-dd').format(r.date!) == date)
            .toList();
        if (matches.length > 1) throw StateError('duplicate');
        attendance = matches.isEmpty ? null : matches.single;
      }
      if (attendance != null &&
          (attendance.userId != user!.id ||
              attendance.date == null ||
              DateFormat('yyyy-MM-dd').format(attendance.date!) != workDate)) {
        throw StateError('foreign date');
      }
      final data = HomeData(
          refreshedAt: now(),
          company: company,
          user: user!,
          date: workDate,
          schedule: status.schedule,
          qrDate: status.qrDate,
          actionable: status.actionable,
          checkedIn: status.checkedIn,
          checkedOut: status.checkedOut,
          attendance: attendance);
      if (isClosed || generation != _generation) {
        return;
      }
      emit(WorkspaceHomeState(data: data, location: LocationState.loading));
      await locate(generation: generation);
    } catch (_) {
      if (!isClosed && generation == _generation) {
        emit(const WorkspaceHomeState(
            error:
                'Data Beranda belum dapat dimuat. Periksa koneksi dan hubungan akun dengan perusahaan, lalu coba lagi.'));
      }
    }
  }

  Future<void> locate({int? generation}) async {
    final current = generation ?? _generation;
    final locationGeneration = ++_locationGeneration;
    final data = state.data;
    if (data == null) {
      return;
    }
    emit(WorkspaceHomeState(data: data, location: LocationState.loading));
    final lat = double.tryParse(data.company.latitude ?? '');
    final lon = double.tryParse(data.company.longitude ?? '');
    final radius = double.tryParse(data.company.radiusKm ?? '');
    if (lat == null ||
        lon == null ||
        radius == null ||
        !lat.isFinite ||
        !lon.isFinite ||
        !radius.isFinite ||
        lat.abs() > 90 ||
        lon.abs() > 180 ||
        radius <= 0) {
      if (!isClosed &&
          current == _generation &&
          locationGeneration == _locationGeneration) {
        emit(WorkspaceHomeState(
            data: data, location: LocationState.invalidOffice));
      }
      return;
    }
    try {
      final reading =
          await locationService.read().timeout(const Duration(seconds: 20));
      if (isClosed ||
          current != _generation ||
          locationGeneration != _locationGeneration) {
        return;
      }
      if (reading.latitude == null || reading.longitude == null) {
        emit(WorkspaceHomeState(
            data: data,
            location: [LocationState.inside, LocationState.outside]
                    .contains(reading.state)
                ? LocationState.failed
                : reading.state));
        return;
      }
      if (!reading.latitude!.isFinite ||
          !reading.longitude!.isFinite ||
          reading.latitude!.abs() > 90 ||
          reading.longitude!.abs() > 180) {
        throw StateError('position');
      }
      final distance = Geolocator.distanceBetween(
          lat, lon, reading.latitude!, reading.longitude!);
      emit(WorkspaceHomeState(
          data: data,
          location: distance <= radius * 1000
              ? LocationState.inside
              : LocationState.outside,
          distanceMeters: distance));
    } catch (_) {
      if (!isClosed &&
          current == _generation &&
          locationGeneration == _locationGeneration) {
        emit(WorkspaceHomeState(data: data, location: LocationState.failed));
      }
    }
  }
}
