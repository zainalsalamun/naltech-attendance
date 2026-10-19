import 'package:flutter_test/flutter_test.dart';
import 'package:flutter_absensi_app/data/datasources/attendance_qr_proof.dart';

void main() {
  tearDown(AttendanceQrProof.clear);

  test('QR evidence is restricted to login and action', () {
    AttendanceQrProof.remember('session-a', 'IN123', 'qr_checkin');
    expect(AttendanceQrProof.codeFor('session-a', 'qr_checkin'), 'IN123');
    expect(AttendanceQrProof.codeFor('session-b', 'qr_checkin'), isNull);
    expect(AttendanceQrProof.codeFor('session-a', 'qr_checkout'), isNull);
    expect(AttendanceQrProof.codeFor(null, 'qr_checkin'), isNull);
  });

  test('clearing scan evidence removes the previous QR', () {
    AttendanceQrProof.remember('session-a', 'IN123', 'qr_checkin');
    AttendanceQrProof.clear();
    expect(AttendanceQrProof.codeFor('session-a', 'qr_checkin'), isNull);
  });
}
