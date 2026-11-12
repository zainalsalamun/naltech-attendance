import 'dart:typed_data';
import 'package:dartz/dartz.dart' show Either, Left;
import '../../../../data/models/response/leave_policy_record.dart';
import 'leave_policy_summary.dart';
import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:image_picker/image_picker.dart';
import '../../../../data/datasources/submission_remote_datasource.dart';
import '../../../../data/models/response/submission_record.dart';
import '../../bloc/submissions/submission_cubit.dart';
import '../../models/history_presentation.dart';
import '../history_page.dart';

class SubmissionFormPage extends StatefulWidget {
  const SubmissionFormPage(
      {super.key,
      required this.kind,
      this.source,
      this.pickImage,
      this.pickDate});
  final SubmissionKind kind;
  final SubmissionRemoteDatasource? source;
  final Future<XFile?> Function()? pickImage;
  final Future<DateTime?> Function()? pickDate;
  @override
  State<SubmissionFormPage> createState() => _SubmissionFormPageState();
}

class _SubmissionFormPageState extends State<SubmissionFormPage> {
  final _form = GlobalKey<FormState>();
  final _description = TextEditingController(),
      _amount = TextEditingController(),
      _dateText = TextEditingController(),
      _endDateText = TextEditingController();
  late final _source = widget.source ?? SubmissionRemoteDatasource();
  late final _sender = SubmissionSendCubit(_source, widget.kind);
  List<LeavePolicyRecord> _types = [];
  LeavePolicyRecord? _selectedType;
  bool _typesLoading = false, _selectionUnavailable = false;
  String? _typesError;

  DateTime? _date;
  DateTime? _endDate;
  XFile? _image;
  Uint8List? _preview;
  bool _picking = false, _sending = false, _reviewing = false;
  int _calcRevision = 0;
  String? _attachmentError;

  Map<String, dynamic>? _calcData;
  bool _calcLoading = false;
  String? _calcError;

  @override
  void initState() {
    super.initState();
    if (widget.kind.isPermission) _loadTypes();
  }

  Future<void> _loadTypes() async {
    if (_typesLoading || _sending) return;
    setState(() {
      _typesLoading = true;
      _typesError = null;
    });
    Either<String, List<LeavePolicyRecord>> result;
    try {
      result = await _source.leaveTypes();
    } catch (_) {
      result = const Left('Jenis cuti belum dapat dimuat. Coba lagi.');
    }
    if (!mounted) return;
    setState(() {
      _typesLoading = false;
      result.fold((error) {
        _typesError = error;
        _types = [];
        _selectionUnavailable = _selectedType != null;
      }, (rows) {
        _types = rows;
        if (_selectedType != null) {
          final matches = rows.where((row) => row.id == _selectedType!.id);
          _selectionUnavailable = matches.isEmpty;
          if (matches.isNotEmpty) _selectedType = matches.first;
        }
      });
    });
  }

  @override
  void dispose() {
    _description.dispose();
    _amount.dispose();
    _dateText.dispose();
    _endDateText.dispose();
    _sender.close();
    super.dispose();
  }

  Future<void> _selectDate() async {
    final now = DateTime.now();
    final date = await (widget.pickDate?.call() ??
        showDialog<DateTime>(
            context: context,
            builder: (_) => HistoryDateDialog(
                selected: _date ?? now, today: DateTime(2100, 12, 31))));
    if (date != null && mounted) {
      setState(() {
        _date = date;
        _dateText.text = historyDate(date);
        if (_endDate == null || _endDate!.isBefore(date)) {
          _endDate = date;
          _endDateText.text = historyDate(date);
        }
      });
      _recalculate();
    }
  }

  Future<void> _selectEndDate() async {
    final now = DateTime.now();
    final date = await (widget.pickDate?.call() ??
        showDialog<DateTime>(
            context: context,
            builder: (_) => HistoryDateDialog(
                selected: _endDate ?? _date ?? now,
                today: DateTime(2100, 12, 31))));
    if (date != null && mounted) {
      setState(() {
        _endDate = date;
        _endDateText.text = historyDate(date);
      });
      _recalculate();
    }
  }

