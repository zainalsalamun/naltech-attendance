import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import '../../../core/theme/theme_controller.dart';
import '../bloc/get_attendance_by_date/get_attendance_by_date_bloc.dart';
import '../models/history_presentation.dart';
import '../widgets/history_attendace.dart';

class HistoryPage extends StatefulWidget {
  const HistoryPage({super.key, this.now});
  final DateTime Function()? now;
  @override
  State<HistoryPage> createState() => _HistoryPageState();
}

class _HistoryPageState extends State<HistoryPage> {
  late DateTime _selected;
  DateTime get _today {
    final now = (widget.now ?? DateTime.now)();
    return DateTime(now.year, now.month, now.day);
  }

  @override
  void initState() {
    super.initState();
    _selected = _today;
    _request();
  }

  void _request() => context.read<GetAttendanceByDateBloc>().add(
      GetAttendanceByDateEvent.getAttendanceByDate(historyDateKey(_selected)));
  void _select(DateTime date) {
    setState(() => _selected = DateTime(date.year, date.month, date.day));
    _request();
  }

  Future<void> _refresh() async {
    final bloc = context.read<GetAttendanceByDateBloc>();
    final completion = bloc.stream.firstWhere(
        (state) => state.maybeWhen(loading: () => false, orElse: () => true));
    _request();
    await completion;
  }

  Future<void> _choose() async {
    final date = await showDialog<DateTime>(
        context: context,
        builder: (_) => HistoryDateDialog(selected: _selected, today: _today));
    if (date != null && mounted) _select(date);
  }

  Widget _message(
      BuildContext context, IconData icon, String title, String detail,
      {bool retry = false}) {
    final theme = Theme.of(context);
    return Container(
        width: double.infinity,
        padding: const EdgeInsets.all(24),
        decoration: BoxDecoration(
            color: theme.colorScheme.surface,
            border: Border.all(color: theme.colorScheme.outline),
            borderRadius: BorderRadius.circular(16)),
        child: Column(children: [
          Icon(icon, size: 36, color: theme.colorScheme.primary),
          const SizedBox(height: 16),
          Text(title,
              textAlign: TextAlign.center,
              style: theme.textTheme.titleMedium
                  ?.copyWith(fontWeight: FontWeight.w700)),
          const SizedBox(height: 8),
          Text(detail,
              textAlign: TextAlign.center,
              style: TextStyle(color: theme.colorScheme.onSurfaceVariant)),
          if (retry) ...[
            const SizedBox(height: 18),
            OutlinedButton(onPressed: _request, child: const Text('Coba lagi'))
          ],
        ]));
  }

