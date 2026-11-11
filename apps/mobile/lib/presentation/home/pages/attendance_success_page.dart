import 'package:flutter/material.dart';
import '../../../data/models/response/checkinout_response_model.dart';

class AttendanceSuccessPage extends StatelessWidget {
  const AttendanceSuccessPage(
      {super.key, required this.status, this.isCheckin = true, this.response});
  final String status;
  final bool isCheckin;
  final CheckInOutResponseModel? response;
  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final record = response?.attendance;
    final time = isCheckin ? record?.timeIn : record?.timeOut;
    final date = record?.date;
    return Scaffold(
        appBar: AppBar(title: const Text('Hasil absensi')),
        body: SafeArea(
            child: ListView(padding: const EdgeInsets.all(24), children: [
          const SizedBox(height: 32),
          Icon(Icons.task_alt, size: 64, color: theme.colorScheme.primary),
          const SizedBox(height: 24),
          Text('Kehadiran tercatat',
              textAlign: TextAlign.center,
              style: theme.textTheme.headlineSmall
                  ?.copyWith(fontWeight: FontWeight.w700)),
          const SizedBox(height: 12),
          Text(status,
              textAlign: TextAlign.center, style: theme.textTheme.titleMedium),
          const SizedBox(height: 28),
          Container(
              padding: const EdgeInsets.all(24),
              decoration: BoxDecoration(
                  color: theme.colorScheme.surface,
                  border: Border.all(color: theme.colorScheme.outline),
                  borderRadius: BorderRadius.circular(16)),
              child: Column(children: [
                Text(isCheckin ? 'Jam masuk' : 'Jam pulang',
                    style:
                        TextStyle(color: theme.colorScheme.onSurfaceVariant)),
                const SizedBox(height: 10),
                Text(time ?? 'Belum tersedia',
                    style: theme.textTheme.headlineMedium
                        ?.copyWith(fontWeight: FontWeight.w700)),
                const SizedBox(height: 8),
                Text(date == null
                    ? 'Tanggal belum tersedia'
                    : '${date.day.toString().padLeft(2, '0')}-${date.month.toString().padLeft(2, '0')}-${date.year}'),
              ])),
          const SizedBox(height: 20),
          const Text(
              'Tanggal dan jam mengikuti catatan absensi yang diterima. Muat ulang Beranda bila data belum berubah.',
              textAlign: TextAlign.center),
          const SizedBox(height: 28),
          FilledButton(
              onPressed: () {
                Navigator.of(context).popUntil((route) => route.isFirst);
              },
              child: const Text('Kembali ke Beranda')),
        ])));
  }
}
