import 'dart:io';

import 'package:camera/camera.dart';
import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_absensi_app/core/ml/recognition_embedding.dart';
import 'package:flutter_absensi_app/core/ml/recognizer.dart';
import 'package:flutter_absensi_app/presentation/home/bloc/checkin_attendance/checkin_attendance_bloc.dart';
import 'package:flutter_absensi_app/presentation/home/pages/attendance_success_page.dart';
import 'package:flutter_absensi_app/presentation/home/pages/location_page.dart';
import 'package:flutter_absensi_app/presentation/home/widgets/face_detector_painter.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:google_mlkit_face_detection/google_mlkit_face_detection.dart';
import 'package:image/image.dart' as img;
import 'package:geolocator/geolocator.dart';

import '../../../core/core.dart';

class AttendanceCheckinPage extends StatefulWidget {
  const AttendanceCheckinPage({super.key});

  @override
  State<AttendanceCheckinPage> createState() => _AttendanceCheckinPageState();
}

class _AttendanceCheckinPageState extends State<AttendanceCheckinPage> {
  List<CameraDescription>? _availableCameras;
  late CameraDescription description = _availableCameras![1];
  CameraController? _controller;
  bool isBusy = false;
  late List<RecognitionEmbedding> recognitions = [];
  late Size size;
  CameraLensDirection camDirec = CameraLensDirection.front;
  bool isFaceRegistered = false;
  String faceStatusMessage = '';

  //TODO declare face detectore
  late FaceDetector detector;

  //TODO declare face recognizer
  late Recognizer recognizer;

  @override
  void initState() {
    super.initState();

    //TODO initialize face detector
    detector = FaceDetector(
        options: FaceDetectorOptions(performanceMode: FaceDetectorMode.fast));

    //TODO initialize face recognizer
    recognizer = Recognizer();

    _initializeCamera();

    getCurrentPosition();
  }

  _initializeCamera() async {
    _availableCameras = await availableCameras();
    _controller = CameraController(
      description,
      ResolutionPreset.high,
      enableAudio: false,
      imageFormatGroup: Platform.isAndroid
          ? ImageFormatGroup.nv21
          : ImageFormatGroup.bgra8888,
    );
    await _controller!.initialize().then((_) {
      if (!mounted) {
        return;
      }
      size = _controller!.value.previewSize!;

      _controller!.startImageStream((image) {
        if (!isBusy) {
          isBusy = true;
          frame = image;
          doFaceDetectionOnFrame();
        }
      });
    });
  }

  //TODO face detection on a frame
  dynamic _scanResults;
  CameraImage? frame;
  doFaceDetectionOnFrame() async {
    InputImage? inputImage = getInputImage();
    if (inputImage == null) return;

    //TODO pass InputImage to face detection model and detect faces
    List<Face> faces = await detector.processImage(inputImage);

    for (Face face in faces) {
      print("Face location ${face.boundingBox}");
    }

    //TODO perform face recognition on detected faces
    performFaceRecognition(faces);
  }

  img.Image? image;
  bool register = false;
  // TODO perform Face Recognition
  performFaceRecognition(List<Face> faces) async {
    recognitions.clear();

    //TODO convert CameraImage to Image and rotate it so that our frame will be in a portrait
    image = convertToImage(frame!);
    image = img.copyRotate(image!,
        angle: camDirec == CameraLensDirection.front ? 270 : 90);

    for (Face face in faces) {
      Rect faceRect = face.boundingBox;
      //TODO crop face
      img.Image croppedFace = img.copyCrop(image!,
          x: faceRect.left.toInt(),
          y: faceRect.top.toInt(),
          width: faceRect.width.toInt(),
          height: faceRect.height.toInt());

      //TODO pass cropped face to face recognition model
      RecognitionEmbedding recognition =
          recognizer.recognize(croppedFace, face.boundingBox);

      recognitions.add(recognition);

      // Memeriksa validitas wajah yang dikenali
      bool isValid = await recognizer.isValidFace(recognition.embedding);

      // Perbarui status wajah dan pesan teks berdasarkan hasil pengenalan
      if (isValid) {
        setState(() {
          isFaceRegistered = true;
          faceStatusMessage = 'Wajah sudah terdaftar';
        });
      } else {
        setState(() {
          isFaceRegistered = false;
          faceStatusMessage = 'Wajah belum terdaftar';
        });
      }
    }

    setState(() {
      isBusy = false;
      _scanResults = recognitions;
    });
  }

