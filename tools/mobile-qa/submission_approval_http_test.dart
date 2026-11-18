// Live datasource contract against the marked disposable loopback backend only.
import 'dart:convert';
import 'dart:io';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:shared_preferences/shared_preferences.dart';
import 'package:flutter_absensi_app/data/datasources/auth_local_datasource.dart';
import 'package:flutter_absensi_app/data/datasources/submission_remote_datasource.dart';
import 'package:flutter_absensi_app/data/models/response/auth_response_model.dart';
import 'package:flutter_absensi_app/data/models/response/submission_record.dart';
void main(){
 test('real HTTP status metadata and cancellation for three submission kinds',()async{
  final qa=Platform.environment['NALTECH_APPROVAL_QA_DIR']!;
  expect(File('$qa/.naltech-qa').existsSync(),true);
  final fixture=jsonDecode(File('$qa/fixture.json').readAsStringSync());
  SharedPreferences.setMockInitialValues({});HttpOverrides.global=null;
  final client=http.Client();addTearDown(client.close);
  final login=await client.post(Uri.parse('http://127.0.0.1:8018/api/login'),headers:{'Accept':'application/json','Content-Type':'application/json'},body:jsonEncode({'email':'face@example.test','password':fixture['password']}));expect(login.statusCode,200);
  await AuthLocalDatasource().saveAuthData(AuthResponseModel.fromJson(login.body));
  final source=SubmissionRemoteDatasource(client:client,baseUrl:'http://127.0.0.1:8018');var checks=0;
  for(final kind in SubmissionKind.values){
   final batch=await source.list(kind);expect(batch.isRight(),true);checks++;
   final rows=batch.getOrElse(()=>throw StateError('List unavailable')).records;
   final approved=rows.firstWhere((r)=>r.status=='approved');expect(approved.reviewerName,'HR Penguji');expect(approved.decisionTime,isNotNull);expect(approved.decisionReason!.isNotEmpty,true);checks+=3;
   final detail=await source.detail(approved);expect(detail.isRight(),true);expect(detail.getOrElse(()=>throw StateError('Detail unavailable')).status,'approved');checks++;
   final created=await source.submit(kind,date:'2026-10-21',description:'Pengajuan live QA',amount:kind.isReimbursement?'125.50':null,duration:kind.isOvertime?90:null);expect(created.isRight(),true);checks++;
   final row=created.getOrElse(()=>throw StateError('Create unavailable'));expect(row.canCancel,true);
   final cancelled=await source.cancel(row,'Pembatalan live QA');expect(cancelled.isRight(),true);checks++;
   final latest=await source.detail(row);expect(latest.isRight(),true);expect(latest.getOrElse(()=>throw StateError('Reload unavailable')).status,'cancelled');checks++;
   expect((await source.cancel(row,'Replay')).isLeft(),true);checks++;
  }
  await File('${Directory.current.parent.parent.path}/docs/mobile-approval-http-qa.json').writeAsString(jsonEncode({'scope':'Real Flutter datasource to disposable SQLite/storage over loopback HTTP; no native device','checks':checks,'passed':true}));
 });
}
