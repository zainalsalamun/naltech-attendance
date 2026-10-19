part of 'get_reimbursements_bloc.dart';

@freezed
class GetReimbursementsState with _$GetReimbursementsState {
  const factory GetReimbursementsState.initial() = _Initial;
  const factory GetReimbursementsState.loading() = _Loading;
  const factory GetReimbursementsState.success(
      List<Reimbursement> reimbursements) = _Success;
  const factory GetReimbursementsState.error(String message) = _Error;
}
