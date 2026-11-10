import 'dart:async';
import '../../data/datasources/auth_local_datasource.dart';

class SessionEvents {
  static final _events = StreamController<void>.broadcast();
  static Stream<void> get expired => _events.stream;
  static Future<void> expire(String? token) async {
    if (token == null || token.isEmpty) return;
    if (await AuthLocalDatasource().removeIfToken(token)) _events.add(null);
  }
}
