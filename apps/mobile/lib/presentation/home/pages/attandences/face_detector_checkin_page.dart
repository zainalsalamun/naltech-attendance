// ignore_for_file: public_member_api_docs, sort_constructors_first
import 'package:camera/camera.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:google_mlkit_face_detection/google_mlkit_face_detection.dart';
import 'package:image/image.dart' as img;

import 'package:flutter_absensi_app/core/core.dart';

import 'package:flutter_absensi_app/presentation/home/pages/face_detector_painter.dart';

import '../../../../core/ml/recognition_embedding.dart';
import '../../../../core/ml/recognizer.dart';
import 'camera_view_attendance_page.dart';
import 'attendance_result_page.dart';

class FaceDetectorCheckinPage extends StatefulWidget {
  final bool isCheckedIn;
  const FaceDetectorCheckinPage({
    super.key,
    required this.isCheckedIn,
  });

  @override
  State<FaceDetectorCheckinPage> createState() => _FaceDetectorViewState();
}

class _FaceDetectorViewState extends State<FaceDetectorCheckinPage> {
  final FaceDetector _faceDetector = FaceDetector(
    options: FaceDetectorOptions(
      enableContours: false,
      enableLandmarks: false,
      // enableClassification: true,
    ),
  );
  bool _canProcess = true;
  bool _isBusy = false;
  CustomPaint? _customPaint;
  String? _text;
  var _cameraLensDirection = CameraLensDirection.front;

  late List<RecognitionEmbedding> recognitions = [];
  CameraImage? frame;

  late Recognizer recognizer;
  bool isTakePicture = false;
  bool _didCloseEyes = false;

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
    if (mounted) {
      setState(() {
        frame = cameraImage;
        isTakePicture = true;
      });
    }
  }

  img.Image? image;
  performFaceRecognition(List<Face> faces) async {
    recognitions.clear();

    image = convertNV21ToImage(frame!);
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

      bool isValid = await recognizer.isValidFace(recognition.embedding);
      Navigator.pushReplacement(context, MaterialPageRoute(builder: (context) {
        return AttendanceResultPage(
          isMatch: isValid,
          isCheckin: widget.isCheckedIn,
          attendanceType: 'Face',
        );
      }));
      // if (isValid) {
      //   showRegisteredDialogue();
      // } else {
      //   showNotRegisteredDialogue();
      // }
    }
  }

  void _detect({
    required Face face,
  }) async {
    const double blinkThreshold = 0.25;
    if ((face.leftEyeOpenProbability ?? 1.0) < (blinkThreshold) &&
        (face.rightEyeOpenProbability ?? 1.0) < (blinkThreshold)) {
      if (mounted) {
        setState(
          () => _didCloseEyes = true,
        );
      }
    }
  }

  Future<void> _processKedip(List<Face> faces) async {
    try {
      final Face firstFace = faces.first;

      if (_didCloseEyes) {
        if ((faces.first.leftEyeOpenProbability ?? 1.0) < 0.75 &&
            (faces.first.rightEyeOpenProbability ?? 1.0) < 0.75) {
          performFaceRecognition(faces);
        }
      }

      if (!_didCloseEyes) {
        _detect(face: firstFace);
      }
      // _detect(
      //   face: firstFace,
      // );
    } catch (e) {
      print(e);
    }
  }

  void showNotRegisteredDialogue() {
    showDialog(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text("Wajah Tidak Terdaftar", textAlign: TextAlign.center),
        content: const Text(
          "Wajah anda tidak terdaftar, pastikan check in dengan wajah yang sudah terdaftar",
          textAlign: TextAlign.center,
        ),
        actions: [
          Button.filled(
            onPressed: () {
              Navigator.of(context).pop();
            },
            color: Colors.red,
            label: 'Ulangi',
          ),
        ],
      ),
    );
  }

  void showRegisteredDialogue() {
    showDialog(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text("Wajah Anda Terdaftar", textAlign: TextAlign.center),
        content: const Text(
          "Wajah anda sudah terdaftar, silahkan lanjutkan proses check in",
          textAlign: TextAlign.center,
        ),
        actions: [
          Button.filled(
            onPressed: () {
              Navigator.of(context).pop();
            },
            label: 'Proses Check In',
          ),
        ],
      ),
    );
  }

  img.Image convertNV21ToImage(CameraImage cameraImage) {
    final width = cameraImage.width;
    final height = cameraImage.height;

    Uint8List nv21Bytes = cameraImage.planes[0].bytes;
    final image = img.Image(width: width, height: height);

    for (int y = 0; y < height; y++) {
      for (int x = 0; x < width; x++) {
        int yValue = nv21Bytes[y * width + x];

        var color = image.getColor(yValue, yValue, yValue);
        image.setPixel(x, y, color);
      }
    }
    return image;
  }

  @override
  Widget build(BuildContext context) {
    return CameraViewAttendancePage(
      title: 'Check In Camera',
      customPaint: _customPaint,
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
    if (mounted) {
      setState(() {
        _text = '';
      });
    }
    final faces = await _faceDetector.processImage(inputImage);
    if (inputImage.metadata?.size != null &&
        inputImage.metadata?.rotation != null) {
      final painter = FaceDetectorPainter(
        faces,
        inputImage.metadata!.size,
        inputImage.metadata!.rotation,
        _cameraLensDirection,
      );
      if (isTakePicture) {
        performFaceRecognition(faces);
      }
      isTakePicture = false;
      // _processKedip(faces);
      _customPaint = CustomPaint(painter: painter);
    } else {
      String text = 'Faces found: ${faces.length}\n\n';
      for (final face in faces) {
        text += 'face: ${face.boundingBox}\n\n';
      }
      _text = text;

      _customPaint = null;
    }
    _isBusy = false;
    if (mounted) {
      setState(() {});
    }
  }
}
