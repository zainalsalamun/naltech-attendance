import 'package:dartz/dartz.dart';
import 'package:flutter_absensi_app/core/constants/variables.dart';
import 'package:flutter_absensi_app/data/datasources/auth_local_datasource.dart';
import 'package:http/http.dart' as http;
import 'package:image_picker/image_picker.dart';

import '../models/response/reimbursement_response_model.dart';
import 'dart:convert';

class ReimbursementRemoteDatasource {
  Future<Either<String, ReimbursementResponseModel>> getReimbursements() async {
    final authData = await AuthLocalDatasource().getAuthData();
    final url = Uri.parse('${Variables.baseUrl}/api/api-reimbursements');

    final response = await http.get(
      url,
      headers: {
        'Accept': 'application/json',
        'Authorization': 'Bearer ${authData?.token}',
      },
    );

    if (response.statusCode == 200) {
      return Right(ReimbursementResponseModel.fromJson(response.body));
    } else {
      try {
        final error = jsonDecode(response.body);
        return Left(error['message'] ?? 'Failed to get reimbursements');
      } catch (e) {
        return const Left('Failed to get reimbursements');
      }
    }
  }

  Future<Either<String, void>> addReimbursement(
      String date, String description, String amount, XFile? image) async {
    final authData = await AuthLocalDatasource().getAuthData();
    final url = Uri.parse('${Variables.baseUrl}/api/api-reimbursements');

    var request = http.MultipartRequest('POST', url)
      ..headers.addAll({
        'Accept': 'application/json',
        'Authorization': 'Bearer ${authData?.token}',
      })
      ..fields['date'] = date
      ..fields['description'] = description
      ..fields['amount'] = amount;

    if (image != null) {
      request.files.add(
        await http.MultipartFile.fromPath('image', image.path),
      );
    }

    final streamResponse = await request.send();
    final response = await http.Response.fromStream(streamResponse);

    if (response.statusCode == 201 || response.statusCode == 200) {
      return const Right(null);
    } else {
      try {
        final error = jsonDecode(response.body);
        return Left(error['message'] ?? 'Failed to add reimbursement');
      } catch (e) {
        return const Left('Failed to add reimbursement');
      }
    }
  }
}
