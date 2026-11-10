import 'package:http/http.dart' as http;
import 'session_events.dart';

class AuthenticatedClient extends http.BaseClient {
  AuthenticatedClient(this.inner);
  final http.Client inner;
  @override
  Future<http.StreamedResponse> send(http.BaseRequest request) async {
    final response = await inner.send(request);
    if (response.statusCode == 401) {
      final authorization =
          request.headers['Authorization'] ?? request.headers['authorization'];
      if (authorization?.startsWith('Bearer ') == true) {
        await SessionEvents.expire(authorization!.substring(7));
      }
    }
    return response;
  }

  @override
  void close() => inner.close();
}

class SessionHttp {
  static final http.Client client = AuthenticatedClient(http.Client());
}
