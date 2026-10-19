import 'package:bloc/bloc.dart';
import 'package:freezed_annotation/freezed_annotation.dart';

part 'get_qrcode_checkout_event.dart';
part 'get_qrcode_checkout_state.dart';
part 'get_qrcode_checkout_bloc.freezed.dart';

class GetQrcodeCheckoutBloc
    extends Bloc<GetQrcodeCheckoutEvent, GetQrcodeCheckoutState> {
  GetQrcodeCheckoutBloc() : super(_Initial()) {
    on<_GetQrcodeCheckout>((event, emit) {
      emit(const _Loading());
      emit(_Success(event.barcode));
    });
    on<_Started>((event, emit) {
      emit(const _Initial());
    });
  }
}
