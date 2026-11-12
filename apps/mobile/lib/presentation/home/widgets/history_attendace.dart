import 'package:flutter/material.dart';
import '../models/history_presentation.dart';
import 'history_location.dart';

class HistoryAttendance extends StatelessWidget {
  const HistoryAttendance(
      {super.key,
      required this.time,
      required this.date,
      required this.statusAbsen,
      this.isAttendanceIn = true,
      this.overtimeMinutes,
      this.overtimePay,
      this.coordinates});
  final String time, date, statusAbsen;
  final bool isAttendanceIn;
  final int? overtimeMinutes;
  final dynamic overtimePay;
  final String? coordinates;
  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final color = isAttendanceIn
        ? (theme.brightness == Brightness.dark
            ? const Color(0xFF9ED2B9)
            : const Color(0xFF34725B))
        : theme.colorScheme.primary;
    final recorded = historyValidTime(time);
    final invalid = time.isNotEmpty && !recorded;
    return Container(
        padding: const EdgeInsets.all(18),
        decoration: BoxDecoration(
            color: theme.colorScheme.surface,
            border: Border.all(color: theme.colorScheme.outline),
            borderRadius: BorderRadius.circular(16)),
        child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          Row(children: [
            Container(
                padding: const EdgeInsets.all(10),
                decoration: BoxDecoration(
                    color: color.withValues(alpha: .10),
                    borderRadius: BorderRadius.circular(12)),
                child: Icon(isAttendanceIn ? Icons.login : Icons.logout,
                    color: color, size: 24)),
            const SizedBox(width: 12),
            Expanded(
                child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                  Text(statusAbsen,
                      style: theme.textTheme.titleMedium
                          ?.copyWith(fontWeight: FontWeight.w700)),
                  const SizedBox(height: 3),
                  Text(
                      invalid
                          ? 'Jam perlu ditinjau'
                          : recorded
                              ? 'Kehadiran tercatat'
                              : 'Belum tercatat',
                      style: TextStyle(
                          fontSize: 12,
                          color: theme.colorScheme.onSurfaceVariant))
                ]))
          ]),
          const SizedBox(height: 18),
          Text(recorded ? time : '--:--',
              style: theme.textTheme.headlineMedium
                  ?.copyWith(fontWeight: FontWeight.w700)),
          const SizedBox(height: 14),
          HistoryLocation(
              coordinates: coordinates,
              isAttendance: isAttendanceIn,
              recorded: recorded),
          if (overtimeMinutes != null) ...[
            const Divider(height: 28),
            Wrap(spacing: 12, runSpacing: 8, children: [
              const Text('Lembur tercatat'),
              Text(
                  overtimeMinutes! >= 0
                      ? '$overtimeMinutes menit'
                      : 'Perlu tinjau',
                  style: const TextStyle(fontWeight: FontWeight.w600))
            ]),
            const SizedBox(height: 6),
            Text('Nominal lembur: ${historyPay(overtimePay)}',
                style: TextStyle(color: theme.colorScheme.onSurfaceVariant))
          ],
        ]));
  }
}
