import 'dart:async';
import 'core/session/session_events.dart';
import 'presentation/auth/pages/login_page.dart';
import 'package:firebase_core/firebase_core.dart';
import 'package:flutter/material.dart';
import 'package:flutter_dotenv/flutter_dotenv.dart';
import 'package:flutter_absensi_app/data/datasources/attendance_remote_datasource.dart';
import 'package:flutter_absensi_app/data/datasources/auth_remote_datasource.dart';
import 'package:flutter_absensi_app/data/datasources/firebase_messanging_remote_datasource.dart';
import 'package:flutter_absensi_app/data/datasources/permisson_remote_datasource.dart';
import 'package:flutter_absensi_app/data/models/response/qr_absen_remote_datasource.dart';
import 'package:flutter_absensi_app/firebase_options.dart';
import 'package:flutter_absensi_app/presentation/auth/bloc/logout/logout_bloc.dart';
import 'package:flutter_absensi_app/presentation/home/bloc/add_permission/add_permission_bloc.dart';
import 'package:flutter_absensi_app/presentation/home/bloc/check_qr/check_qr_bloc.dart';
import 'package:flutter_absensi_app/presentation/home/bloc/checkin_attendance/checkin_attendance_bloc.dart';
import 'package:flutter_absensi_app/presentation/home/bloc/checkout_attendance/checkout_attendance_bloc.dart';
import 'package:flutter_absensi_app/presentation/home/bloc/get_attendance_by_date/get_attendance_by_date_bloc.dart';
import 'package:flutter_absensi_app/presentation/home/bloc/get_company/get_company_bloc.dart';
import 'package:flutter_absensi_app/presentation/home/bloc/get_qrcode_checkin/get_qrcode_checkin_bloc.dart';
import 'package:flutter_absensi_app/presentation/home/bloc/get_qrcode_checkout/get_qrcode_checkout_bloc.dart';
import 'package:flutter_absensi_app/presentation/home/bloc/is_checkedin/is_checkedin_bloc.dart';
import 'package:flutter_absensi_app/presentation/home/bloc/update_user_register_face/update_user_register_face_bloc.dart';
import 'package:flutter_absensi_app/presentation/profile/bloc/get_user/get_user_bloc.dart';
import 'package:flutter_absensi_app/presentation/profile/bloc/update_user/update_user_bloc.dart';
import 'package:flutter_absensi_app/data/datasources/reimbursement_remote_datasource.dart';
import 'package:flutter_absensi_app/presentation/home/bloc/add_reimbursement/add_reimbursement_bloc.dart';
import 'package:flutter_absensi_app/presentation/home/bloc/get_reimbursements/get_reimbursements_bloc.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'core/theme/workspace_theme.dart';
import 'core/theme/theme_controller.dart';

import 'data/datasources/user_remote_datasource.dart';
import 'presentation/auth/bloc/login/login_bloc.dart';
import 'presentation/auth/pages/splash_page.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

void main() async {
  WidgetsFlutterBinding.ensureInitialized();
  await dotenv.load(fileName: ".env");
  try {
    await Firebase.initializeApp(
      options: DefaultFirebaseOptions.currentPlatform,
    );
    FirebaseMessangingRemoteDatasource().initialize().catchError((_) {
      debugPrint('Notifikasi belum dapat diinisialisasi.');
    });
  } catch (_) {
    // The app must still open when optional notifications cannot initialize.
    debugPrint('Layanan notifikasi belum tersedia.');
  }
  final themeController =
      ThemeController(await SharedPreferences.getInstance());
  runApp(MyApp(themeController: themeController));
}

class MyApp extends StatefulWidget {
  const MyApp({super.key, required this.themeController});

  final ThemeController themeController;

  @override
  State<MyApp> createState() => _MyAppState();
}

class _MyAppState extends State<MyApp> {
  final _navigator = GlobalKey<NavigatorState>();
  StreamSubscription<void>? _subscription;
  bool _redirecting = false;
  ThemeController get themeController => widget.themeController;
  @override
  void initState() {
    super.initState();
    _subscription = SessionEvents.expired.listen((_) {
      if (!mounted || _redirecting) return;
      _redirecting = true;
      _navigator.currentState
          ?.pushAndRemoveUntil(
              MaterialPageRoute<void>(
                  builder: (_) => const LoginPage(
                      initialMessage:
                          'Sesi Anda berakhir. Silakan masuk kembali.')),
              (_) => false)
          .whenComplete(() => _redirecting = false);
    });
  }

  @override
  void dispose() {
    _subscription?.cancel();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return ThemeScope(
      controller: themeController,
      child: ListenableBuilder(
        listenable: themeController,
        builder: (context, child) => MultiBlocProvider(
          providers: [
            BlocProvider(
              create: (context) => LoginBloc(AuthRemoteDatasource()),
            ),
            BlocProvider(
              create: (context) => LogoutBloc(AuthRemoteDatasource()),
            ),
            BlocProvider(
              create: (context) =>
                  UpdateUserRegisterFaceBloc(AuthRemoteDatasource()),
            ),
            BlocProvider(
              create: (context) => GetCompanyBloc(AttendanceRemoteDatasource()),
            ),
            BlocProvider(
              create: (context) =>
                  IsCheckedinBloc(AttendanceRemoteDatasource()),
            ),
            BlocProvider(
              create: (context) =>
                  CheckinAttendanceBloc(AttendanceRemoteDatasource()),
            ),
            BlocProvider(
              create: (context) =>
                  CheckoutAttendanceBloc(AttendanceRemoteDatasource()),
            ),
            BlocProvider(
              create: (context) =>
                  AddPermissionBloc(PermissonRemoteDatasource()),
            ),
            BlocProvider(
              create: (context) =>
                  GetAttendanceByDateBloc(AttendanceRemoteDatasource()),
            ),
            BlocProvider(
              create: (context) => CheckQrBloc(QrAbsenRemoteDatasource()),
            ),
            BlocProvider(
              create: (context) => GetQrcodeCheckinBloc(),
            ),
            BlocProvider(
              create: (context) => GetQrcodeCheckoutBloc(),
            ),
            BlocProvider(
              create: (context) => GetUserBloc(UserRemoteDatasource()),
            ),
            BlocProvider(
              create: (context) => UpdateUserBloc(UserRemoteDatasource()),
            ),
            BlocProvider(
              create: (context) =>
                  AddReimbursementBloc(ReimbursementRemoteDatasource()),
            ),
            BlocProvider(
              create: (context) =>
                  GetReimbursementsBloc(ReimbursementRemoteDatasource()),
            ),
          ],
          child: MaterialApp(
            navigatorKey: _navigator,
            debugShowCheckedModeBanner: false,
            title: 'Naltech Attendance',
            theme: WorkspaceTheme.light,
            darkTheme: WorkspaceTheme.dark,
            themeMode: themeController.mode,
            home: const SplashPage(),
          ),
        ),
      ),
    );
  }
}
