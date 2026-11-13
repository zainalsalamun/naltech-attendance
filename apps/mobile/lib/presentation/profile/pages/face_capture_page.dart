import 'dart:io';
import 'package:camera/camera.dart';
import 'package:flutter/material.dart';
import 'package:google_mlkit_face_detection/google_mlkit_face_detection.dart';

class FaceCapturePage extends StatefulWidget {
  const FaceCapturePage({super.key});
  @override
  State<FaceCapturePage> createState() => _FaceCapturePageState();
}

class _FaceCapturePageState extends State<FaceCapturePage>
    with WidgetsBindingObserver {
  CameraController? _camera;
  static Future<void> _operation = Future<void>.value();
  int _generation = 0;
  bool _active = true, _busy = false;
  String? _error;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
    _open();
  }

  void _open() {
    final generation = ++_generation;
    _enqueue(() async {
      if (!mounted || !_active || generation != _generation) return;
      CameraController? candidate;
      try {
        final cameras = await availableCameras();
        final front = cameras.firstWhere(
            (camera) => camera.lensDirection == CameraLensDirection.front);
        candidate = CameraController(front, ResolutionPreset.medium,
            enableAudio: false);
        await candidate.initialize();
        if (!mounted || !_active || generation != _generation) {
          await candidate.dispose();
          return;
        }
        setState(() {
          _camera = candidate;
          _error = null;
        });
      } catch (_) {
        await candidate?.dispose();
        if (mounted && generation == _generation) {
          setState(() => _error =
              'Kamera depan tidak tersedia. Periksa izin kamera lalu buka kembali halaman ini.');
        }
      }
    });
  }

  void _enqueue(Future<void> Function() task) {
    _operation = _operation.then((_) => task()).catchError((Object _) {
      if (mounted) {
        setState(() =>
            _error = 'Kamera belum siap. Tutup halaman ini dan coba lagi.');
      }
    });
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    _active = state == AppLifecycleState.resumed;
    if (!_active) {
      _generation++;
      final previous = _camera;
      _camera = null;
      _enqueue(() async {
        await previous?.dispose();
      });
      if (mounted) setState(() {});
    } else if (_camera == null) {
      _open();
    }
  }

  Future<void> _take() async {
    final camera = _camera;
    if (_busy || camera == null) return;
    setState(() {
      _busy = true;
      _error = null;
    });
    final generation = _generation;
    XFile? photo;
    var transferred = false;
    final detector = FaceDetector(
        options: FaceDetectorOptions(performanceMode: FaceDetectorMode.fast));
    try {
      photo = await camera.takePicture();
      final faces =
          await detector.processImage(InputImage.fromFilePath(photo.path));
      if (faces.length != 1 || minFaceSize(faces.single) < 80) {
        if (mounted) {
          setState(() => _error =
              'Pastikan hanya satu wajah terlihat jelas dan cukup dekat. Ambil foto lagi.');
        }
        return;
      }
      if (!mounted || !_active || generation != _generation) return;
      transferred = true;
      Navigator.of(context).pop(photo);
    } catch (_) {
      if (mounted) {
        setState(() => _error = 'Foto belum dapat diambil. Coba lagi.');
      }
    } finally {
      try {
        await detector.close();
      } catch (_) {
        if (mounted && !transferred) {
          setState(() => _error = 'Pemeriksaan foto belum selesai. Coba lagi.');
        }
      } finally {
        if (!transferred && photo != null) {
          try {
            await File(photo.path).delete();
          } catch (_) {/* OS cache cleanup remains available. */}
        }
        if (mounted) setState(() => _busy = false);
      }
    }
  }

  double minFaceSize(Face face) =>
      face.boundingBox.width < face.boundingBox.height
          ? face.boundingBox.width
          : face.boundingBox.height;

  @override
  void dispose() {
    _generation++;
    WidgetsBinding.instance.removeObserver(this);
    final previous = _camera;
    _camera = null;
    _enqueue(() async {
      await previous?.dispose();
    });
    super.dispose();
  }

  @override
  Widget build(BuildContext context) => Scaffold(
        appBar: AppBar(title: const Text('Ambil foto wajah')),
        body: SafeArea(
            child: ListView(padding: const EdgeInsets.all(24), children: [
          const Text('Posisikan wajah di dalam bingkai',
              style: TextStyle(fontSize: 20, fontWeight: FontWeight.w700)),
          const SizedBox(height: 8),
          const Text(
              'Gunakan pencahayaan yang cukup dan pastikan wajah tidak tertutup.'),
          const SizedBox(height: 20),
          if (_camera != null && _camera!.value.isInitialized)
            ClipRRect(
                borderRadius: BorderRadius.circular(20),
                child: AspectRatio(
                    aspectRatio: 3 / 4,
                    child: Stack(fit: StackFit.expand, children: [
                      Center(child: CameraPreview(_camera!)),
                      Center(
                          child: FractionallySizedBox(
                              widthFactor: .8,
                              heightFactor: .8,
                              child: DecoratedBox(
                                  decoration: BoxDecoration(
                                      borderRadius: BorderRadius.circular(200),
                                      border: Border.all(
                                          color: Colors.white, width: 2))))),
                    ])))
          else if (_error == null)
            const Center(child: CircularProgressIndicator()),
          if (_error != null)
            Padding(
                padding: const EdgeInsets.symmetric(vertical: 16),
                child: Text(_error!,
                    style:
                        TextStyle(color: Theme.of(context).colorScheme.error))),
          const SizedBox(height: 20),
          FilledButton.icon(
              onPressed: _busy || _camera == null ? null : _take,
              icon: const Icon(Icons.camera_alt_outlined),
              label: Text(_busy ? 'Memeriksa foto…' : 'Ambil foto')),
          const SizedBox(height: 12),
          const Text(
              'Foto akan ditampilkan untuk Anda periksa sebelum dikirim.'),
        ])),
      );
}
