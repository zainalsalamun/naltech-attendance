// Widget-only evidence from synthetic decisions, not a native device capture.
import 'dart:io';
import 'dart:ui' as ui;
import 'package:dartz/dartz.dart' show Either, Right;
import 'package:flutter/material.dart';
import 'package:flutter/rendering.dart';
import 'package:flutter/services.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:flutter_absensi_app/core/theme/workspace_theme.dart';
import 'package:flutter_absensi_app/data/datasources/submission_remote_datasource.dart';
import 'package:flutter_absensi_app/data/models/response/submission_record.dart';
import 'package:flutter_absensi_app/presentation/home/pages/submissions/submission_list_page.dart';
class VisualDecisions extends SubmissionRemoteDatasource {
 @override Future<Either<String,SubmissionRecord>> detail(SubmissionRecord record) async => Right(record);
}
void main() {
 testWidgets('workflow detail light dark and review history', (tester) async {
  final output=Platform.environment['NALTECH_APPROVAL_VISUAL_DIR']!;
  await tester.runAsync(() async {
   await Directory(output).create(recursive:true);
   final font=FontLoader('ApprovalQaArial')..addFont(File('/System/Library/Fonts/Supplemental/Arial.ttf').readAsBytes().then(ByteData.sublistView));await font.load();
   final icons=FontLoader('MaterialIcons')..addFont(File('/Users/macbookpro/development/flutter/bin/cache/artifacts/material_fonts/MaterialIcons-Regular.otf').readAsBytes().then(ByteData.sublistView));await icons.load();
  });
  tester.view.physicalSize=const Size(390,1000);tester.view.devicePixelRatio=1;
  addTearDown(tester.view.resetPhysicalSize);addTearDown(tester.view.resetDevicePixelRatio);
  for(final dark in [false,true]) {
   final theme=dark?WorkspaceTheme.dark:WorkspaceTheme.light;final key=GlobalKey();
   final record=SubmissionRecord.parse({'id':21,'user_id':7,'date_permission':'2026-10-21','reason':'Keperluan keluarga. Mohon izin tidak masuk.','status':'pending','approval_version':1,'reviewer':{'name':'Atasan Naltech'},'decision_time':'2026-10-21T08:00:00+07:00','decision_reason':'Dokumen sudah diperiksa.','approval_progress':{'current':2,'total':2,'label':'HR','steps':['Atasan langsung','HR']},'approval_current_reviewer':'HR Naltech','approval_history':[{'actor':'Atasan Naltech','action':'approved','stage':1,'reason':'Dokumen sudah diperiksa.','time':'2026-10-21T08:00:00+07:00'}]},SubmissionKind.permission,7);
   await tester.pumpWidget(RepaintBoundary(key:key,child:MaterialApp(debugShowCheckedModeBanner:false,theme:theme.copyWith(textTheme:theme.textTheme.apply(fontFamily:'ApprovalQaArial')),home:SubmissionDetailPage(key:ValueKey(dark),record:record,source:VisualDecisions()))));await tester.pumpAndSettle();expect(tester.takeException(),isNull);
   final boundary=key.currentContext!.findRenderObject()! as RenderRepaintBoundary;
   await tester.runAsync(()async{final image=await boundary.toImage(pixelRatio:2);final bytes=await image.toByteData(format:ui.ImageByteFormat.png);await File('$output/workflow-${dark?'dark':'light'}-widget.png').writeAsBytes(bytes!.buffer.asUint8List());image.dispose();});
  }
 });
}
