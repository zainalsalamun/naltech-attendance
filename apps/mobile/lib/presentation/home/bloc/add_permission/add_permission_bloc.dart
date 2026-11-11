import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:freezed_annotation/freezed_annotation.dart';
import 'package:image_picker/image_picker.dart';

import 'package:flutter_absensi_app/data/datasources/permisson_remote_datasource.dart';

part 'add_permission_bloc.freezed.dart';
part 'add_permission_event.dart';
part 'add_permission_state.dart';

class AddPermissionBloc extends Bloc<AddPermissionEvent, AddPermissionState> {
  final PermissonRemoteDatasource datasource;
  AddPermissionBloc(
    this.datasource,
  ) : super(const _Initial()) {
    on<_AddPermission>((event, emit) async {
      if (state is _Loading) return;
      emit(const _Loading());
      try {
        final result = await datasource.addPermission(
            event.date, event.reason, event.image);
        result.fold(
          (l) => emit(_Error(l)),
          (r) => emit(const _Success()),
        );
      } catch (_) {
        emit(const _Error(
            'Pengajuan belum dapat diproses. Muat ulang daftar lalu coba lagi.'));
      }
    });
  }
}
