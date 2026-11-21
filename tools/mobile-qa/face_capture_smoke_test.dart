import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:integration_test/integration_test.dart';

import 'package:flutter_absensi_app/face_capture_spike.dart' as app;

void main() {
  final binding = IntegrationTestWidgetsFlutterBinding.ensureInitialized();
  testWidgets('physical camera capture and detection timing', (tester) async {
    app.main();
    final button = find.byKey(const ValueKey('face-spike-capture'));
    final status = find.byKey(const ValueKey('face-spike-status'));
    final readyDeadline = DateTime.now().add(const Duration(seconds: 60));
    while (DateTime.now().isBefore(readyDeadline)) {
      await tester.pump(const Duration(milliseconds: 250));
      if (button.evaluate().isNotEmpty &&
          tester.widget<FilledButton>(button).onPressed != null) {
        break;
      }
    }
    expect(tester.widget<FilledButton>(button).onPressed, isNotNull);
    // Give the volunteer time to position their face; never save photo evidence.
    await tester.pump(const Duration(seconds: 15));
    final samples = <Map<String, Object>>[];
    for (var index = 0; index < 10; index++) {
      await tester.ensureVisible(button);
      await tester.tap(button);
      final deadline = DateTime.now().add(const Duration(seconds: 45));
      var finished = false;
      while (DateTime.now().isBefore(deadline)) {
        await tester.pump(const Duration(milliseconds: 100));
        if (tester.widget<FilledButton>(button).onPressed != null) {
          finished = true;
          break;
        }
      }
      expect(finished, isTrue, reason: 'Capture/detection timed out');
      final message = tester.widget<Text>(status).data!;
      final values = RegExp(
        r'(?:Wajah terdeteksi|Ambil foto|Deteksi|Total): (\d+)',
      ).allMatches(message).map((match) => int.parse(match.group(1)!)).toList();
      expect(
        values,
        hasLength(4),
        reason: 'Capture/detection did not complete',
      );
      samples.add({
        'sample': index + 1,
        'faces': values[0],
        'capture_ms': values[1],
        'detection_ms': values[2],
        'total_ms': values[3],
      });
      if (index == 4) {
        // Exercise serialized close/open with real camera calls. These lifecycle
        // events are injected by the test; this is not an OS backgrounding test.
        tester.binding.handleAppLifecycleStateChanged(
          AppLifecycleState.inactive,
        );
        await tester.pump(const Duration(milliseconds: 100));
        tester.binding.handleAppLifecycleStateChanged(
          AppLifecycleState.resumed,
        );
        final resumeDeadline = DateTime.now().add(const Duration(seconds: 30));
        while (DateTime.now().isBefore(resumeDeadline)) {
          await tester.pump(const Duration(milliseconds: 100));
          if (tester.widget<FilledButton>(button).onPressed != null) break;
        }
        expect(tester.widget<FilledButton>(button).onPressed, isNotNull);
      }
      await tester.pump(const Duration(seconds: 1));
    }
    binding.reportData = {
      'scope': 'capture and face detection only; no identity/liveness/upload',
      'detector_lifetime': 'new detector each sample',
      'injected_lifecycle_restart_after_sample': 5,
      'samples': samples,
    };
    // Numeric timing and counts only, no photos, paths, embeddings or credentials.
    debugPrint('NALTECH_FACE_METRICS=${jsonEncode(binding.reportData)}');
  });
}
