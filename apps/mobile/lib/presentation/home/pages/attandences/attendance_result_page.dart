import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:geolocator/geolocator.dart';
import '../../../../data/datasources/auth_local_datasource.dart';
import '../../../../data/datasources/attendance_qr_proof.dart';
import '../../../../data/models/response/checkinout_response_model.dart';
import '../../bloc/workspace_home/workspace_home_cubit.dart';
import '../../bloc/checkin_attendance/checkin_attendance_bloc.dart';
import '../../bloc/checkout_attendance/checkout_attendance_bloc.dart';
import '../attendance_success_page.dart';
import 'scanner_page.dart';

class AttendanceResultPage extends StatefulWidget {
  const AttendanceResultPage(
      {super.key,
      required this.isCheckin,
      required this.isMatch,
      required this.attendanceType,
      this.locationService});
  final bool isCheckin, isMatch;
  final String attendanceType;
  final HomeLocationService? locationService;
  @override
  State<AttendanceResultPage> createState() => _AttendanceResultPageState();
}

class _AttendanceResultPageState extends State<AttendanceResultPage> {
  bool _busy = false, _completed = false;
  String? _error;
  LocationState _location = LocationState.idle;
  bool get _supported =>
      ['none', 'qr'].contains(widget.attendanceType.toLowerCase()) &&
      widget.isMatch;
  bool get _qr => widget.attendanceType.toLowerCase() == 'qr';
  @override
  void initState() {
    super.initState();
    if (_supported) _prepare();
  }

  Future<LocationReading?> _readLocation() async {
    try {
      final reading = await (widget.locationService ?? HomeLocationService())
          .read()
          .timeout(const Duration(seconds: 20));
      if (!mounted) return null;
      setState(() => _location = reading.state);
      if (reading.state != LocationState.inside ||
          reading.latitude == null ||
          reading.longitude == null ||
          !reading.latitude!.isFinite ||
          !reading.longitude!.isFinite ||
          reading.latitude!.abs() > 90 ||
          reading.longitude!.abs() > 180) {
        return null;
      }
      return reading;
    } catch (_) {
      if (mounted) setState(() => _location = LocationState.failed);
      return null;
    }
  }

  Future<void> _prepare() async {
    setState(() {
      _busy = true;
      _location = LocationState.loading;
    });
    await _readLocation();
    if (mounted) setState(() => _busy = false);
  }

  Future<void> _submit() async {
    if (_busy || _completed || !_supported) return;
    setState(() {
      _busy = true;
      _error = null;
      _location = LocationState.loading;
    });
    final reading = await _readLocation();
    if (!mounted) return;
    if (reading == null) {
      setState(() => _busy = false);
      return;
    }
    if (_qr) {
      try {
        final auth = await AuthLocalDatasource().getAuthData();
        if (!mounted) return;
        if (AttendanceQrProof.codeFor(
                auth?.token, widget.isCheckin ? 'qr_checkin' : 'qr_checkout') ==
            null) {
          setState(() {
            _busy = false;
            _error =
                'QR sudah tidak tersedia. Pindai ulang sebelum melanjutkan.';
          });
          return;
        }
      } catch (_) {
        if (mounted) {
          setState(() {
            _busy = false;
            _error =
                'Sesi tidak tersedia. Kembali ke Beranda dan masuk kembali.';
          });
        }
        return;
      }
    }
    if (!mounted) return;
    if (widget.isCheckin) {
      context.read<CheckinAttendanceBloc>().add(CheckinAttendanceEvent.checkin(
          reading.latitude.toString(), reading.longitude.toString()));
    } else {
      context.read<CheckoutAttendanceBloc>().add(
          CheckoutAttendanceEvent.checkout(
              reading.latitude.toString(), reading.longitude.toString()));
    }
  }

  void _failed(String message) {
    if (mounted) {
      setState(() {
        _busy = false;
        _error = message;
      });
    }
  }

