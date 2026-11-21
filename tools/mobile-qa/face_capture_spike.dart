// Standalone exploration target. No login, attendance API, or identity matching.
import 'dart:io';

import 'package:camera/camera.dart';
import 'package:flutter/material.dart';
import 'package:google_mlkit_face_detection/google_mlkit_face_detection.dart';

void main() {
  WidgetsFlutterBinding.ensureInitialized();
  runApp(
    MaterialApp(
      theme: ThemeData(colorSchemeSeed: const Color(0xFF315BCE)),
      home: const FaceCaptureSpike(),
    ),
  );
}

class FaceCaptureSpike extends StatefulWidget {
  const FaceCaptureSpike({super.key});

  @override
  State<FaceCaptureSpike> createState() => _FaceCaptureSpikeState();
}

class _FaceCaptureSpikeState extends State<FaceCaptureSpike>
    with WidgetsBindingObserver {
  CameraController? _camera;
  bool _busy = false;
  bool _active = true;
  int _generation = 0;
  Future<void> _cameraOperation = Future<void>.value();
  String _status = 'Menyiapkan kamera…';

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
    _queueOpen();
  }

  void _queueOpen() {
    final generation = ++_generation;
    _cameraOperation = _cameraOperation.then((_) => _open(generation));
  }

  Future<void> _open(int generation) async {
    if (!mounted || !_active || generation != _generation) return;
    CameraController? candidate;
    try {
      final cameras = await availableCameras();
      final front = cameras.where(
        (camera) => camera.lensDirection == CameraLensDirection.front,
      );
      if (front.isEmpty) throw StateError('No front camera');
      candidate = CameraController(
        front.first,
        ResolutionPreset.medium,
        enableAudio: false,
      );
      await candidate.initialize();
      if (!mounted || !_active || generation != _generation) {
        await candidate.dispose();
        return;
      }
      setState(() {
        _camera = candidate;
        _status = 'Siap. Foto diperiksa hanya setelah tombol ditekan.';
      });
    } catch (_) {
      await candidate?.dispose();
      if (mounted && generation == _generation) {
        setState(() => _status = 'Kamera tidak tersedia. Periksa izin kamera.');
      }
    }
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    _active = state == AppLifecycleState.resumed;
    if (!_active) {
      _generation++;
      final previous = _camera;
      _camera = null;
      // Serialize camera close/open, including the first permission dialog.
      // Android camera plugins can fail if resumed initialization overlaps close.
      _cameraOperation = _cameraOperation.then((_) async {
        await previous?.dispose();
      });
      if (mounted) setState(() {});
    } else if (_camera == null) {
      _queueOpen();
    }
  }

  Future<void> _capture() async {
    final camera = _camera;
    if (_busy || camera == null || !camera.value.isInitialized) return;
    setState(() => _busy = true);
    XFile? photo;
    final detector = FaceDetector(
      options: FaceDetectorOptions(performanceMode: FaceDetectorMode.fast),
    );
    final total = Stopwatch()..start();
    try {
      photo = await camera.takePicture();
      final captureMs = total.elapsedMilliseconds;
      final detection = Stopwatch()..start();
      final faces = await detector.processImage(
        InputImage.fromFilePath(photo.path),
      );
      detection.stop();
      total.stop();
      if (mounted) {
        setState(() {
          _status =
              'Wajah terdeteksi: ${faces.length}\n'
              'Ambil foto: $captureMs ms\n'
              'Deteksi: ${detection.elapsedMilliseconds} ms\n'
              'Total: ${total.elapsedMilliseconds} ms\n\n'
              'Ini deteksi wajah, bukan verifikasi identitas atau liveness.';
        });
      }
    } catch (_) {
      if (mounted) {
        setState(() => _status = 'Pengambilan atau pemeriksaan foto gagal.');
      }
    } finally {
      try {
        await detector.close();
      } finally {
        if (photo != null) {
          try {
            await File(photo.path).delete();
          } catch (_) {
            if (mounted) {
              setState(
                () => _status =
                    'Pembersihan foto sementara gagal. Hapus data aplikasi QA.',
              );
            }
          }
        }
        if (mounted) setState(() => _busy = false);
      }
    }
  }

  @override
  void dispose() {
    _generation++;
    WidgetsBinding.instance.removeObserver(this);
    final previous = _camera;
    _camera = null;
    _cameraOperation = _cameraOperation.then((_) async {
      await previous?.dispose();
    });
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final camera = _camera;
    return Scaffold(
      appBar: AppBar(title: const Text('Uji selfie · tanpa kirim absensi')),
      body: ListView(
        padding: const EdgeInsets.all(24),
        children: [
          if (camera != null && camera.value.isInitialized)
            AspectRatio(
              aspectRatio: camera.value.aspectRatio,
              child: CameraPreview(camera),
            ),
          const SizedBox(height: 20),
          Text(_status, key: const ValueKey('face-spike-status')),
          const SizedBox(height: 20),
          FilledButton(
            key: const ValueKey('face-spike-capture'),
            onPressed: _busy || camera == null ? null : _capture,
            child: Text(_busy ? 'Memeriksa…' : 'Ambil foto dan ukur'),
          ),
        ],
      ),
    );
  }
}
