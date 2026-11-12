import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:dartz/dartz.dart';
import 'package:image_picker/image_picker.dart';
import '../../../../data/datasources/submission_remote_datasource.dart';
import '../../../../data/models/response/submission_record.dart';
import '../../../../data/models/response/leave_policy_record.dart';

class SubmissionListState {
  const SubmissionListState(
      {this.loading = false,
      this.moreLoading = false,
      this.records = const [],
      this.page = 0,
      this.lastPage = 1,
      this.error,
      this.moreError});
  final bool loading, moreLoading;
  final List<SubmissionRecord> records;
  final int page, lastPage;
  final String? error, moreError;
}

class SubmissionListCubit extends Cubit<SubmissionListState> {
  SubmissionListCubit(this.source, this.kind)
      : super(const SubmissionListState());
  final SubmissionRemoteDatasource source;
  final SubmissionKind kind;
  int _revision = 0;
  Future<Either<String, SubmissionBatch>> _fetch(int page) async {
    try {
      return await source.list(kind, page: page);
    } catch (_) {
      return const Left(
          'Daftar belum dapat dimuat. Periksa koneksi lalu coba lagi.');
    }
  }

  Future<void> refresh() async {
    final revision = ++_revision;
    emit(const SubmissionListState(loading: true));
    final result = await _fetch(1);
    if (isClosed || revision != _revision) return;
    result.fold(
        (error) => emit(SubmissionListState(error: error)),
        (batch) => emit(SubmissionListState(
            records: batch.records,
            page: batch.page,
            lastPage: batch.lastPage)));
  }

  Future<void> more() async {
    if (state.loading ||
        state.moreLoading ||
        state.page >= state.lastPage ||
        state.page == 0) {
      return;
    }
    final revision = _revision;
    final previous = state;
    emit(SubmissionListState(
        records: previous.records,
        page: previous.page,
        lastPage: previous.lastPage,
        moreLoading: true));
    final result = await _fetch(previous.page + 1);
    if (isClosed || revision != _revision) return;
    result.fold(
        (error) => emit(SubmissionListState(
            records: previous.records,
            page: previous.page,
            lastPage: previous.lastPage,
            moreError: error)), (batch) {
      final ids = previous.records.map((e) => e.id).toSet();
      emit(SubmissionListState(records: [
        ...previous.records,
        ...batch.records.where((e) => !ids.contains(e.id))
      ], page: batch.page, lastPage: batch.lastPage));
    });
  }
}

class SubmissionSendState {
  const SubmissionSendState({this.loading = false, this.record, this.error});
  final bool loading;
  final SubmissionRecord? record;
  final String? error;
}

class SubmissionSendCubit extends Cubit<SubmissionSendState> {
  SubmissionSendCubit(this.source, this.kind)
      : super(const SubmissionSendState());
  final SubmissionRemoteDatasource source;
  final SubmissionKind kind;
  Future<void> send(
      {required String date,
      String? startDate,
      String? endDate,
      required String description,
      String? amount,
      int? duration,
      LeavePolicyRecord? leaveType,
      XFile? image}) async {
    if (state.loading) return;
    emit(const SubmissionSendState(loading: true));
    try {
      final result = await source.submit(kind,
          date: date,
          startDate: startDate,
          endDate: endDate,
          description: description,
          amount: amount,
          duration: duration,
          leaveType: leaveType,
          image: image);
      if (isClosed) return;
      result.fold((error) => emit(SubmissionSendState(error: error)),
          (record) => emit(SubmissionSendState(record: record)));
    } catch (_) {
      if (!isClosed) {
        emit(const SubmissionSendState(
            error:
                'Hasil pengiriman belum dapat dipastikan. Muat ulang daftar sebelum mengirim ulang.'));
      }
    }
  }
}
