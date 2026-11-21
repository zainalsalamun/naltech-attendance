// Widget-only visual evidence using synthetic state and local QA fonts.
import 'dart:io';
import 'dart:ui' as ui;
import 'package:dartz/dartz.dart';
import 'package:flutter/material.dart';
import 'package:flutter/rendering.dart';
import 'package:flutter/services.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:flutter_absensi_app/core/theme/workspace_theme.dart';
import 'package:flutter_absensi_app/data/datasources/face_enrollment_remote_datasource.dart';
import 'package:flutter_absensi_app/presentation/profile/pages/face_enrollment_page.dart';

class VisualFixture extends FaceEnrollmentRemoteDatasource {
  VisualFixture(this.pending);
  final bool pending;
  @override
  Future<Either<String, FaceEnrollmentSnapshot>> load() async => Right(FaceEnrollmentSnapshot(
    pending ? [const FaceEnrollmentRecord(1, 'pending')] : []));
}

void main() {
  testWidgets('export enrollment widget light/dark without personal photos', (tester) async {
    final output = Platform.environment['NALTECH_FACE_VISUAL_DIR'];
    expect(output, isNotNull);
    await tester.runAsync(() async {
      final font = FontLoader('FaceQaArial')..addFont(File('/System/Library/Fonts/Supplemental/Arial.ttf').readAsBytes().then(ByteData.sublistView));
      await font.load();
      final icons = FontLoader('MaterialIcons')..addFont(File('/Users/macbookpro/development/flutter/bin/cache/artifacts/material_fonts/MaterialIcons-Regular.otf').readAsBytes().then(ByteData.sublistView));
      await icons.load();
      await Directory(output!).create(recursive: true);
    });
    tester.view.physicalSize = const Size(390, 900);
    tester.view.devicePixelRatio = 1;
    addTearDown(tester.view.resetPhysicalSize);
    addTearDown(tester.view.resetDevicePixelRatio);
    for (final dark in [false, true]) {
      final key = GlobalKey();
      final theme = dark ? WorkspaceTheme.dark : WorkspaceTheme.light;
      await tester.pumpWidget(RepaintBoundary(key: key, child: MaterialApp(
        debugShowCheckedModeBanner: false,
        theme: theme.copyWith(textTheme: theme.textTheme.apply(fontFamily: 'FaceQaArial')),
        home: FaceEnrollmentPage(key: ValueKey(dark), source: VisualFixture(dark)),
      )));
      await tester.pumpAndSettle();
      expect(tester.takeException(), isNull);
      final boundary = key.currentContext!.findRenderObject()! as RenderRepaintBoundary;
      await tester.runAsync(() async {
        final image = await boundary.toImage(pixelRatio: 2);
        final bytes = await image.toByteData(format: ui.ImageByteFormat.png);
        await File('$output/mobile-enrollment-${dark ? 'dark' : 'light'}-widget.png').writeAsBytes(bytes!.buffer.asUint8List());
        image.dispose();
      });
    }
  });
}