  //ketika absen authdata->face_embedding compare dengan yang dari tflite.

  // TODO method to convert CameraImage to Image
  img.Image convertToImage(CameraImage cameraImage) {
    if (cameraImage.format.group == ImageFormatGroup.bgra8888) {
      return _convertBGRA8888ToImage(cameraImage);
    } else if (cameraImage.format.group == ImageFormatGroup.yuv420 ||
        cameraImage.format.group == ImageFormatGroup.nv21) {
      return _convertYUVToImage(cameraImage);
    }
    return img.Image(width: cameraImage.width, height: cameraImage.height);
  }

  img.Image _convertBGRA8888ToImage(CameraImage cameraImage) {
    final plane = cameraImage.planes[0];
    return img.Image.fromBytes(
      width: cameraImage.width,
      height: cameraImage.height,
      bytes: plane.bytes.buffer,
      order: img.ChannelOrder.bgra,
    );
  }

  img.Image _convertYUVToImage(CameraImage cameraImage) {
    final width = cameraImage.width;
    final height = cameraImage.height;
    final img.Image image = img.Image(width: width, height: height);

    if (cameraImage.format.group == ImageFormatGroup.nv21 ||
        cameraImage.planes.length == 1) {
      // NV21 (Android)
      final bytes = cameraImage.planes[0].bytes;
      for (int y = 0; y < height; y++) {
        for (int x = 0; x < width; x++) {
          final int yIndex = y * width + x;
          final int uvIndex = width * height + (y >> 1) * width + (x & ~1);

          final int yValue = bytes[yIndex];
          final int vValue = bytes[uvIndex];
          final int uValue = bytes[uvIndex + 1];

          image.setPixelR(x, y, yuv2rgb(yValue, uValue, vValue));
        }
      }
    } else {
      // YUV420 (standard)
      final yRowStride = cameraImage.planes[0].bytesPerRow;
      final uvRowStride = cameraImage.planes[1].bytesPerRow;
      final uvPixelStride = cameraImage.planes[1].bytesPerPixel!;

      for (var w = 0; w < width; w++) {
        for (var h = 0; h < height; h++) {
          final uvIndex =
              uvPixelStride * (w / 2).floor() + uvRowStride * (h / 2).floor();
          final yIndex = h * yRowStride + w;

          final y = cameraImage.planes[0].bytes[yIndex];
          final u = cameraImage.planes[1].bytes[uvIndex];
          final v = cameraImage.planes[2].bytes[uvIndex];

          image.setPixelR(w, h, yuv2rgb(y, u, v));
        }
      }
    }
    return image;
  }

  int yuv2rgb(int y, int u, int v) {
    // Convert yuv pixel to rgb
    var r = (y + v * 1436 / 1024 - 179).round();
    var g = (y - u * 46549 / 131072 + 44 - v * 93604 / 131072 + 91).round();
    var b = (y + u * 1814 / 1024 - 227).round();

    // Clipping RGB values to be inside boundaries [ 0 , 255 ]
    r = r.clamp(0, 255);
    g = g.clamp(0, 255);
    b = b.clamp(0, 255);

    return 0xff000000 |
        ((b << 16) & 0xff0000) |
        ((g << 8) & 0xff00) |
        (r & 0xff);
  }

  final _orientations = {
    DeviceOrientation.portraitUp: 0,
    DeviceOrientation.landscapeLeft: 90,
    DeviceOrientation.portraitDown: 180,
    DeviceOrientation.landscapeRight: 270,
  };

