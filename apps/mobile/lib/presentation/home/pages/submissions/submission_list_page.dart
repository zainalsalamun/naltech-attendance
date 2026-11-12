import 'dart:typed_data';
import 'package:dartz/dartz.dart' show Either;
import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import '../../../../core/constants/variables.dart';
import '../../../../data/datasources/submission_remote_datasource.dart';
import '../../../../data/models/response/submission_record.dart';
import '../../bloc/submissions/submission_cubit.dart';
import '../../models/history_presentation.dart';
import 'submission_form_page.dart';
import 'leave_policy_summary.dart';

class SubmissionListPage extends StatefulWidget {
  const SubmissionListPage({super.key, required this.kind, this.source});
  final SubmissionKind kind;
  final SubmissionRemoteDatasource? source;
  @override
  State<SubmissionListPage> createState() => _SubmissionListPageState();
}

class _SubmissionListPageState extends State<SubmissionListPage> {
  late final _source = widget.source ?? SubmissionRemoteDatasource();
  late final _list = SubmissionListCubit(_source, widget.kind)..refresh();
  @override
  void dispose() {
    _list.close();
    super.dispose();
  }

  Future<void> _add() async {
    final record = await Navigator.of(context).push<SubmissionRecord>(
        MaterialPageRoute(
            builder: (_) =>
                SubmissionFormPage(kind: widget.kind, source: _source)));
    if (!mounted || record == null) return;
    ScaffoldMessenger.of(context).showSnackBar(SnackBar(
        content: Text(
            'Pengajuan #${record.id} berhasil dikirim. ${record.statusLabel}.')));
    await _list.refresh();
  }

  @override
  Widget build(BuildContext context) =>
      BlocBuilder<SubmissionListCubit, SubmissionListState>(
          bloc: _list,
          builder: (context, state) => SubmissionListView(
              kind: widget.kind,
              state: state,
              onRefresh: _list.refresh,
              onMore: _list.more,
              onAdd: _add,
              onDetail: (record) async {
                await Navigator.of(context).push(MaterialPageRoute<void>(
                    builder: (_) =>
                        SubmissionDetailPage(record: record, source: _source)));
                if (mounted) await _list.refresh();
              }));
}

