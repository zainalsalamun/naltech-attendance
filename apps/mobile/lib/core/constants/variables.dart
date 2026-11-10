class Variables {
  static const String appName = 'Naltech Attendance';

  /// Konfigurasi URL API Backend Laravel:
  /// - Android Emulator : 'http://10.0.2.2:8000'
  /// - iOS Simulator    : 'http://localhost:8000'
  /// - Perangkat Fisik  : 'http://192.168.x.x:8000' (sesuaikan IP LAN komputer)
  /// - Server Produksi  : 'https://api.perusahaan-anda.com'
  static const String baseUrl = 'http://10.0.2.2:8000';
}
