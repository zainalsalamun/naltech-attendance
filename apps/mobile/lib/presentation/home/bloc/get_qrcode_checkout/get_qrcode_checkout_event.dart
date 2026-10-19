part of 'get_qrcode_checkout_bloc.dart';

@freezed
class GetQrcodeCheckoutEvent with _$GetQrcodeCheckoutEvent {
  const factory GetQrcodeCheckoutEvent.started() = _Started;
  const factory GetQrcodeCheckoutEvent.getQrcodeCheckout(String barcode) =
      _GetQrcodeCheckout;
}