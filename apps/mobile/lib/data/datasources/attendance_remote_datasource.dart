import '../../core/session/authenticated_client.dart';
import 'dart:async';
import 'dart:convert';

import 'package:dartz/dartz.dart';
import 'package:flutter_absensi_app/core/constants/variables.dart';
import 'package:flutter_absensi_app/data/datasources/auth_local_datasource.dart';
import 'package:flutter_absensi_app/data/models/request/checkinout_request_model.dart';
import 'package:flutter_absensi_app/data/models/response/attendance_response_model.dart';
import 'package:flutter_absensi_app/data/models/response/checkinout_response_model.dart';
import 'package:flutter_absensi_app/data/models/response/company_response_model.dart';
import 'package:http/http.dart' as http;
import 'attendance_qr_proof.dart';
import '../models/response/attendance_context.dart';

class AttendanceRemoteDatasource {
  AttendanceRemoteDatasource(
      {http.Client? client, AuthLocalDatasource? authLocal})
      : _client = client,
        _authLocal = authLocal ?? AuthLocalDatasource();
  final http.Client? _client;
  final AuthLocalDatasource _authLocal;
  Future<Either<String, CompanyResponseModel>> getCompanyProfile() async {
    final authData = await _authLocal.getAuthData();
    final url = Uri.parse('${Variables.baseUrl}/api/company');
    final response = await SessionHttp.client.get(
      url,
      headers: {
        'Accept': 'application/json',
        'Content-Type': 'application/json',
        'Authorization': 'Bearer ${authData?.token}',
      },
    );

    if (response.statusCode == 200) {
      return Right(CompanyResponseModel.fromJson(response.body));
    } else {
      return const Left('Failed to get company profile');
    }
  }

  Future<Either<String, (bool, bool)>> isCheckedin() async {
    final result = await attendanceState();
    return result.map((state) => (state.checkedIn, state.checkedOut));
  }

  Future<Either<String, AttendanceContext>> attendanceState() async {
    try {
      final auth = await _authLocal.getAuthData();
      if (auth?.token?.isNotEmpty != true || auth?.user?.id == null) {
        return const Left('Sesi tidak tersedia.');
      }
      final request =
          http.Request('GET', Uri.parse('${Variables.baseUrl}/api/is-checkin'))
            ..followRedirects = false
            ..headers.addAll({
              'Accept': 'application/json',
              'Authorization': 'Bearer ${auth!.token}'
            });
      final response = await http.Response.fromStream(
              await (_client ?? SessionHttp.client)
                  .send(request)
                  .timeout(const Duration(seconds: 20)))
          .timeout(const Duration(seconds: 20));
      if (response.statusCode != 200 ||
          (await _authLocal.getAuthData())?.token != auth.token) {
        return const Left('Status absensi belum dapat dimuat.');
      }
      return Right(
          AttendanceContext.parse(jsonDecode(response.body), auth.user!.id!));
    } catch (_) {
      return const Left('Status absensi belum dapat dimuat.');
    }
  }

  Future<Either<String, CheckInOutResponseModel>> checkin(
          CheckInOutRequestModel data) =>
      _record(data, false);
  Future<Either<String, CheckInOutResponseModel>> checkout(
          CheckInOutRequestModel data) =>
      _record(data, true);

  Future<Either<String, CheckInOutResponseModel>> _record(
      CheckInOutRequestModel data, bool checkout) async {
    try {
      final auth = await _authLocal.getAuthData();
      if (auth?.token == null || auth!.token!.isEmpty) {
        return const Left('Sesi berakhir. Silakan masuk kembali.');
      }
      final action = checkout ? 'checkout' : 'checkin';
      final response = await (_client?.post ?? SessionHttp.client.post)(
        Uri.parse('${Variables.baseUrl}/api/$action'),
        headers: {
          'Accept': 'application/json',
          'Content-Type': 'application/json',
          'Authorization': 'Bearer ${auth.token}'
        },
        body: jsonEncode({
          ...data.toMap(),
          'qr_code': AttendanceQrProof.codeFor(auth.token, 'qr_$action')
        }),
      ).timeout(const Duration(seconds: 20));
      if (response.statusCode == 401) {
        return const Left('Sesi berakhir. Silakan masuk kembali.');
      }
      if (response.statusCode == 403) {
        return const Left(
            'Akses absensi ditolak. Periksa akun dan perusahaan Anda.');
      }
      if (response.statusCode == 200) {
        final result = CheckInOutResponseModel.fromJson(response.body);
        final recordedTime =
            checkout ? result.attendance?.timeOut : result.attendance?.timeIn;
        if (result.attendance?.userId != auth.user?.id ||
            result.attendance?.id == null ||
            result.attendance?.date == null ||
            recordedTime == null ||
            recordedTime.isEmpty) {
          return const Left(
              'Hasil absensi belum lengkap. Kembali ke Beranda dan muat ulang sebelum mencoba lagi.');
        }
        AttendanceQrProof.clear();
        return Right(result);
      }
      if (response.statusCode == 422 || response.statusCode == 409) {
        final decoded = jsonDecode(response.body);
        final message = decoded is Map ? decoded['message'] : null;
        return Left(message is String &&
                message.isNotEmpty &&
                message.length <= 1000
            ? message
            : 'Absensi ditolak. Periksa lokasi, QR, dan status kehadiran Anda.');
      }
      return const Left(
          'Belum dapat memastikan hasil absensi. Kembali ke Beranda dan muat ulang sebelum mencoba lagi.');
    } on TimeoutException {
      return const Left(
          'Koneksi terlalu lama. Hasil absensi belum dapat dipastikan. Kembali ke Beranda dan muat ulang sebelum mencoba lagi.');
    } catch (_) {
      return const Left(
          'Koneksi atau respons absensi bermasalah. Kembali ke Beranda dan muat ulang sebelum mencoba lagi.');
    }
  }

  Future<Either<String, AttendanceResponseModel>> getAttendance(
      String date) async {
    final authData = await _authLocal.getAuthData();
    final url =
        Uri.parse('${Variables.baseUrl}/api/api-attendances?date=$date');
    final response = await SessionHttp.client.get(
      url,
      headers: {
        'Accept': 'application/json',
        'Content-Type': 'application/json',
        'Authorization': 'Bearer ${authData?.token}',
      },
    );

    if (response.statusCode == 200) {
      return Right(AttendanceResponseModel.fromJson(response.body));
    } else {
      return const Left('Failed to get attendance');
    }
  }
}
