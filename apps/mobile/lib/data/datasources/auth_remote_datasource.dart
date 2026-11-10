import 'dart:convert';
import '../../core/session/authenticated_client.dart';
import '../../core/session/session_events.dart';

import 'package:dartz/dartz.dart';
import 'package:flutter_absensi_app/core/constants/variables.dart';
import 'package:flutter_absensi_app/data/datasources/auth_local_datasource.dart';
import 'package:flutter_absensi_app/data/models/response/auth_response_model.dart';
import 'package:http/http.dart' as http;

import '../models/response/user_response_model.dart';

enum SessionCheck { signedOut, active, companyMissing, unavailable }

class AuthRemoteDatasource {
  AuthRemoteDatasource({http.Client? client, AuthLocalDatasource? local})
      : _client = client ?? SessionHttp.client,
        _local = local ?? AuthLocalDatasource();
  final http.Client _client;
  final AuthLocalDatasource _local;
  Future<SessionCheck> restoreSession() async {
    try {
      final auth = await _local.getAuthData();
      if (auth?.token?.isNotEmpty != true || auth?.user?.id == null) {
        await _local.removeAuthData();
        return SessionCheck.signedOut;
      }
      final response = await _client
          .get(Uri.parse('${Variables.baseUrl}/api/user'), headers: {
        'Accept': 'application/json',
        'Authorization': 'Bearer ${auth!.token}'
      }).timeout(const Duration(seconds: 15));
      if (response.statusCode == 401) {
        await SessionEvents.expire(auth.token);
        return SessionCheck.signedOut;
      }
      if (response.statusCode == 403) return SessionCheck.companyMissing;
      if (response.statusCode != 200) return SessionCheck.unavailable;
      final user = User.fromMap(jsonDecode(response.body));
      if (user.id != auth.user!.id) return SessionCheck.unavailable;
      await _local.updateAuthData(UserResponseModel(user: user),
          expectedToken: auth.token);
      return SessionCheck.active;
    } catch (_) {
      return SessionCheck.unavailable;
    }
  }

  Future<Either<String, AuthResponseModel>> login(
      String username, String password) async {
    final url = Uri.parse('${Variables.baseUrl}/api/login');
    final response = await _client
        .post(
          url,
          headers: {
            'Accept': 'application/json',
            'Content-Type': 'application/json',
          },
          body: jsonEncode({
            'email': username,
            'password': password,
          }),
        )
        .timeout(const Duration(seconds: 20));

    if (response.statusCode == 200) {
      final data = AuthResponseModel.fromJson(response.body);
      if (data.token?.isNotEmpty != true || data.user?.id == null) {
        return const Left('Respons akun belum lengkap. Coba masuk kembali.');
      }
      return Right(data);
    } else {
      return Left(response.statusCode == 429
          ? 'Terlalu banyak percobaan. Tunggu sebentar sebelum mencoba lagi.'
          : response.statusCode == 401 || response.statusCode == 422
              ? 'Email atau kata sandi belum sesuai.'
              : 'Belum dapat masuk. Silakan coba lagi.');
    }
  }

  Future<Either<String, String>> logout() async {
    try {
      final auth = await _local.getAuthData();
      if (auth?.token?.isNotEmpty != true) {
        await _local.removeAuthData();
        return const Right('Keluar akun berhasil');
      }
      final response = await _client
          .post(Uri.parse('${Variables.baseUrl}/api/logout'), headers: {
        'Accept': 'application/json',
        'Authorization': 'Bearer ${auth!.token}'
      }).timeout(const Duration(seconds: 15));
      if (response.statusCode == 200 || response.statusCode == 401) {
        await _local.removeIfToken(auth.token!);
        return const Right('Keluar akun berhasil');
      }
      return const Left(
          'Belum dapat keluar akun. Periksa koneksi dan coba lagi.');
    } catch (_) {
      return const Left(
          'Keluar akun belum dikonfirmasi. Periksa koneksi lalu coba lagi.');
    }
  }

  Future<Either<String, UserResponseModel>> updateProfileRegisterFace(
    String embedding,
  ) async {
    final authData = await _local.getAuthData();
    final url = Uri.parse('${Variables.baseUrl}/api/update-profile');
    final request = http.MultipartRequest('POST', url)
      ..headers['Authorization'] = 'Bearer ${authData?.token}'
      ..fields['face_embedding'] = embedding;

    final response = await _client.send(request);
    final responseString = await response.stream.bytesToString();

    if (response.statusCode == 200) {
      return Right(UserResponseModel.fromJson(responseString));
    } else {
      return const Left('Failed to update profile');
    }
  }

  Future<void> updateFcmToken(String fcmToken) async {
    final authData = await _local.getAuthData();
    final url = Uri.parse('${Variables.baseUrl}/api/update-fcm-token');
    await _client.post(
      url,
      headers: {
        'Accept': 'application/json',
        'Content-Type': 'application/json',
        'Authorization': 'Bearer ${authData?.token}',
      },
      body: jsonEncode({
        'fcm_token': fcmToken,
      }),
    );
  }
}
