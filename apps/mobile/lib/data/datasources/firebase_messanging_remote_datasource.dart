import 'dart:async';
import 'dart:convert';
import 'package:firebase_core/firebase_core.dart';
import 'package:firebase_messaging/firebase_messaging.dart';
import 'package:flutter/material.dart';
import 'package:flutter_absensi_app/core/helper/app_navigator.dart';
import 'package:flutter_absensi_app/data/datasources/auth_local_datasource.dart';
import 'package:flutter_absensi_app/data/datasources/auth_remote_datasource.dart';
import 'package:flutter_absensi_app/data/datasources/submission_remote_datasource.dart';
import 'package:flutter_absensi_app/data/models/response/submission_record.dart';
import 'package:flutter_absensi_app/presentation/home/pages/submissions/submission_list_page.dart';
import 'package:flutter_local_notifications/flutter_local_notifications.dart';

class FirebaseMessangingRemoteDatasource {
  static FirebaseMessangingRemoteDatasource? _instance;
  factory FirebaseMessangingRemoteDatasource() =>
      _instance ??= FirebaseMessangingRemoteDatasource._internal();
  FirebaseMessangingRemoteDatasource._internal();

  late final FirebaseMessaging _firebaseMessaging = FirebaseMessaging.instance;
  late final flutterLocalNotificationsPlugin = FlutterLocalNotificationsPlugin();

  static final StreamController<Map<String, dynamic>>
      _notificationStreamController =
      StreamController<Map<String, dynamic>>.broadcast();

  static Stream<Map<String, dynamic>> get onNotificationTap =>
      _notificationStreamController.stream;

  static Future<void> handleNotificationData(Map<String, dynamic> data,
      {SubmissionRemoteDatasource? source,
      void Function(SubmissionRecord record)? onNavigate}) async {
    _notificationStreamController.add(data);

    final submissionId = int.tryParse('${data['submission_id']}');
    final kindStr = data['kind'] as String?;
    if (submissionId != null && kindStr != null) {
      final auth = await AuthLocalDatasource().getAuthData();
      final userId = auth?.user?.id;
      if (userId != null &&
          (onNavigate != null || AppNavigator.navigatorKey.currentState != null)) {
        final kind = kindStr == 'permission'
            ? SubmissionKind.permission
            : kindStr == 'reimbursement'
                ? SubmissionKind.reimbursement
                : SubmissionKind.overtime;
        final placeholder = SubmissionRecord(
          id: submissionId,
          userId: userId,
          kind: kind,
          status: (data['status'] as String?) ?? 'pending',
          date: '',
          description: '',
        );
        if (onNavigate != null) {
          onNavigate(placeholder);
        } else {
          AppNavigator.navigatorKey.currentState?.push(
            MaterialPageRoute(
              builder: (_) =>
                  SubmissionDetailPage(record: placeholder, source: source),
            ),
          );
        }
      }
    }
  }

  Future<void> initialize() async {
    await _firebaseMessaging.requestPermission(
      alert: true,
      badge: true,
      sound: true,
    );

    const initializationSettingsAndroid =
        AndroidInitializationSettings('ic_permission');
    final initializationSettingsIOS = DarwinInitializationSettings(
      requestAlertPermission: true,
      requestBadgePermission: true,
      requestSoundPermission: true,
      onDidReceiveLocalNotification:
          (int id, String? title, String? body, String? payload) async {},
    );

    final initializationSettings = InitializationSettings(
      android: initializationSettingsAndroid,
      iOS: initializationSettingsIOS,
    );

    await flutterLocalNotificationsPlugin.initialize(
      initializationSettings,
      onDidReceiveNotificationResponse:
          (NotificationResponse notificationResponse) async {
        final payload = notificationResponse.payload;
        if (payload != null && payload.isNotEmpty) {
          try {
            final data = jsonDecode(payload);
            if (data is Map<String, dynamic>) {
              handleNotificationData(data);
            }
          } catch (_) {}
        }
      },
    );

    final fcmToken = await _firebaseMessaging.getToken();

    if (fcmToken != null && await AuthLocalDatasource().getAuthData() != null) {
      await AuthRemoteDatasource().updateFcmToken(fcmToken);
    }

    final initialMessage = await _firebaseMessaging.getInitialMessage();
    if (initialMessage != null) {
      handleNotificationData(initialMessage.data);
    }

    FirebaseMessaging.onMessage.listen((RemoteMessage message) {
      firebaseBackgroundHandler(message);
    });

    FirebaseMessaging.onBackgroundMessage(_firebaseMessagingBackgroundHandler);

    FirebaseMessaging.onMessageOpenedApp.listen((RemoteMessage message) {
      handleNotificationData(message.data);
    });
  }

  Future showNotification({
    int id = 0,
    String? title,
    String? body,
    String? payLoad,
  }) async {
    return flutterLocalNotificationsPlugin.show(
      id,
      title,
      body,
      const NotificationDetails(
        android: AndroidNotificationDetails(
          'com.naltech.attendance',
          'app',
          importance: Importance.max,
        ),
        iOS: DarwinNotificationDetails(),
      ),
      payload: payLoad,
    );
  }

  Future<void> firebaseBackgroundHandler(RemoteMessage message) async {
    final title = message.notification?.title ??
        message.data['title'] ??
        'Notifikasi HRIS';
    final body = message.notification?.body ??
        message.data['body'] ??
        message.data['summary'] ??
        '';

    showNotification(
      id: DateTime.now().millisecondsSinceEpoch ~/ 1000,
      title: title,
      body: body,
      payLoad: jsonEncode(message.data),
    );
  }
}

@pragma('vm:entry-point')
Future<void> _firebaseMessagingBackgroundHandler(RemoteMessage message) async {
  await Firebase.initializeApp();

  FirebaseMessangingRemoteDatasource().firebaseBackgroundHandler(message);
}
