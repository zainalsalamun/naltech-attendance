import 'package:dartz/dartz.dart';
import 'package:image_picker/image_picker.dart';
import '../models/response/submission_record.dart';
import '../models/response/reimbursement_response_model.dart';
import 'submission_remote_datasource.dart';

/// Compatibility adapter for the older Bloc entry points; the Workspace list
/// uses SubmissionRemoteDatasource directly to retain pagination metadata.
class ReimbursementRemoteDatasource {
  Future<Either<String, ReimbursementResponseModel>> getReimbursements() async {
    final result =
        await SubmissionRemoteDatasource().list(SubmissionKind.reimbursement);
    return result.map((batch) => ReimbursementResponseModel(
        data: batch.records
            .map((row) => Reimbursement(
                id: row.id,
                userId: row.userId,
                date: row.date,
                description: row.description,
                amount: row.amount,
                status: row.status,
                image: row.image))
            .toList()));
  }

  Future<Either<String, void>> addReimbursement(
      String date, String description, String amount, XFile? image) async {
    final result = await SubmissionRemoteDatasource().submit(
        SubmissionKind.reimbursement,
        date: date,
        description: description,
        amount: amount,
        image: image);
    return result.map((_) {});
  }
}