  //TODO convert CameraImage to InputImage
  InputImage? getInputImage() {
    final camera = description;
    final sensorOrientation = camera.sensorOrientation;
    InputImageRotation? rotation;
    if (Platform.isIOS) {
      rotation = InputImageRotationValue.fromRawValue(sensorOrientation);
    } else if (Platform.isAndroid) {
      var rotationCompensation =
          _orientations[_controller!.value.deviceOrientation];
      if (rotationCompensation != null) {
        if (camera.lensDirection == CameraLensDirection.front) {
          // front-facing
          rotationCompensation =
              (sensorOrientation + rotationCompensation) % 360;
        } else {
          // back-facing
          rotationCompensation =
              (sensorOrientation - rotationCompensation + 360) % 360;
        }
        rotation = InputImageRotationValue.fromRawValue(rotationCompensation);
      }
    }
    rotation ??= InputImageRotation.rotation0deg;

    final format = InputImageFormatValue.fromRawValue(frame!.format.raw);
    if (format == null) return null;

    if (format == InputImageFormat.nv21 && frame!.planes.length == 1) {
      final plane = frame!.planes.first;
      return InputImage.fromBytes(
        bytes: plane.bytes,
        metadata: InputImageMetadata(
          size: Size(frame!.width.toDouble(), frame!.height.toDouble()),
          rotation: rotation, // used only in Android
          format: format, // used only in iOS
          bytesPerRow: plane.bytesPerRow, // used only in iOS
        ),
      );
    } else {
      final WriteBuffer allBytes = WriteBuffer();
      for (final Plane plane in frame!.planes) {
        allBytes.putUint8List(plane.bytes);
      }
      final bytes = allBytes.done().buffer.asUint8List();

      return InputImage.fromBytes(
        bytes: bytes,
        metadata: InputImageMetadata(
          size: Size(frame!.width.toDouble(), frame!.height.toDouble()),
          rotation: rotation,
          format: format,
          bytesPerRow: frame!.planes.first.bytesPerRow,
        ),
      );
    }
  }

