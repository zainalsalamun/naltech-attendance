import 'package:flutter/material.dart';
import 'package:flutter_map/flutter_map.dart';
import 'package:latlong2/latlong.dart';

import '../../../core/core.dart';

class LocationPage extends StatefulWidget {
  final double? latitude;
  final double? longitude;
  const LocationPage({
    super.key,
    this.latitude,
    this.longitude,
  });

  @override
  State<LocationPage> createState() => _LocationPageState();
}

class _LocationPageState extends State<LocationPage> {
  @override
  Widget build(BuildContext context) {
    LatLng center = LatLng(widget.latitude ?? 0, widget.longitude ?? 0);

    return Scaffold(
      body: Stack(
        children: [
          SizedBox(
            child: widget.latitude == null
                ? const Center(
                    child: CircularProgressIndicator(
                      color: AppColors.primary,
                    ),
                  )
                : FlutterMap(
                    options: MapOptions(
                      initialCenter: center,
                      initialZoom: 18.0,
                    ),
                    children: [
                      TileLayer(
                        urlTemplate:
                            'https://tile.openstreetmap.org/{z}/{x}/{y}.png',
                        userAgentPackageName: 'com.naltech.attendance',
                      ),
                      MarkerLayer(
                        markers: [
                          Marker(
                            point: center,
                            width: 80,
                            height: 80,
                            child: const Icon(
                              Icons.location_on,
                              color: Colors.red,
                              size: 40,
                            ),
                          ),
                        ],
                      ),
                    ],
                  ),
          ),
          Padding(
            padding: const EdgeInsets.symmetric(
              horizontal: 20.0,
              vertical: 50.0,
            ),
            child: GestureDetector(
              onTap: () => context.pop(),
              child: Container(
                padding: const EdgeInsets.all(8),
                decoration: const BoxDecoration(
                  color: Colors.white,
                  shape: BoxShape.circle,
                  boxShadow: [
                    BoxShadow(
                      color: Colors.black26,
                      blurRadius: 10,
                    )
                  ],
                ),
                child: const Icon(Icons.arrow_back, color: AppColors.black),
              ),
            ),
          ),
        ],
      ),
    );
  }
}
