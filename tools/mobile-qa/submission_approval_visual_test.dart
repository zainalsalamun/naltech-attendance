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
void main(){
 testWidgets('export mobile decisions light dark and inspect narrow large text',(tester)async{
  final output=Platform.environment['NALTECH_APPROVAL_VISUAL_DIR']!;
  await tester.runAsync(()async{
   await Directory(output).create(recursive:true);
   final font=FontLoader('ApprovalQaArial')..addFont(File('/System/Library/Fonts/Supplemental/Arial.ttf').readAsBytes().then(ByteData.sublistView));await font.load();
   final icons=FontLoader('MaterialIcons')..addFont(File('/Users/macbookpro/development/flutter/bin/cache/artifacts/material_fonts/MaterialIcons-Regular.otf').readAsBytes().then(ByteData.sublistView));await icons.load();
  });
  tester.view.physicalSize=const Size(390,900);tester.view.devicePixelRatio=1;addTearDown(tester.view.resetPhysicalSize);addTearDown(tester.view.resetDevicePixelRatio);
  for(final dark in [false,true]){
   for(final status in ['pending','approved','rejected','cancelled','needs_review']){
    final key=GlobalKey();final theme=dark?WorkspaceTheme.dark:WorkspaceTheme.light;
    final record=SubmissionRecord(id:21,userId:7,date:'2026-10-21',description:'Keperluan keluarga. Mohon izin tidak masuk pada tanggal yang diajukan.',kind:SubmissionKind.permission,status:status,approvalVersion:status=='pending'?0:1,reviewerName:status=='pending'||status=='needs_review'?null:'HR Naltech',decisionTime:status=='pending'||status=='needs_review'?null:DateTime.utc(2026,10,21,8,30),decisionReason:status=='pending'||status=='needs_review'?null:status=='cancelled'?'Keperluan dibatalkan oleh pemohon.':'Dokumen dan keterangan sudah diperiksa oleh pengelola.');
    await tester.pumpWidget(RepaintBoundary(key:key,child:MaterialApp(debugShowCheckedModeBanner:false,theme:theme.copyWith(textTheme:theme.textTheme.apply(fontFamily:'ApprovalQaArial')),home:SubmissionDetailPage(key:ValueKey('$status-$dark'),record:record,source:VisualDecisions()))));await tester.pumpAndSettle();expect(tester.takeException(),isNull);
    Future<void> save(String name)async{final boundary=key.currentContext!.findRenderObject()! as RenderRepaintBoundary;await tester.runAsync(()async{final image=await boundary.toImage(pixelRatio:2);final bytes=await image.toByteData(format:ui.ImageByteFormat.png);await File('$output/$name.png').writeAsBytes(bytes!.buffer.asUint8List());image.dispose();});}
    await save('permission-$status-${dark?'dark':'light'}-widget');
    if(status=='pending'){
     await tester.scrollUntilVisible(find.text('Batalkan pengajuan'),150,scrollable:find.descendant(of:find.byType(ListView),matching:find.byType(Scrollable)).first);await tester.tap(find.text('Batalkan pengajuan'));await tester.pumpAndSettle();await save('cancel-dialog-${dark?'dark':'light'}-widget');
    }
   }
  }
  tester.view.physicalSize=const Size(320,640);
  await tester.pumpWidget(MaterialApp(theme:WorkspaceTheme.dark,home:MediaQuery(data:const MediaQueryData(size:Size(320,640),textScaler:TextScaler.linear(1.8)),child:SubmissionDetailPage(record:const SubmissionRecord(id:22,userId:7,date:'2026-10-21',description:'Pekerjaan lembur untuk penyelesaian laporan.',kind:SubmissionKind.overtime,status:'pending',approvalVersion:0,duration:90),source:VisualDecisions()))));await tester.pumpAndSettle();expect(tester.takeException(),isNull);
 });
}
