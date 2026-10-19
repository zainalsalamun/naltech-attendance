import 'dart:convert';

import 'package:dartz/dartz.dart';
import 'package:http/http.dart' as http;

import '../../../core/constants/variables.dart';
import '../../datasources/auth_local_datasource.dart';

class QrAbsenRemoteDatasource {
  Future<Either<String, bool>> checkQr(
      String code, String date, String typeQr) async {
    final authData = await AuthLocalDatasource().getAuthData();
    final url = Uri.parse('${Variables.baseUrl}/api/check-qr');
    final response = await http.post(
      url,
      headers: {
        'Accept': 'application/json',
        'Content-Type': 'application/json',
        'Authorization': 'Bearer ${authData?.token}',
      },
      body: jsonEncode({'qr_code': code, 'date': date, 'type_qr': typeQr}),
    );

    if (response.statusCode == 200) {
      final isValid = jsonDecode(response.body)['is_valid'] as bool;
      return Right(isValid);
    } else {
      return const Left('Failed');
    }
  }
}
