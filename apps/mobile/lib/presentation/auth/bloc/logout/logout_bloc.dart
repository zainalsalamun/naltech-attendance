import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:freezed_annotation/freezed_annotation.dart';

import 'package:flutter_absensi_app/data/datasources/auth_remote_datasource.dart';

part 'logout_bloc.freezed.dart';
part 'logout_event.dart';
part 'logout_state.dart';

class LogoutBloc extends Bloc<LogoutEvent, LogoutState> {
  final AuthRemoteDatasource _authRemoteDatasource;
  LogoutBloc(
    this._authRemoteDatasource,
  ) : super(const _Initial()) {
    on<_Logout>((event, emit) async {
      if (state is _Loading) return;
      emit(const _Loading());
      try {
        final result = await _authRemoteDatasource.logout();
        result.fold(
          (l) => emit(_Error(l)),
          (r) => emit(const _Success()),
        );
      } catch (_) {
        emit(const _Error('Keluar akun belum dapat dikonfirmasi. Coba lagi.'));
      }
    });
  }
}
