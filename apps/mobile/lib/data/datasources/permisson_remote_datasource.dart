import 'package:dartz/dartz.dart';
import 'package:image_picker/image_picker.dart';
import '../models/response/submission_record.dart';
import 'submission_remote_datasource.dart';

class PermissonRemoteDatasource {
  Future<Either<String, String>> addPermission(
      String date, String reason, XFile? image) async {
    final result = await SubmissionRemoteDatasource().submit(
        SubmissionKind.permission,
        date: date,
        description: reason,
        image: image);
    return result.map((record) => 'Pengajuan #${record.id} berhasil dikirim.');
  }
}
