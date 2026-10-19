part of 'check_qr_bloc.dart';

@freezed
class CheckQrState with _$CheckQrState {
  const factory CheckQrState.initial() = _Initial;
  const factory CheckQrState.loading() = _Loading;
  const factory CheckQrState.success(bool isValid) = _Success;
  const factory CheckQrState.error(String error) = _Error;
}