class SubmissionListView extends StatelessWidget {
  const SubmissionListView(
      {super.key,
      required this.kind,
      required this.state,
      required this.onRefresh,
      required this.onMore,
      required this.onAdd,
      this.onDetail});
  final SubmissionKind kind;
  final SubmissionListState state;
  final Future<void> Function() onRefresh, onMore;
  final VoidCallback onAdd;
  final void Function(SubmissionRecord)? onDetail;
  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Scaffold(
        appBar: AppBar(title: Text(kind.title), actions: [
          PopupMenuButton<SubmissionKind>(
              tooltip: 'Jenis pengajuan',
              onSelected: (value) {
                if (value != kind) {
                  Navigator.of(context).pushReplacement(MaterialPageRoute<void>(
                      builder: (_) => SubmissionListPage(kind: value)));
                }
              },
              itemBuilder: (_) => [
                    for (final value in SubmissionKind.values)
                      PopupMenuItem(value: value, child: Text(value.title))
                  ]),
          IconButton(
              tooltip: 'Muat ulang pengajuan',
              onPressed: state.loading ? null : onRefresh,
              icon: const Icon(Icons.refresh))
        ]),
        body: SafeArea(
            child: RefreshIndicator(
                onRefresh: onRefresh,
                child: ListView(
                    physics: const AlwaysScrollableScrollPhysics(),
                    padding: const EdgeInsets.all(20),
                    children: [
                      Container(
                          padding: const EdgeInsets.all(20),
                          decoration: BoxDecoration(
                              color: const Color(0xFF192A48),
                              borderRadius: BorderRadius.circular(16)),
                          child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                const Text('PENGAJUAN SAYA',
                                    style: TextStyle(
                                        color: Color(0xFFB9C6DC),
                                        fontSize: 11,
                                        letterSpacing: 1.8)),
                                const SizedBox(height: 10),
                                Text(
                                    kind.isPermission
                                        ? 'Izin kerja'
                                        : kind.isOvertime
                                            ? 'Lembur kerja'
                                            : 'Penggantian biaya',
                                    style: theme.textTheme.headlineSmall
                                        ?.copyWith(
                                            color: Colors.white,
                                            fontWeight: FontWeight.w700)),
                                const SizedBox(height: 8),
                                Text(
                                    kind.isPermission
                                        ? 'Lihat pengajuan untuk satu tanggal dan keputusan pengelola.'
                                        : kind.isOvertime
                                            ? 'Pantau durasi lembur dan keputusan pengelola.'
                                            : 'Pantau rincian biaya dan status pengajuan Anda.',
                                    style: const TextStyle(
                                        color: Color(0xFFB9C6DC))),
                              ])),
                      const SizedBox(height: 16),
                      FilledButton.icon(
                          onPressed: onAdd,
                          icon: const Icon(Icons.add),
                          label: Text('Ajukan ${kind.title.toLowerCase()}')),
                      const SizedBox(height: 24),
                      if (state.loading)
                        const Padding(
                            padding: EdgeInsets.all(32),
                            child: Center(
                                child: CircularProgressIndicator(
                                    semanticsLabel: 'Memuat pengajuan')))
                      else if (state.error != null)
                        _message(
                            context,
                            Icons.cloud_off_outlined,
                            'Daftar belum dapat dimuat',
                            state.error!,
                            onRefresh)
                      else if (state.records.isEmpty)
                        _message(
                            context,
                            Icons.description_outlined,
                            'Belum ada pengajuan',
                            'Pengajuan yang berhasil dikirim akan muncul di sini.',
                            null)
                      else ...[
                        Text('Riwayat pengajuan',
                            style: theme.textTheme.titleMedium
                                ?.copyWith(fontWeight: FontWeight.w700)),
                        const SizedBox(height: 12),
                        for (final record in state.records)
                          Padding(
                              padding: const EdgeInsets.only(bottom: 12),
                              child: SubmissionCard(
                                  record: record,
                                  onDetail: onDetail == null
                                      ? null
                                      : () => onDetail!(record))),
                        if (state.moreError != null)
                          Text(state.moreError!,
                              style: TextStyle(color: theme.colorScheme.error)),
                        if (state.page < state.lastPage)
                          OutlinedButton(
                              onPressed: state.moreLoading ? null : onMore,
                              child: Text(state.moreLoading
                                  ? 'Memuat…'
                                  : state.moreError == null
                                      ? 'Muat lebih banyak'
                                      : 'Coba muat lagi')),
                      ],
                      if (state.records
                          .any((record) => record.status == 'unapproved'))
                        Padding(
                            padding: const EdgeInsets.only(top: 16),
                            child: Text(
                                '“Belum disetujui” mengikuti status sistem; tidak menunjukkan keputusan penolakan.',
                                style: TextStyle(
                                    color: theme.colorScheme.onSurfaceVariant,
                                    fontSize: 12))),
                    ]))));
  }

  Widget _message(BuildContext context, IconData icon, String title,
          String message, Future<void> Function()? retry) =>
      Padding(
          padding: const EdgeInsets.symmetric(vertical: 32),
          child: Column(children: [
            Icon(icon,
                size: 36,
                color: Theme.of(context).colorScheme.onSurfaceVariant),
            const SizedBox(height: 14),
            Text(title,
                style: Theme.of(context).textTheme.titleMedium,
                textAlign: TextAlign.center),
            const SizedBox(height: 8),
            Text(message, textAlign: TextAlign.center),
            if (retry != null) ...[
              const SizedBox(height: 16),
              OutlinedButton(onPressed: retry, child: const Text('Coba lagi'))
            ],
          ]));
}

Color submissionStatusColor(ThemeData theme, String? status) =>
    switch (status) {
      'approved' => theme.brightness == Brightness.dark
          ? const Color(0xFF9ED2B9)
          : const Color(0xFF34725B),
      'pending' ||
      'cancellation_requested' =>
        theme.brightness == Brightness.dark
            ? const Color(0xFFFBBF24)
            : const Color(0xFFB45309),
      'rejected' => theme.colorScheme.error,
      'needs_review' => theme.brightness == Brightness.dark
          ? const Color(0xFFFBBF24)
          : const Color(0xFFB45309),
      'cancelled' => theme.colorScheme.onSurfaceVariant,
      _ => theme.colorScheme.primary,
    };

