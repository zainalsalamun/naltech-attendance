import 'dart:io';
import 'package:flutter/material.dart';
import 'package:image_picker/image_picker.dart';
import '../../../data/datasources/face_enrollment_remote_datasource.dart';
import 'face_capture_page.dart';

class FaceEnrollmentPage extends StatefulWidget {
  const FaceEnrollmentPage({super.key, this.source, this.capture});
  final FaceEnrollmentRemoteDatasource? source;
  final Future<XFile?> Function(BuildContext)? capture;
  @override
  State<FaceEnrollmentPage> createState() => _FaceEnrollmentPageState();
}

class _FaceEnrollmentPageState extends State<FaceEnrollmentPage> {
  late final _source = widget.source ?? FaceEnrollmentRemoteDatasource();
  FaceEnrollmentSnapshot? _snapshot;
  String? _error, _notice;
  XFile? _photo;
  bool _loading = true, _busy = false, _consent = false;
  int _generation = 0;
  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _remove(XFile? file) async {
    if (file == null) return;
    try {
      await File(file.path).delete();
    } catch (_) {/* Temporary OS cache only. */}
  }

  @override
  void dispose() {
    _generation++;
    _remove(_photo);
    super.dispose();
  }

  Future<void> _load() async {
    final generation = ++_generation;
    setState(() {
      _loading = true;
      _error = null;
    });
    final result = await _source.load();
    if (!mounted || generation != _generation) return;
    setState(() {
      _loading = false;
      result.fold((error) {
        _error = error;
        _snapshot = null;
      }, (value) => _snapshot = value);
    });
  }

