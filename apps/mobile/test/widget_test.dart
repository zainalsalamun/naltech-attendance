import 'dart:async';
import 'package:dartz/dartz.dart';
import 'package:flutter/material.dart';
import 'package:flutter_absensi_app/core/theme/theme_controller.dart';
import 'package:flutter_absensi_app/core/theme/workspace_theme.dart';
import 'package:flutter_absensi_app/data/datasources/auth_remote_datasource.dart';
import 'package:flutter_absensi_app/data/models/response/auth_response_model.dart';
import 'package:flutter_absensi_app/presentation/auth/bloc/login/login_bloc.dart';
import 'package:flutter_absensi_app/presentation/auth/pages/login_page.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:shared_preferences/shared_preferences.dart';

class LoginFixture extends AuthRemoteDatasource {
  int calls = 0;
  String? email;
  final response = Completer<Either<String, AuthResponseModel>>();
  @override
  Future<Either<String, AuthResponseModel>> login(
      String username, String password) {
    calls++;
    email = username;
    return response.future;
  }
}

void main() {
  test('Theme preference survives a new controller and ignores unknown values',
      () async {
    SharedPreferences.setMockInitialValues({'naltech.theme': 'unknown'});
    final preferences = await SharedPreferences.getInstance();
    final controller = ThemeController(preferences);
    expect(controller.mode, ThemeMode.light);
    expect(await controller.setMode(ThemeMode.dark), isTrue);
    expect(ThemeController(preferences).mode, ThemeMode.dark);
    expect(await controller.setMode(ThemeMode.light), isTrue);
    expect(ThemeController(preferences).mode, ThemeMode.light);
  });

  testWidgets(
      'Login validates, blocks repeated requests and recovers from errors',
      (tester) async {
    SharedPreferences.setMockInitialValues({});
    final controller = ThemeController(await SharedPreferences.getInstance());
    final fixture = LoginFixture();
    final bloc = LoginBloc(fixture);
    addTearDown(bloc.close);
    await tester.pumpWidget(ThemeScope(
        controller: controller,
        child: BlocProvider.value(
            value: bloc,
            child: MaterialApp(
                theme: WorkspaceTheme.light, home: const LoginPage()))));
    await tester.tap(find.text('Masuk'));
    await tester.pumpAndSettle();
    expect(find.text('Masukkan email Anda.'), findsOneWidget);
    expect(fixture.calls, 0);
    await tester.enterText(
        find.byType(TextFormField).first, ' admin@example.test ');
    await tester.enterText(find.byType(TextFormField).last, 'fixture-password');
    await tester.tap(find.byTooltip('Tampilkan kata sandi'));
    await tester.pump();
    expect(tester.widget<TextField>(find.byType(TextField).last).obscureText,
        isFalse);
    await tester.tap(find.text('Masuk'));
    await tester.pump();
    bloc.add(const LoginEvent.login('admin@example.test', 'fixture-password'));
    await tester.pump();
    expect(fixture.calls, 1);
    expect(fixture.email, 'admin@example.test');
    expect(find.text('Sedang masuk…'), findsOneWidget);
    fixture.response
        .complete(const Left('Email atau kata sandi belum sesuai.'));
    await tester.pumpAndSettle();
    expect(find.text('Email atau kata sandi belum sesuai.'), findsOneWidget);
    expect(tester.widget<FilledButton>(find.byType(FilledButton)).onPressed,
        isNotNull);
  });

  testWidgets(
      'Login supports a small viewport, keyboard, large text and theme switching',
      (tester) async {
    SharedPreferences.setMockInitialValues({});
    final controller = ThemeController(await SharedPreferences.getInstance());
    final bloc = LoginBloc(LoginFixture());
    addTearDown(bloc.close);
    tester.view.physicalSize = const Size(320, 640);
    tester.view.devicePixelRatio = 1;
    addTearDown(tester.view.resetPhysicalSize);
    addTearDown(tester.view.resetDevicePixelRatio);
    await tester.pumpWidget(ThemeScope(
        controller: controller,
        child: BlocProvider.value(
            value: bloc,
            child: ListenableBuilder(
                listenable: controller,
                builder: (context, _) => MaterialApp(
                    theme: WorkspaceTheme.light,
                    darkTheme: WorkspaceTheme.dark,
                    themeMode: controller.mode,
                    builder: (context, child) => MediaQuery(
                        data: MediaQuery.of(context).copyWith(
                            textScaler: const TextScaler.linear(1.8),
                            viewInsets: const EdgeInsets.only(bottom: 250)),
                        child: child!),
                    home: const LoginPage())))));
    expect(tester.takeException(), isNull);
    await tester.tap(find.byTooltip('Gunakan tema gelap'));
    await tester.pumpAndSettle();
    expect(controller.mode, ThemeMode.dark);
    expect(Theme.of(tester.element(find.byType(LoginPage))).brightness,
        Brightness.dark);
    await tester.ensureVisible(find.text('Masuk'));
    await tester.pumpAndSettle();
    expect(tester.takeException(), isNull);
  });

  test('Login transport failures leave the loading state', () async {
    final fixture = LoginFixture();
    final bloc = LoginBloc(fixture);
    final states = <LoginState>[];
    final subscription = bloc.stream.listen(states.add);
    bloc.add(
        const LoginEvent.login('fixture@example.test', 'fixture-password'));
    await Future<void>.delayed(Duration.zero);
    fixture.response.completeError(Exception('offline'));
    await Future<void>.delayed(Duration.zero);
    expect(
        states.last.maybeWhen(error: (_) => true, orElse: () => false), isTrue);
    await subscription.cancel();
    await bloc.close();
  });
}
