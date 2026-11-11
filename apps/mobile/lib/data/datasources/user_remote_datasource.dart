import 'dart:convert';
import 'package:dartz/dartz.dart';
import 'package:http/http.dart' as http;
import '../../core/constants/variables.dart';
import '../../core/session/authenticated_client.dart';
import '../../core/session/session_events.dart';
import '../models/request/user_request_model.dart';
import '../models/response/auth_response_model.dart';
import '../models/response/user_response_model.dart';
import 'auth_local_datasource.dart';

class UserRemoteDatasource {
  UserRemoteDatasource({http.Client? client, AuthLocalDatasource? local})
      : _client = client ?? SessionHttp.client,
        _local = local ?? AuthLocalDatasource();
  final http.Client _client;
  final AuthLocalDatasource _local;
  User? _parse(String body, int expectedId) {
    final decoded = jsonDecode(body);
    if (decoded is! Map<String, dynamic>) return null;
    final raw = decoded['data'] ?? decoded['user'];
    final map = raw ?? (decoded.containsKey('id') ? decoded : null);
    if (map is! Map<String, dynamic>) return null;
    final user = User.fromMap(map);
    return user.id == expectedId ? user : null;
  }

  Future<String> _failure(int status, String token) async {
    if (status == 401) {
      await SessionEvents.expire(token);
      return 'Sesi berakhir. Silakan masuk kembali.';
    }
    if (status == 403) {
      return 'Akun belum dapat mengakses perusahaan. Hubungi pengelola perusahaan.';
    }
    return 'Profil belum dapat diproses. Periksa koneksi dan coba lagi.';
  }

  Future<Either<String, User>> getUser() async {
    try {
      final auth = await _local.getAuthData();
      if (auth?.user?.id == null || auth?.token?.isNotEmpty != true) {
        return const Left('Sesi tidak tersedia. Silakan masuk kembali.');
      }
      final response = await _client.get(
          Uri.parse('${Variables.baseUrl}/api/api-user/${auth!.user!.id}'),
          headers: {
            'Accept': 'application/json',
            'Authorization': 'Bearer ${auth.token}'
          }).timeout(const Duration(seconds: 15));
      if (response.statusCode != 200) {
        return Left(await _failure(response.statusCode, auth.token!));
      }
      final user = _parse(response.body, auth.user!.id!);
      return user == null
          ? const Left('Respons profil belum lengkap. Coba lagi.')
          : Right(user);
    } catch (_) {
      return const Left(
          'Profil belum dapat dimuat. Periksa koneksi dan coba lagi.');
    }
  }

  Future<Either<String, User>> updateProfile(
      UserRequestModel model, int id) async {
    try {
      final auth = await _local.getAuthData();
      if (auth?.token?.isNotEmpty != true || auth?.user?.id == null) {
        return const Left('Sesi tidak tersedia. Silakan masuk kembali.');
      }
      if (id != auth!.user!.id || model.id != auth.user!.id) {
        return const Left('Hanya profil akun sendiri yang dapat diperbarui.');
      }
      final request = http.MultipartRequest(
          'POST', Uri.parse('${Variables.baseUrl}/api/api-user/edit'))
        ..fields.addAll(model.toMap())
        ..headers.addAll({
          'Accept': 'application/json',
          'Authorization': 'Bearer ${auth.token}'
        });
      if (model.image != null) {
        final file = model.image!;
        final extension = file.name.toLowerCase().split('.').last;
        if (!['jpg', 'jpeg', 'png'].contains(extension) ||
            await file.length() > 2 * 1024 * 1024) {
          return const Left('Gunakan foto JPG/PNG maksimal 2 MB.');
        }
        request.files.add(http.MultipartFile.fromBytes(
            'image', await file.readAsBytes(),
            filename: file.name));
      }
      final streamed =
          await _client.send(request).timeout(const Duration(seconds: 20));
      final body = await streamed.stream
          .bytesToString()
          .timeout(const Duration(seconds: 15));
      if (streamed.statusCode == 422) {
        final decoded = jsonDecode(body);
        final errors = decoded is Map ? decoded['errors'] : null;
        if (errors is Map) {
          for (final field in ['name', 'email', 'phone', 'image']) {
            final values = errors[field];
            if (values is List && values.isNotEmpty && values.first is String) {
              return Left(values.first as String);
            }
          }
        }
        return const Left('Periksa kembali data profil dan foto Anda.');
      }
      if (streamed.statusCode != 200) {
        return Left(await _failure(streamed.statusCode, auth.token!));
      }
      final user = _parse(body, id);
      if (user == null) {
        return const Left(
            'Respons perubahan profil belum lengkap. Muat ulang profil sebelum mencoba lagi.');
      }
      try {
        await _local.updateAuthData(UserResponseModel(user: user),
            expectedToken: auth.token);
      } catch (_) {
        return const Left(
            'Profil telah diterima, tetapi sesi lokal belum dapat diperbarui. Muat ulang profil.');
      }
      return Right(user);
    } catch (_) {
      return const Left(
          'Hasil perubahan belum dapat dipastikan. Muat ulang profil sebelum mencoba lagi.');
    }
  }
}
