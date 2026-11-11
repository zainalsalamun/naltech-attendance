import 'dart:async';
import 'dart:convert';
import 'dart:io';
import 'dart:typed_data';
import 'package:dartz/dartz.dart';
import 'package:http/http.dart' as http;
import 'package:image_picker/image_picker.dart';
import '../../core/constants/variables.dart';
import '../../core/session/authenticated_client.dart';
import '../../core/session/session_events.dart';
import '../models/response/submission_record.dart';
import '../models/response/leave_policy_record.dart';
import 'auth_local_datasource.dart';

class SubmissionRemoteDatasource {
  SubmissionRemoteDatasource(
      {http.Client? client, AuthLocalDatasource? local, String? baseUrl})
      : _client = client ?? SessionHttp.client,
        _local = local ?? AuthLocalDatasource(),
        _baseUrl = baseUrl ?? Variables.baseUrl;
  final String _baseUrl;
  final http.Client _client;
  final AuthLocalDatasource _local;
  AuthLocalDatasource get local => _local;

  Future<Either<String, List<LeavePolicyRecord>>> leaveTypes() async {
    try {
      final auth = await _local.getAuthData();
      if (auth?.token?.isNotEmpty != true || auth?.user?.id == null) {
        return const Left('Sesi tidak tersedia. Silakan masuk kembali.');
      }
      final rows = <LeavePolicyRecord>[];
      final ids = <int>{};
      for (var page = 1; page <= 100; page++) {
        final request = http.Request(
            'GET', Uri.parse('$_baseUrl/api/leave-types?page=$page'))
          ..followRedirects = false
          ..headers.addAll({
            'Accept': 'application/json',
            'Authorization': 'Bearer ${auth!.token}'
          });
        final response = await http.Response.fromStream(await _client
                .send(request)
                .timeout(const Duration(seconds: 20)))
            .timeout(const Duration(seconds: 20));
        if (response.statusCode != 200) {
          return Left(response.statusCode == 503
              ? 'Jenis cuti belum tersedia. Hubungi HR atau coba muat ulang.'
              : await _error(response, auth.token!));
        }
        if ((await _local.getAuthData())?.token != auth.token) {
          return const Left('Sesi berubah. Buka ulang formulir akun aktif.');
        }
        final data = jsonDecode(response.body);
        if (data is! Map || data['data'] is! List || data['meta'] is! Map) {
          throw const FormatException();
        }
        final meta = data['meta'] as Map;
        final last = meta['last_page'];
        if (meta['current_page'] != page ||
            last is! int ||
            last < page ||
            last > 100 ||
            ((data['data'] as List).isEmpty && last > page)) {
          throw const FormatException();
        }
        for (final raw in data['data']) {
          final row = LeavePolicyRecord.parse(raw);
          if (!ids.add(row.id)) {
            throw const FormatException();
          }
          rows.add(row);
        }
        if (page == last) {
          return Right(rows);
        }
      }
      throw const FormatException();
    } catch (_) {
      return const Left(
          'Jenis cuti belum dapat dimuat. Periksa koneksi lalu coba lagi.');
    }
  }

  Future<Either<String, Uint8List>> attachment(SubmissionRecord record) async {
    try {
      final auth = await _local.getAuthData();
      if (auth?.token?.isNotEmpty != true || auth?.user?.id != record.userId) {
        return const Left('Sesi berubah. Buka ulang pengajuan akun aktif.');
      }
      final kind = record.kind.isPermission ? 'permission' : 'reimbursement';
      // Never send authorization to a URL supplied in an attachment field.
      final request = http.Request('GET',
          Uri.parse('$_baseUrl/api/submission-attachments/$kind/${record.id}'))
        ..followRedirects = false
        ..headers.addAll({
          'Accept': 'application/json',
          'Authorization': 'Bearer ${auth!.token}'
        });
      final response =
          await _client.send(request).timeout(const Duration(seconds: 20));
      if (response.statusCode != 200) {
        await response.stream.drain<void>();
        if (response.statusCode == 401) {
          await SessionEvents.expire(auth.token!);
          return const Left('Sesi berakhir. Silakan masuk kembali.');
        }
        if (response.statusCode == 403) {
          return const Left('Akses lampiran ditolak. Hubungi pengelola.');
        }
        return const Left('Lampiran belum dapat diakses. Hubungi pengelola.');
      }
      final mime = response.headers['content-type']?.split(';').first;
      if (!['image/jpeg', 'image/png', 'image/gif'].contains(mime)) {
        await response.stream.drain<void>();
        return const Left('Format lampiran belum dapat ditampilkan.');
      }
      final bytes =
          await response.stream.toBytes().timeout(const Duration(seconds: 20));
      if (bytes.length > 2 * 1024 * 1024) throw const FormatException();
      final currentAuth = await _local.getAuthData();
      if (bytes.isEmpty ||
          currentAuth?.token != auth.token ||
          currentAuth?.user?.id != record.userId) {
        return const Left(
            'Sesi akun telah berganti. Buka ulang pengajuan akun aktif.');
      }
      return Right(bytes);
    } on TimeoutException {
      return const Left('Koneksi batas waktu habis. Periksa jaringan Anda.');
    } on SocketException {
      return const Left(
          'Gagal terhubung ke server. Periksa koneksi internet Anda.');
    } on http.ClientException {
      return const Left(
          'Gagal terhubung ke server. Periksa koneksi internet Anda.');
    } catch (_) {
      return const Left(
          'Gagal memuat lampiran. Periksa koneksi internet Anda.');
    }
  }