  Future<void> _capture() async {
    if (_busy) return;
    setState(() => _busy = true);
    try {
      final photo = await (widget.capture?.call(context) ??
          Navigator.of(context).push<XFile>(
              MaterialPageRoute(builder: (_) => const FaceCapturePage())));
      if (!mounted) {
        await _remove(photo);
        return;
      }
      if (photo != null) {
        await _remove(_photo);
        if (!mounted) {
          await _remove(photo);
          return;
        }
        if (mounted) {
          setState(() {
            _photo = photo;
            _consent = false;
            _notice = null;
          });
        }
      }
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Future<void> _submit({bool preview = false}) async {
    final photo = _photo;
    if (_busy || photo == null || (!preview && !_consent)) return;
    setState(() {
      _busy = true;
      _notice = null;
    });
    try {
      final result = preview
          ? await _source.preview(photo)
          : await _source.submit(photo, consent: _consent);
      if (!mounted) return;
      result.fold((error) => setState(() => _notice = error), (matched) {
        setState(() => _notice = preview
            ? '${matched ? 'Wajah cocok' : 'Wajah belum cocok'}. Hasil uji ini tidak mencatat absensi dan belum memeriksa liveness.'
            : 'Foto diajukan. Tunggu peninjauan HR/admin.');
      });
      if (!preview && result.isRight()) {
        await _remove(_photo);
        _photo = null;
        _consent = false;
      }
      await _load();
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Future<void> _withdraw(FaceEnrollmentRecord row) async {
    if (_busy) return;
    final confirmed = await showDialog<bool>(
        context: context,
        builder: (context) => AlertDialog(
              title: const Text('Cabut pendaftaran wajah?'),
              content: const Text(
                  'Foto referensi ini tidak dapat digunakan lagi. Anda dapat mengajukan pendaftaran baru.'),
              actions: [
                TextButton(
                    onPressed: () => Navigator.pop(context, false),
                    child: const Text('Batal')),
                FilledButton(
                    onPressed: () => Navigator.pop(context, true),
                    child: const Text('Cabut'))
              ],
            ));
    if (!mounted || confirmed != true) return;
    setState(() => _busy = true);
    try {
      final result = await _source.withdraw(row.id);
      if (!mounted) return;
      setState(() => _notice =
          result.fold((error) => error, (_) => 'Pendaftaran wajah dicabut.'));
      await _load();
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) => Scaffold(
        appBar: AppBar(title: const Text('Pendaftaran wajah')),
        body: SafeArea(
            child: RefreshIndicator(
                onRefresh: _busy ? () async {} : _load,
                child: ListView(padding: const EdgeInsets.all(24), children: [
                  Text('Foto referensi Anda',
                      style: Theme.of(context).textTheme.headlineSmall),
                  const SizedBox(height: 8),
                  const Text(
                      'Ajukan foto wajah untuk ditinjau HR/admin. Mengganti foto profil tidak mengganti foto referensi ini.'),
                  const SizedBox(height: 16),
                  const Text(
                      'Absensi wajah belum aktif. Persetujuan foto belum berarti verifikasi wajah dan liveness telah selesai.'),
                  const SizedBox(height: 16),
                  if (_loading)
                    const Center(child: CircularProgressIndicator()),
                  if (_error != null) ...[
                    Text(_error!,
                        style: TextStyle(
                            color: Theme.of(context).colorScheme.error)),
                    OutlinedButton(
                        onPressed: _busy ? null : _load,
                        child: const Text('Muat ulang status'))
                  ],
                  if (_notice != null)
                    Padding(
                        padding: const EdgeInsets.symmetric(vertical: 12),
                        child:
                            Text(_notice!, key: const ValueKey('face-notice'))),
                  if (!_loading && _snapshot != null) ...[
                    if (_snapshot!.records.isEmpty)
                      const Text('Belum ada pendaftaran wajah.'),
                    for (final row in _snapshot!.records)
                      Card(
                          color: Theme.of(context).colorScheme.surface,
                          elevation: 0,
                          shape: RoundedRectangleBorder(
                              borderRadius: BorderRadius.circular(16),
                              side: BorderSide(
                                  color:
                                      Theme.of(context).colorScheme.outline)),
                          child: Padding(
                              padding: const EdgeInsets.all(16),
                              child: Column(
                                  crossAxisAlignment: CrossAxisAlignment.start,
                                  children: [
                                    Text(row.label,
                                        style: const TextStyle(
                                            fontWeight: FontWeight.w700)),
                                    if (row.reason?.isNotEmpty == true)
                                      Padding(
                                          padding:
                                              const EdgeInsets.only(top: 8),
                                          child: Text(row.reason!)),
                                    if (row.active)
                                      TextButton(
                                          onPressed: _busy
                                              ? null
                                              : () => _withdraw(row),
                                          child:
                                              const Text('Cabut pendaftaran')),
                                  ]))),
                    if (!_snapshot!.pending ||
                        (_snapshot!.previewAvailable &&
                            _snapshot!.approved)) ...[
                      const SizedBox(height: 20),
                      if (_photo != null)
                        ClipRRect(
                            borderRadius: BorderRadius.circular(16),
                            child: Image.file(File(_photo!.path),
                                height: 240,
                                fit: BoxFit.contain,
                                errorBuilder: (_, __, ___) => const Text(
                                    'Preview foto tidak tersedia. Ambil ulang foto.'))),
                      OutlinedButton.icon(
                          onPressed: _busy ? null : _capture,
                          icon: const Icon(Icons.camera_alt_outlined),
                          label: Text(_photo == null
                              ? 'Ambil foto wajah'
                              : 'Ambil ulang foto')),
                      if (_photo != null && !_snapshot!.pending) ...[
                        CheckboxListTile(
                            value: _consent,
                            onChanged: _busy
                                ? null
                                : (value) =>
                                    setState(() => _consent = value ?? false),
                            contentPadding: EdgeInsets.zero,
                            title: const Text(
                                'Saya menyetujui penggunaan foto ini sebagai referensi verifikasi absensi. Pendaftaran dapat saya cabut.')),
                        FilledButton(
                            onPressed:
                                _busy || !_consent ? null : () => _submit(),
                            child: Text(
                                _busy ? 'Memproses…' : 'Ajukan ke HR/admin')),
                      ],
                      if (_photo != null &&
                          _snapshot!.previewAvailable &&
                          _snapshot!.approved)
                        OutlinedButton(
                            onPressed:
                                _busy ? null : () => _submit(preview: true),
                            child: const Text(
                                'Uji pencocokan · tanpa mencatat absensi')),
                    ],
                  ],
                ]))),
      );
}