class SubmissionCard extends StatelessWidget {
  const SubmissionCard({super.key, required this.record, this.onDetail});
  final VoidCallback? onDetail;
  final SubmissionRecord record;
  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final date = submissionDate(record.date);
    final color = submissionStatusColor(theme, record.status);
    return Container(
        padding: const EdgeInsets.all(18),
        decoration: BoxDecoration(
            color: theme.colorScheme.surface,
            border: Border.all(color: theme.colorScheme.outline),
            borderRadius: BorderRadius.circular(16)),
        child:
            Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
          Wrap(
              alignment: WrapAlignment.spaceBetween,
              spacing: 12,
              runSpacing: 8,
              children: [
                Text('#${record.id}',
                    style:
                        TextStyle(color: theme.colorScheme.onSurfaceVariant)),
                Text(record.statusLabel,
                    style: TextStyle(color: color, fontWeight: FontWeight.w700))
              ]),
          if (record.status == 'pending' && record.progress != null) ...[
            const SizedBox(height: 8),
            Text(
                'Tahap ${record.progress!.current}/${record.progress!.total}: ${record.progress!.label ?? 'Menunggu peninjauan'}'),
          ],
          const SizedBox(height: 12),
          Text(record.dateRangeLabel,
              style: theme.textTheme.titleSmall
                  ?.copyWith(fontWeight: FontWeight.w600)),
          const SizedBox(height: 8),
          if (record.leavePolicy != null)
            Text(record.leavePolicy!.name, style: theme.textTheme.titleSmall),
          Text(record.description,
              maxLines: 3, overflow: TextOverflow.ellipsis),
          if (record.kind.isReimbursement) ...[
            const SizedBox(height: 12),
            Text(submissionMoney(record.amount),
                style: theme.textTheme.titleMedium?.copyWith(
                    fontWeight: FontWeight.w700,
                    color: theme.colorScheme.primary))
          ],
          if (record.kind.isOvertime) Text('${record.duration ?? '—'} menit'),
          const SizedBox(height: 8),
          TextButton.icon(
              onPressed: onDetail ??
                  () => Navigator.of(context).push(MaterialPageRoute<void>(
                      builder: (_) => SubmissionDetailPage(record: record))),
              icon: const Icon(Icons.arrow_forward, size: 18),
              label: const Text('Lihat detail')),
        ]));
  }
}

String? submissionImageUrl(SubmissionRecord record) {
  final image = record.image?.trim();
  if (image == null || image.isEmpty) return null;
  final kind = record.kind.isPermission ? 'permission' : 'reimbursement';
  final raw =
      '${Variables.baseUrl}/api/submission-attachments/$kind/${record.id}';
  final uri = Uri.tryParse(raw);
  return uri != null && ['http', 'https'].contains(uri.scheme) ? raw : null;
}

class SubmissionDetailPage extends StatefulWidget {
  const SubmissionDetailPage({super.key, required this.record, this.source});
  final SubmissionRecord record;
  final SubmissionRemoteDatasource? source;
  @override
  State<SubmissionDetailPage> createState() => _SubmissionDetailPageState();
}

class _SubmissionDetailPageState extends State<SubmissionDetailPage> {
  late final _source = widget.source ?? SubmissionRemoteDatasource();
  late SubmissionRecord _record = widget.record;
  Uint8List? _attachmentBytes;
  bool _attachmentLoading = false;
  String? _attachmentError;
  int _attachmentRevision = 0;

