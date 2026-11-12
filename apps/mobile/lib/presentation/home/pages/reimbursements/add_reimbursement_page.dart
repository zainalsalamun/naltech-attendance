import 'package:flutter/material.dart';
import '../../../../data/models/response/submission_record.dart';
import '../submissions/submission_form_page.dart';

class AddReimbursementPage extends StatelessWidget {
  const AddReimbursementPage({super.key});
  @override
  Widget build(BuildContext context) =>
      const SubmissionFormPage(kind: SubmissionKind.reimbursement);
}