  void _info() => showModalBottomSheet<void>(
      context: context,
      showDragHandle: true,
      isScrollControlled: true,
      builder: (context) => SafeArea(
          child: SingleChildScrollView(
              padding: const EdgeInsets.fromLTRB(24, 0, 24, 24),
              child: Column(
                  mainAxisSize: MainAxisSize.min,
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text('Tentang catatan kehadiran',
                        style: Theme.of(context).textTheme.titleLarge),
                    const SizedBox(height: 12),
                    const Text(
                        'Tanggal pilihan mengikuti perangkat. Jam masuk dan pulang ditampilkan sesuai catatan yang tersimpan. Zona waktu perusahaan dan jadwal shift historis belum tersedia; ketepatan waktu dan keterlambatan belum dapat ditentukan di sini.'),
                    const SizedBox(height: 12),
                    const Text(
                        'Tidak ada catatan bukan berarti hari libur atau tidak hadir. Metode absensi dan kesesuaian lokasi tidak disimpulkan dari koordinat tersimpan.'),
                    const SizedBox(height: 12),
                    TextButton(
                        onPressed: () => Navigator.pop(context),
                        child: const Text('Mengerti')),
                  ]))));
  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final colors = theme.colorScheme;
    return Scaffold(
        appBar: AppBar(title: const Text('Riwayat'), actions: [
          IconButton(
              tooltip: 'Informasi riwayat',
              onPressed: _info,
              icon: const Icon(Icons.info_outline)),
          const WorkspaceThemeToggle()
        ]),
        body: SafeArea(
            child: RefreshIndicator(
                onRefresh: _refresh,
                child: ListView(
                    padding: const EdgeInsets.all(20),
                    physics: const AlwaysScrollableScrollPhysics(),
                    children: [
                      Text('Catatan kehadiran',
                          style: theme.textTheme.headlineSmall?.copyWith(
                              fontWeight: FontWeight.w700, letterSpacing: -.5)),
                      const SizedBox(height: 8),
                      Text('Lihat jam masuk, pulang, dan lokasi yang tercatat.',
                          style: TextStyle(color: colors.onSurfaceVariant)),
                      const SizedBox(height: 20),
                      Container(
                          decoration: BoxDecoration(
                              color: colors.surface,
                              border: Border.all(color: colors.outline),
                              borderRadius: BorderRadius.circular(14)),
                          child: Row(children: [
                            IconButton(
                                tooltip: 'Hari sebelumnya',
                                onPressed: _selected.isAfter(DateTime(1900))
                                    ? () => _select(_selected
                                        .subtract(const Duration(days: 1)))
                                    : null,
                                icon: const Icon(Icons.chevron_left)),
                            Expanded(
                                child: TextButton(
                                    onPressed: _choose,
                                    child: Column(children: [
                                      const Icon(Icons.calendar_today_outlined,
                                          size: 19),
                                      const SizedBox(height: 6),
                                      Text(historyDate(_selected),
                                          textAlign: TextAlign.center,
                                          style: TextStyle(
                                              fontWeight: FontWeight.w700,
                                              color: colors.onSurface))
                                    ]))),
                            IconButton(
                                tooltip: 'Hari berikutnya',
                                onPressed: _selected.isBefore(_today)
                                    ? () => _select(
                                        _selected.add(const Duration(days: 1)))
                                    : null,
                                icon: const Icon(Icons.chevron_right)),
                          ])),
                      if (_selected != _today)
                        Align(
                            alignment: Alignment.centerRight,
                            child: TextButton(
                                onPressed: () => _select(_today),
                                child: const Text('Hari ini'))),
                      const SizedBox(height: 18),
                      BlocBuilder<GetAttendanceByDateBloc,
                              GetAttendanceByDateState>(
                          builder: (context, state) => state.maybeWhen(
                                loading: () => const Padding(
                                    padding: EdgeInsets.all(36),
                                    child: Center(
                                        child: CircularProgressIndicator(
                                            semanticsLabel: 'Memuat riwayat'))),
                                empty: () => _message(
                                    context,
                                    Icons.event_note_outlined,
                                    'Belum ada catatan',
                                    'Tidak ada catatan kehadiran pada tanggal pilihan. Coba tanggal lain atau muat ulang.'),
                                error: (message) => _message(
                                    context,
                                    Icons.cloud_off_outlined,
                                    'Riwayat belum dapat dimuat',
                                    message,
                                    retry: true),
                                loaded: (record) {
                                  if (record.date == null ||
                                      historyDateKey(record.date!) !=
                                          historyDateKey(_selected)) {
                                    return const Padding(
                                        padding: EdgeInsets.all(36),
                                        child: Center(
                                            child: CircularProgressIndicator(
                                                semanticsLabel:
                                                    'Memuat tanggal pilihan')));
                                  }
                                  final status = historyStatus(record);
                                  return Column(
                                      crossAxisAlignment:
                                          CrossAxisAlignment.stretch,
                                      children: [
                                        Container(
                                            padding: const EdgeInsets.all(18),
                                            decoration: BoxDecoration(
                                                color: const Color(0xFF192A48),
                                                borderRadius:
                                                    BorderRadius.circular(16)),
                                            child: Row(children: [
                                              const Icon(
                                                  Icons.fact_check_outlined,
                                                  color: Color(0xFFB9C6DC),
                                                  size: 28),
                                              const SizedBox(width: 12),
                                              Expanded(
                                                  child: Column(
                                                      crossAxisAlignment:
                                                          CrossAxisAlignment
                                                              .start,
                                                      children: [
                                                    const Text(
                                                        'Kehadiran hari pilihan',
                                                        style: TextStyle(
                                                            color: Color(
                                                                0xFFB9C6DC),
                                                            fontSize: 12)),
                                                    const SizedBox(height: 5),
                                                    Text(status,
                                                        style: const TextStyle(
                                                            color: Colors.white,
                                                            fontSize: 17,
                                                            fontWeight:
                                                                FontWeight
                                                                    .w700))
                                                  ]))
                                            ])),
                                        const SizedBox(height: 16),
                                        HistoryAttendance(
                                            statusAbsen: 'Absen masuk',
                                            time: record.timeIn ?? '',
                                            date: historyDateKey(_selected),
                                            coordinates: record.latlonIn),
                                        const SizedBox(height: 12),
                                        HistoryAttendance(
                                            statusAbsen: 'Absen pulang',
                                            isAttendanceIn: false,
                                            time: record.timeOut ?? '',
                                            date: historyDateKey(_selected),
                                            coordinates: record.latlonOut,
                                            overtimeMinutes:
                                                record.overtimeMinutes,
                                            overtimePay: record.overtimePay),
                                      ]);
                                },
                                orElse: () => _message(
                                    context,
                                    Icons.history,
                                    'Pilih tanggal riwayat',
                                    'Muat ulang untuk melihat catatan kehadiran.',
                                    retry: true),
                              )),
                      const SizedBox(height: 16),
                      Text(
                          'Jam sesuai catatan kehadiran • Tanggal pilihan mengikuti perangkat',
                          style: TextStyle(
                              color: colors.onSurfaceVariant, fontSize: 12)),
                      const SizedBox(height: 8),
                      TextButton.icon(
                          onPressed: _request,
                          icon: const Icon(Icons.sync, size: 18),
                          label: const Text('Muat ulang riwayat')),
                    ]))));
  }
}

class HistoryDateDialog extends StatefulWidget {
  const HistoryDateDialog(
      {super.key, required this.selected, required this.today});
  final DateTime selected, today;
  @override
  State<HistoryDateDialog> createState() => _HistoryDateDialogState();
}