  Future<Either<String, SubmissionRecord>> detail(SubmissionRecord record) =>
      _recordRequest(record);

  Future<Either<String, SubmissionRecord>> cancel(
          SubmissionRecord record, String reason,
          {bool requestCancellation = false}) =>
      _recordRequest(record,
          cancellationReason: reason, requestCancellation: requestCancellation);

  Future<Either<String, SubmissionRecord>> _recordRequest(
      SubmissionRecord record,
      {String? cancellationReason,
      bool requestCancellation = false}) async {
    try {
      final auth = await _local.getAuthData();
      if (auth?.token?.isNotEmpty != true || auth?.user?.id != record.userId) {
        return const Left('Sesi berubah. Buka ulang pengajuan akun aktif.');
      }
      final cancelling = cancellationReason != null;
      if (cancelling &&
          ((!record.canCancel &&
                  (!requestCancellation || !record.canRequestCancel)) ||
              cancellationReason.trim().isEmpty ||
              cancellationReason.trim().length > 1000)) {
        return const Left(
            'Muat ulang pengajuan dan isi alasan pembatalan (maksimal 1.000 karakter).');
      }
      final endpoint = cancelling
          ? 'submission-cancellations/${record.kind.apiKind}/${record.id}'
          : '${record.kind.endpoint}/${record.id}';
      final request = http.Request(
          cancelling ? 'POST' : 'GET', Uri.parse('$_baseUrl/api/$endpoint'))
        ..followRedirects = false
        ..headers.addAll({
          'Accept': 'application/json',
          'Authorization': 'Bearer ${auth!.token}'
        });
      if (cancelling) {
        request.headers['Content-Type'] = 'application/json';
        final Map<String, dynamic> body = {
          'approval_version': record.approvalVersion,
          'decision_reason': cancellationReason.trim()
        };
        if (requestCancellation && record.canRequestCancel) {
          body['request_cancellation'] = true;
        }
        request.body = jsonEncode(body);
      }
      final response = await http.Response.fromStream(
              await _client.send(request).timeout(const Duration(seconds: 20)))
          .timeout(const Duration(seconds: 20));
      if (response.statusCode != 200) {
        return Left(await _error(response, auth.token!));
      }
      final payload = jsonDecode(response.body);
      final result = SubmissionRecord.parse(
          payload['data'] as Map<String, dynamic>, record.kind, auth.user!.id!);
      if (result.id != record.id ||
          (cancelling &&
              (!['cancelled', 'cancellation_requested']
                      .contains(result.status) ||
                  result.approvalVersion != record.approvalVersion! + 1))) {
        throw const FormatException();
      }
      if ((await _local.getAuthData())?.token != auth.token) {
        return const Left('Sesi berubah. Buka ulang pengajuan akun aktif.');
      }
      return Right(result);
    } catch (_) {
      return const Left(
          'Hasil belum dapat dipastikan. Muat ulang detail sebelum mencoba pembatalan lagi.');
    }
  }

  Future<String> _error(http.Response response, String token) async {
    if (response.statusCode == 401) {
      await SessionEvents.expire(token);
      return 'Sesi berakhir. Silakan masuk kembali.';
    }
    if (response.statusCode == 403) {
      return 'Akses perusahaan ditolak. Hubungi pengelola.';
    }
    if (response.statusCode == 409) {
      return 'Pengajuan sudah berubah atau sudah final. Detail terbaru akan dimuat ulang.';
    }
    if (response.statusCode == 503) {
      return 'Layanan approval belum tersedia. Hubungi pengelola.';
    }
    if (response.statusCode == 422) {
      final data = jsonDecode(response.body);
      final errors = data is Map ? data['errors'] : null;
      if (errors is Map) {
        for (final field in [
          'date',
          'reason',
          'description',
          'amount',
          'image',
          'duration',
          'decision_reason',
          'approval_version',
          'leave_type_id'
        ]) {
          final error = errors[field];
          if (error is List && error.isNotEmpty && error.first is String) {
            return switch (field) {
              'duration' =>
                'Isi durasi dalam menit berupa bilangan bulat positif.',
              'decision_reason' =>
                'Alasan pembatalan wajib diisi, maksimal 1.000 karakter.',
              'approval_version' =>
                'Muat ulang detail pengajuan sebelum membatalkan.',
              'date' => 'Tanggal pengajuan belum valid. Pilih kembali tanggal.',
              'amount' =>
                'Nominal harus lebih dari nol, maksimal 9.999.999.999,99 dan dua angka desimal.',
              'image' => 'Gunakan lampiran JPG/PNG maksimal 2 MB.',
              _ => 'Keterangan wajib diisi dan maksimal 5.000 karakter.',
            };
          }
        }
      }
      return 'Periksa tanggal, keterangan, nominal dan lampiran Anda.';
    }
    return 'Pengajuan belum dapat diproses. Muat ulang daftar sebelum mencoba lagi.';
  }

