part of 'add_reimbursement_bloc.dart';

@freezed
class AddReimbursementEvent with _$AddReimbursementEvent {
  const factory AddReimbursementEvent.started() = _Started;
  const factory AddReimbursementEvent.addReimbursement(
          String date, String description, String amount, XFile? image) =
      _AddReimbursement;
}
