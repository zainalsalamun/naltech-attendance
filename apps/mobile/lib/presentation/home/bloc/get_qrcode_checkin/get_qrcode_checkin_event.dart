part of 'get_qrcode_checkin_bloc.dart';

@freezed
class GetQrcodeCheckinEvent with _$GetQrcodeCheckinEvent {
  const factory GetQrcodeCheckinEvent.started() = _Started;
  const factory GetQrcodeCheckinEvent.getQrcodeCheckin(String barcode, bool isCheckedIn) =
      _GetQrcodeCheckin;
}