import 'package:intl/intl.dart';
import '../../../data/models/response/attendance_response_model.dart';

String historyDate(DateTime date) {
  const days = ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu'];
  return '${days[date.weekday - 1]}, ${date.day} ${historyMonth(date.month)} ${date.year}';
}

String historyMonth(int month) => const [
      'Januari',
      'Februari',
      'Maret',
      'April',
      'Mei',
      'Juni',
      'Juli',
      'Agustus',
      'September',
      'Oktober',
      'November',
      'Desember'
    ][month - 1];
String historyDateKey(DateTime date) => DateFormat('yyyy-MM-dd').format(date);

class HistoryCoordinates {
  const HistoryCoordinates(this.latitude, this.longitude);
  final double latitude, longitude;
  static HistoryCoordinates? parse(String? value) {
    final parts = value?.split(',');
    if (parts == null || parts.length != 2) return null;
    final lat = double.tryParse(parts[0].trim());
    final lon = double.tryParse(parts[1].trim());
    if (lat == null ||
        lon == null ||
        !lat.isFinite ||
        !lon.isFinite ||
        lat.abs() > 90 ||
        lon.abs() > 180) {
      return null;
    }
    return HistoryCoordinates(lat, lon);
  }
}

bool historyValidTime(String? value) =>
    value != null &&
    RegExp(r'^(?:[01]\d|2[0-3]):[0-5]\d(?::[0-5]\d)?$').hasMatch(value);

String historyStatus(Attendance record) {
  final hasIn = historyValidTime(record.timeIn);
  final hasOut = historyValidTime(record.timeOut);
  if (!hasIn || (record.timeOut?.isNotEmpty == true && !hasOut)) {
    return 'Perlu tinjau';
  }
  return hasOut ? 'Masuk & pulang tercatat' : 'Masuk tercatat';
}

String historyPay(dynamic value) {
  final amount = num.tryParse(value?.toString() ?? '');
  if (amount == null || !amount.isFinite || amount < 0) return 'Belum tersedia';
  return NumberFormat.currency(locale: 'id_ID', symbol: 'Rp ', decimalDigits: 2)
      .format(amount);
}
