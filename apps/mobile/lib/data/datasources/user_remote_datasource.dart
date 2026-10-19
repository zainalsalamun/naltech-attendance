import 'dart:convert';

import 'package:dartz/dartz.dart';
import 'package:http/http.dart' as http;

import '../../core/constants/variables.dart';
import '../models/request/user_request_model.dart';
import '../models/response/auth_response_model.dart';
import 'auth_local_datasource.dart';

class UserRemoteDatasource {
  Future<Either<String, User>> getUser() async {
    final authData = await AuthLocalDatasource().getAuthData();
    final url =
        Uri.parse('${Variables.baseUrl}/api/api-user/${authData!.user!.id!}');
    final response = await http.get(
      url,
      headers: {
        'Accept': 'application/json',
        'Content-Type': 'application/json',
        'Authorization': 'Bearer ${authData.token}',
      },
    );
    if (response.statusCode == 200) {
      try {
        final decoded = jsonDecode(response.body);
        Map<String, dynamic>? userMap;
        if (decoded is Map<String, dynamic>) {
          if (decoded.containsKey('data')) {
            userMap = decoded['data'];
          } else if (decoded.containsKey('user')) {
            userMap = decoded['user'];
          } else {
            userMap = decoded;
          }
        }
        if (userMap != null) {
          return right(User.fromMap(userMap));
        } else {
          return left('Format API tidak sesuai');
        }
      } catch (e) {
        return left('Gagal memparsing respons');
      }
    } else {
      return left(response.body);
    }
  }

  Future<Either<String, User>> updateProfile(
      UserRequestModel model, int id) async {
    final authData = await AuthLocalDatasource().getAuthData();
    final Map<String, String> headers = {
      'Authorization': 'Bearer ${authData!.token}',
      'Content-Type': 'multipart/form-data',
      'Accept': 'multipart/form-data'
    };
    var request = http.MultipartRequest(
        'POST', Uri.parse('${Variables.baseUrl}/api/api-user/edit'));
    request.fields.addAll(model.toMap());
    if (model.image != null) {
      request.files
          .add(await http.MultipartFile.fromPath('image', model.image!.path));
    }
    request.headers.addAll(headers);

    http.StreamedResponse response = await request.send();

    final String body = await response.stream.bytesToString();

    if (response.statusCode == 200) {
      try {
        final decoded = jsonDecode(body);
        Map<String, dynamic>? userMap;
        if (decoded is Map<String, dynamic>) {
          if (decoded.containsKey('data')) {
            userMap = decoded['data'];
          } else if (decoded.containsKey('user')) {
            userMap = decoded['user'];
          } else {
            userMap = decoded;
          }
        }
        if (userMap != null) {
          return right(User.fromMap(userMap));
        } else {
          return left('Format API tidak sesuai: $body');
        }
      } catch (e) {
        return left('Gagal memparsing respons: $body');
      }
    } else {
      return left(body);
    }
  }
}
