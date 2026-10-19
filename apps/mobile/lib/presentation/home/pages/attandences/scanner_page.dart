// ignore_for_file: public_member_api_docs, sort_constructors_first
import 'dart:async';
import 'dart:developer';

import 'package:flutter/material.dart';
import 'package:flutter_absensi_app/presentation/home/pages/main_page.dart';

import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:intl/intl.dart';
import 'package:mobile_scanner/mobile_scanner.dart';

import 'package:flutter_absensi_app/core/core.dart';

import '../../bloc/check_qr/check_qr_bloc.dart';
import '../../bloc/get_qrcode_checkin/get_qrcode_checkin_bloc.dart';
import '../../bloc/get_qrcode_checkout/get_qrcode_checkout_bloc.dart';
import 'attendance_result_page.dart';

class ScannerPage extends StatefulWidget {
  final bool isCheckin;
  const ScannerPage({
    super.key,
    required this.isCheckin,
  });

  @override
  State<ScannerPage> createState() => _ScannerPageState();
}

class _ScannerPageState extends State<ScannerPage> with WidgetsBindingObserver {
  MobileScannerController cameraController = MobileScannerController(
    autoStart: true,
    torchEnabled: false,
  );

  Barcode? _barcode;
  bool isScan = false;
  StreamSubscription<Object?>? _subscription;

  Widget _buildBarcode(Barcode? value) {
    if (value == null) {
      return const Text(
        'Scan something!',
        overflow: TextOverflow.fade,
        style: TextStyle(color: Colors.white),
      );
    }

    return Text(
      value.displayValue ?? 'No display value.',
      overflow: TextOverflow.fade,
      style: const TextStyle(color: Colors.white),
    );
  }

  void _handleBarcode(BarcodeCapture barcodes) {
    if (mounted) {
      // setState(() {
      _barcode = barcodes.barcodes.firstOrNull;

      if (_barcode != null && !isScan) {
        context.read<GetQrcodeCheckinBloc>().add(
            GetQrcodeCheckinEvent.getQrcodeCheckin(
                _barcode!.displayValue ?? '', widget.isCheckin));

        // log("BarCode Value: ${_barcode?.displayValue}");
        //back
        setState(() {
          isScan = true;
        });
        // context.pushReplacement(MainPage());
      }
      // });
    }
  }

  @override
  void initState() {
    super.initState();
    // WidgetsBinding.instance.addObserver(this);

    cameraController.start();
    _subscription = cameraController.barcodes.listen(_handleBarcode);
  }

  @override
  Future<void> dispose() async {
    WidgetsBinding.instance.removeObserver(this);
    unawaited(_subscription?.cancel());
    _subscription = null;
    super.dispose();
    await cameraController.dispose();
  }

  // @override
  // void didChangeAppLifecycleState(AppLifecycleState state) {
  //   if (!cameraController.value.isInitialized) {
  //     return;
  //   }

  //   switch (state) {
  //     case AppLifecycleState.detached:
  //     case AppLifecycleState.hidden:
  //     case AppLifecycleState.paused:
  //       return;
  //     case AppLifecycleState.resumed:
  //       _subscription = cameraController.barcodes.listen(_handleBarcode);

  //       unawaited(cameraController.start());
  //     case AppLifecycleState.inactive:
  //       unawaited(_subscription?.cancel());
  //       _subscription = null;
  //       unawaited(cameraController.stop());
  //   }
  // }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        leading: IconButton(
          icon: const Icon(Icons.arrow_back_ios),
          onPressed: () {
            cameraController.dispose();
            Navigator.pop(context);
          },
        ),
        title: const Text('Scanning'),
        actions: [
          IconButton(
            color: Colors.white,
            icon: ValueListenableBuilder(
              valueListenable: cameraController,
              builder: (context, state, child) {
                switch (state.torchState) {
                  case TorchState.auto:
                    return IconButton(
                      color: Colors.white,
                      iconSize: 32.0,
                      icon: const Icon(Icons.flash_auto),
                      onPressed: () async {
                        await cameraController.toggleTorch();
                      },
                    );
                  case TorchState.off:
                    return const Icon(Icons.flash_off, color: Colors.grey);
                  case TorchState.on:
                    return const Icon(Icons.flash_on, color: Colors.yellow);
                  case TorchState.unavailable:
                    return const Icon(
                      Icons.no_flash,
                      color: Colors.grey,
                    );
                }
              },
            ),
            iconSize: 32.0,
            onPressed: () => cameraController.toggleTorch(),
          ),
          IconButton(
            color: Colors.white,
            icon: ValueListenableBuilder(
              valueListenable: cameraController,
              builder: (context, state, child) {
                if (!state.isInitialized || !state.isRunning) {
                  return const SizedBox.shrink();
                }

                final int? availableCameras = state.availableCameras;

                if (availableCameras != null && availableCameras < 2) {
                  return const SizedBox.shrink();
                }

                final Widget icon;

                switch (state.cameraDirection) {
                  case CameraFacing.front:
                    icon = const Icon(Icons.camera_front);
                  case CameraFacing.back:
                    icon = const Icon(Icons.camera_rear);
                  case CameraFacing.external:
                    icon = const Icon(Icons.camera_rear);
                  case CameraFacing.unknown:
                    icon = const Icon(Icons.camera);
                }

                return IconButton(
                  iconSize: 32.0,
                  icon: icon,
                  onPressed: () async {
                    await cameraController.switchCamera();
                  },
                );
              },
            ),
            iconSize: 32.0,
            onPressed: () => cameraController.switchCamera(),
          ),
        ],
      ),
      body: Stack(
        children: [
          MobileScanner(
            controller: cameraController,
          ),
          MultiBlocListener(
            listeners: [
              BlocListener<GetQrcodeCheckinBloc, GetQrcodeCheckinState>(
                listener: (context, state) {
                  state.maybeWhen(
                    orElse: () {},
                    success: (data, isCheckin) {
                      final now = DateTime.now();
                      String formattedDate =
                          DateFormat('yyyy-MM-dd').format(now);
                      context.read<CheckQrBloc>().add(CheckQrEvent.checkQr(
                          data,
                          formattedDate,
                          isCheckin ? 'qr_checkin' : 'qr_checkout'));
                    },
                  );
                },
              ),
              BlocListener<CheckQrBloc, CheckQrState>(
                listener: (context, state) {
                  state.maybeWhen(
                    orElse: () {},
                    error: (error) {
                      setState(() {
                        isScan = false;
                      });
                      context.pushReplacement(AttendanceResultPage(
                        isMatch: false,
                        isCheckin: widget.isCheckin,
                        attendanceType: 'QR',
                      ));
                      context
                          .read<GetQrcodeCheckinBloc>()
                          .add(GetQrcodeCheckinEvent.started());
                      context.read<CheckQrBloc>().add(CheckQrEvent.started());
                    },
                    success: (isValid) {
                      context.pushReplacement(AttendanceResultPage(
                        isMatch: true,
                        isCheckin: widget.isCheckin,
                        attendanceType: 'QR',
                      ));
                    },
                  );
                },
              ),
            ],
            child: Align(
              alignment: Alignment.bottomCenter,
              child: Container(
                padding: const EdgeInsets.all(16.0),
                color: Colors.black.withOpacity(0.5),
                child: _buildBarcode(_barcode),
              ),
            ),
          ),
        ],
      ),
    );
  }
}
