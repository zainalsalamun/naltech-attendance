import 'package:camera/camera.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_absensi_app/core/core.dart';
import 'package:flutter_absensi_app/presentation/home/pages/face_detector_painter.dart';
import 'package:flutter_absensi_app/presentation/home/pages/main_page.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:google_mlkit_face_detection/google_mlkit_face_detection.dart';
import 'package:image/image.dart' as img;
import '../../../core/ml/recognition_embedding.dart';
import '../../../core/ml/recognizer.dart';
import '../../../data/datasources/auth_local_datasource.dart';
import '../bloc/update_user_register_face/update_user_register_face_bloc.dart';
import 'detector_view.dart';

class FaceDetectorView extends StatefulWidget {
  const FaceDetectorView({super.key});

  @override
  State<FaceDetectorView> createState() => _FaceDetectorViewState();
}

class _FaceDetectorViewState extends State<FaceDetectorView> {
  final FaceDetector _faceDetector = FaceDetector(
    options: FaceDetectorOptions(
      enableContours: false,
      enableLandmarks: false,
    ),
  );
  bool _canProcess = true;
  bool _isBusy = false;
  CustomPaint? _customPaint;
  String? _text;
  var _cameraLensDirection = CameraLensDirection.front;

  late List<RecognitionEmbedding> recognitions = [];
  CameraImage? frame;
  // CameraLensDirection camDirec = CameraLensDirection.front;
  late Recognizer recognizer;
  bool register = false;

  @override
  void initState() {
    super.initState();
    recognizer = Recognizer();
  }

  @override
  void dispose() {
    _canProcess = false;
    _faceDetector.close();
    super.dispose();
  }

  void _takePicture(CameraImage cameraImage) async {
    // await _controller!.takePicture();
    // if (mounted) {
    //   setState(() {
    //     // register = true;
    //   });
    // }

    setState(() {
      frame = cameraImage;
      register = true;
    });
  }

  img.Image? image;
  performFaceRecognition(List<Face> faces) async {
    recognitions.clear();

    image = convertToImage(frame!);
    image = img.copyRotate(image!,
        angle: _cameraLensDirection == CameraLensDirection.front ? 270 : 90);

    for (Face face in faces) {
      Rect faceRect = face.boundingBox;

      img.Image croppedFace = img.copyCrop(image!,
          x: faceRect.left.toInt(),
          y: faceRect.top.toInt(),
          width: faceRect.width.toInt(),
          height: faceRect.height.toInt());

      RecognitionEmbedding recognition =
          recognizer.recognize(croppedFace, face.boundingBox);

      recognitions.add(recognition);

      if (register) {
        final bool isValid =
            await recognizer.isValidFace(recognition.embedding);
        if (isValid) {
          if (mounted) {
            ScaffoldMessenger.of(context).showSnackBar(
              const SnackBar(
                content: Text('Wajah sudah terdaftar!'),
                backgroundColor: AppColors.red,
              ),
            );
          }
        } else {
          showFaceRegistrationDialogue(
            croppedFace,
            recognition,
          );
        }
        register = false;
      }
    }

    setState(() {});

    // setState(() {
    //   isBusy = false;
    //   _scanResults = recognitions;
    // });
  }

  void showFaceRegistrationDialogue(
      img.Image croppedFace, RecognitionEmbedding recognition) {
    showDialog(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text("Daftarkan Wajah", textAlign: TextAlign.center),
        alignment: Alignment.center,
        content: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.center,
          children: [
            const SizedBox(
              height: 20,
            ),
            Image.memory(
              Uint8List.fromList(img.encodeBmp(croppedFace)),
              width: 200,
              height: 200,
            ),
            Padding(
              padding: const EdgeInsets.all(16.0),
              child: BlocConsumer<UpdateUserRegisterFaceBloc,
                  UpdateUserRegisterFaceState>(
                listener: (context, state) {
                  state.maybeWhen(
                    orElse: () {},
                    error: (message) {
                      return ScaffoldMessenger.of(context).showSnackBar(
                        SnackBar(
                          content: Text(message),
                        ),
                      );
                    },
                    success: (data) {
                      AuthLocalDatasource().updateAuthData(data);
                      context.pushReplacement(const MainPage());
                    },
                  );
                },
                builder: (context, state) {
                  return state.maybeWhen(
                    orElse: () {
                      return Button.filled(
                          onPressed: () async {
                            context.read<UpdateUserRegisterFaceBloc>().add(
                                UpdateUserRegisterFaceEvent
                                    .updateProfileRegisterFace(
                                        recognition.embedding.join(','), null));
                          },
                          label: 'Simpan');
                    },
                    loading: () => const Center(
                      child: CircularProgressIndicator(),
                    ),
                  );
                },
              ),
            ),
          ],
        ),
        // contentPadding: EdgeInsets.zero,
      ),
    );
  }

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

  @override
  Widget build(BuildContext context) {
    return DetectorView(
      title: 'Face Detector',
      customPaint: _customPaint,
      text: _text,
      onImage: _processImage,
      initialCameraLensDirection: _cameraLensDirection,
      onCameraLensDirectionChanged: (value) => _cameraLensDirection = value,
      onTakePicture: _takePicture,
    );
  }

  Future<void> _processImage(InputImage inputImage) async {
    if (!_canProcess) return;
    if (_isBusy) return;
    _isBusy = true;
    setState(() {
      _text = '';
    });
    final faces = await _faceDetector.processImage(inputImage);
    if (inputImage.metadata?.size != null &&
        inputImage.metadata?.rotation != null) {
      final painter = FaceDetectorPainter(
        faces,
        inputImage.metadata!.size,
        inputImage.metadata!.rotation,
        _cameraLensDirection,
      );
      if (register) {
        performFaceRecognition(faces);
      }
      _customPaint = CustomPaint(painter: painter);
    } else {
      String text = 'Faces found: ${faces.length}\n\n';
      for (final face in faces) {
        text += 'face: ${face.boundingBox}\n\n';
      }
      _text = text;
      // TODO: set _customPaint to draw boundingRect on top of image
      _customPaint = null;
    }
    _isBusy = false;
    if (mounted) {
      setState(() {});
    }
  }
}
