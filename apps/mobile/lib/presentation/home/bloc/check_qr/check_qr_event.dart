part of 'check_qr_bloc.dart';

@freezed
class CheckQrEvent with _$CheckQrEvent {
  const factory CheckQrEvent.started() = _Started;
  const factory CheckQrEvent.checkQr(
      String qrCode, String date, String typeQr) = _CheckQr;
}