  bool _loading = false, _busy = false, _verified = false;
  String? _error, _actionError;
  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    if (_loading) return;
    setState(() {
      _loading = true;
      _verified = false;
      _error = null;
    });
    final result = await _source.detail(_record);
    if (!mounted) return;
    setState(() {
      _loading = false;
      result.fold((error) => _error = error, (record) {
        _record = record;
        _verified = true;
      });
    });
    if (_verified) {
      await _loadAttachment();
    }
  }

  Future<void> _loadAttachment() async {
    if (submissionImageUrl(_record) == null) {
      if (mounted) {
        setState(() {
          _attachmentBytes = null;
          _attachmentError = null;
          _attachmentLoading = false;
        });
      }
      return;
    }
    final revision = ++_attachmentRevision;
    final expectedUserId = _record.userId;
    setState(() {
      _attachmentLoading = true;
      _attachmentError = null;
    });

    final result = await _source.attachment(_record);
    if (!mounted || revision != _attachmentRevision) return;

    final currentAuth = await _source.local.getAuthData();
    if (currentAuth?.user?.id != expectedUserId || currentAuth?.token == null) {
      setState(() {
        _attachmentLoading = false;
        _attachmentBytes = null;
        _attachmentError =
            'Sesi akun telah berganti. Buka ulang pengajuan akun aktif.';
      });
      return;
    }

    setState(() {
      _attachmentLoading = false;
      result.fold(
        (error) {
          _attachmentBytes = null;
          _attachmentError = error;
        },
        (bytes) {
          _attachmentBytes = bytes;
          _attachmentError = null;
        },
      );
    });
  }

  Future<void> _cancel() async {
    if (_busy ||
        _loading ||
        !_verified ||
        (!_record.canCancel && !_record.canRequestCancel)) {
      return;
    }
    final isApproved = _record.canRequestCancel;
    final reason = await showDialog<String>(
        context: context,
        builder: (_) => _CancellationDialog(isApproved: isApproved));
    if (!mounted || reason == null) return;
    setState(() {
      _busy = true;
      _actionError = null;
    });
    final result =
        await _source.cancel(_record, reason, requestCancellation: isApproved);
    if (!mounted) return;
    result.fold((error) => _actionError = error, (record) => _record = record);
    // A timeout or conflict may have happened after a decision committed. Never retry blindly.
    await _load();
    if (mounted) setState(() => _busy = false);
  }

  @override
  Widget build(BuildContext context) {
    final record = _record;
    return PopScope(
        canPop: !_busy,
        child: Scaffold(
          appBar: AppBar(
              title: Text('Detail ${record.kind.title.toLowerCase()}'),
              actions: [
                IconButton(
                    tooltip: 'Muat ulang detail',
                    onPressed: _loading || _busy ? null : _load,
                    icon: const Icon(Icons.refresh))
              ]),
          body: SafeArea(
              child: ListView(padding: const EdgeInsets.all(24), children: [
            Text('Pengajuan #${record.id}',
                style: Theme.of(context).textTheme.headlineSmall),
            const SizedBox(height: 12),
            Text(record.statusLabel,
                style: TextStyle(
                    color:
                        submissionStatusColor(Theme.of(context), record.status),
                    fontWeight: FontWeight.w700)),
            if (_loading)
              const LinearProgressIndicator(
                  semanticsLabel: 'Memuat detail terbaru'),
            if (_error != null)
              Padding(
                  padding: const EdgeInsets.symmetric(vertical: 12),
                  child: Text(_error!,
                      style: TextStyle(
                          color: Theme.of(context).colorScheme.error))),
            if (!_verified && !_loading)
              const Text(
                  'Detail belum terkonfirmasi. Muat ulang sebelum mengambil tindakan.'),
            if (record.progress != null) ...[
              const SizedBox(height: 16),
              Text('Alur: ${record.progress!.steps.join(' → ')}'),
              if (record.status == 'pending') ...[
                const SizedBox(height: 8),
                Text(
                    'Tahap ${record.progress!.current} dari ${record.progress!.total}: ${record.progress!.label ?? 'Menunggu peninjauan'}'),
                Text(
                    'Peninjau saat ini: ${record.currentReviewer ?? 'Belum tersedia; hubungi HR.'}'),
              ],
            ],
            const SizedBox(height: 24),
            if (record.leavePolicy != null) ...[
              LeavePolicySummary(policy: record.leavePolicy!, historical: true),
              const SizedBox(height: 20),
            ] else if (record.kind.isPermission) ...[
              const Text(
                  'Izin umum atau data lama yang belum diklasifikasi sebagai cuti.'),
              const SizedBox(height: 20),
            ],
            Text(record.dateRangeLabel,
                style: Theme.of(context)
                    .textTheme
                    .titleSmall
                    ?.copyWith(fontWeight: FontWeight.w600)),
            if (record.datesBreakdown != null) ...[
              const SizedBox(height: 10),
              Container(
                padding: const EdgeInsets.all(12),
                decoration: BoxDecoration(
                  color: Theme.of(context)
                      .colorScheme
                      .surfaceVariant
                      .withOpacity(0.3),
                  borderRadius: BorderRadius.circular(8),
                ),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    const Text('Rincian Kalender:',
                        style: TextStyle(
                            fontSize: 11, fontWeight: FontWeight.w600)),
                    const SizedBox(height: 6),
                    for (final day in record.datesBreakdown!)
                      Padding(
                        padding: const EdgeInsets.symmetric(vertical: 2),
                        child: Text(
                          '${(day is Map && day['is_working'] == true) ? '✓' : '—'} ${day is Map ? day['date'] : ''}: ${day is Map ? (day['label'] ?? '') : ''}',
                          style: TextStyle(
                            fontSize: 11,
                            color: (day is Map && day['is_working'] == true)
                                ? Colors.green
                                : Theme.of(context)
                                    .colorScheme
                                    .onSurfaceVariant,
                          ),
                        ),
                      ),
                  ],
                ),
              ),
            ],
            const SizedBox(height: 20),
            Text(
                record.kind.isPermission
                    ? 'Alasan pengajuan'
                    : 'Keterangan pengajuan',
                style: Theme.of(context).textTheme.titleMedium),
            const SizedBox(height: 8),
            Container(
              width: double.infinity,
              padding: const EdgeInsets.all(12),
              decoration: BoxDecoration(
                color: Theme.of(context)
                    .colorScheme
                    .surfaceVariant
                    .withOpacity(0.25),
                borderRadius: BorderRadius.circular(8),
                border: Border.all(
                  color: Theme.of(context)
                      .colorScheme
                      .outlineVariant
                      .withOpacity(0.4),
                ),
              ),
              child: SelectableText(record.description),
            ),
            if (record.kind.isReimbursement) ...[
              const SizedBox(height: 20),
              Text(submissionMoney(record.amount),
                  style: Theme.of(context).textTheme.titleLarge)
            ],
            if (record.kind.isOvertime) ...[
              const SizedBox(height: 20),
              Text('Durasi: ${record.duration ?? '—'} menit')
            ],
            const SizedBox(height: 24),
            Text('Keputusan pengajuan',
                style: Theme.of(context).textTheme.titleMedium),
            const SizedBox(height: 12),
            Container(
              width: double.infinity,
              padding: const EdgeInsets.all(14),
              decoration: BoxDecoration(
                color: Theme.of(context)
                    .colorScheme
                    .surfaceVariant
                    .withOpacity(0.3),
                borderRadius: BorderRadius.circular(8),
                border: Border.all(
                  color: Theme.of(context)
                      .colorScheme
                      .outlineVariant
                      .withOpacity(0.5),
                ),
              ),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                      '${record.status == 'cancelled' ? 'Dibatalkan oleh' : 'Peninjau'}: ${record.reviewerName ?? 'Belum tersedia'}',
                      style: const TextStyle(fontWeight: FontWeight.w600)),
                  const SizedBox(height: 4),
                  Text(record.decisionTime == null &&
                          record.rawDecisionTime == null
                      ? 'Waktu keputusan belum tersedia'
                      : 'Waktu keputusan: ${formatSubmissionTime(record.decisionTime, timezone: record.timezone, rawTime: record.rawDecisionTime)}'),
                  if (record.decisionReason?.isNotEmpty == true) ...[
                    const SizedBox(height: 10),
                    const Text('Catatan keputusan:',
                        style: TextStyle(
                            fontSize: 12, fontWeight: FontWeight.w600)),
                    const SizedBox(height: 4),
                    SelectableText(record.decisionReason!),
                  ],
                ],
              ),
            ),
            if (record.approvalHistory.isNotEmpty) ...[
              const SizedBox(height: 24),
              Text('Riwayat peninjauan',
                  style: Theme.of(context).textTheme.titleMedium),
              const SizedBox(height: 10),
              for (final event in record.approvalHistory)
                Container(
                  width: double.infinity,
                  margin: const EdgeInsets.only(bottom: 12),
                  padding: const EdgeInsets.all(12),
                  decoration: BoxDecoration(
                    color: Theme.of(context)
                        .colorScheme
                        .surfaceVariant
                        .withOpacity(0.18),
                    borderRadius: BorderRadius.circular(8),
                    border: Border.all(
                      color: Theme.of(context)
                          .colorScheme
                          .outlineVariant
                          .withOpacity(0.35),
                    ),
                  ),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Row(
                        children: [
                          Icon(Icons.history,
                              size: 16,
                              color: Theme.of(context).colorScheme.primary),
                          const SizedBox(width: 6),
                          Expanded(
                            child: Text(
                              '${event.stage == null ? '' : 'Tahap ${event.stage} · '}${event.label}',
                              style:
                                  const TextStyle(fontWeight: FontWeight.w700),
                            ),
                          ),
                        ],
                      ),
                      const SizedBox(height: 6),
                      Text(event.actor ?? 'Aktor belum tersedia'),
                      if (event.time != null || event.rawTime != null) ...[
                        const SizedBox(height: 2),
                        Text(
                          formatSubmissionTime(event.time,
                              timezone: record.timezone,
                              rawTime: event.rawTime),
                          style: TextStyle(
                            fontSize: 12,
                            color: Theme.of(context)
                                .colorScheme
                                .onSurfaceVariant,
                          ),
                        ),
                      ],
                      if (event.reason.isNotEmpty) ...[
                        const SizedBox(height: 8),
                        const Text('Catatan tahap ini:',
                            style: TextStyle(
                                fontSize: 11, fontWeight: FontWeight.w600)),
                        const SizedBox(height: 2),
                        SelectableText(event.reason),
                      ],
                    ],
                  ),
                ),
            ],
            if (record.status == 'needs_review')
              const Text('Data lama perlu direkonsiliasi oleh HR/admin.'),
            if (record.status == 'unapproved')
              const Text(
                  'Status lama belum membedakan menunggu dan ditolak. Hubungi pengelola.'),
            if (!record.kind.isOvertime) ...[
              const SizedBox(height: 24),
              Text('Lampiran', style: Theme.of(context).textTheme.titleMedium),
              const SizedBox(height: 12),
              if (submissionImageUrl(record) == null)
                const Text('Tidak ada lampiran.')
              else if (_attachmentLoading)
                const Padding(
                  padding: EdgeInsets.symmetric(vertical: 20),
                  child: Center(
                    child: Column(
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        CircularProgressIndicator(
                          semanticsLabel: 'Memuat lampiran privat',
                        ),
                        SizedBox(height: 12),
                        Text('Memuat lampiran privat…',
                            style: TextStyle(fontSize: 12)),
                      ],
                    ),
                  ),
                )
              else if (_attachmentBytes != null)
                ClipRRect(
                  borderRadius: BorderRadius.circular(12),
                  child: Image.memory(
                    _attachmentBytes!,
                    fit: BoxFit.contain,
                    errorBuilder: (_, __, ___) =>
                        const Text('Lampiran belum dapat ditampilkan.'),
                  ),
                )
              else if (_attachmentError != null) ...[
                Padding(
                  padding: const EdgeInsets.symmetric(vertical: 8),
                  child: Text(
                    _attachmentError!,
                    style: TextStyle(
                      color: Theme.of(context).colorScheme.error,
                    ),
                  ),
                ),
                OutlinedButton.icon(
                  onPressed: _busy ? null : _loadAttachment,
                  icon: const Icon(Icons.refresh),
                  label: const Text('Muat ulang lampiran'),
                ),
              ],
            ],
            if (record.cancellationReason?.isNotEmpty == true) ...[
              const SizedBox(height: 16),
              Container(
                padding: const EdgeInsets.all(12),
                decoration: BoxDecoration(
                  color: Theme.of(context)
                      .colorScheme
                      .errorContainer
                      .withOpacity(0.3),
                  borderRadius: BorderRadius.circular(8),
                  border: Border.all(
                      color:
                          Theme.of(context).colorScheme.error.withOpacity(0.3)),
                ),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    const Text('Alasan Permintaan Pembatalan:',
                        style: TextStyle(
                            fontWeight: FontWeight.w700, fontSize: 12)),
                    const SizedBox(height: 4),
                    Text(record.cancellationReason!,
                        style: const TextStyle(fontSize: 12)),
                  ],
                ),
              ),
            ],
            if (record.status == 'cancellation_requested') ...[
              const SizedBox(height: 16),
              const Text(
                  'Permohonan pembatalan menunggu keputusan pengelola. Pengajuan tetap berlaku dan saldo terpakai belum dikembalikan.',
                  style: TextStyle(
                      color: Color(0xFFB45309), fontWeight: FontWeight.w600)),
            ],
            if (_actionError != null)
              Padding(
                  padding: const EdgeInsets.symmetric(vertical: 16),
                  child: Semantics(
                      liveRegion: true,
                      child: Text(_actionError!,
                          style: TextStyle(
                              color: Theme.of(context).colorScheme.error)))),
            if (_verified && record.canCancel) ...[
              const SizedBox(height: 24),
              OutlinedButton.icon(
                  onPressed: _busy || _loading ? null : _cancel,
                  icon: const Icon(Icons.cancel_outlined),
                  label: Text(_busy ? 'Memproses…' : 'Batalkan pengajuan'))
            ] else if (_verified && record.canRequestCancel) ...[
              const SizedBox(height: 24),
              OutlinedButton.icon(
                  onPressed: _busy || _loading ? null : _cancel,
                  icon: const Icon(Icons.cancel_presentation_outlined),
                  label: Text(_busy ? 'Memproses…' : 'Ajukan pembatalan ke HR'))
            ],
          ])),
        ));
  }
}

