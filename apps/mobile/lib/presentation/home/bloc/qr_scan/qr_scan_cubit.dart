import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:intl/intl.dart';
import '../../../../data/models/response/qr_absen_remote_datasource.dart';

class QrScanState {
  const QrScanState({this.busy = false, this.valid = false, this.error});
  final bool busy, valid;
  final String? error;
}

class QrScanCubit extends Cubit<QrScanState> {
  QrScanCubit(this.source, {DateTime Function()? now, this.serverDate})
      : now = now ?? DateTime.now,
        super(const QrScanState());
  final String? serverDate;
  final QrAbsenRemoteDatasource source;
  final DateTime Function() now;
  Future<bool> scan(String code, bool checkin) async {
    if (isClosed || state.busy || state.valid) {
      return false;
    }
    if (code.isEmpty || code.length > 255) {
      emit(const QrScanState(
          error: 'QR tidak terbaca atau formatnya tidak sesuai.'));
      return false;
    }
    emit(const QrScanState(busy: true));
    try {
      final result = await source.checkQr(
          code,
          serverDate ?? DateFormat('yyyy-MM-dd').format(now()),
          checkin ? 'qr_checkin' : 'qr_checkout');
      if (isClosed) {
        return false;
      }
      final valid = result.fold((_) => false, (value) => value);
      final error = result.fold((message) => message,
          (_) => 'QR tidak valid untuk tanggal atau jenis absensi ini.');
      emit(QrScanState(valid: valid, error: valid ? null : error));
      return valid;
    } catch (_) {
      if (!isClosed) {
        emit(const QrScanState(
            error:
                'QR belum dapat diperiksa. Periksa koneksi lalu pindai ulang.'));
      }
      return false;
    }
  }

  void retry() {
    if (!isClosed && !state.busy) {
      emit(const QrScanState());
    }
  }
}