  Future<Either<String, SubmissionBatch>> list(SubmissionKind kind,
      {int page = 1}) async {
    try {
      final auth = await _local.getAuthData();
      if (auth?.token?.isNotEmpty != true || auth?.user?.id == null) {
        return const Left('Sesi tidak tersedia. Silakan masuk kembali.');
      }
      final response = await _client.get(
          Uri.parse('$_baseUrl/api/${kind.endpoint}?page=$page'),
          headers: {
            'Accept': 'application/json',
            'Authorization': 'Bearer ${auth!.token}'
          }).timeout(const Duration(seconds: 20));
      if (response.statusCode != 200) {
        return Left(await _error(response, auth.token!));
      }
      final data = jsonDecode(response.body);
      if (data is! Map || data['data'] is! List) throw const FormatException();
      final records = (data['data'] as List)
          .map((row) => SubmissionRecord.parse(
              row as Map<String, dynamic>, kind, auth.user!.id!))
          .toList();
      final meta = data['meta'];
      // A legacy unpaginated response is accepted only as the first and final page.
      final current = meta is Map ? meta['current_page'] : 1;
      final last = meta is Map ? meta['last_page'] : 1;
      if (current != page ||
          last is! int ||
          last < page ||
          records.map((e) => e.id).toSet().length != records.length) {
        throw const FormatException();
      }
      if ((await _local.getAuthData())?.token != auth.token) {
        return const Left('Sesi berubah. Muat ulang pengajuan.');
      }
      return Right(SubmissionBatch(records, page: page, lastPage: last));
    } catch (_) {
      return const Left(
          'Daftar belum dapat dimuat. Periksa koneksi lalu coba lagi.');
    }
  }

