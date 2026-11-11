import 'package:intl/intl.dart';
import 'leave_policy_record.dart';

enum SubmissionKind { permission, reimbursement, overtime }

extension SubmissionKindInfo on SubmissionKind {
  bool get isPermission => this == SubmissionKind.permission;
  bool get isReimbursement => this == SubmissionKind.reimbursement;
  bool get isOvertime => this == SubmissionKind.overtime;
  String get apiKind => name;
  String get title => switch (this) {
        SubmissionKind.permission => 'Izin',
        SubmissionKind.reimbursement => 'Reimbursement',
        SubmissionKind.overtime => 'Lembur',
      };
  String get endpoint => switch (this) {
        SubmissionKind.permission => 'api-permissions',
        SubmissionKind.reimbursement => 'api-reimbursements',
        SubmissionKind.overtime => 'api-overtimes',
      };
  String get descriptionKey => isPermission ? 'reason' : 'description';
}

DateTime? submissionDate(String? raw) {
  if (raw == null || !RegExp(r'^\d{4}-\d{2}-\d{2}$').hasMatch(raw)) return null;
  final date = DateTime.tryParse(raw);
  return date != null && DateFormat('yyyy-MM-dd').format(date) == raw
      ? date
      : null;
}

/// Indonesian user input to an exact decimal string, without floating point rounding.
String? submissionAmount(String raw) {
  final text = raw.trim();
  if (!RegExp(r'^(?:\d+|\d{1,3}(?:\.\d{3})+)(?:,\d{1,2})?$').hasMatch(text)) {
    return null;
  }
  final parts = text.replaceAll('.', '').split(',');
  final whole = int.tryParse(parts.first);
  if (whole == null || whole > 9999999999) return null;
  final cents = (parts.length == 2 ? parts.last : '').padRight(2, '0');
  if (whole == 0 && cents == '00') return null;
  return '$whole.$cents';
}

String submissionMoney(String? value) {
  if (value == null || !RegExp(r'^\d+(?:\.\d{1,2})?$').hasMatch(value)) {
    return 'Nominal belum tersedia';
  }
  final number = num.tryParse(value);
  if (number == null ||
      !number.isFinite ||
      number <= 0 ||
      number > 9999999999.99) {
    return 'Nominal belum tersedia';
  }
  return NumberFormat.currency(locale: 'id_ID', symbol: 'Rp ', decimalDigits: 2)
      .format(number);
}

class SubmissionProgress {
  const SubmissionProgress(this.current, this.total, this.label, this.steps);
  final int current, total;
  final String? label;
  final List<String> steps;
  static SubmissionProgress? parse(dynamic raw) {
    if (raw is! Map ||
        raw['current'] is! int ||
        raw['total'] is! int ||
        raw['steps'] is! List) {
      return null;
    }
    final current = raw['current'] as int, total = raw['total'] as int;
    final steps = (raw['steps'] as List).whereType<String>().toList();
    if (total < 1 ||
        total > 4 ||
        current < 1 ||
        current > total ||
        steps.length != total) {
      return null;
    }
    return SubmissionProgress(current, total,
        raw['label'] is String ? raw['label'] as String : null, steps);
  }
}