  void _takeAbsen() async {
    if (latitude == null || longitude == null) {
      try {
        final position = await Geolocator.getCurrentPosition(
            desiredAccuracy: LocationAccuracy.best);
        latitude = position.latitude;
        longitude = position.longitude;
      } catch (e) {
        // ignore
      }
    }

    if (latitude == null || longitude == null) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('Lokasi belum ditemukan, pastikan GPS aktif.'),
          backgroundColor: Colors.red,
        ),
      );
      return;
    }
    if (mounted) {
      context.read<CheckinAttendanceBloc>().add(
            CheckinAttendanceEvent.checkin(
                latitude.toString(), longitude.toString()),
          );
    }
  }

  void _reverseCamera() async {
    if (camDirec == CameraLensDirection.back) {
      camDirec = CameraLensDirection.front;
      description = _availableCameras![1];
    } else {
      camDirec = CameraLensDirection.back;
      description = _availableCameras![0];
    }
    await _controller!.stopImageStream();
    setState(() {
      _controller;
    });
    // Inisialisasi kamera dengan deskripsi kamera baru
    _initializeCamera();
  }

  // TODO Show rectangles around detected faces
  Widget buildResult() {
    if (_scanResults == null || !_controller!.value.isInitialized) {
      return const Center(child: Text('Camera is not initialized'));
    }
    final Size imageSize = Size(
      _controller!.value.previewSize!.height,
      _controller!.value.previewSize!.width,
    );
    CustomPainter painter =
        FaceDetectorPainter(imageSize, _scanResults, camDirec);
    return CustomPaint(
      painter: painter,
    );
  }

  double? latitude;
  double? longitude;

  Future<void> getCurrentPosition() async {
    try {
      LocationPermission permission = await Geolocator.checkPermission();
      if (permission == LocationPermission.denied) {
        permission = await Geolocator.requestPermission();
        if (permission == LocationPermission.denied) {
          return;
        }
      }

      if (permission == LocationPermission.deniedForever) {
        return;
      }

      final position = await Geolocator.getCurrentPosition(
          desiredAccuracy: LocationAccuracy.best);
      if (mounted) {
        setState(() {
          latitude = position.latitude;
          longitude = position.longitude;
        });
      }
    } catch (e) {
      debugPrint('Error getting location: $e');
    }
  }

  @override
  Widget build(BuildContext context) {
    size = MediaQuery.of(context).size;
    if (_controller == null || !_controller!.value.isInitialized) {
      return const Scaffold(
        body: Center(child: CircularProgressIndicator()),
      );
    }
    return SafeArea(
      child: Scaffold(
        body: Stack(
          children: [
            Positioned(
              top: 0.0,
              left: 0.0,
              width: size.width,
              height: size.height,
              child: AspectRatio(
                aspectRatio: _controller!.value.aspectRatio,
                child: CameraPreview(_controller!),
              ),
            ),
            Positioned(
                top: 0.0,
                left: 0.0,
                width: size.width,
                height: size.height,
                child: buildResult()),
            Positioned(
              top: 20.0,
              left: 40.0,
              right: 40.0,
              child: Container(
                padding: const EdgeInsets.all(10.0),
                decoration: BoxDecoration(
                  color: isFaceRegistered
                      ? AppColors.primary.withOpacity(0.47)
                      : AppColors.red.withOpacity(0.47),
                  borderRadius: BorderRadius.circular(10.0),
                ),
                child: Text(
                  faceStatusMessage,
                  textAlign: TextAlign.center,
                  style: const TextStyle(color: Colors.white),
                ),
              ),
            ),
            Positioned(
              bottom: 5.0,
              left: 0.0,
              right: 0.0,
              child: Padding(
                padding: const EdgeInsets.all(40.0),
                child: Column(
                  mainAxisAlignment: MainAxisAlignment.end,
                  children: [
                    Container(
                      padding: const EdgeInsets.all(16.0),
                      decoration: BoxDecoration(
                        color: AppColors.primary.withOpacity(0.47),
                        borderRadius: BorderRadius.circular(10.0),
                      ),
                      child: Row(
                        mainAxisAlignment: MainAxisAlignment.spaceBetween,
                        children: [
                          const Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Text(
                                'Absensi Datang',
                                style: TextStyle(
                                  color: AppColors.white,
                                  fontWeight: FontWeight.w700,
                                ),
                              ),
                              Text(
                                'Kantor',
                                style: TextStyle(
                                  color: AppColors.white,
                                ),
                              ),
                            ],
                          ),
                          GestureDetector(
                            onTap: () {
                              context.push(LocationPage(
                                latitude: latitude,
                                longitude: longitude,
                              ));
                            },
                            child:
                                Assets.images.seeLocation.image(height: 30.0),
                          ),
                        ],
                      ),
                    ),
                    const SpaceHeight(15.0),
                    const SpaceHeight(15.0),
                    Row(
                      children: [
                        IconButton(
                          onPressed: _reverseCamera,
                          icon: Assets.icons.reverse.svg(width: 48.0),
                        ),
                        const Spacer(),
                        BlocConsumer<CheckinAttendanceBloc,
                            CheckinAttendanceState>(
                          listener: (context, state) {
                            state.maybeWhen(
                              orElse: () {},
                              error: (message) {
                                ScaffoldMessenger.of(context).showSnackBar(
                                  SnackBar(
                                    content: Text(message),
                                  ),
                                );
                              },
                              loaded: (responseModel) {
                                context.pushReplacement(
                                    const AttendanceSuccessPage(
                                  status: 'Berhasil Checkin',
                                ));
                              },
                            );
                          },
                          builder: (context, state) {
                            return state.maybeWhen(
                              orElse: () {
                                return IconButton(
                                  onPressed:
                                      isFaceRegistered ? _takeAbsen : null,
                                  icon: const Icon(
                                    Icons.circle,
                                    size: 70.0,
                                  ),
                                  color: isFaceRegistered
                                      ? AppColors.red
                                      : AppColors.grey,
                                );
                              },
                              loading: () => const Center(
                                child: CircularProgressIndicator(),
                              ),
                            );
                          },
                        ),
                        const Spacer(),
                        const SpaceWidth(48.0)
                      ],
                    ),
                  ],
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }
}
