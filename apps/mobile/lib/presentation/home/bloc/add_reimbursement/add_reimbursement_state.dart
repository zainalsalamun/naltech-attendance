part of 'add_reimbursement_bloc.dart';

@freezed
class AddReimbursementState with _$AddReimbursementState {
  const factory AddReimbursementState.initial() = _Initial;
  const factory AddReimbursementState.loading() = _Loading;
  const factory AddReimbursementState.success() = _Success;
  const factory AddReimbursementState.error(String message) = _Error;
}
