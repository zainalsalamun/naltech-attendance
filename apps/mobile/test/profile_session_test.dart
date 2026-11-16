import 'dart:async';
import 'dart:convert';
import 'dart:typed_data';
import 'package:dartz/dartz.dart';
import 'package:flutter/material.dart';
import 'package:flutter_absensi_app/core/session/authenticated_client.dart';
import 'package:flutter_absensi_app/core/session/session_events.dart';
import 'package:flutter_absensi_app/core/theme/theme_controller.dart';
import 'package:flutter_absensi_app/core/theme/workspace_theme.dart';
import 'package:flutter_absensi_app/data/datasources/auth_local_datasource.dart';
import 'package:flutter_absensi_app/data/datasources/auth_remote_datasource.dart';
import 'package:flutter_absensi_app/data/datasources/attendance_remote_datasource.dart';
import 'package:flutter_absensi_app/data/datasources/user_remote_datasource.dart';
import 'package:flutter_absensi_app/data/models/response/company_response_model.dart';
import 'package:flutter_absensi_app/data/models/request/user_request_model.dart';
import 'package:flutter_absensi_app/data/models/response/auth_response_model.dart';
import 'package:flutter_absensi_app/presentation/auth/pages/splash_page.dart';
import 'package:flutter_absensi_app/presentation/auth/pages/login_page.dart';
import 'package:flutter_absensi_app/main.dart';
import 'package:flutter_absensi_app/presentation/profile/bloc/profile_workspace/profile_workspace_cubit.dart';
import 'package:flutter_absensi_app/presentation/profile/bloc/update_user/update_user_bloc.dart';
import 'package:flutter_absensi_app/presentation/profile/pages/profile_page.dart';
import 'package:flutter_absensi_app/presentation/profile/pages/update_profile_page.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:image_picker/image_picker.dart';
import 'package:shared_preferences/shared_preferences.dart';

final fixtureUser = User(
    id: 7,
    name: 'Nadia Putri',
    email: 'nadia@example.test',
    phone: '081234567',
    role: 'staff',
    position: 'Engineer',
    department: 'Teknologi');
final local = AuthLocalDatasource();
Future<void> save({String token = 'fixture-token', User? user}) => local
    .saveAuthData(AuthResponseModel(token: token, user: user ?? fixtureUser));
UserRequestModel model({int id = 7, XFile? image}) => UserRequestModel(
    id: id,
    name: 'Nama Baru',
    email: 'new@example.test',
    phone: '081234999',
    image: image);

class UpdateFixture extends UserRemoteDatasource {
  int calls = 0;
  final result = Completer<Either<String, User>>();
  @override
  Future<Either<String, User>> updateProfile(UserRequestModel model, int id) {
    calls++;
    return result.future;
  }
}

class RestoreFixture extends AuthRemoteDatasource {
  int calls = 0;
  SessionCheck status = SessionCheck.unavailable;
  @override
  Future<SessionCheck> restoreSession() async {
    calls++;
    return status;
  }
}

class ProfileUserFixture extends UserRemoteDatasource {
  final requests = <Completer<Either<String, User>>>[];
  @override
  Future<Either<String, User>> getUser() {
    final pending = Completer<Either<String, User>>();
    requests.add(pending);
    return pending.future;
  }
}

class ProfileCompanyFixture extends AttendanceRemoteDatasource {
  @override
  Future<Either<String, CompanyResponseModel>> getCompanyProfile() async =>
      const Left('Perusahaan gagal dimuat');
}

Future<ThemeController> controller() async =>
    ThemeController(await SharedPreferences.getInstance());
Widget app(ThemeController theme, Widget page) => ThemeScope(
    controller: theme,
    child: ListenableBuilder(
        listenable: theme,
        builder: (_, __) => MaterialApp(
            theme: WorkspaceTheme.light,
            darkTheme: WorkspaceTheme.dark,
            themeMode: theme.mode,
            home: page)));