  Future<void> _recalculate() async {
    final revision = ++_calcRevision;
    if (!widget.kind.isPermission || _date == null) return;
    final startKey = historyDateKey(_date!);
    final endKey = historyDateKey(_endDate ?? _date!);
    if ((_endDate ?? _date!).isBefore(_date!)) {
      setState(() {
        _calcError =
            'Tanggal selesai tidak boleh lebih awal dari tanggal mulai.';
        _calcData = null;
        _calcLoading = false;
      });
      return;
    }

    setState(() {
      _calcLoading = true;
      _calcData = null;
      _calcError = null;
    });

    Either<String, Map<String, dynamic>> res;
    try {
      res = await _source.calculateLeave(startKey, endKey, _selectedType?.id);
    } catch (_) {
      res = const Left('Perhitungan belum dapat dimuat. Coba hitung ulang.');
    }
    if (!mounted || revision != _calcRevision) return;
    setState(() {
      _calcLoading = false;
      res.fold(
        (err) {
          _calcError = err;
          _calcData = null;
        },
        (data) {
          _calcData = data;
          _calcError = data['calculation']?['calendar_complete'] != true
              ? 'Kalender kerja belum lengkap. Hubungi HR sebelum mengajukan.'
              : (data['calculation']?['working_days_count'] ?? 0) <= 0
                  ? 'Tidak ada hari kerja dalam rentang ini untuk diajukan.'
                  : null;
        },
      );
    });
  }

  Future<void> _selectImage() async {
    if (_picking || _sending) return;
    setState(() => _picking = true);
    try {
      final image = await (widget.pickImage?.call() ??
          ImagePicker().pickImage(
              source: ImageSource.gallery, maxWidth: 1600, imageQuality: 85));
      if (image == null) return;
      final ext = image.name.toLowerCase().split('.').last;
      if (!['jpg', 'jpeg', 'png'].contains(ext)) {
        if (mounted) {
          setState(() =>
              _attachmentError = 'Gunakan lampiran dengan format JPG atau PNG.');
        }
        return;
      }
      final length = await image.length();
      if (length > 2097152) {
        if (mounted) {
          setState(() => _attachmentError =
              'Ukuran file melebihi 2.048 KiB (2.097.152 byte). Gunakan file maksimal 2 MB.');
        }
        return;
      }
      final bytes = await image.readAsBytes();
      if (mounted) {
        setState(() {
          _image = image;
          _preview = bytes;
          _attachmentError = null;
        });
      }
    } catch (_) {
      if (mounted) {
        setState(() => _attachmentError =
            'Galeri belum dapat dibuka. Periksa izin foto lalu coba lagi.');
      }
    } finally {
      if (mounted) setState(() => _picking = false);
    }
  }

  bool get _calculationReady {
    if (!widget.kind.isPermission) return true;
    if (_calcLoading || _calcError != null || _calcData == null) return false;
    final calc = _calcData!['calculation'];
    final balance = _calcData!['balance'];
    return calc is Map &&
        calc['calendar_complete'] == true &&
        calc['working_days_count'] is int &&
        calc['working_days_count'] > 0 &&
        (balance == null ||
            (balance is Map &&
                balance['sufficient'] == true &&
                balance['not_allocated'] != true));
  }

