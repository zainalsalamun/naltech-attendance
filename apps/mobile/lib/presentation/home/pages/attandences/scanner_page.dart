import 'dart:async';
import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:geolocator/geolocator.dart';
import 'package:mobile_scanner/mobile_scanner.dart';
import '../../../../data/datasources/attendance_qr_proof.dart';
import '../../../../data/models/response/qr_absen_remote_datasource.dart';
import '../../bloc/qr_scan/qr_scan_cubit.dart';
import 'attendance_result_page.dart';

class ScannerPage extends StatefulWidget {
  const ScannerPage({super.key, required this.isCheckin, this.serverDate});
  final bool isCheckin;
  final String? serverDate;
  @override
  State<ScannerPage> createState() => _ScannerPageState();
}

class _ScannerPageState extends State<ScannerPage> with WidgetsBindingObserver {
  final _camera = MobileScannerController(
      autoStart: false, formats: const [BarcodeFormat.qrCode]);
  late final _scan =
      QrScanCubit(QrAbsenRemoteDatasource(), serverDate: widget.serverDate);
  bool _delivered = false;
  String? _cameraError;
  bool _starting = false;
  bool _active = true;
  @override
  void initState() {
    super.initState();
    AttendanceQrProof.clear();
    WidgetsBinding.instance.addObserver(this);
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (mounted) {
        _start();
      }
    });
  }

  Future<void> _start() async {
    if (_camera.value.isRunning ||
        _starting ||
        !_active ||
        _delivered ||
        _scan.state.busy ||
        _scan.state.error != null) {
      return;
    }
    _starting = true;
    try {
      await _camera.start();
      if (!mounted || !_active) {
        await _stop();
        return;
      }
      if (mounted) setState(() => _cameraError = null);
    } catch (_) {
      if (mounted) {
        setState(() => _cameraError =
            'Kamera belum tersedia. Periksa izin kamera dan coba lagi.');
      }
    } finally {
      _starting = false;
    }
  }

  Future<void> _stop() async {
    try {
      await _camera.stop();
    } catch (_) {/* Camera may already be stopped by the platform. */}
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    _active = state == AppLifecycleState.resumed;
    if (_active) {
      _start();
    } else {
      _stop();
    }
  }

  Future<void> _detect(BarcodeCapture capture) async {
    if (!_active ||
        _delivered ||
        _scan.state.busy ||
        _scan.state.error != null) {
      return;
    }
    final code = capture.barcodes.firstOrNull?.rawValue;
    if (code == null) {
      return;
    }
    final pending = _scan.scan(code, widget.isCheckin);
    await _stop();
    final valid = await pending;
    if (!mounted || !valid || _delivered) {
      return;
    }
    _delivered = true;
    Navigator.of(context).pushReplacement(MaterialPageRoute<void>(
        builder: (_) => AttendanceResultPage(
            isCheckin: widget.isCheckin, isMatch: true, attendanceType: 'QR')));
  }

  @override
  void dispose() {
    WidgetsBinding.instance.removeObserver(this);
    if (!_delivered) {
      AttendanceQrProof.clear();
    }
    _scan.close();
    unawaited(_camera.dispose());
    super.dispose();
  }

  @override
  Widget build(BuildContext context) => Scaffold(
        appBar: AppBar(
            title:
                Text(widget.isCheckin ? 'QR absen masuk' : 'QR absen pulang'),
            actions: [
              ValueListenableBuilder(
                  valueListenable: _camera,
                  builder: (context, value, _) => IconButton(
                      tooltip: 'Lampu kamera',
                      onPressed: value.isRunning
                          ? () async {
                              try {
                                await _camera.toggleTorch();
                              } catch (_) {
                                if (context.mounted) {
                                  ScaffoldMessenger.of(context).showSnackBar(
                                      const SnackBar(
                                          content: Text(
                                              'Lampu kamera tidak tersedia.')));
                                }
                              }
                            }
                          : null,
                      icon: const Icon(Icons.flashlight_on_outlined))),
            ]),
        body: SafeArea(
            child: BlocBuilder<QrScanCubit, QrScanState>(
                bloc: _scan,
                builder: (context, state) =>
                    ListView(padding: const EdgeInsets.all(20), children: [
                      Text('Pindai QR di kantor',
                          style: Theme.of(context)
                              .textTheme
                              .headlineSmall
                              ?.copyWith(fontWeight: FontWeight.w700)),
                      const SizedBox(height: 10),
                      const Text(
                          'Arahkan kamera ke QR sesuai jenis absensi. Pastikan kode terlihat jelas dan Anda berada di area kantor.'),
                      const SizedBox(height: 20),
                      ClipRRect(
                          borderRadius: BorderRadius.circular(20),
                          child: AspectRatio(
                              aspectRatio: 1,
                              child: Stack(fit: StackFit.expand, children: [
                                MobileScanner(
                                    controller: _camera,
                                    onDetect: _detect,
                                    errorBuilder: (context, error) => ColoredBox(
                                        color: const Color(0xFF192A48),
                                        child: Center(
                                            child: Padding(
                                                padding:
                                                    const EdgeInsets.all(24),
                                                child: Text(
                                                    error.errorCode ==
                                                            MobileScannerErrorCode
                                                                .permissionDenied
                                                        ? 'Izin kamera ditolak. Buka pengaturan aplikasi untuk mengizinkan kamera.'
                                                        : 'Kamera belum tersedia pada perangkat ini.',
                                                    textAlign: TextAlign.center,
                                                    style: const TextStyle(
                                                        color:
                                                            Colors.white)))))),
                                IgnorePointer(
                                    child: Center(
                                        child: FractionallySizedBox(
                                            widthFactor: .7,
                                            heightFactor: .7,
                                            child: DecoratedBox(
                                                decoration: BoxDecoration(
                                                    border: Border.all(
                                                        color: Colors.white,
                                                        width: 2),
                                                    borderRadius:
                                                        BorderRadius.circular(
                                                            16)))))),
                                if (state.busy)
                                  const ColoredBox(
                                      color: Color(0xAA192A48),
                                      child: Center(
                                          child: CircularProgressIndicator(
                                              semanticsLabel: 'Memeriksa QR'))),
                              ]))),
                      const SizedBox(height: 20),
                      Semantics(
                          liveRegion: true,
                          child: Text(
                              state.busy
                                  ? 'Memeriksa QR…'
                                  : state.error ??
                                      _cameraError ??
                                      'Setelah QR sesuai, lanjutkan konfirmasi lokasi untuk mencatat absensi.',
                              style: TextStyle(
                                  color: state.error == null
                                      ? Theme.of(context)
                                          .colorScheme
                                          .onSurfaceVariant
                                      : Theme.of(context).colorScheme.error))),
                      const SizedBox(height: 16),
                      if (state.error != null || _cameraError != null)
                        FilledButton(
                            onPressed: state.busy
                                ? null
                                : () {
                                    _scan.retry();
                                    _start();
                                  },
                            child: const Text('Coba pindai lagi')),
                      OutlinedButton(
                          onPressed:
                              state.busy ? null : Geolocator.openAppSettings,
                          child: const Text('Pengaturan izin kamera')),
                      TextButton(
                          onPressed: state.busy
                              ? null
                              : () => Navigator.of(context).pop(),
                          child: const Text('Batal')),
                    ]))),
      );
}
