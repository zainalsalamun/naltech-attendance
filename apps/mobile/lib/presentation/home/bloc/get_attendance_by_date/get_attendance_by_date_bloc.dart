import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:freezed_annotation/freezed_annotation.dart';
import 'package:flutter_absensi_app/data/datasources/attendance_remote_datasource.dart';
import 'package:flutter_absensi_app/data/models/response/attendance_response_model.dart';
part 'get_attendance_by_date_bloc.freezed.dart';
part 'get_attendance_by_date_event.dart';
part 'get_attendance_by_date_state.dart';

class GetAttendanceByDateBloc
    extends Bloc<GetAttendanceByDateEvent, GetAttendanceByDateState> {
  final AttendanceRemoteDatasource datasource;
  String? _selectedDate;
  int _generation = 0;
  String? get selectedDate => _selectedDate;
  void refreshCurrent() {
    if (_selectedDate != null && !isClosed) {
      add(GetAttendanceByDateEvent.getAttendanceByDate(_selectedDate!));
    }
  }

  GetAttendanceByDateBloc(this.datasource) : super(const _Initial()) {
    on<_GetAttendanceByDate>((event, emit) async {
      _selectedDate = event.date;
      final generation = ++_generation;
      emit(const _Loading());
      final parsed = DateTime.tryParse(event.date);
      if (parsed == null ||
          event.date !=
              '${parsed.year.toString().padLeft(4, '0')}-${parsed.month.toString().padLeft(2, '0')}-${parsed.day.toString().padLeft(2, '0')}') {
        emit(const _Error('Tanggal riwayat tidak valid.'));
        return;
      }
      try {
        final result = await datasource
            .getAttendance(event.date)
            .timeout(const Duration(seconds: 20));
        if (generation != _generation) {
          return;
        }
        result.fold((message) => emit(_Error(message)), (response) {
          if (response.data == null) {
            emit(const _Error('Respons riwayat belum lengkap.'));
          } else if (response.data!.isEmpty) {
            emit(const _Empty());
          } else if (response.data!.length != 1 ||
              response.data!.single.date == null ||
              response.data!.single.date!.year != parsed.year ||
              response.data!.single.date!.month != parsed.month ||
              response.data!.single.date!.day != parsed.day) {
            emit(const _Error(
                'Catatan belum sesuai tanggal pilihan. Muat ulang riwayat.'));
          } else {
            emit(_Loaded(response.data!.single));
          }
        });
      } catch (_) {
        if (generation == _generation) {
          emit(const _Error('Riwayat belum dapat dimuat. Coba lagi.'));
        }
      }
    });
  }
}