class SubmissionApprovalEvent {
  const SubmissionApprovalEvent(
      {required this.action,
      required this.reason,
      this.actionLabel,
      this.eventType,
      this.actor,
      this.time,
      this.rawTime,
      this.stage});
  final String action, reason;
  final String? actionLabel;
  final String? eventType;
  final String? actor;
  final DateTime? time;
  final String? rawTime;
  final int? stage;
  String get label {
    if (actionLabel != null && actionLabel!.isNotEmpty) {
      return actionLabel!;
    }
    return switch (action) {
      'stage_approved' => 'Tahap disetujui',
      'cancellation_approved' => 'Pembatalan disetujui',
      'cancellation_rejected' => 'Pembatalan ditolak',
      'cancellation_requested' => 'Menunggu pembatalan',
      'approved' => 'Disetujui',
      'rejected' => 'Ditolak',
      'cancelled' => 'Dibatalkan',
      'updated' => 'Informasi diperbarui',
      _ => 'Perubahan pengajuan',
    };
  }
  static List<SubmissionApprovalEvent> parse(dynamic raw) {
    if (raw is! List) return const [];
    return raw
        .take(50)
        .whereType<Map>()
        .where((e) => e['action'] is String && e['reason'] is String)
        .map((e) => SubmissionApprovalEvent(
            action: e['action'] as String,
            reason: e['reason'] as String,
            actionLabel:
                e['action_label'] is String ? e['action_label'] as String : null,
            eventType:
                e['event_type'] is String ? e['event_type'] as String : null,
            actor: e['actor'] is String ? e['actor'] as String : null,
            rawTime: e['time'] is String ? e['time'] as String : null,
            time: e['time'] is String ? DateTime.tryParse(e['time']) : null,
            stage:
                e['stage'] is int && e['stage'] > 0 ? e['stage'] as int : null))
        .toList();
  }
}

class SubmissionRecord {
  const SubmissionRecord(
      {required this.id,
      required this.userId,
      required this.date,
      required this.description,
      required this.kind,
      this.startDate,
      this.endDate,
      this.durationDays,
      this.datesBreakdown,
      this.cancellationReason,
      this.amount,
      this.image,
      this.status,
      this.approvalVersion,
      this.reviewerName,
      this.decisionTime,
      this.rawDecisionTime,
      this.decisionReason,
      this.duration,
      this.progress,
      this.currentReviewer,
      this.approvalHistory = const [],
      this.leavePolicy,
      this.timezone});
  final LeavePolicyRecord? leavePolicy;
  final String? timezone;
  final int id, userId;
  final SubmissionKind kind;
  final String date, description;
  final String? startDate, endDate;
  final int? durationDays;
  final List<dynamic>? datesBreakdown;
  final String? cancellationReason;
  final String? amount, image, status;
  final int? approvalVersion, duration;
  final String? reviewerName, decisionReason;
  final DateTime? decisionTime;
  final String? rawDecisionTime;
  final SubmissionProgress? progress;
  final String? currentReviewer;
  final List<SubmissionApprovalEvent> approvalHistory;

  bool get canCancel => status == 'pending' && approvalVersion != null;
  bool get canRequestCancel =>
      kind == SubmissionKind.permission &&
      status == 'approved' &&
      approvalVersion != null;

  String get dateRangeLabel {
    if (kind.isPermission) {
      if (startDate != null && endDate != null && startDate != endDate) {
        return '$startDate s/d $endDate (${durationDays ?? 1} hari kerja)';
      }
      return '$date (${durationDays ?? 1} hari kerja)';
    }
    return date;
  }

  String get statusLabel => switch (status) {
        'pending' => 'Menunggu persetujuan',
        'approved' => 'Disetujui',
        'rejected' => 'Ditolak',
        'cancelled' => 'Dibatalkan',
        'cancellation_requested' => 'Menunggu pembatalan',
        'needs_review' => 'Perlu ditinjau',
        'unapproved' => 'Belum disetujui',
        _ => 'Status belum tersedia',
      };

