import 'package:flutter/material.dart';
import '../../../data/datasources/submission_remote_datasource.dart';
import '../../../data/models/response/submission_record.dart';
import 'submissions/submission_list_page.dart';

class PermissionPage extends StatelessWidget {
  const PermissionPage({super.key, this.source});
  final SubmissionRemoteDatasource? source;
  @override
  Widget build(BuildContext context) =>
      SubmissionListPage(kind: SubmissionKind.permission, source: source);
}
