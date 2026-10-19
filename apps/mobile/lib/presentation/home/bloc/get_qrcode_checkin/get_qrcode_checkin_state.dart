part of 'get_qrcode_checkin_bloc.dart';

@freezed
class GetQrcodeCheckinState with _$GetQrcodeCheckinState {
  const factory GetQrcodeCheckinState.initial() = _Initial;
  const factory GetQrcodeCheckinState.loading() = _Loading;

  const factory GetQrcodeCheckinState.success(String barcode, bool isCheckedIn) = _Success;
}
