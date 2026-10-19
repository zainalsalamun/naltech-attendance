part of 'get_qrcode_checkout_bloc.dart';

@freezed
class GetQrcodeCheckoutState with _$GetQrcodeCheckoutState {
  const factory GetQrcodeCheckoutState.initial() = _Initial;
  const factory GetQrcodeCheckoutState.loading() = _Loading;
  const factory GetQrcodeCheckoutState.success(String qrCode) = _Success;
}