  static String? _resolveDecisionReason(
      Map<String, dynamic> data, dynamic rawDescription) {
    // 1. Prioritaskan decision_reason (kontrak modern)
    final decisionReason = data['decision_reason'];
    if (decisionReason is String && decisionReason.trim().isNotEmpty) {
      return decisionReason.trim();
    }

    // 2. Catatan sah pada approval tahap antara yang tercatat di approval_history
    final rawHistory = data['approval_history'];
    if (rawHistory is List) {
      for (final event in rawHistory) {
        if (event is Map &&
            event['action'] == 'approved' &&
            event['reason'] is String &&
            (event['reason'] as String).trim().isNotEmpty) {
          return (event['reason'] as String).trim();
        }
      }
    }

    // 3. Key eksplisit catatan peninjau pada kontrak legacy/alternatif
    final explicitReviewerNote =
        data['reviewer_note'] ?? data['approval_note'];
    if (explicitReviewerNote is String &&
        explicitReviewerNote.trim().isNotEmpty) {
      return explicitReviewerNote.trim();
    }

    // 4. Fallback ke field generik note HANYA pada kontrak peninjau yang terbukti sah,
    // bukan berdasarkan keberadaan is_approved, bukan perkiraan dari status, dan bukan perbedaan teks semata.
    final rawNote = data['note'];
    if (rawNote is String && rawNote.trim().isNotEmpty) {
      final trimmedNote = rawNote.trim();

      // Tolak jika note sama dengan deskripsi atau alasan pemohon
      final applicantReason = data['reason']?.toString().trim();
      final applicantDesc =
          (rawDescription ?? data['description'])?.toString().trim();
      if (trimmedNote == applicantReason || trimmedNote == applicantDesc) {
        return null;
      }

      // Harus ada bukti peninjau yang terkonfirmasi (bukan is_approved!)
      final hasConfirmedReviewer = data['reviewed_by'] != null ||
          (data['reviewer'] is Map &&
              (data['reviewer']['id'] != null ||
                  data['reviewer']['name'] != null));

      final hasReviewerContext = hasConfirmedReviewer ||
          data['decision_time'] != null ||
          data['reviewed_at'] != null ||
          data['approval_legacy'] == true;

      final isPending = data['status'] == 'pending';
      final hasIntermediateProgress = (data['approval_version'] is int &&
              (data['approval_version'] as int) > 0) ||
          (data['approval_progress'] is Map &&
              ((data['approval_progress']['current'] as int?) ?? 1) > 1);

      // Pada status pending, note hanya sah jika ada bukti kemajuan peninjauan tahap antara
      final isProvenReviewerContract = hasReviewerContext &&
          (!isPending || (hasConfirmedReviewer && hasIntermediateProgress));

      if (isProvenReviewerContract) {
        return trimmedNote;
      }
    }

    return null;
  }

