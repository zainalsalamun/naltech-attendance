part of 'get_reimbursements_bloc.dart';

@freezed
class GetReimbursementsEvent with _$GetReimbursementsEvent {
  const factory GetReimbursementsEvent.started() = _Started;
  const factory GetReimbursementsEvent.getReimbursements() = _GetReimbursements;
}
