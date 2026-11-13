import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:freezed_annotation/freezed_annotation.dart';

import '../../../../data/datasources/user_remote_datasource.dart';
import '../../../../data/models/response/auth_response_model.dart';

part 'get_user_bloc.freezed.dart';
part 'get_user_event.dart';
part 'get_user_state.dart';

class GetUserBloc extends Bloc<GetUserEvent, GetUserState> {
  final UserRemoteDatasource datasource;
  GetUserBloc(
    this.datasource,
  ) : super(const _Initial()) {
    on<_GetUser>(
      (event, emit) async {
        if (state is _Loading) return;
        emit(const _Loading());
        try {
          final result = await datasource.getUser();
          result.fold(
            (l) => emit(_Error(l)),
            (r) => emit(_Success(r)),
          );
        } catch (_) {
          emit(const _Error(
              'Profil belum dapat diproses. Periksa koneksi dan coba lagi.'));
        }
      },
    );
  }
}
