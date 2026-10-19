import 'package:bloc/bloc.dart';
import 'package:freezed_annotation/freezed_annotation.dart';

import 'package:flutter_absensi_app/data/models/response/qr_absen_remote_datasource.dart';

part 'check_qr_bloc.freezed.dart';
part 'check_qr_event.dart';
part 'check_qr_state.dart';

class CheckQrBloc extends Bloc<CheckQrEvent, CheckQrState> {
  final QrAbsenRemoteDatasource qrAbsenRemoteDatasource;
  CheckQrBloc(
    this.qrAbsenRemoteDatasource,
  ) : super(_Initial()) {
    on<_CheckQr>((event, emit) async {
      emit(const _Loading());
      try {
        final result = await qrAbsenRemoteDatasource.checkQr(
          event.qrCode,
          event.date,
          event.typeQr,
        );
        result.fold(
          (l) => emit(_Error(l)),
          (r) => emit(_Success(r)),
        );
      } catch (e) {
        emit(_Error(e.toString()));
      }
    });

    on<_Started>((event, emit) async {
      emit(const _Initial());
    });
  }
}