  Future<Either<String, Map<String, dynamic>>> calculateLeave(
      String startDate, String endDate, int? leaveTypeId) async {
    try {
      final auth = await _local.getAuthData();
      if (auth?.token?.isNotEmpty != true) {
        return const Left('Sesi tidak tersedia.');
      }
      final response = await _client
          .post(Uri.parse('$_baseUrl/api/leave-calculate'),
              headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'Authorization': 'Bearer ${auth!.token}'
              },
              body: jsonEncode({
                'start_date': startDate,
                'end_date': endDate,
                if (leaveTypeId != null) 'leave_type_id': leaveTypeId
              }))
          .timeout(const Duration(seconds: 15));

      if ((await _local.getAuthData())?.token != auth.token) {
        return const Left('Sesi berubah. Muat ulang sebelum melanjutkan.');
      }
      final data = jsonDecode(response.body);
      if (response.statusCode != 200) {
        final errors = data is Map ? data['errors'] : null;
        if (errors is Map) {
          final messages = errors.values
              .expand((value) => value is List ? value : [value])
              .whereType<String>()
              .toList();
          if (messages.isNotEmpty) return Left(messages.join(' '));
        }
        return const Left(
            'Perhitungan belum dapat dimuat. Periksa koneksi atau sesi, lalu hitung ulang.');
      }
      if (data is Map && data['data'] is Map) {
        final result = Map<String, dynamic>.from(data['data']);
        final calc = result['calculation'];
        final balance = result['balance'];
        if (calc is! Map ||
            calc['calendar_complete'] is! bool ||
            calc['working_days_count'] is! int ||
            calc['total_calendar_days'] is! int ||
            calc['skipped_days_count'] is! int) {
          throw const FormatException();
        }
        if (balance != null &&
            (balance is! Map || balance['sufficient'] is! bool)) {
          throw const FormatException();
        }
        if (balance is Map && balance['not_allocated'] != true) {
          for (final field in [
            'allocated_days',
            'reserved_days',
            'used_days',
            'remaining_days',
            'remaining_after'
          ]) {
            if (balance[field] is! int) throw const FormatException();
          }
        }
        return Right(result);
      }
      return const Left('Format respons kalkulasi tidak sesuai.');
    } catch (_) {
      return const Left('Tidak dapat menghubungkan ke server kalkulasi.');
    }
  }

  Future<Either<String, List<Map<String, dynamic>>>> leaveBalances() async {
    try {
      final auth = await _local.getAuthData();
      if (auth?.token?.isNotEmpty != true) {
        return const Left('Sesi tidak tersedia.');
      }
      final response = await _client
          .get(Uri.parse('$_baseUrl/api/leave-balances'), headers: {
        'Accept': 'application/json',
        'Authorization': 'Bearer ${auth!.token}'
      }).timeout(const Duration(seconds: 15));

      if (response.statusCode != 200) {
        return const Left('Gagal memuat saldo cuti.');
      }
      final data = jsonDecode(response.body);
      if (data is Map && data['data'] is List) {
        return Right(
            (data['data'] as List).whereType<Map<String, dynamic>>().toList());
      }
      return const Left('Format saldo cuti tidak sesuai.');
    } catch (_) {
      return const Left('Tidak dapat memuat saldo cuti.');
    }
  }

  Future<Either<String, SubmissionRecord>> submit(SubmissionKind kind,
      {required String date,
      String? startDate,
      String? endDate,
      required String description,
      String? amount,
      int? duration,
      LeavePolicyRecord? leaveType,
      XFile? image}) async {
    try {
      if (leaveType != null &&
          (!kind.isPermission ||
              (leaveType.requiresAttachment && image == null))) {
        return const Left(
            'Jenis cuti ini memerlukan lampiran. Pilih bukti sebelum mengirim.');
      }
      final primaryDate = startDate ?? date;
      if (submissionDate(primaryDate) == null ||
          description.trim().isEmpty ||
          description.trim().length > 5000) {
        return const Left(
            'Periksa tanggal dan keterangan (maksimal 5.000 karakter).');
      }
      if (kind.isReimbursement &&
          (amount == null ||
              submissionMoney(amount) == 'Nominal belum tersedia')) {
        return const Left(
            'Nominal harus lebih dari nol dan maksimal Rp 9.999.999.999,99.');
      }
      if (kind.isOvertime &&
          (duration == null || duration < 1 || duration > 2147483647)) {
        return const Left(
            'Isi durasi lembur dalam menit (bilangan bulat positif).');
      }
      final auth = await _local.getAuthData();
      if (auth?.token?.isNotEmpty != true || auth?.user?.id == null) {
        return const Left('Sesi tidak tersedia. Silakan masuk kembali.');
      }
      final fields = <String, String>{
        'date': primaryDate,
        if (startDate != null) 'start_date': startDate,
        if (endDate != null) 'end_date': endDate,
        kind.descriptionKey: description.trim(),
        if (kind.isReimbursement) 'amount': amount!,
        if (kind.isOvertime) 'duration': duration.toString(),
        if (leaveType != null) 'leave_type_id': leaveType.id.toString()
      };
      final request = http.MultipartRequest(
          'POST', Uri.parse('$_baseUrl/api/${kind.endpoint}'))
        ..followRedirects = false
        ..headers.addAll({
          'Accept': 'application/json',
          'Authorization': 'Bearer ${auth!.token}'
        })
        ..fields.addAll(fields);
      if (image != null && !kind.isOvertime) {
        if (!['jpg', 'jpeg', 'png']
                .contains(image.name.toLowerCase().split('.').last) ||
            await image.length() > 2 * 1024 * 1024) {
          return const Left('Gunakan lampiran JPG/PNG maksimal 2 MB.');
        }
        request.files.add(http.MultipartFile.fromBytes(
            'image', await image.readAsBytes(),
            filename: image.name));
      }
      final streamed =
          await _client.send(request).timeout(const Duration(seconds: 20));
      final response = await http.Response.fromStream(streamed)
          .timeout(const Duration(seconds: 15));
      if (response.statusCode != 201) {
        return Left(await _error(response, auth.token!));
      }
      final data = jsonDecode(response.body);
      final record = SubmissionRecord.parse(
          data['data'] as Map<String, dynamic>, kind, auth.user!.id!);
      if ((kind.isPermission && record.leavePolicy?.id != leaveType?.id) ||
          (kind.isOvertime && record.duration != duration) ||
          (record.date != primaryDate && record.startDate != primaryDate) ||
          record.description != description.trim() ||
          (kind.isReimbursement &&
              record.amount != amount &&
              num.tryParse(record.amount ?? '') != num.tryParse(amount!))) {
        throw const FormatException();
      }
      if ((await _local.getAuthData())?.token != auth.token) {
        return const Left('Sesi berubah. Periksa daftar pengajuan akun aktif.');
      }
      return Right(record);
    } catch (_) {
      return const Left(
          'Hasil pengiriman belum dapat dipastikan. Kembali dan muat ulang daftar sebelum mengirim ulang.');
    }
  }
}
