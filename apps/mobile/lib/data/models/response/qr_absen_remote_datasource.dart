import '../../../core/session/authenticated_client.dart';
import 'dart:convert';

import 'package:dartz/dartz.dart';
import 'package:http/http.dart' as http;

import '../../../core/constants/variables.dart';
import '../../datasources/auth_local_datasource.dart';
import '../../datasources/attendance_qr_proof.dart';

class QrAbsenRemoteDatasource {
  QrAbsenRemoteDatasource({http.Client? client}) : _client = client;
  final http.Client? _client;
  Future<Either<String, bool>> checkQr(
      String code, String date, String typeQr) async {
    AttendanceQrProof.clear();
    final revision = AttendanceQrProof.revision;
    final authData = await AuthLocalDatasource().getAuthData();
    final token = authData?.token;
    if (token == null || token.isEmpty) {
      return const Left('Sesi berakhir. Silakan masuk kembali.');
    }
    final url = Uri.parse('${Variables.baseUrl}/api/check-qr');
    final response = await (_client?.post ?? SessionHttp.client.post)(
      url,
      headers: {
        'Accept': 'application/json',
        'Content-Type': 'application/json',
        'Authorization': 'Bearer $token',
      },
      body: jsonEncode({'qr_code': code, 'date': date, 'type_qr': typeQr}),
    ).timeout(const Duration(seconds: 15));

    if (revision != AttendanceQrProof.revision) {
      return const Left('Pemindaian sudah dibatalkan.');
    }
    if (response.statusCode == 200) {
      final isValid = jsonDecode(response.body)['is_valid'] as bool;
      if (isValid) {
        AttendanceQrProof.remember(token, code, typeQr);
      }
      return Right(isValid);
    } else {
      return Left(response.statusCode == 401
          ? 'Sesi berakhir. Silakan masuk kembali.'
          : 'QR tidak valid untuk tanggal atau jenis absensi ini.');
    }
  }
}
