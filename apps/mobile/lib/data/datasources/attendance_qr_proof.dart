/// Ephemeral scan evidence, bound to the current login and attendance action.
/// The server independently validates this QR; no client boolean is trusted.
class AttendanceQrProof {
  static String? _token;
  static String? _code;
  static String? _action;
  static DateTime? _scannedAt;

  static void remember(String token, String code, String action) {
    _token = token;
    _code = code;
    _action = action;
    _scannedAt = DateTime.now();
  }

  static String? codeFor(String? token, String action) {
    if (token == null || token != _token || action != _action ||
        _scannedAt == null ||
        DateTime.now().difference(_scannedAt!) > const Duration(minutes: 5)) {
      return null;
    }
    return _code;
  }

  static void clear() {
    _token = null;
    _code = null;
    _action = null;
    _scannedAt = null;
  }
}
