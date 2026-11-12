import 'package:flutter/material.dart';
import 'package:flutter_map/flutter_map.dart';
import 'package:latlong2/latlong.dart';
import '../models/history_presentation.dart';

class LocationPage extends StatelessWidget {
  const LocationPage({super.key, this.latitude, this.longitude});
  final double? latitude, longitude;
  @override
  Widget build(BuildContext context) {
    final point = HistoryCoordinates.parse(
        latitude != null && longitude != null ? '$latitude,$longitude' : null);
    return Scaffold(
        appBar: AppBar(title: const Text('Lokasi tercatat')),
        body: SafeArea(
            child: point == null
                ? const Center(
                    child: Padding(
                        padding: EdgeInsets.all(24),
                        child: Text(
                            'Koordinat catatan belum tersedia atau tidak valid.')))
                : Column(children: [
                    Expanded(
                        child: FlutterMap(
                            options: MapOptions(
                                initialCenter:
                                    LatLng(point.latitude, point.longitude),
                                initialZoom: 17),
                            children: [
                          TileLayer(
                              urlTemplate:
                                  'https://tile.openstreetmap.org/{z}/{x}/{y}.png',
                              userAgentPackageName: 'com.naltech.attendance'),
                          MarkerLayer(markers: [
                            Marker(
                                point: LatLng(point.latitude, point.longitude),
                                width: 48,
                                height: 48,
                                child: Icon(Icons.location_on,
                                    color:
                                        Theme.of(context).colorScheme.primary,
                                    size: 40))
                          ]),
                        ])),
                    const Padding(
                        padding: EdgeInsets.all(12),
                        child: Text(
                            '© OpenStreetMap contributors • Titik lokasi yang tersimpan',
                            textAlign: TextAlign.center))
                  ])));
  }
}
