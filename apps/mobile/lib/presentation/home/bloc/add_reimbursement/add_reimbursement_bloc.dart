import 'package:bloc/bloc.dart';
import 'package:freezed_annotation/freezed_annotation.dart';
import 'package:image_picker/image_picker.dart';

import '../../../../../data/datasources/reimbursement_remote_datasource.dart';

part 'add_reimbursement_bloc.freezed.dart';
part 'add_reimbursement_event.dart';
part 'add_reimbursement_state.dart';

class AddReimbursementBloc
    extends Bloc<AddReimbursementEvent, AddReimbursementState> {
  final ReimbursementRemoteDatasource datasource;
  AddReimbursementBloc(
    this.datasource,
  ) : super(const _Initial()) {
    on<_AddReimbursement>(
      (event, emit) async {
        emit(const _Loading());

        final result = await datasource.addReimbursement(
            event.date, event.description, event.amount, event.image);
        result.fold(
          (l) => emit(_Error(l)),
          (r) => emit(const _Success()),
        );
      },
    );
  }
}
