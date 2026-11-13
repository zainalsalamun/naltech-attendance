import 'dart:async';
import 'dart:convert';
import 'dart:io';
import 'package:dartz/dartz.dart';
import 'package:flutter/material.dart';
import 'package:flutter_absensi_app/core/theme/workspace_theme.dart';
import 'package:flutter_absensi_app/data/datasources/auth_local_datasource.dart';
import 'package:flutter_absensi_app/data/datasources/face_enrollment_remote_datasource.dart';
import 'package:flutter_absensi_app/data/models/response/auth_response_model.dart';
import 'package:flutter_absensi_app/presentation/profile/pages/face_enrollment_page.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:image_picker/image_picker.dart';
import 'package:shared_preferences/shared_preferences.dart';

class EnrollmentFixture extends FaceEnrollmentRemoteDatasource {
  FaceEnrollmentSnapshot snapshot = const FaceEnrollmentSnapshot([]);
  final sent = Completer<Either<String, bool>>();
  int calls = 0;
  @override
  Future<Either<String, FaceEnrollmentSnapshot>> load() async =>
      Right(snapshot);
  @override
  Future<Either<String, bool>> submit(XFile photo, {required bool consent}) {
    expect(consent, isTrue);
    calls++;
    return sent.future;
  }
}

void main() {
  setUp(() async {
    SharedPreferences.setMockInitialValues({});
    await AuthLocalDatasource().saveAuthData(
        AuthResponseModel(token: 'fixture-token', user: User(id: 7)));
  });

  test(
      'consent is required and multipart upload cannot choose approval or identity',
      () async {
    var calls = 0;
    final source =
        FaceEnrollmentRemoteDatasource(client: MockClient((request) async {
      calls++;
      expect(request.url.path, '/api/face-enrollments');
      expect(request.headers['Authorization'], 'Bearer fixture-token');
      final body = utf8.decode(request.bodyBytes, allowMalformed: true);
      expect(body, contains('name="consent"'));
      expect(body, contains('name="photo"'));
      expect(body, isNot(contains('name="user_id"')));
      expect(body, isNot(contains('name="status"')));
      return http.Response(
          jsonEncode({
            'data': {'id': 1, 'status': 'pending'}
          }),
          201);
    }));
    final photo =
        XFile.fromData(utf8.encode('fixture-only-image'), name: 'fixture.jpg');
    expect((await source.submit(photo, consent: false)).isLeft(), isTrue);
    expect(calls, 0);
    expect((await source.submit(photo, consent: true)).isRight(), isTrue);
    expect(calls, 1);
  });

  test('unknown enrollment status and claimed attendance proof are rejected',
      () async {
    final malformed = FaceEnrollmentRemoteDatasource(
        client: MockClient((_) async => http.Response(
            jsonEncode({
              'data': [
                {'id': 1, 'status': 'untrusted-status'}
              ]
            }),
            200)));
    expect((await malformed.load()).isLeft(), isTrue);
    final proof = FaceEnrollmentRemoteDatasource(
        client: MockClient((_) async => http.Response(
            jsonEncode({
              'data': {
                'matched': true,
                'liveness': 'passed',
                'attendance_eligible': true
              }
            }),
            200)));
    expect(
        (await proof.preview(
                XFile.fromData(utf8.encode('fixture'), name: 'probe.jpg')))
            .isLeft(),
        isTrue);
  });

  testWidgets(
      'consent gates submission, pending disables duplicates and photo is deleted',
      (tester) async {
    final directory = (await tester.runAsync(
        () => Directory.systemTemp.createTemp('naltech-face-widget-')))!;
    addTearDown(() => directory.delete(recursive: true));
    final photo = File('${directory.path}/fixture.png');
    await tester.runAsync(() => photo.writeAsBytes(base64Decode(
        'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAusB9Wl6b9kAAAAASUVORK5CYII=')));
    final source = EnrollmentFixture();
    await tester.pumpWidget(MaterialApp(
        theme: WorkspaceTheme.light,
        home: FaceEnrollmentPage(
            source: source, capture: (_) async => XFile(photo.path))));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Ambil foto wajah'));
    await tester.pumpAndSettle();
    await tester.scrollUntilVisible(find.text('Ajukan ke HR/admin'), 200);
    expect(
        tester
            .widget<FilledButton>(
                find.widgetWithText(FilledButton, 'Ajukan ke HR/admin'))
            .onPressed,
        isNull);
    await tester.ensureVisible(find.byType(CheckboxListTile));
    await tester.tap(find.byType(CheckboxListTile));
    await tester.pump();
    await tester.ensureVisible(find.text('Ajukan ke HR/admin'));
    await tester.tap(find.text('Ajukan ke HR/admin'));
    await tester.pump();
    expect(source.calls, 1);
    expect(
        tester
            .widget<FilledButton>(
                find.widgetWithText(FilledButton, 'Memproses…'))
            .onPressed,
        isNull);
    source.snapshot =
        const FaceEnrollmentSnapshot([FaceEnrollmentRecord(1, 'pending')]);
    await tester.runAsync(() async {
      source.sent.complete(const Right(true));
      await Future<void>.delayed(const Duration(milliseconds: 100));
    });
    await tester.pumpAndSettle();
    expect(find.text('Menunggu persetujuan'), findsOneWidget);
    expect(find.text('Ajukan ke HR/admin'), findsNothing);
    expect(await tester.runAsync(() => photo.exists()), isFalse);
    expect(find.textContaining('Absensi wajah belum aktif'), findsOneWidget);
  });

  testWidgets(
      'dark pending state and rejection reason remain readable at large text',
      (tester) async {
    final source = EnrollmentFixture()
      ..snapshot = const FaceEnrollmentSnapshot([
        FaceEnrollmentRecord(2, 'pending'),
        FaceEnrollmentRecord(1, 'rejected', 'Foto kurang jelas.')
      ]);
    await tester.pumpWidget(MaterialApp(
        theme: WorkspaceTheme.dark,
        home: MediaQuery(
            data: const MediaQueryData(textScaler: TextScaler.linear(1.5)),
            child: FaceEnrollmentPage(source: source))));
    await tester.pumpAndSettle();
    expect(find.text('Foto kurang jelas.'), findsOneWidget);
    expect(find.text('Ambil foto wajah'), findsNothing);
    expect(tester.takeException(), isNull);
  });
}
