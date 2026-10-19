import 'package:bloc/bloc.dart';
import 'package:freezed_annotation/freezed_annotation.dart';

import '../../../../../data/datasources/reimbursement_remote_datasource.dart';
import '../../../../../data/models/response/reimbursement_response_model.dart';

part 'get_reimbursements_bloc.freezed.dart';
part 'get_reimbursements_event.dart';
part 'get_reimbursements_state.dart';

class GetReimbursementsBloc
    extends Bloc<GetReimbursementsEvent, GetReimbursementsState> {
  final ReimbursementRemoteDatasource datasource;
  GetReimbursementsBloc(
    this.datasource,
  ) : super(const _Initial()) {
    on<_GetReimbursements>(
      (event, emit) async {
        emit(const _Loading());

        final result = await datasource.getReimbursements();
        result.fold(
          (l) => emit(_Error(l)),
          (r) => emit(_Success(r.data ?? [])),
        );
      },
    );
  }
}
