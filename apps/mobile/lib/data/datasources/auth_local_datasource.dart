import 'package:flutter_absensi_app/data/models/response/auth_response_model.dart';
import 'package:flutter_absensi_app/data/models/response/user_response_model.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'attendance_qr_proof.dart';

class AuthLocalDatasource {
  static Future<void> _pending = Future.value();
  static void resetForTesting() {
    _pending = Future.value();
  }
  static Future<T> _serial<T>(Future<T> Function() work) {
    final result = _pending.then((_) => work());
    _pending = result.then<void>((_) {}, onError: (Object _, StackTrace __) {});
    return result;
  }

  Future<AuthResponseModel?> _read(SharedPreferences preferences) async {
    try {
      final raw = preferences.getString('auth_data');
      if (raw == null) return null;
      final auth = AuthResponseModel.fromJson(raw);
      if (auth.token?.isNotEmpty != true || auth.user?.id == null) {
        throw StateError('Invalid session');
      }
      return auth;
    } catch (_) {
      if (!await preferences.remove('auth_data')) {
        throw StateError('Cannot clear invalid session');
      }
      AttendanceQrProof.clear();
      return null;
    }
  }

  Future<void> saveAuthData(AuthResponseModel data) => _serial(() async {
        if (data.token?.isNotEmpty != true || data.user?.id == null) {
          throw StateError('Invalid session');
        }
        final pref = await SharedPreferences.getInstance();
        if (!await pref.setString('auth_data', data.toJson())) {
          throw StateError('Cannot save session');
        }
        AttendanceQrProof.clear();
      });
  Future<void> updateAuthData(UserResponseModel data,
          {String? expectedToken}) =>
      _serial(() async {
        final pref = await SharedPreferences.getInstance();
        final auth = await _read(pref);
        if (auth == null ||
            data.user?.id != auth.user?.id ||
            (expectedToken != null && auth.token != expectedToken)) {
          throw StateError('Session changed');
        }
        if (!await pref.setString(
            'auth_data', auth.copyWith(user: data.user).toJson())) {
          throw StateError('Cannot save profile cache');
        }
      });
  Future<void> removeAuthData() => _serial(() async {
        final pref = await SharedPreferences.getInstance();
        if (!await pref.remove('auth_data')) {
          throw StateError('Cannot remove session');
        }
        AttendanceQrProof.clear();
      });
  Future<bool> removeIfToken(String token) => _serial(() async {
        final pref = await SharedPreferences.getInstance();
        final auth = await _read(pref);
        if (auth?.token != token) return false;
        if (!await pref.remove('auth_data')) {
          throw StateError('Cannot remove session');
        }
        AttendanceQrProof.clear();
        return true;
      });
  Future<AuthResponseModel?> getAuthData() =>
      _serial(() async => _read(await SharedPreferences.getInstance()));
  Future<bool> isAuth() async {
    final auth = await getAuthData();
    return auth?.token?.isNotEmpty == true && auth?.user?.id != null;
  }
}