  Future<void> _send() async {
    if (_sending ||
        _picking ||
        _reviewing ||
        !_form.currentState!.validate() ||
        !_calculationReady) {
      return;
    }
    if (_selectedType != null &&
        (_typesLoading || _selectionUnavailable || _typesError != null)) {
      return;
    }
    if (_selectedType?.requiresAttachment == true && _image == null) {
      setState(() => _attachmentError = 'Jenis cuti ini memerlukan lampiran.');
      return;
    }
    if (_image != null && await _image!.length() > 2097152) {
      setState(() => _attachmentError =
          'Ukuran file melebihi 2.048 KiB (2.097.152 byte). Gunakan file maksimal 2 MB.');
      return;
    }
    FocusScope.of(context).unfocus();
    if (widget.kind.isPermission) {
      final revision = _calcRevision;
      setState(() => _reviewing = true);
      final approved = await showDialog<bool>(
          context: context,
          builder: (context) => AlertDialog(
                title: const Text('Tinjau pengajuan'),
                scrollable: true,
                content: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      Text(_selectedType?.name ?? 'Izin umum (bukan cuti)'),
                      Text(
                          '${historyDate(_date!)} sampai ${historyDate(_endDate ?? _date!)}'),
                      Text(
                          '${_calcData!['calculation']['working_days_count']} hari kerja · ${_calcData!['calculation']['skipped_days_count']} hari libur dilewati'),
                      const SizedBox(height: 12),
                      Text(_calcData!['balance'] == null
                          ? 'Tidak mengurangi saldo cuti.'
                          : '${_calcData!['calculation']['working_days_count']} hari menjadi cadangan saat dikirim, belum terpakai. Tersedia setelah dikirim: ${_calcData!['balance']['remaining_after']} hari.'),
                      Text('Alasan: ${_description.text.trim()}'),
                      Text('Lampiran: ${_image?.name ?? 'Tidak ada'}'),
                      const SizedBox(height: 12),
                      const Text(
                          'Saldo belum berubah. Server memeriksa ulang kalender, saldo dan pengajuan bertumpuk saat dikirim.'),
                    ]),
                actions: [
                  TextButton(
                      onPressed: () => Navigator.pop(context, false),
                      child: const Text('Kembali mengedit')),
                  FilledButton(
                      onPressed: () => Navigator.pop(context, true),
                      child: const Text('Kirim pengajuan'))
                ],
              ));
      if (!mounted) return;
      setState(() => _reviewing = false);
      if (approved != true || revision != _calcRevision || !_calculationReady) {
        return;
      }
    }
    setState(() => _sending = true);
    _sender.send(
        date: historyDateKey(_date!),
        startDate: widget.kind.isPermission ? historyDateKey(_date!) : null,
        endDate: widget.kind.isPermission
            ? historyDateKey(_endDate ?? _date!)
            : null,
        description: _description.text.trim(),
        amount:
            widget.kind.isReimbursement ? submissionAmount(_amount.text) : null,
        duration:
            widget.kind.isOvertime ? int.tryParse(_amount.text.trim()) : null,
        image: _image,
        leaveType: _selectedType);
  }

  @override
  Widget build(BuildContext context) => BlocConsumer<SubmissionSendCubit,
          SubmissionSendState>(
      bloc: _sender,
      listener: (context, state) {
        if (state.record != null) {
          Navigator.pop(context, state.record);
        } else if (!state.loading) {
          setState(() => _sending = false);
        }
      },
      builder: (context, state) {
        final theme = Theme.of(context);
        final busy = _sending || _picking || _reviewing;
        return PopScope(
            canPop: !busy,
            child: Scaffold(
                appBar: AppBar(
                    title: Text('Ajukan ${widget.kind.title.toLowerCase()}')),
                body: SafeArea(
                    child: Form(
                        key: _form,
                        child: ListView(
                            padding: const EdgeInsets.all(24),
                            children: [
                              Text(
                                  widget.kind.isPermission
                                      ? 'Sampaikan keperluan Anda'
                                      : widget.kind.isOvertime
                                          ? 'Ajukan lembur'
                                          : 'Ajukan penggantian biaya',
                                  style: theme.textTheme.headlineSmall
                                      ?.copyWith(fontWeight: FontWeight.w700)),
                              const SizedBox(height: 8),
                              Text(
                                  widget.kind.isPermission
                                      ? 'Pilih rentang tanggal, periksa hari kerja dan saldo, lalu tinjau sebelum dikirim.'
                                      : widget.kind.isOvertime
                                          ? 'Isi tanggal, durasi dalam menit dan catatan pekerjaan.'
                                          : 'Isi tanggal, nominal dan rincian biaya. Status diperbarui oleh pengelola.',
                                  style: TextStyle(
                                      color:
                                          theme.colorScheme.onSurfaceVariant)),
                              const SizedBox(height: 24),
                              if (widget.kind.isPermission) ...[
                                if (_typesLoading)
                                  const LinearProgressIndicator(
                                      semanticsLabel: 'Memuat jenis cuti'),
                                if (_typesError != null)
                                  Text(_typesError!,
                                      style: TextStyle(
                                          color: theme.colorScheme.error)),
                                if (!_typesLoading &&
                                    _typesError == null &&
                                    _types.isEmpty)
                                  const Text(
                                      'Belum ada jenis cuti aktif. Izin umum tetap tersedia.'),
                                DropdownButtonFormField<int>(
                                  initialValue: _selectedType?.id ?? 0,
                                  isExpanded: true,
                                  itemHeight: null,
                                  decoration: const InputDecoration(
                                      labelText: 'Jenis pengajuan'),
                                  items: [
                                    const DropdownMenuItem(
                                        value: 0,
                                        child: Text('Izin umum (bukan cuti)')),
                                    for (final type in _types)
                                      DropdownMenuItem(
                                          value: type.id,
                                          child: Text(
                                              '${type.code} · ${type.name}')),
                                    if (_selectionUnavailable &&
                                        _selectedType != null)
                                      DropdownMenuItem(
                                          value: _selectedType!.id,
                                          enabled: false,
                                          child: Text(
                                              '${_selectedType!.name} (belum tersedia)')),
                                  ],
                                  selectedItemBuilder: (_) => [
                                    const Text('Izin umum (bukan cuti)',
                                        maxLines: 1,
                                        overflow: TextOverflow.ellipsis),
                                    for (final type in _types)
                                      Text(type.name,
                                          maxLines: 1,
                                          overflow: TextOverflow.ellipsis),
                                    if (_selectionUnavailable &&
                                        _selectedType != null)
                                      Text(_selectedType!.name,
                                          maxLines: 1,
                                          overflow: TextOverflow.ellipsis),
                                  ],
                                  onChanged: busy || _typesLoading
                                      ? null
                                      : (id) {
                                          setState(() {
                                            _selectedType = id == 0
                                                ? null
                                                : _types.firstWhere(
                                                    (row) => row.id == id);
                                            _selectionUnavailable = false;
                                            _attachmentError = null;
                                          });
                                          _recalculate();
                                        },
                                ),
                                if (_selectionUnavailable)
                                  Text(
                                      'Pilihan cuti belum tersedia. Muat ulang atau pilih jenis lain; pilihan Anda belum diubah.',
                                      style: TextStyle(
                                          color: theme.colorScheme.error)),
                                Align(
                                    alignment: Alignment.centerLeft,
                                    child: TextButton.icon(
                                        onPressed: busy || _typesLoading
                                            ? null
                                            : _loadTypes,
                                        icon: const Icon(Icons.refresh),
                                        label: const Text(
                                            'Muat ulang jenis cuti'))),
                                if (_selectedType != null)
                                  LeavePolicySummary(policy: _selectedType!),
                                const SizedBox(height: 20),
                              ],
                              if (widget.kind.isPermission) ...[
                                Column(children: [
                                  Padding(
                                    padding: EdgeInsets.zero,
                                    child: TextFormField(
                                        controller: _dateText,
                                        readOnly: true,
                                        enabled: !busy,
                                        onTap: _selectDate,
                                        decoration: const InputDecoration(
                                            labelText: 'Tanggal mulai',
                                            suffixIcon: Icon(
                                                Icons.calendar_today_outlined)),
                                        validator: (_) => _date == null
                                            ? 'Pilih tanggal mulai.'
                                            : null),
                                  ),
                                  const SizedBox(height: 12),
                                  Padding(
                                    padding: EdgeInsets.zero,
                                    child: TextFormField(
                                        controller: _endDateText,
                                        readOnly: true,
                                        enabled: !busy,
                                        onTap: _selectEndDate,
                                        decoration: const InputDecoration(
                                            labelText: 'Tanggal selesai',
                                            suffixIcon:
                                                Icon(Icons.event_outlined)),
                                        validator: (_) => _endDate == null
                                            ? 'Pilih tanggal selesai.'
                                            : null),
                                  ),
                                ]),
                                if (_calcLoading)
                                  const Padding(
                                      padding:
                                          EdgeInsets.symmetric(vertical: 8),
                                      child: LinearProgressIndicator(
                                          semanticsLabel:
                                              'Menghitung hari kerja…')),
                                if (_calcError != null)
                                  Padding(
                                      padding: const EdgeInsets.symmetric(
                                          vertical: 8),
                                      child: Container(
                                          padding: const EdgeInsets.all(12),
                                          decoration: BoxDecoration(
                                              color: theme
                                                  .colorScheme.errorContainer
                                                  .withOpacity(0.5),
                                              borderRadius:
                                                  BorderRadius.circular(8)),
                                          child: Text(_calcError!,
                                              style: TextStyle(
                                                  color:
                                                      theme.colorScheme.error,
                                                  fontSize: 12)))),
                                if (_calcData != null) ...[
                                  const SizedBox(height: 10),
                                  Container(
                                    padding: const EdgeInsets.all(14),
                                    decoration: BoxDecoration(
                                        color: theme.colorScheme.surfaceVariant
                                            .withOpacity(0.4),
                                        border: Border.all(
                                            color: theme
                                                .colorScheme.outlineVariant),
                                        borderRadius:
                                            BorderRadius.circular(10)),
                                    child: Column(
                                      crossAxisAlignment:
                                          CrossAxisAlignment.start,
                                      children: [
                                        Wrap(
                                          spacing: 12,
                                          runSpacing: 4,
                                          children: [
                                            Text(
                                              '${_calcData!['calculation']['working_days_count']} Hari Kerja',
                                              style: TextStyle(
                                                  fontWeight: FontWeight.w700,
                                                  color: theme
                                                      .colorScheme.primary),
                                            ),
                                            Text(
                                              'Total ${_calcData!['calculation']['total_calendar_days']} hari kalender',
                                              style: TextStyle(
                                                  fontSize: 11,
                                                  color: theme.colorScheme
                                                      .onSurfaceVariant),
                                            ),
                                          ],
                                        ),
                                        if ((_calcData!['calculation']
                                                    ['skipped_days_count'] ??
                                                0) >
                                            0)
                                          Padding(
                                            padding:
                                                const EdgeInsets.only(top: 4),
                                            child: Text(
                                              '(${_calcData!['calculation']['skipped_days_count']} hari libur dilewati otomatis)',
                                              style: TextStyle(
                                                  fontSize: 11,
                                                  color: theme.colorScheme
                                                      .onSurfaceVariant),
                                            ),
                                          ),
                                        if (_calcData!['balance'] != null) ...[
                                          const Divider(height: 14),
                                          if (_calcData!['balance']
                                                  ['not_allocated'] ==
                                              true)
                                            Text(
                                              'Saldo cuti tahun ini belum dialokasikan untuk Anda.',
                                              style: TextStyle(
                                                  fontSize: 12,
                                                  color:
                                                      theme.colorScheme.error,
                                                  fontWeight: FontWeight.w600),
                                            )
                                          else ...[
                                            Text(
                                                'Periode ${_calcData!['period_year'] ?? _date!.year}. Alokasi: ${_calcData!['balance']['allocated_days']} · Cadangan: ${_calcData!['balance']['reserved_days']} · Terpakai: ${_calcData!['balance']['used_days']} hari'),
                                            Text(
                                              'Tersedia: ${_calcData!['balance']['remaining_days']} hari → Setelah dikirim: ${_calcData!['balance']['remaining_after']} hari. Hari pengajuan menjadi cadangan sampai approval akhir.',
                                              style: TextStyle(
                                                  fontSize: 12,
                                                  color: _calcData!['balance']
                                                              ['sufficient'] ==
                                                          true
                                                      ? theme
                                                          .colorScheme.onSurface
                                                      : theme.colorScheme.error,
                                                  fontWeight: FontWeight.w600),
                                            ),
                                            if (_calcData!['balance']
                                                    ['sufficient'] !=
                                                true)
                                              Text(
                                                'Saldo tidak mencukupi untuk durasi ini.',
                                                style: TextStyle(
                                                    fontSize: 11,
                                                    color: theme
                                                        .colorScheme.error),
                                              ),
                                          ],
                                        ],
                                      ],
                                    ),
                                  ),
                                ],
                                TextButton(
                                    onPressed:
                                        busy || _calcLoading || _date == null
                                            ? null
                                            : _recalculate,
                                    child: const Text(
                                        'Hitung ulang hari dan saldo')),
                                const Text(
                                    'Tersedia dapat diajukan. Cadangan menunggu keputusan. Terpakai sudah disetujui.'),
                              ] else ...[
                                TextFormField(
                                    controller: _dateText,
                                    readOnly: true,
                                    enabled: !busy,
                                    onTap: _selectDate,
                                    decoration: const InputDecoration(
                                        labelText: 'Tanggal',
                                        suffixIcon: Icon(
                                            Icons.calendar_today_outlined)),
                                    validator: (_) => _date == null
                                        ? 'Pilih tanggal pengajuan.'
                                        : null),
                              ],
                              if (!widget.kind.isPermission) ...[
                                const SizedBox(height: 20),
                                TextFormField(
                                    controller: _amount,
                                    enabled: !busy,
                                    keyboardType:
                                        TextInputType.numberWithOptions(
                                            decimal: !widget.kind.isOvertime),
                                    decoration: InputDecoration(
                                        labelText: widget.kind.isOvertime
                                            ? 'Durasi (menit)'
                                            : 'Nominal (Rp)',
                                        hintText: widget.kind.isOvertime
                                            ? '120'
                                            : '150.000,50',
                                        helperText: widget.kind.isOvertime
                                            ? 'Bilangan bulat positif, dalam menit (maks. 1.440 menit / 24 jam).'
                                            : 'Gunakan koma untuk desimal, maksimal 2 angka.'),
                                    validator: (value) => widget.kind.isOvertime
                                        ? ((int.tryParse(value?.trim() ?? '') ??
                                                        0) >
                                                    0 &&
                                                (int.tryParse(value?.trim() ??
                                                            '') ??
                                                        0) <=
                                                    1440
                                            ? null
                                            : 'Isi durasi 1–1.440 menit (maks. 24 jam).')
                                        : submissionAmount(value ?? '') == null
                                            ? 'Isi nominal positif, maksimal 9.999.999.999,99.'
                                            : null),
                              ],
                              const SizedBox(height: 20),
                              TextFormField(
                                  controller: _description,
                                  enabled: !busy,
                                  minLines: 3,
                                  maxLines: 6,
                                  maxLength: 5000,
                                  decoration: InputDecoration(
                                      labelText: widget.kind.isPermission
                                          ? 'Keperluan'
                                          : widget.kind.isOvertime
                                              ? 'Catatan pekerjaan'
                                              : 'Rincian biaya',
                                      alignLabelWithHint: true),
                                  validator: (value) =>
                                      value?.trim().isNotEmpty != true
                                          ? 'Keterangan wajib diisi.'
                                          : value!.trim().length > 5000
                                              ? 'Maksimal 5.000 karakter.'
                                              : null),
                              if (!widget.kind.isOvertime) ...[
                                const SizedBox(height: 20),
                                Text(
                                    _selectedType?.requiresAttachment == true
                                        ? 'Lampiran (wajib)'
                                        : 'Lampiran (opsional)',
                                    style: theme.textTheme.titleMedium
                                        ?.copyWith(
                                            fontWeight: FontWeight.w700)),
                                const SizedBox(height: 8),
                                Text('JPG/PNG, maksimal 2 MB.',
                                    style: TextStyle(
                                        color:
                                            theme.colorScheme.onSurfaceVariant,
                                        fontSize: 12)),
                                const SizedBox(height: 12),
                                if (_preview != null) ...[
                                  ClipRRect(
                                      borderRadius: BorderRadius.circular(12),
                                      child: Image.memory(_preview!,
                                          height: 160,
                                          fit: BoxFit.cover,
                                          errorBuilder: (_, __, ___) =>
                                              const Padding(
                                                  padding: EdgeInsets.all(20),
                                                  child: Text(
                                                      'Pratinjau tidak tersedia. Pilih kembali lampiran bila diperlukan.')))),
                                  const SizedBox(height: 8),
                                  Text(_image!.name),
                                ],
                                Wrap(spacing: 12, children: [
                                  OutlinedButton.icon(
                                      onPressed: busy ? null : _selectImage,
                                      icon: const Icon(Icons.attach_file),
                                      label: Text(_picking
                                          ? 'Membuka galeri…'
                                          : _image == null
                                              ? 'Pilih lampiran'
                                              : 'Ganti lampiran')),
                                  if (_image != null)
                                    TextButton(
                                        onPressed: busy
                                            ? null
                                            : () => setState(() {
                                                  _image = null;
                                                  _preview = null;
                                                  _attachmentError = null;
                                                }),
                                        child: const Text('Hapus lampiran')),
                                ]),
                                if (_attachmentError != null)
                                  Text(_attachmentError!,
                                      style: TextStyle(
                                          color: theme.colorScheme.error)),
                              ],
                              if (state.error != null)
                                Padding(
                                    padding: const EdgeInsets.only(top: 16),
                                    child: Semantics(
                                        liveRegion: true,
                                        child: Text(state.error!,
                                            style: TextStyle(
                                                color:
                                                    theme.colorScheme.error)))),
                              const SizedBox(height: 24),
                              FilledButton(
                                  onPressed: busy ||
                                          !_calculationReady ||
                                          (_selectedType != null &&
                                              (_typesLoading ||
                                                  _selectionUnavailable ||
                                                  _typesError != null))
                                      ? null
                                      : _send,
                                  child: Text(_sending
                                      ? 'Mengirim…'
                                      : widget.kind.isPermission
                                          ? 'Tinjau pengajuan'
                                          : 'Kirim pengajuan')),
                              const SizedBox(height: 8),
                              TextButton(
                                  onPressed: busy
                                      ? null
                                      : () => Navigator.pop(context),
                                  child: const Text('Batal')),
                            ])))));
      });
}