class _CancellationDialog extends StatefulWidget {
  const _CancellationDialog({this.isApproved = false});
  final bool isApproved;
  @override
  State<_CancellationDialog> createState() => _CancellationDialogState();
}

class _CancellationDialogState extends State<_CancellationDialog> {
  final _form = GlobalKey<FormState>();
  final _reason = TextEditingController();
  @override
  void dispose() {
    _reason.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) => AlertDialog(
        title: Text(widget.isApproved
            ? 'Ajukan pembatalan cuti ke HR?'
            : 'Batalkan pengajuan?'),
        content: SingleChildScrollView(
            child: Form(
                key: _form,
                child: Column(mainAxisSize: MainAxisSize.min, children: [
                  Text(widget.isApproved
                      ? 'Pengajuan tetap berlaku selama permohonan pembatalan ditinjau pengelola. Jika pembatalan disetujui, saldo yang pernah terpakai dikembalikan; jika ditolak, saldo tetap terpakai.'
                      : 'Pengajuan dan bukti tetap tersimpan. Jika pengajuan ini mencadangkan saldo cuti, pembatalan mengembalikan cadangannya ke tersedia.'),
                  const SizedBox(height: 16),
                  TextFormField(
                      controller: _reason,
                      autofocus: true,
                      maxLength: 1000,
                      minLines: 2,
                      maxLines: 4,
                      decoration: InputDecoration(
                          labelText: widget.isApproved
                              ? 'Alasan permohonan pembatalan'
                              : 'Alasan pembatalan'),
                      validator: (value) => value?.trim().isNotEmpty != true
                          ? 'Isi alasan pembatalan.'
                          : null),
                ]))),
        actions: [
          TextButton(
              onPressed: () => Navigator.pop(context),
              child: const Text('Kembali')),
          FilledButton(
              onPressed: () {
                if (_form.currentState!.validate()) {
                  Navigator.pop(context, _reason.text.trim());
                }
              },
              child: Text(widget.isApproved
                  ? 'Kirim permohonan'
                  : 'Konfirmasi pembatalan'))
        ],
      );
}
