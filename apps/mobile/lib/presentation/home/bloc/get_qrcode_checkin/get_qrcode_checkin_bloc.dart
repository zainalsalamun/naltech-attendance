import 'package:bloc/bloc.dart';
import 'package:freezed_annotation/freezed_annotation.dart';

part 'get_qrcode_checkin_event.dart';
part 'get_qrcode_checkin_state.dart';
part 'get_qrcode_checkin_bloc.freezed.dart';

class GetQrcodeCheckinBloc extends Bloc<GetQrcodeCheckinEvent, GetQrcodeCheckinState> {
  GetQrcodeCheckinBloc() : super(_Initial()) {
    on<_GetQrcodeCheckin>((event, emit) {
      emit(const _Loading());
      emit(_Success(event.barcode, event.isCheckedIn));
    });
    on<_Started>((event, emit) {
      emit(const _Initial());
    });
  }
}
