import 'package:flutter/material.dart';
import '../models/history_presentation.dart';
import '../pages/location_page.dart';

class HistoryLocation extends StatelessWidget {
  const HistoryLocation(
      {super.key,
      this.coordinates,
      this.latitude,
      this.longitude,
      this.isAttendance = true,
      this.recorded = true});
  final String? coordinates;
  final double? latitude, longitude;
  final bool isAttendance, recorded;
  @override
  Widget build(BuildContext context) {
    final pageContext = context;
    final point = HistoryCoordinates.parse(coordinates ??
        (latitude != null && longitude != null
            ? '$latitude,$longitude'
            : null));
    final colors = Theme.of(context).colorScheme;
    if (!recorded || point == null) {
      return Text(recorded ? 'Lokasi belum tersedia' : 'Lokasi belum tercatat',
          style: TextStyle(color: colors.onSurfaceVariant, fontSize: 12));
    }
    return Material(
        color: Colors.transparent,
        child: InkWell(
            borderRadius: BorderRadius.circular(8),
            onTap: () => showModalBottomSheet<void>(
                context: context,
                showDragHandle: true,
                isScrollControlled: true,
                builder: (context) => SafeArea(
                    child: SingleChildScrollView(
                        padding: const EdgeInsets.fromLTRB(24, 0, 24, 24),
                        child: Column(
                            crossAxisAlignment: CrossAxisAlignment.stretch,
                            mainAxisSize: MainAxisSize.min,
                            children: [
                              Text(
                                  isAttendance
                                      ? 'Lokasi masuk'
                                      : 'Lokasi pulang',
                                  style:
                                      Theme.of(context).textTheme.titleLarge),
                              const SizedBox(height: 12),
                              const Text(
                                  'Koordinat yang tersimpan pada catatan kehadiran.'),
                              const SizedBox(height: 16),
                              Text('Lintang: ${point.latitude}'),
                              Text('Bujur: ${point.longitude}'),
                              const SizedBox(height: 20),
                              FilledButton(
                                  onPressed: () {
                                    Navigator.pop(context);
                                    Navigator.of(pageContext).push(
                                        MaterialPageRoute<void>(
                                            builder: (_) => LocationPage(
                                                latitude: point.latitude,
                                                longitude: point.longitude)));
                                  },
                                  child: const Text('Lihat di peta')),
                            ])))),
            child: Padding(
                padding: const EdgeInsets.symmetric(vertical: 10),
                child: Row(children: [
                  Icon(Icons.location_on_outlined,
                      size: 19, color: colors.primary),
                  const SizedBox(width: 8),
                  Expanded(
                      child: Text('Lokasi tercatat',
                          style:
                              TextStyle(color: colors.primary, fontSize: 13))),
                  Icon(Icons.chevron_right,
                      size: 18, color: colors.onSurfaceVariant)
                ]))));
  }
}
