import 'dart:io';

import 'package:camera/camera.dart';
import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_absensi_app/core/assets/assets.gen.dart';
import 'package:flutter_absensi_app/core/components/spaces.dart';
import 'package:flutter_absensi_app/core/constants/colors.dart';
import 'package:flutter_absensi_app/core/ml/recognition_embedding.dart';
import 'package:flutter_absensi_app/core/ml/recognizer.dart';
import 'package:flutter_absensi_app/data/datasources/auth_local_datasource.dart';
import 'package:flutter_absensi_app/presentation/home/bloc/update_user_register_face/update_user_register_face_bloc.dart';
import 'package:flutter_absensi_app/presentation/home/pages/main_page.dart';
import 'package:flutter_absensi_app/presentation/home/widgets/face_detector_painter.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:google_mlkit_face_detection/google_mlkit_face_detection.dart';

import 'package:image/image.dart' as img;

import '../../../core/core.dart';

class RegisterFaceAttendencePage extends StatefulWidget {
  const RegisterFaceAttendencePage({super.key});

  @override
  State<RegisterFaceAttendencePage> createState() =>
      _RegisterFaceAttendencePageState();
}

class _RegisterFaceAttendencePageState
    extends State<RegisterFaceAttendencePage> {
  List<CameraDescription>? _availableCameras;
  late CameraDescription description = _availableCameras![1];
  CameraController? _controller;

  CameraLensDirection camDirec = CameraLensDirection.front;

  bool register = false;

  late Size size;

  late List<RecognitionEmbedding> recognitions = [];

  //TODO declare face detectore
  late FaceDetector detector;

  //TODO declare face recognizer
  late Recognizer recognizer;

  bool isBusy = false;

//   Future<XFile> convertImageToXFile(img.Image image) async {
//   // Get a temporary directory path
//   String tempDir = (await getTemporaryDirectory()).path;

//   // Create a file path within the temporary directory
//   String filePath = '$tempDir/image.jpg';

//   // Save the image to the file path
//   File file = File(filePath);
//   await file.writeAsBytes(img.encodeJpg(image));

//   // Create an XFile from the saved file
//   XFile xFile = XFile(filePath);

//   return xFile;
// }

  @override
  void initState() {
    super.initState();

//TODO initialize face detector
    detector = FaceDetector(
        options: FaceDetectorOptions(performanceMode: FaceDetectorMode.fast));

    //TODO initialize face recognizer
    recognizer = Recognizer();

    _initializeCamera();
  }

  void _initializeCamera() async {
    _availableCameras = await availableCameras();
    _controller = CameraController(
      description,
      ResolutionPreset.high,
      enableAudio: false,
      imageFormatGroup: Platform.isAndroid
          ? ImageFormatGroup.nv21
          : ImageFormatGroup.bgra8888,
    );

    // size = _controller!.value.previewSize!;

    await _controller!.initialize().then((_) {
      if (!mounted) {
        return;
      }

      _controller!.startImageStream((CameraImage image) {
        if (!isBusy) {
          isBusy = true;
          frame = image;
          doFaceDetectionOnFrame();
        }
      });

      setState(() {});
    });
  }

  dynamic _scanResults;
  CameraImage? frame;

  final _orientations = {
    DeviceOrientation.portraitUp: 0,
    DeviceOrientation.landscapeLeft: 90,
    DeviceOrientation.portraitDown: 180,
    DeviceOrientation.landscapeRight: 270,
  };

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

  doFaceDetectionOnFrame() async {
    InputImage? inputImage = getInputImage();
    if (inputImage == null) return;

    List<Face> faces = await detector.processImage(inputImage);

    performFaceRecognition(faces);
  }

  img.Image? image;
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

      //TODO show face registration dialogue
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

    setState(() {
      isBusy = false;
      _scanResults = recognitions;
    });
  }

  void showFaceRegistrationDialogue(
      img.Image croppedFace, RecognitionEmbedding recognition) {
    showDialog(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text("Face Registration", textAlign: TextAlign.center),
        alignment: Alignment.center,
        content: SizedBox(
          height: MediaQuery.of(context).size.height / 2,
          child: Column(
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
                padding: const EdgeInsets.all(8.0),
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
                        // AuthLocalDataSource()
                        //     .reSaveAuthData(responseModel.user!);
                        // Navigator.pop(context);
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
                              // Image to XFile to be able to pass it to the bloc

                              // final XFile newCroppedFaced = XFile(croppedFace.);
                              context.read<UpdateUserRegisterFaceBloc>().add(
                                  UpdateUserRegisterFaceEvent
                                      .updateProfileRegisterFace(
                                          recognition.embedding.join(','),
                                          null));
                            },
                            label: 'Register');
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
        ),
        contentPadding: EdgeInsets.zero,
      ),
    );
  }

  void _reverseCamera() async {
    if (camDirec == CameraLensDirection.back) {
      camDirec = CameraLensDirection.front;
      description = _availableCameras![1];
    } else {
      camDirec = CameraLensDirection.back;
      description = _availableCameras![0];
    }
    // await _controller!.stopImageStream();
    // setState(() {
    //   _controller;
    // });
    // Inisialisasi kamera dengan deskripsi kamera baru
    _initializeCamera();
  }

  void _takePicture() async {
    await _controller!.takePicture();
    if (mounted) {
      setState(() {
        register = true;
      });
    }
  }

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
              bottom: 5.0,
              left: 0.0,
              right: 0.0,
              child: Padding(
                padding: const EdgeInsets.all(40.0),
                child: Column(
                  mainAxisAlignment: MainAxisAlignment.end,
                  children: [
                    Row(
                      children: [
                        IconButton(
                          onPressed: _reverseCamera,
                          icon: Assets.icons.reverse.svg(width: 48.0),
                        ),
                        const Spacer(),
                        IconButton(
                          onPressed: _takePicture,
                          icon: const Icon(
                            Icons.circle,
                            size: 70.0,
                          ),
                          color: AppColors.red,
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