void main() {
  setUp(() {
    SharedPreferences.setMockInitialValues({'naltech.theme': 'dark'});
  });

  test(
      'Profile refresh rejects stale snapshots and preserves partial company failure',
      () async {
    final users = ProfileUserFixture();
    final cubit = ProfileWorkspaceCubit(users, ProfileCompanyFixture());
    final old = cubit.load();
    final current = cubit.load();
    users.requests[1].complete(Right(fixtureUser));
    await current;
    expect(cubit.state.user?.id, 7);
    expect(cubit.state.company, isNull);
    expect(cubit.state.companyError, isNotNull);
    expect(cubit.state.error, isNull);
    users.requests[0].complete(const Left('Old error'));
    await old;
    expect(cubit.state.user?.id, 7);
    final failure = cubit.load();
    users.requests[2].complete(const Left('Profil gagal dimuat'));
    await failure;
    expect(cubit.state.user, isNull);
    expect(cubit.state.error, 'Profil gagal dimuat');
    await cubit.close();
  });

  testWidgets(
      'Successful edit returns to profile and gallery failure remains recoverable',
      (tester) async {
    tester.view.physicalSize = const Size(390, 844);
    tester.view.devicePixelRatio = 1;
    addTearDown(tester.view.resetPhysicalSize);
    addTearDown(tester.view.resetDevicePixelRatio);
    final fixture = UpdateFixture();
    final bloc = UpdateUserBloc(fixture);
    addTearDown(bloc.close);
    bool? updated;
    await tester.pumpWidget(app(
        await controller(),
        BlocProvider.value(
            value: bloc,
            child: Scaffold(
                body: Builder(
                    builder: (context) => FilledButton(
                        onPressed: () async {
                          updated = await Navigator.of(context).push<bool>(
                              MaterialPageRoute(
                                  builder: (_) => BlocProvider.value(
                                      value: bloc,
                                      child: UpdateProfilePage(
                                          user: fixtureUser,
                                          pickImage: () async =>
                                              throw Exception(
                                                  'gallery denied')))));
                        },
                        child: const Text('Open profile')))))));
    await tester.tap(find.text('Open profile'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Pilih foto'));
    await tester.pumpAndSettle();
    expect(
        find.text(
            'Foto belum dapat dipilih. Periksa izin galeri dan coba lagi.'),
        findsOneWidget);
    await tester.ensureVisible(find.text('Simpan perubahan'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Simpan perubahan'));
    await tester.pump();
    fixture.result.complete(Right(fixtureUser));
    await tester.pumpAndSettle();
    expect(updated, isTrue);
    expect(find.text('Open profile'), findsOneWidget);
    await tester.pumpWidget(const SizedBox());
  });

  test('Invalid persisted sessions are removed without deleting theme',
      () async {
    final preferences = await SharedPreferences.getInstance();
    for (final bad in ['{broken', '{}', '{"token":"x","user":{}}', 123]) {
      if (bad is String) {
        await preferences.setString('auth_data', bad);
      } else {
        await preferences.setInt('auth_data', bad as int);
      }
      expect(await local.getAuthData(), isNull);
      expect(preferences.containsKey('auth_data'), isFalse);
      expect(preferences.getString('naltech.theme'), 'dark');
    }
  });

  test(
      '401 expires current session once; stale requests and login rejection preserve new session',
      () async {
    await save();
    var events = 0;
    final subscription = SessionEvents.expired.listen((_) => events++);
    addTearDown(subscription.cancel);
    final transport =
        AuthenticatedClient(MockClient((_) async => http.Response('{}', 401)));
    addTearDown(transport.close);
    final url = Uri.parse('https://fixture.invalid/api/user');
    await save(token: 'new-token');
    await transport
        .get(url, headers: {'Authorization': 'Bearer fixture-token'});
    await transport.post(url);
    expect((await local.getAuthData())?.token, 'new-token');
    expect(events, 0);
    await Future.wait(List.generate(
        2,
        (_) => transport
            .get(url, headers: {'Authorization': 'Bearer new-token'})));
    await Future<void>.delayed(Duration.zero);
    expect(await local.isAuth(), isFalse);
    expect(events, 1);
    expect((await SharedPreferences.getInstance()).getString('naltech.theme'),
        'dark');
  });

  test(
      'Startup verifies account, preserves offline session and rejects identity mismatch',
      () async {
    await save();
    var status = 200;
    var user = fixtureUser;
    final source = AuthRemoteDatasource(client: MockClient((request) async {
      expect(request.url.path, '/api/user');
      return http.Response(jsonEncode(user.toMap()), status);
    }));
    expect(await source.restoreSession(), SessionCheck.active);
    status = 403;
    expect(await source.restoreSession(), SessionCheck.companyMissing);
    status = 500;
    expect(await source.restoreSession(), SessionCheck.unavailable);
    status = 200;
    user = User(id: 99, name: 'Other');
    expect(await source.restoreSession(), SessionCheck.unavailable);
    expect((await local.getAuthData())?.user?.id, 7);
    final offline = AuthRemoteDatasource(
        client: MockClient((_) async => throw Exception('offline')));
    expect(await offline.restoreSession(), SessionCheck.unavailable);
    expect(await local.isAuth(), isTrue);
    status = 401;
    expect(await source.restoreSession(), SessionCheck.signedOut);
    expect(await local.isAuth(), isFalse);
  });

  test(
      'Logout clears only on confirmation or expired token and retains offline session',
      () async {
    for (final status in [500, 200, 401]) {
      await save();
      final source = AuthRemoteDatasource(client: MockClient((request) async {
        expect(request.url.path, '/api/logout');
        return http.Response('{}', status);
      }));
      final result = await source.logout();
      expect(result.isRight(), status != 500);
      expect(await local.isAuth(), status == 500);
    }
    await save();
    final source = AuthRemoteDatasource(
        client: MockClient((_) async => throw Exception('offline')));
    expect((await source.logout()).isLeft(), isTrue);
    expect(await local.isAuth(), isTrue);
  });

  test(
      'Profile multipart uses own identity, image field and updates local cache preserving token',
      () async {
    await save();
    var requests = 0;
    final source = UserRemoteDatasource(client: MockClient((request) async {
      requests++;
      expect(request.method, 'POST');
      expect(request.url.path, '/api/api-user/edit');
      expect(request.body, contains('name="image"; filename="avatar.png"'));
      expect(request.body, contains('name="id"\r\n\r\n7'));
      return http.Response(
          jsonEncode({'data': User(id: 7, name: 'Nama Baru').toMap()}), 200);
    }));
    expect((await source.updateProfile(model(id: 99), 99)).isLeft(), isTrue);
    expect(
        (await source.updateProfile(
                model(
                    image: XFile.fromData(Uint8List(3),
                        name: 'bad.svg', path: 'bad.svg')),
                7))
            .isLeft(),
        isTrue);
    expect(requests, 0);
    expect(
        (await source.updateProfile(
                model(
                    image: XFile.fromData(Uint8List(3),
                        name: 'avatar.png', path: 'avatar.png')),
                7))
            .isRight(),
        isTrue);
    expect(requests, 1);
    expect((await local.getAuthData())?.user?.name, 'Nama Baru');
    expect((await local.getAuthData())?.token, 'fixture-token');
  });

  test('Validation and late profile responses cannot overwrite another session',
      () async {
    await save();
    final invalid = UserRemoteDatasource(
        client: MockClient((_) async => http.Response(
            jsonEncode({
              'errors': {
                'email': ['Email sudah digunakan.']
              }
            }),
            422)));
    expect((await invalid.updateProfile(model(), 7)).fold((e) => e, (_) => ''),
        'Email sudah digunakan.');
    expect((await local.getAuthData())?.user?.name, 'Nadia Putri');
    final pending = Completer<http.Response>();
    final started = Completer<void>();
    final source = UserRemoteDatasource(client: MockClient((_) {
      started.complete();
      return pending.future;
    }));
    final response = source.updateProfile(model(), 7);
    await started.future;
    await save(token: 'other-token', user: User(id: 88, name: 'Other'));
    pending.complete(
        http.Response(jsonEncode({'data': fixtureUser.toMap()}), 200));
    expect((await response).isLeft(), isTrue);
    expect((await local.getAuthData())?.user?.id, 88);
    expect((await local.getAuthData())?.token, 'other-token');
  });

  testWidgets(
      'Profile theme persists and supports narrow screen with enlarged text',
      (tester) async {
    tester.view.physicalSize = const Size(320, 640);
    tester.view.devicePixelRatio = 1;
    addTearDown(tester.view.resetPhysicalSize);
    addTearDown(tester.view.resetDevicePixelRatio);
    final theme = await controller();
    await tester.pumpWidget(app(
        theme,
        MediaQuery(
            data: const MediaQueryData(
                size: Size(320, 640), textScaler: TextScaler.linear(1.8)),
            child: ProfileDashboard(
                state: ProfileWorkspaceState(
                    user: fixtureUser, company: 'Perusahaan'),
                onRefresh: () async {},
                onEdit: () {},
                onLogout: () {}))));
    await tester.scrollUntilVisible(find.text('Terang'), 200,
        scrollable: find
            .descendant(
                of: find.byType(ListView), matching: find.byType(Scrollable))
            .first);
    await tester.pumpAndSettle();
    await tester.ensureVisible(find.text('Terang'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Terang'));
    await tester.pumpAndSettle();
    expect(theme.mode, ThemeMode.light);
    expect(ThemeController(await SharedPreferences.getInstance()).mode,
        ThemeMode.light);
    await tester.scrollUntilVisible(find.text('Keluar akun'), 200,
        scrollable: find
            .descendant(
                of: find.byType(ListView), matching: find.byType(Scrollable))
            .first);
    expect(tester.takeException(), isNull);
  });

  testWidgets(
      'Edit validates fields, handles gallery cancellation, blocks duplicate save and recovers',
      (tester) async {
    tester.view.physicalSize = const Size(390, 844);
    tester.view.devicePixelRatio = 1;
    addTearDown(tester.view.resetPhysicalSize);
    addTearDown(tester.view.resetDevicePixelRatio);
    final fixture = UpdateFixture();
    final bloc = UpdateUserBloc(fixture);
    addTearDown(bloc.close);
    var picks = 0;
    await tester.pumpWidget(app(
        await controller(),
        BlocProvider.value(
            value: bloc,
            child: UpdateProfilePage(
                user: fixtureUser,
                pickImage: () async {
                  picks++;
                  return null;
                }))));
    await tester.tap(find.text('Pilih foto'));
    await tester.pumpAndSettle();
    expect(picks, 1);
    await tester.enterText(find.byType(TextFormField).at(1), 'invalid');
    await tester.ensureVisible(find.text('Simpan perubahan'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Simpan perubahan'));
    await tester.pumpAndSettle();
    expect(find.text('Masukkan email yang valid.'), findsOneWidget);
    expect(fixture.calls, 0);
    await tester.enterText(
        find.byType(TextFormField).at(1), 'new@example.test');
    await tester.ensureVisible(find.text('Simpan perubahan'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Simpan perubahan'));
    await tester.pump();
    bloc.add(UpdateUserEvent.updateUser(model(), 7));
    await tester.pump();
    expect(fixture.calls, 1);
    fixture.result.complete(const Left('Email sudah digunakan.'));
    await tester.pumpAndSettle();
    expect(find.text('Email sudah digunakan.'), findsOneWidget);
    expect(tester.widget<FilledButton>(find.byType(FilledButton)).onPressed,
        isNotNull);
    await tester.pumpWidget(const SizedBox.shrink());
  });

  testWidgets(
      'Startup retries offline state and navigates only after verified session',
      (tester) async {
    final source = RestoreFixture();
    final theme = await controller();
    await tester.pumpWidget(app(
        theme,
        SplashPage(
            source: source,
            signedIn: const Scaffold(body: Text('Verified account')))));
    await tester.pumpAndSettle();
    expect(find.text('Akun belum dapat diperiksa'), findsOneWidget);
    await theme.setMode(ThemeMode.light);
    await tester.pumpAndSettle();
    expect(source.calls, 1);
    source.status = SessionCheck.companyMissing;
    await tester.tap(find.text('Coba lagi'));
    await tester.pumpAndSettle();
    expect(find.text('Akun belum terhubung perusahaan'), findsOneWidget);
    source.status = SessionCheck.active;
    await tester.tap(find.text('Coba lagi'));
    await tester.pumpAndSettle();
    expect(find.text('Verified account'), findsOneWidget);
    expect(source.calls, 3);
  });

  testWidgets(
      'App removes private routes after session expiry and preserves theme',
      (tester) async {
    await tester.pumpWidget(MyApp(themeController: await controller()));
    await tester.runAsync(() async {
      await Future<void>.delayed(const Duration(milliseconds: 20));
    });
    await tester.pumpAndSettle();
    expect(find.byType(LoginPage), findsOneWidget);
    final navigator = Navigator.of(tester.element(find.byType(LoginPage)));
    navigator.push(MaterialPageRoute<void>(
        builder: (_) => const Scaffold(body: Text('Private screen'))));
    await tester.pumpAndSettle();
    await tester.runAsync(() async {
      await save();
      await SessionEvents.expire('fixture-token');
    });
    await tester.pumpAndSettle();
    expect(find.text('Private screen'), findsNothing);
    expect(find.text('Sesi Anda berakhir. Silakan masuk kembali.'),
        findsOneWidget);
    expect(navigator.canPop(), isFalse);
    expect(await tester.runAsync(local.isAuth), isFalse);
    expect(Theme.of(tester.element(find.byType(LoginPage))).brightness,
        Brightness.dark);
    await tester.pumpWidget(const SizedBox());
  });
}