  void _success(CheckInOutResponseModel response) {
    if (_completed) return;
    _completed = true;
    Navigator.of(context).pushReplacement(MaterialPageRoute<void>(
        builder: (_) => AttendanceSuccessPage(
            status: widget.isCheckin ? 'Absen masuk' : 'Absen pulang',
            isCheckin: widget.isCheckin,
            response: response)));
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final label = widget.isCheckin ? 'Absen masuk' : 'Absen pulang';
    final locationLabel = switch (_location) {
      LocationState.loading => 'Memeriksa lokasi…',
      LocationState.inside => 'Lokasi siap diperiksa',
      LocationState.disabled => 'Layanan lokasi mati',
      LocationState.denied => 'Izin lokasi ditolak',
      LocationState.deniedForever => 'Izin lokasi diblokir',
      _ => 'Lokasi belum dapat diperoleh',
    };
    return MultiBlocListener(
        listeners: [
          BlocListener<CheckinAttendanceBloc, CheckinAttendanceState>(
              listener: (_, state) {
            if (widget.isCheckin && _busy) {
              state.maybeWhen(loaded: _success, error: _failed, orElse: () {});
            }
          }),
          BlocListener<CheckoutAttendanceBloc, CheckoutAttendanceState>(
              listener: (_, state) {
            if (!widget.isCheckin && _busy) {
              state.maybeWhen(loaded: _success, error: _failed, orElse: () {});
            }
          }),
        ],
        child: PopScope(
            canPop: !_busy,
            child: Scaffold(
              appBar: AppBar(title: Text(label)),
              body: SafeArea(
                  child: ListView(padding: const EdgeInsets.all(24), children: [
                Container(
                    padding: const EdgeInsets.all(22),
                    decoration: BoxDecoration(
                        color: const Color(0xFF192A48),
                        borderRadius: BorderRadius.circular(16)),
                    child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Icon(
                              _qr
                                  ? Icons.qr_code_scanner
                                  : Icons.location_on_outlined,
                              color: Colors.white,
                              size: 34),
                          const SizedBox(height: 18),
                          Text('Konfirmasi kehadiran',
                              style: theme.textTheme.headlineSmall?.copyWith(
                                  color: Colors.white,
                                  fontWeight: FontWeight.w700)),
                          const SizedBox(height: 10),
                          Text(
                              _qr
                                  ? 'QR sudah diperiksa. Konfirmasi lokasi untuk mencatat kehadiran.'
                                  : 'Gunakan lokasi Anda saat ini untuk mencatat kehadiran.',
                              style: const TextStyle(color: Color(0xFFB9C6DC))),
                        ])),
                const SizedBox(height: 24),
                if (!_supported)
                  Text(
                      widget.attendanceType.toLowerCase() == 'face'
                          ? 'Absensi wajah belum tersedia. Hubungi pengelola perusahaan.'
                          : 'QR belum valid. Pindai ulang sebelum melanjutkan.',
                      style: TextStyle(color: theme.colorScheme.error))
                else ...[
                  Text('Lokasi perangkat', style: theme.textTheme.titleMedium),
                  const SizedBox(height: 10),
                  Semantics(liveRegion: true, child: Text(locationLabel)),
                  const SizedBox(height: 12),
                  if (_location == LocationState.disabled)
                    OutlinedButton(
                        onPressed:
                            _busy ? null : Geolocator.openLocationSettings,
                        child: const Text('Buka pengaturan lokasi'))
                  else if (_location == LocationState.deniedForever)
                    OutlinedButton(
                        onPressed: _busy ? null : Geolocator.openAppSettings,
                        child: const Text('Buka pengaturan aplikasi')),
                  const Text(
                      'Lokasi dan aturan kantor diperiksa saat absensi dikirim. Absensi belum tercatat sebelum ada konfirmasi.'),
                ],
                if (_error != null) ...[
                  const SizedBox(height: 20),
                  Semantics(
                      liveRegion: true,
                      child: Text(_error!,
                          style: TextStyle(color: theme.colorScheme.error)))
                ],
                const SizedBox(height: 28),
                FilledButton(
                    onPressed: _busy || !_supported ? null : _submit,
                    child: _busy
                        ? const Row(mainAxisSize: MainAxisSize.min, children: [
                            SizedBox(
                                width: 18,
                                height: 18,
                                child:
                                    CircularProgressIndicator(strokeWidth: 2)),
                            SizedBox(width: 12),
                            Flexible(child: Text('Memproses…'))
                          ])
                        : Text('Konfirmasi $label')),
                if (_qr) ...[
                  const SizedBox(height: 12),
                  OutlinedButton(
                      onPressed: _busy
                          ? null
                          : () => Navigator.of(context).pushReplacement(
                              MaterialPageRoute<void>(
                                  builder: (_) => ScannerPage(
                                      isCheckin: widget.isCheckin))),
                      child: const Text('Pindai ulang QR'))
                ],
                const SizedBox(height: 12),
                TextButton(
                    onPressed: _busy
                        ? null
                        : () => Navigator.of(context)
                            .popUntil((route) => route.isFirst),
                    child: const Text('Kembali ke Beranda')),
              ])),
            )));
  }
}