class _HistoryDateDialogState extends State<HistoryDateDialog> {
  Future<void> _chooseYear() async {
    final selected = await showDialog<DateTime>(
        context: context,
        builder: (context) => Dialog(
            child: ConstrainedBox(
                constraints:
                    const BoxConstraints(maxWidth: 360, maxHeight: 420),
                child: Column(children: [
                  const Padding(
                      padding: EdgeInsets.all(16),
                      child: Text('Pilih tahun',
                          style: TextStyle(fontWeight: FontWeight.w700))),
                  Expanded(
                      child: YearPicker(
                          firstDate: DateTime(1900),
                          lastDate: widget.today,
                          selectedDate: _month,
                          onChanged: (date) => Navigator.pop(context, date))),
                  TextButton(
                      onPressed: () => Navigator.pop(context),
                      child: const Text('Batal'))
                ]))));
    if (selected != null && mounted) {
      setState(() => _month = DateTime(
          selected.year,
          selected.year == widget.today.year &&
                  _month.month > widget.today.month
              ? widget.today.month
              : _month.month));
    }
  }

  late DateTime _month = DateTime(widget.selected.year, widget.selected.month);
  @override
  Widget build(BuildContext context) {
    final colors = Theme.of(context).colorScheme;
    final offset = DateTime(_month.year, _month.month).weekday - 1;
    final count = DateTime(_month.year, _month.month + 1, 0).day;
    return Dialog(
        child: ConstrainedBox(
            constraints: const BoxConstraints(maxWidth: 420),
            child: SingleChildScrollView(
                padding: const EdgeInsets.all(16),
                child: Column(mainAxisSize: MainAxisSize.min, children: [
                  const Text('Pilih tanggal',
                      style:
                          TextStyle(fontSize: 18, fontWeight: FontWeight.w700)),
                  const SizedBox(height: 12),
                  Row(children: [
                    IconButton(
                        tooltip: 'Bulan sebelumnya',
                        onPressed: _month.isAfter(DateTime(1900))
                            ? () => setState(() => _month =
                                DateTime(_month.year, _month.month - 1))
                            : null,
                        icon: const Icon(Icons.chevron_left)),
                    Expanded(
                        child: TextButton(
                            onPressed: _chooseYear,
                            child: Text(
                                '${historyMonth(_month.month)} ${_month.year}',
                                textAlign: TextAlign.center,
                                style: TextStyle(
                                    fontWeight: FontWeight.w700,
                                    color: colors.onSurface)))),
                    IconButton(
                        tooltip: 'Bulan berikutnya',
                        onPressed: _month.isBefore(
                                DateTime(widget.today.year, widget.today.month))
                            ? () => setState(() => _month =
                                DateTime(_month.year, _month.month + 1))
                            : null,
                        icon: const Icon(Icons.chevron_right))
                  ]),
                  Row(children: [
                    for (final day in [
                      'Sen',
                      'Sel',
                      'Rab',
                      'Kam',
                      'Jum',
                      'Sab',
                      'Min'
                    ])
                      Expanded(
                          child: Center(
                              child: FittedBox(
                                  fit: BoxFit.scaleDown,
                                  child: Text(day,
                                      style: TextStyle(
                                          fontSize: 11,
                                          color: colors.onSurfaceVariant)))))
                  ]),
                  const SizedBox(height: 8),
                  GridView.builder(
                      shrinkWrap: true,
                      physics: const NeverScrollableScrollPhysics(),
                      itemCount: offset + count,
                      gridDelegate:
                          const SliverGridDelegateWithFixedCrossAxisCount(
                              crossAxisCount: 7, mainAxisExtent: 46),
                      itemBuilder: (context, index) {
                        if (index < offset) return const SizedBox();
                        final date = DateTime(
                            _month.year, _month.month, index - offset + 1);
                        final selected = date == widget.selected;
                        final disabled = date.isAfter(widget.today);
                        return Padding(
                            padding: const EdgeInsets.all(2),
                            child: Semantics(
                                label: 'Pilih ${historyDate(date)}',
                                selected: selected,
                                button: true,
                                enabled: !disabled,
                                child: ExcludeSemantics(
                                    child: TextButton(
                                        onPressed: disabled
                                            ? null
                                            : () =>
                                                Navigator.pop(context, date),
                                        style: TextButton.styleFrom(
                                            padding: EdgeInsets.zero,
                                            backgroundColor: selected
                                                ? colors.primary
                                                : null,
                                            foregroundColor: selected
                                                ? colors.onPrimary
                                                : colors.onSurface,
                                            shape: RoundedRectangleBorder(
                                                borderRadius:
                                                    BorderRadius.circular(8))),
                                        child: FittedBox(
                                            fit: BoxFit.scaleDown,
                                            child: Text('${date.day}'))))));
                      }),
                  const SizedBox(height: 8),
                  TextButton(
                      onPressed: () => Navigator.pop(context),
                      child: const Text('Batal')),
                ]))));
  }
}