  static SubmissionRecord parse(
      Map<String, dynamic> data, SubmissionKind kind, int ownId) {
    final id = data['id'];
    final date = data[kind.isPermission ? 'date_permission' : 'date'];
    final rawDescription = data[kind.descriptionKey];
    final description = kind.isOvertime &&
            (rawDescription == null ||
                (rawDescription is String && rawDescription.trim().isEmpty))
          ? 'Tanpa catatan'
        : rawDescription;
    if (id is! int ||
        id <= 0 ||
        data['user_id'] != ownId ||
        date is! String ||
        submissionDate(date) == null ||
        description is! String ||
        description.trim().isEmpty) {
      throw const FormatException('Invalid submission');
    }
    final policy = kind.isPermission && data['leave_policy_snapshot'] != null
        ? LeavePolicyRecord.parse(data['leave_policy_snapshot'])
        : null;
    if (kind.isPermission &&
        (data['leave_type_id'] != null || policy != null) &&
        (policy == null || data['leave_type_id'] != policy.id)) {
      throw const FormatException('Mismatched leave policy');
    }
    final approved = data['is_approved'];
    final startDate = data['start_date'] as String?;
    final endDate = data['end_date'] as String?;
    final durationDays = data['duration_days'] is int
        ? data['duration_days'] as int
        : (data['duration_days'] != null ? int.tryParse(data['duration_days'].toString()) : 1);
    final datesBreakdown = data['dates_breakdown'] is List ? data['dates_breakdown'] as List : null;
    final cancellationReason = data['cancellation_reason'] as String?;

    return SubmissionRecord(
        id: id,
        leavePolicy: policy,
        userId: ownId,
        date: date,
        startDate: startDate,
        endDate: endDate,
        durationDays: durationDays,
        datesBreakdown: datesBreakdown,
        cancellationReason: cancellationReason,
        description: description,
        kind: kind,
        amount: data['amount']?.toString(),
        image: (data['attachment_url'] ?? data['image']) as String?,
        approvalVersion:
            data['approval_version'] is int && data['approval_version'] >= 0
                ? data['approval_version'] as int
                : null,
        duration: data['duration'] is int ? data['duration'] as int : null,
        reviewerName: data['reviewer'] is Map
            ? data['reviewer']['name'] as String?
            : null,
        decisionReason: _resolveDecisionReason(data, rawDescription),
        rawDecisionTime: data['decision_time'] is String
            ? data['decision_time'] as String
            : null,
        decisionTime: data['decision_time'] is String
            ? DateTime.tryParse(data['decision_time'])
            : null,
        progress: SubmissionProgress.parse(data['approval_progress']),
        currentReviewer: data['approval_current_reviewer'] is String
            ? data['approval_current_reviewer'] as String
            : null,
        approvalHistory:
            SubmissionApprovalEvent.parse(data['approval_history']),
        timezone: data['timezone'] is String
            ? data['timezone'] as String
            : data['company_timezone'] is String
                ? data['company_timezone'] as String
                : data['attendance_timezone'] is String
                    ? data['attendance_timezone'] as String
                    : null,
        status: data.containsKey('status')
            ? data['status'] as String?
            : kind.isPermission
                ? (approved == true || approved == 1 || approved == '1'
                    ? 'approved'
                    : approved == false || approved == 0 || approved == '0'
                        ? 'unapproved'
                        : null)
                : data['status'] as String?);
  }
}

