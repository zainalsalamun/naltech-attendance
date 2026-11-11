import 'attendance_response_model.dart';

class AttendanceContext {
  const AttendanceContext(
      {required this.checkedIn,
      required this.checkedOut,
      this.date,
      this.qrDate,
      this.attendance,
      this.schedule,
      this.actionable = true});
  final bool checkedIn, checkedOut, actionable;
  final String? date, qrDate;
  final Attendance? attendance;
  final Map<String, dynamic>? schedule;
  static bool validDate(dynamic value) {
    if (value is! String || !RegExp(r'^\d{4}-\d{2}-\d{2}$').hasMatch(value)) {
      return false;
    }
    final parsed = DateTime.tryParse(value);
    return parsed != null && parsed.toIso8601String().substring(0, 10) == value;
  }

  static AttendanceContext parse(Map<String, dynamic> raw, int ownId) {
    if (raw['checkedin'] is! bool || raw['checkedout'] is! bool) {
      throw const FormatException();
    }
    if (!raw.containsKey('attendance_date')) {
      return AttendanceContext(
          checkedIn: raw['checkedin'], checkedOut: raw['checkedout']);
    }
    final date = raw['attendance_date'];
    final qr = raw['qr_date'];
    if (!validDate(date) ||
        !validDate(qr) ||
        raw['actionable'] is! bool ||
        raw['schedule'] is! Map) {
      throw const FormatException();
    }
    final row = raw['attendance'] == null
        ? null
        : Attendance.fromMap(Map<String, dynamic>.from(raw['attendance']));
    if (row != null &&
        (row.userId != ownId ||
            row.date?.toIso8601String().substring(0, 10) != date)) {
      throw const FormatException();
    }
    final schedule = Map<String, dynamic>.from(raw['schedule']);
    for (final key in [
      'scheduled_name',
      'scheduled_in',
      'scheduled_out',
      'attendance_timezone'
    ]) {
      if (schedule[key] != null && schedule[key] is! String) {
        throw const FormatException();
      }
    }
    return AttendanceContext(
        checkedIn: raw['checkedin'],
        checkedOut: raw['checkedout'],
        date: date as String,
        qrDate: qr as String,
        attendance: row,
        schedule: schedule,
        actionable: raw['actionable']);
  }
}
