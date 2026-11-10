import 'dart:convert';
import 'package:dartz/dartz.dart';
import 'package:http/http.dart' as http;
import 'package:image_picker/image_picker.dart';
import '../../core/constants/variables.dart';
import '../../core/session/authenticated_client.dart';
import '../../core/session/session_events.dart';
import 'auth_local_datasource.dart';

class FaceEnrollmentRecord {
  const FaceEnrollmentRecord(this.id, this.status, [this.reason]);
  final int id;
  final String status;
  final String? reason;
  bool get active => ['pending', 'approved'].contains(status);
  String get label =>
      const {
        'pending': 'Menunggu persetujuan',
        'approved': 'Disetujui',
        'rejected': 'Ditolak',
        'revoked': 'Dicabut',
        'superseded': 'Digantikan',
        'expired': 'Kedaluwarsa',
      }[status] ??
      'Status belum tersedia';
}

class FaceEnrollmentSnapshot {
  const FaceEnrollmentSnapshot(this.records, {this.previewAvailable = false});
  final List<FaceEnrollmentRecord> records;
  final bool previewAvailable;
  bool get pending => records.any((row) => row.status == 'pending');
  bool get approved => records.any((row) => row.status == 'approved');
}

class FaceEnrollmentRemoteDatasource {
  FaceEnrollmentRemoteDatasource(
      {http.Client? client, AuthLocalDatasource? local})
      : _client = client ?? SessionHttp.client,
        _local = local ?? AuthLocalDatasource();
  final http.Client _client;
  final AuthLocalDatasource _local;

  Future<String> _error(int status, String token) async {
    if (status == 401) {
      await SessionEvents.expire(token);
      return 'Sesi berakhir. Silakan masuk kembali.';
    }
    if (status == 409) {
      return 'Status wajah berubah. Muat ulang sebelum mencoba lagi.';
    }
    if (status == 422) {
      return 'Gunakan satu wajah yang jelas pada foto JPG/PNG 320–4096 piksel, maksimal 2 MB, dan setujui penggunaan foto.';
    }
    if (status == 403) {
      return 'Akun belum dapat mengakses perusahaan. Hubungi HR/admin.';
    }
    if (status == 503) {
      return 'Layanan wajah belum tersedia. Coba lagi atau hubungi HR/admin.';
    }
    return 'Hasil belum dapat dipastikan. Muat ulang status sebelum mencoba lagi.';
  }

  Future<Either<String, FaceEnrollmentSnapshot>> load() async {
    try {
      final auth = await _local.getAuthData();
      final token = auth?.token;
      if (token == null || token.isEmpty) {
        return const Left('Silakan masuk kembali.');
      }
      final response = await _client.get(
          Uri.parse('${Variables.baseUrl}/api/face-enrollments'),
          headers: {
            'Accept': 'application/json',
            'Authorization': 'Bearer $token'
          }).timeout(const Duration(seconds: 15));
      if (response.statusCode != 200) {
        return Left(await _error(response.statusCode, token));
      }
      final body = jsonDecode(response.body);
      if (body is! Map || body['data'] is! List) throw const FormatException();
      final rows = <FaceEnrollmentRecord>[];
      for (final raw in body['data']) {
        if (raw is! Map ||
            raw['id'] is! int ||
            raw['status'] is! String ||
            ![
              'pending',
              'approved',
              'rejected',
              'revoked',
              'superseded',
              'expired'
            ].contains(raw['status'])) {
          throw const FormatException();
        }
        rows.add(FaceEnrollmentRecord(
            raw['id'], raw['status'], raw['review_reason'] as String?));
      }
      return Right(FaceEnrollmentSnapshot(rows,
          previewAvailable: body['matching_preview_available'] == true));
    } catch (_) {
      return const Left(
          'Status wajah belum dapat dimuat. Periksa koneksi dan coba lagi.');
    }
  }

  Future<Either<String, bool>> submit(XFile photo,
      {required bool consent}) async {
    if (!consent) {
      return const Left('Setujui penggunaan foto sebelum mengirim.');
    }
    return _photoRequest('face-enrollments', photo, fields: {'consent': '1'});
  }

  Future<Either<String, bool>> preview(XFile photo) =>
      _photoRequest('face-matching-preview', photo, preview: true);

  Future<Either<String, bool>> _photoRequest(String endpoint, XFile photo,
      {Map<String, String> fields = const {}, bool preview = false}) async {
    try {
      final auth = await _local.getAuthData();
      final token = auth?.token;
      if (token == null || token.isEmpty) {
        return const Left('Silakan masuk kembali.');
      }
      if (await photo.length() > 2 * 1024 * 1024) {
        return const Left('Foto maksimal 2 MB. Ambil ulang foto.');
      }
      final request = http.MultipartRequest(
          'POST', Uri.parse('${Variables.baseUrl}/api/$endpoint'))
        ..headers.addAll(
            {'Accept': 'application/json', 'Authorization': 'Bearer $token'})
        ..fields.addAll(fields)
        ..files.add(http.MultipartFile.fromBytes(
            'photo', await photo.readAsBytes(),
            filename: 'selfie.jpg'));
      final response =
          await _client.send(request).timeout(const Duration(seconds: 25));
      final raw = await response.stream
          .bytesToString()
          .timeout(const Duration(seconds: 15));
      if (response.statusCode != (preview ? 200 : 201)) {
        return Left(await _error(response.statusCode, token));
      }
      final body = jsonDecode(raw);
      if (preview) {
        final data = body is Map ? body['data'] : null;
        if (data is! Map ||
            data['matched'] is! bool ||
            data['attendance_eligible'] != false ||
            data['liveness'] != 'not_configured') {
          throw const FormatException();
        }
        return Right(data['matched']);
      }
      if (body is! Map ||
          body['data'] is! Map ||
          body['data']['status'] != 'pending') {
        throw const FormatException();
      }
      return const Right(true);
    } catch (_) {
      return const Left(
          'Hasil pengiriman belum dapat dipastikan. Muat ulang status sebelum mencoba lagi.');
    }
  }

  Future<Either<String, bool>> withdraw(int id) async {
    try {
      final auth = await _local.getAuthData();
      final token = auth?.token;
      if (token == null || token.isEmpty) {
        return const Left('Silakan masuk kembali.');
      }
      final response = await _client.delete(
          Uri.parse('${Variables.baseUrl}/api/face-enrollments/$id'),
          headers: {
            'Accept': 'application/json',
            'Authorization': 'Bearer $token'
          }).timeout(const Duration(seconds: 15));
      return response.statusCode == 200
          ? const Right(true)
          : Left(await _error(response.statusCode, token));
    } catch (_) {
      return const Left(
          'Hasil pencabutan belum dapat dipastikan. Muat ulang status.');
    }
  }
}