String formatSubmissionTime(
  DateTime? time, {
  String? timezone,
  String? rawTime,
}) {
  if (time == null && (rawTime == null || rawTime.trim().isEmpty)) {
    return 'Waktu belum tersedia';
  }

  final tz = timezone?.trim();
  final isWib = tz == 'Asia/Jakarta' || tz == 'Asia/Pontianak' || tz == 'WIB';
  final isWita =
      tz == 'Asia/Makassar' || tz == 'Asia/Ujung_Pandang' || tz == 'WITA';
  final isWit = tz == 'Asia/Jayapura' || tz == 'WIT';
  final isUtcZone = tz?.toUpperCase() == 'UTC';

  // 1. If raw ISO-8601 string from server is available, inspect its exact parts
  if (rawTime != null && rawTime.trim().isNotEmpty) {
    final raw = rawTime.trim();
    final match = RegExp(
            r'^(\d{4}-\d{2}-\d{2})[T ](\d{2}:\d{2}:\d{2})(?:\.\d+)?(Z|[+-]\d{2}(?::?\d{2})?)?$')
        .firstMatch(raw);
    if (match != null) {
      final datePart = match.group(1)!;
      final timePart = match.group(2)!;
      final offsetPart = match.group(3);

      // 1a. If server provided an explicit timezone offset (e.g. +07:00, -04:00, +08:00)
      if (offsetPart != null && offsetPart != 'Z') {
        final normalizedOffset = offsetPart.contains(':')
            ? offsetPart
            : (offsetPart.length == 5
                ? '${offsetPart.substring(0, 3)}:${offsetPart.substring(3)}'
                : offsetPart);

        String label;
        if (isWib || normalizedOffset == '+07:00') {
          label = 'WIB';
        } else if (isWita || normalizedOffset == '+08:00') {
          label = 'WITA';
        } else if (isWit || normalizedOffset == '+09:00') {
          label = 'WIT';
        } else if (tz != null && tz.isNotEmpty) {
          label = '$tz (UTC$normalizedOffset)';
        } else {
          label = 'UTC$normalizedOffset';
        }
        return '$datePart $timePart $label';
      }

      // 1b. If raw string is in UTC ('Z' or offset is missing/zero)
      if (offsetPart == 'Z' ||
          offsetPart == '+00:00' ||
          offsetPart == '-00:00') {
        if (isUtcZone) {
          return '$datePart $timePart UTC';
        }
        if (isWib || tz == null || tz.isEmpty) {
          final dt =
              DateTime.parse(raw).toUtc().add(const Duration(hours: 7));
          return '${_formatYmdHms(dt)} WIB';
        }
        if (isWita) {
          final dt =
              DateTime.parse(raw).toUtc().add(const Duration(hours: 8));
          return '${_formatYmdHms(dt)} WITA';
        }
        if (isWit) {
          final dt =
              DateTime.parse(raw).toUtc().add(const Duration(hours: 9));
          return '${_formatYmdHms(dt)} WIT';
        }
        // Unrecognized IANA timezone with raw UTC string:
        // Do NOT guess DST or offset and do NOT claim UTC is the company local time
        return '$datePart $timePart UTC (Zona: $tz)';
      }

      // 1c. If no offset was attached, treat as pre-formatted local time from server
      final label = isWib || tz == null || tz.isEmpty
          ? 'WIB'
          : isWita
              ? 'WITA'
              : isWit
                  ? 'WIT'
                  : isUtcZone
                      ? 'UTC'
                      : tz;
      return '$datePart $timePart $label';
    }
  }

  // 2. Fallback when only DateTime object is supplied
  if (time != null) {
    if (isUtcZone) {
      final utc = time.toUtc();
      return '${_formatYmdHms(utc)} UTC';
    }
    if (isWib || tz == null || tz.isEmpty) {
      final target = time.toUtc().add(const Duration(hours: 7));
      return '${_formatYmdHms(target)} WIB';
    }
    if (isWita) {
      final target = time.toUtc().add(const Duration(hours: 8));
      return '${_formatYmdHms(target)} WITA';
    }
    if (isWit) {
      final target = time.toUtc().add(const Duration(hours: 9));
      return '${_formatYmdHms(target)} WIT';
    }

    // Check fixed offset format in timezone string (+07:00, -05:00)
    final matchOffset =
        RegExp(r'^([+-])(\d{1,2})(?::?(\d{2}))?$').firstMatch(tz);
    if (matchOffset != null) {
      final sign = matchOffset.group(1) == '-' ? -1 : 1;
      final h = int.tryParse(matchOffset.group(2) ?? '0') ?? 0;
      final m = int.tryParse(matchOffset.group(3) ?? '0') ?? 0;
      final target =
          time.toUtc().add(Duration(minutes: sign * (h * 60 + m)));
      final formattedOffset =
          '${matchOffset.group(1)}${h.toString().padLeft(2, '0')}:${m.toString().padLeft(2, '0')}';
      return '${_formatYmdHms(target)} UTC$formattedOffset';
    }

    // Unrecognized IANA zone without server offset:
    // Do NOT guess or silently convert to UTC and label it with the company zone!
    final utc = time.toUtc();
    return '${_formatYmdHms(utc)} UTC (Zona: $tz)';
  }

  return 'Waktu belum tersedia';
}

String _formatYmdHms(DateTime dt) {
  final y = dt.year.toString().padLeft(4, '0');
  final m = dt.month.toString().padLeft(2, '0');
  final d = dt.day.toString().padLeft(2, '0');
  final h = dt.hour.toString().padLeft(2, '0');
  final min = dt.minute.toString().padLeft(2, '0');
  final s = dt.second.toString().padLeft(2, '0');
  return '$y-$m-$d $h:$min:$s';
}

class SubmissionBatch {
  const SubmissionBatch(this.records,
      {required this.page, required this.lastPage});
  final List<SubmissionRecord> records;
  final int page, lastPage;
}
