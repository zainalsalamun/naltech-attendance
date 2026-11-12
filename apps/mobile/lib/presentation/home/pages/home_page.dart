import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:geolocator/geolocator.dart';
import '../../../core/theme/theme_controller.dart';
import '../../../data/datasources/attendance_remote_datasource.dart';
import '../../../data/datasources/user_remote_datasource.dart';
import '../bloc/workspace_home/workspace_home_cubit.dart';
import '../bloc/checkin_attendance/checkin_attendance_bloc.dart';
import '../bloc/get_attendance_by_date/get_attendance_by_date_bloc.dart';
import '../bloc/checkout_attendance/checkout_attendance_bloc.dart';
import 'attandences/scanner_page.dart';
import 'attandences/attendance_result_page.dart';
import 'permission_page.dart';
import 'reimbursements/reimbursement_page.dart';

class HomePage extends StatefulWidget {
  const HomePage({super.key, this.active = true, this.onProfile});
  final VoidCallback? onProfile;
  final bool active;
  @override
  State<HomePage> createState() => _HomePageState();
}

class _HomePageState extends State<HomePage> with WidgetsBindingObserver {
  late final WorkspaceHomeCubit _home;
  bool _opening = false;
  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
    _home = WorkspaceHomeCubit(AttendanceRemoteDatasource(),
        UserRemoteDatasource(), HomeLocationService())
      ..load();
  }

  @override
  void didUpdateWidget(covariant HomePage oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (widget.active && !oldWidget.active && !_opening) {
      _home.load();
    }
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    if (state == AppLifecycleState.resumed && widget.active && !_opening) {
      _home.load();
    }
  }

  @override
  void dispose() {
    WidgetsBinding.instance.removeObserver(this);
    _home.close();
    super.dispose();
  }

  Future<void> _attend() async {
    if (_opening || !_home.state.canAttend) return;
    setState(() => _opening = true);
    try {
      // Refresh status and location immediately before opening the existing flow.
      await _home.load();
      if (!mounted || !_home.state.canAttend) return;
      final data = _home.state.data!;
      final checkin = data.action == HomeAction.checkin;
      await Navigator.of(context).push(MaterialPageRoute<void>(
          builder: (_) => data.method == 'qr'
              ? ScannerPage(isCheckin: checkin, serverDate: data.qrDate)
              : AttendanceResultPage(
                  isCheckin: checkin, isMatch: true, attendanceType: 'None')));
    } finally {
      if (mounted) {
        setState(() => _opening = false);
        await _home.load();
      }
    }
  }

  @override
  Widget build(BuildContext context) => MultiBlocListener(
        listeners: [
          BlocListener<CheckinAttendanceBloc, CheckinAttendanceState>(
              listener: (_, state) {
            state.maybeWhen(
                loaded: (_) {
                  _home.load();
                  context.read<GetAttendanceByDateBloc>().refreshCurrent();
                },
                orElse: () {});
          }),
          BlocListener<CheckoutAttendanceBloc, CheckoutAttendanceState>(
              listener: (_, state) {
            state.maybeWhen(
                loaded: (_) {
                  _home.load();
                  context.read<GetAttendanceByDateBloc>().refreshCurrent();
                },
                orElse: () {});
          }),
        ],
        child: BlocBuilder<WorkspaceHomeCubit, WorkspaceHomeState>(
            bloc: _home,
            builder: (context, state) => HomeDashboard(
                  state: state,
                  opening: _opening,
                  onProfile: widget.onProfile,
                  onRefresh: _home.load,
                  onLocate: () => _home.locate(),
                  onAttend: _attend,
                  onPermission: () => Navigator.of(context).push(
                      MaterialPageRoute<void>(
                          builder: (_) => const PermissionPage())),
                  onReimbursement: () => Navigator.of(context).push(
                      MaterialPageRoute<void>(
                          builder: (_) => const ReimbursementPage())),
                )),
      );
}

/// Workspace home: compact presentation of the current server snapshot.
class HomeDashboard extends StatelessWidget {
  const HomeDashboard(
      {super.key,
      required this.state,
      required this.onRefresh,
      required this.onLocate,
      required this.onAttend,
      required this.onPermission,
      required this.onReimbursement,
      this.onProfile,
      this.opening = false});
  final WorkspaceHomeState state;
  final Future<void> Function() onRefresh;
  final VoidCallback onLocate, onAttend, onPermission, onReimbursement;
  final VoidCallback? onProfile;
  final bool opening;

  static const _blue = Color(0xFF315BCE);
  static const _navy = Color(0xFF192A48);
  String _time(String? value) =>
      value == null || value.isEmpty ? '--:--' : value;
  String _scheduleTime(String? value) => value == null || value.isEmpty
      ? 'Belum diatur'
      : value.length >= 5
          ? value.substring(0, 5).replaceAll(':', '.')
          : value;
  String _date(String value) {
    final date = DateTime.tryParse(value);
    if (date == null) return value;
    const days = [
      'Senin',
      'Selasa',
      'Rabu',
      'Kamis',
      'Jumat',
      'Sabtu',
      'Minggu'
    ];
    const months = [
      'Januari',
      'Februari',
      'Maret',
      'April',
      'Mei',
      'Juni',
      'Juli',
      'Agustus',
      'September',
      'Oktober',
      'November',
      'Desember'
    ];
    return '${days[date.weekday - 1]}, ${date.day} ${months[date.month - 1]} ${date.year}';
  }

  String get _locationLabel => switch (state.location) {
        LocationState.idle => 'Lokasi belum diperiksa',
        LocationState.loading => 'Memeriksa lokasi…',
        LocationState.disabled => 'Layanan lokasi mati',
        LocationState.denied => 'Izin lokasi ditolak',
        LocationState.deniedForever => 'Izin lokasi diblokir',
        LocationState.failed => 'Lokasi belum dapat diperoleh',
        LocationState.invalidOffice => 'Lokasi kantor belum valid',
        LocationState.inside => 'Dalam radius kantor',
        LocationState.outside => 'Di luar radius kantor',
      };
  Widget _surface(BuildContext context, Widget child,
          {EdgeInsets padding = const EdgeInsets.all(14)}) =>
      Container(
          padding: padding,
          decoration: BoxDecoration(
              color: Theme.of(context).colorScheme.surface,
              border: Border.all(color: Theme.of(context).colorScheme.outline),
              borderRadius: BorderRadius.circular(16)),
          child: child);

  void _showLocation(BuildContext context) {
    showModalBottomSheet<void>(
        context: context,
        showDragHandle: true,
        isScrollControlled: true,
        builder: (context) => SafeArea(
            child: SingleChildScrollView(
                padding: const EdgeInsets.fromLTRB(24, 0, 24, 24),
                child: Column(
                    mainAxisSize: MainAxisSize.min,
                    crossAxisAlignment: CrossAxisAlignment.stretch,
                    children: [
                      Text('Lokasi absensi',
                          style: Theme.of(context).textTheme.titleLarge),
                      const SizedBox(height: 12),
                      Text(_locationLabel),
                      const SizedBox(height: 8),
                      Text(state.data?.company.address?.isNotEmpty == true
                          ? state.data!.company.address!
                          : 'Alamat kantor belum tersedia'),
                      const SizedBox(height: 12),
                      const Text(
                          'Lokasi diperiksa kembali saat melanjutkan absensi. Pastikan Anda berada di area kantor.'),
                      const SizedBox(height: 16),
                      if (state.location == LocationState.deniedForever)
                        FilledButton(
                            onPressed: () {
                              Navigator.pop(context);
                              Geolocator.openAppSettings();
                            },
                            child: const Text('Buka pengaturan aplikasi'))
                      else if (state.location == LocationState.disabled)
                        FilledButton(
                            onPressed: () {
                              Navigator.pop(context);
                              Geolocator.openLocationSettings();
                            },
                            child: const Text('Buka pengaturan lokasi'))
                      else
                        FilledButton(
                            onPressed: state.location == LocationState.loading
                                ? null
                                : () {
                                    Navigator.pop(context);
                                    onLocate();
                                  },
                            child: const Text('Periksa lokasi lagi')),
                    ]))));
  }

  void _showSchedule(BuildContext context) {
    showModalBottomSheet<void>(
        context: context,
        showDragHandle: true,
        builder: (context) => SafeArea(
            child: Padding(
                padding: const EdgeInsets.fromLTRB(24, 0, 24, 24),
                child: Column(
                    mainAxisSize: MainAxisSize.min,
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                          state.data?.schedule == null
                              ? 'Jam operasional perusahaan'
                              : 'Jadwal shift',
                          style: Theme.of(context).textTheme.titleLarge),
                      const SizedBox(height: 12),
                      Text(state.data?.schedule == null
                          ? 'Jam kantor bukan jadwal shift pribadi. Jadwal shift dan zona waktu perusahaan belum tersedia. Tanggal di Beranda mengikuti perangkat.'
                          : 'Tanggal kerja: ${state.data!.date}. ${state.data!.schedule!['scheduled_name'] ?? 'Tanpa shift'} · ${state.data!.schedule!['attendance_timezone'] ?? 'Zona belum tersedia'}. Jam pulang lebih kecil dari jam masuk berarti berakhir keesokan hari. Checkout tetap pada sesi masuk, maksimal 24 jam; sesi lewat batas perlu koreksi HR.'),
                      const SizedBox(height: 12),
                      TextButton(
                          onPressed: () => Navigator.pop(context),
                          child: const Text('Mengerti')),
                    ]))));
  }

  Widget _service(BuildContext context, IconData icon, String label,
          VoidCallback action) =>
      Material(
          color: Theme.of(context).colorScheme.surface,
          shape: RoundedRectangleBorder(
              borderRadius: BorderRadius.circular(14),
              side: BorderSide(color: Theme.of(context).colorScheme.outline)),
          child: InkWell(
              borderRadius: BorderRadius.circular(14),
              onTap: action,
              child: Padding(
                  padding:
                      const EdgeInsets.symmetric(horizontal: 12, vertical: 14),
                  child: Row(children: [
                    Icon(icon,
                        color: Theme.of(context).colorScheme.primary, size: 26),
                    const SizedBox(width: 10),
                    Expanded(
                        child: Text(label,
                            style: Theme.of(context)
                                .textTheme
                                .labelLarge
                                ?.copyWith(fontSize: 12))),
                    const SizedBox(width: 4),
                    Icon(Icons.chevron_right,
                        size: 18,
                        color: Theme.of(context).colorScheme.onSurfaceVariant)
                  ]))));

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final colors = theme.colorScheme;
    final dark = theme.brightness == Brightness.dark;
    final data = state.data;
    final name = data?.user.name?.trim();
    final initials = name == null || name.isEmpty
        ? '?'
        : name
            .split(RegExp(r'\s+'))
            .take(2)
            .map((part) => part.characters.first)
            .join()
            .toUpperCase();
    final hour = DateTime.now().hour;
    final greeting = hour < 11
        ? 'Selamat pagi'
        : hour < 15
            ? 'Selamat siang'
            : hour < 18
                ? 'Selamat sore'
                : 'Selamat malam';
    final action = data?.action;
    final actionLabel = switch (action) {
      HomeAction.checkin => 'Absen masuk',
      HomeAction.checkout => 'Absen pulang',
      HomeAction.complete => 'Absensi selesai',
      _ => 'Muat ulang status'
    };
    final status = switch (action) {
      HomeAction.checkin => 'Belum absen',
      HomeAction.checkout => 'Sudah masuk',
      HomeAction.complete => 'Selesai',
      _ => 'Perlu tinjau'
    };
    final title = switch (action) {
      HomeAction.checkout => 'Semangat bekerja hari ini',
      HomeAction.complete => 'Terima kasih untuk hari ini',
      _ => 'Siap memulai hari ini?'
    };
    final good = Color(dark ? 0xFF9ED2B9 : 0xFF34725B);
    final locationColor =
        state.location == LocationState.inside ? good : colors.onSurfaceVariant;
    final radius = double.tryParse(data?.company.radiusKm ?? '');
    final locationDetail = [
      if (state.distanceMeters != null)
        '${state.distanceMeters!.round()} m dari kantor',
      if (radius != null && radius.isFinite && radius > 0)
        'Radius ${(radius * 1000).round()} m'
    ].join(' • ');
    final compact = MediaQuery.sizeOf(context).width < 350 ||
        MediaQuery.textScalerOf(context).scale(14) > 19;
    return Scaffold(
      body: SafeArea(
          child: RefreshIndicator(
              onRefresh: onRefresh,
              child: ListView(
                physics: const AlwaysScrollableScrollPhysics(),
                padding: const EdgeInsets.fromLTRB(20, 10, 20, 12),
                children: [
                  Row(children: [
                    Expanded(
                        child: Semantics(
                            label: 'Naltech Attendance',
                            child: ExcludeSemantics(
                                child: Column(
                                    crossAxisAlignment:
                                        CrossAxisAlignment.start,
                                    children: [
                                  Text('naltech',
                                      style: TextStyle(
                                          fontSize: 28,
                                          fontWeight: FontWeight.w800,
                                          letterSpacing: -1,
                                          color:
                                              dark ? colors.onSurface : _navy,
                                          height: 1.1)),
                                  Text('ATTENDANCE',
                                      style: TextStyle(
                                          fontSize: 9,
                                          letterSpacing: 2.6,
                                          fontWeight: FontWeight.w700,
                                          color:
                                              dark ? colors.onSurface : _navy)),
                                ])))),
                    const WorkspaceThemeToggle(),
                    IconButton(
                        tooltip: 'Profil akun',
                        onPressed: onProfile,
                        icon: CircleAvatar(
                            radius: 19,
                            backgroundColor: dark
                                ? const Color(0xFF283A5A)
                                : const Color(0xFFEDF2FF),
                            child: Text(initials,
                                style: TextStyle(
                                    fontSize: 12,
                                    fontWeight: FontWeight.w700,
                                    color: colors.primary)))),
                  ]),
                  const SizedBox(height: 14),
                  if (data != null) ...[
                    Text(
                        '$greeting, ${name?.isNotEmpty == true ? name! : 'Anda'}',
                        style: theme.textTheme.titleMedium
                            ?.copyWith(fontWeight: FontWeight.w700)),
                    const SizedBox(height: 4),
                    Text(data.company.name ?? 'Perusahaan belum tersedia',
                        style: TextStyle(color: colors.onSurfaceVariant)),
                    const SizedBox(height: 12),
                    Text(title,
                        style: theme.textTheme.headlineSmall?.copyWith(
                            fontSize: 25,
                            fontWeight: FontWeight.w700,
                            letterSpacing: -.6)),
                    const SizedBox(height: 14),
                    ClipRRect(
                        borderRadius: BorderRadius.circular(16),
                        child: Container(
                            color: _navy,
                            child: Stack(children: [
                              Positioned.fill(
                                  child:
                                      CustomPaint(painter: _ScheduleLines())),
                              Padding(
                                  padding: const EdgeInsets.all(14),
                                  child: Column(
                                      crossAxisAlignment:
                                          CrossAxisAlignment.start,
                                      children: [
                                        Wrap(
                                            alignment:
                                                WrapAlignment.spaceBetween,
                                            crossAxisAlignment:
                                                WrapCrossAlignment.center,
                                            spacing: 12,
                                            runSpacing: 6,
                                            children: [
                                              Row(
                                                  mainAxisSize:
                                                      MainAxisSize.min,
                                                  children: [
                                                    const Icon(
                                                        Icons
                                                            .calendar_today_outlined,
                                                        color: Colors.white,
                                                        size: 19),
                                                    const SizedBox(width: 10),
                                                    Flexible(
                                                        child: Text(
                                                            _date(data.date),
                                                            style:
                                                                const TextStyle(
                                                                    color: Colors
                                                                        .white,
                                                                    fontSize:
                                                                        13)))
                                                  ]),
                                              TextButton(
                                                  onPressed: () =>
                                                      _showSchedule(context),
                                                  style: TextButton.styleFrom(
                                                      foregroundColor:
                                                          Colors.white,
                                                      backgroundColor:
                                                          const Color(
                                                              0xFF2D4264),
                                                      padding: const EdgeInsets
                                                          .symmetric(
                                                          horizontal: 12),
                                                      minimumSize:
                                                          const Size(48, 36)),
                                                  child: Text(
                                                      data.schedule == null
                                                          ? 'Jam kantor'
                                                          : 'Jadwal shift',
                                                      style: const TextStyle(
                                                          fontSize: 11))),
                                            ]),
                                        const SizedBox(height: 10),
                                        Text(
                                            '${_scheduleTime(data.schedule == null ? data.company.timeIn : data.schedule!['scheduled_in'] as String?)} — ${_scheduleTime(data.schedule == null ? data.company.timeOut : data.schedule!['scheduled_out'] as String?)}',
                                            style: const TextStyle(
                                                color: Colors.white,
                                                fontSize: 29,
                                                fontWeight: FontWeight.w700,
                                                letterSpacing: -.7)),
                                        const SizedBox(height: 6),
                                        Text(
                                            data.schedule == null
                                                ? 'Jam operasional perusahaan'
                                                : (data.schedule![
                                                            'scheduled_name']
                                                        as String? ??
                                                    'Belum ada shift efektif'),
                                            style: const TextStyle(
                                                color: Color(0xFFB9C6DC),
                                                fontSize: 13)),
                                      ])),
                            ]))),
                    const SizedBox(height: 12),
                    Material(
                        color: colors.surface,
                        shape: RoundedRectangleBorder(
                            borderRadius: BorderRadius.circular(14),
                            side: BorderSide(color: colors.outline)),
                        child: InkWell(
                            onTap: () => _showLocation(context),
                            borderRadius: BorderRadius.circular(14),
                            child: Padding(
                                padding: const EdgeInsets.all(14),
                                child: Row(children: [
                                  Icon(
                                      state.location == LocationState.inside
                                          ? Icons.location_on
                                          : Icons.location_off_outlined,
                                      color: locationColor,
                                      size: 28),
                                  const SizedBox(width: 12),
                                  Expanded(
                                      child: Semantics(
                                          liveRegion: true,
                                          child: Column(
                                              crossAxisAlignment:
                                                  CrossAxisAlignment.start,
                                              children: [
                                                Text(_locationLabel,
                                                    style: theme
                                                        .textTheme.labelLarge
                                                        ?.copyWith(
                                                            fontWeight:
                                                                FontWeight
                                                                    .w700)),
                                                if (locationDetail
                                                    .isNotEmpty) ...[
                                                  const SizedBox(height: 4),
                                                  Text(locationDetail,
                                                      style: TextStyle(
                                                          color: colors
                                                              .onSurfaceVariant,
                                                          fontSize: 12))
                                                ]
                                              ]))),
                                  const SizedBox(width: 6),
                                  Icon(Icons.chevron_right,
                                      color: colors.onSurfaceVariant, size: 20),
                                ])))),
                    const SizedBox(height: 12),
                    _surface(
                        context,
                        Column(
                            crossAxisAlignment: CrossAxisAlignment.stretch,
                            children: [
                              Wrap(
                                  alignment: WrapAlignment.spaceBetween,
                                  spacing: 10,
                                  runSpacing: 8,
                                  crossAxisAlignment: WrapCrossAlignment.center,
                                  children: [
                                    Text(
                                        data.schedule == null
                                            ? 'Kehadiran hari ini'
                                            : 'Kehadiran sesi ini',
                                        style: theme.textTheme.titleMedium
                                            ?.copyWith(
                                                fontWeight: FontWeight.w700)),
                                    Container(
                                        padding: const EdgeInsets.symmetric(
                                            horizontal: 10, vertical: 5),
                                        decoration: BoxDecoration(
                                            color: dark
                                                ? const Color(0xFF283449)
                                                : const Color(0xFFF0F3F8),
                                            borderRadius:
                                                BorderRadius.circular(20)),
                                        child: Text(status,
                                            style:
                                                const TextStyle(fontSize: 11))),
                                  ]),
                              const SizedBox(height: 16),
                              Flex(
                                  direction:
                                      compact ? Axis.vertical : Axis.horizontal,
                                  crossAxisAlignment: CrossAxisAlignment.start,
                                  children: [
                                    _AttendanceTime(
                                        label: 'Masuk',
                                        value: _time(data.attendance?.timeIn),
                                        icon: Icons.login,
                                        color: good),
                                    if (compact)
                                      const SizedBox(height: 14)
                                    else
                                      const Spacer(),
                                    _AttendanceTime(
                                        label: 'Pulang',
                                        value: _time(data.attendance?.timeOut),
                                        icon: Icons.logout,
                                        color: colors.error),
                                  ]),
                              const SizedBox(height: 16),
                              if (action == HomeAction.unavailable) ...[
                                Text(!data.actionable
                                    ? 'Sesi melewati batas 24 jam. Hubungi HR untuk koreksi sebelum melanjutkan.'
                                    : 'Catatan belum konsisten. Muat ulang sebelum melanjutkan.'),
                                const SizedBox(height: 12)
                              ],
                              if (!['qr', 'none'].contains(data.method)) ...[
                                Text(data.method == 'face'
                                    ? 'Absensi wajah belum tersedia. Hubungi pengelola perusahaan.'
                                    : 'Metode absensi belum tersedia. Hubungi pengelola perusahaan.'),
                                const SizedBox(height: 12)
                              ],
                              FilledButton(
                                  onPressed: !opening && state.canAttend
                                      ? onAttend
                                      : null,
                                  style: FilledButton.styleFrom(
                                      backgroundColor: _blue,
                                      foregroundColor: Colors.white,
                                      padding: const EdgeInsets.symmetric(
                                          horizontal: 16, vertical: 14),
                                      shape: RoundedRectangleBorder(
                                          borderRadius:
                                              BorderRadius.circular(12))),
                                  child: Row(
                                      mainAxisAlignment:
                                          MainAxisAlignment.center,
                                      children: [
                                        Icon(
                                            data.method == 'qr'
                                                ? Icons.qr_code_scanner
                                                : Icons.fingerprint,
                                            size: 25),
                                        const SizedBox(width: 12),
                                        Flexible(
                                            child: Column(
                                                crossAxisAlignment:
                                                    CrossAxisAlignment.start,
                                                children: [
                                              Text(
                                                  opening
                                                      ? 'Memeriksa data…'
                                                      : actionLabel,
                                                  style: const TextStyle(
                                                      fontWeight:
                                                          FontWeight.w700,
                                                      fontSize: 16)),
                                              const SizedBox(height: 3),
                                              Text(
                                                  data.method == 'qr'
                                                      ? 'Scan QR dan konfirmasi lokasi Anda'
                                                      : 'Konfirmasi lokasi untuk absensi',
                                                  style: const TextStyle(
                                                      fontSize: 11))
                                            ])),
                                      ])),
                            ])),
                  ],
                  if (state.loading)
                    const Padding(
                        padding: EdgeInsets.all(32),
                        child: Center(
                            child: CircularProgressIndicator(
                                semanticsLabel: 'Memuat Beranda'))),
                  if (state.error != null)
                    _surface(
                        context,
                        Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              const Icon(Icons.cloud_off_outlined),
                              const SizedBox(height: 12),
                              Text(state.error!),
                              const SizedBox(height: 12),
                              OutlinedButton(
                                  onPressed: onRefresh,
                                  child: const Text('Coba lagi'))
                            ])),
                  const SizedBox(height: 16),
                  Text('Layanan',
                      style: theme.textTheme.titleMedium
                          ?.copyWith(fontWeight: FontWeight.w700)),
                  const SizedBox(height: 12),
                  if (compact) ...[
                    _service(context, Icons.calendar_today_outlined,
                        'Ajukan izin', onPermission),
                    const SizedBox(height: 10),
                    _service(context, Icons.receipt_long_outlined,
                        'Reimbursement', onReimbursement)
                  ] else
                    Row(children: [
                      Expanded(
                          child: _service(
                              context,
                              Icons.calendar_today_outlined,
                              'Ajukan izin',
                              onPermission)),
                      const SizedBox(width: 10),
                      Expanded(
                          child: _service(context, Icons.receipt_long_outlined,
                              'Reimbursement', onReimbursement))
                    ]),
                  const SizedBox(height: 12),
                  Wrap(
                      alignment: WrapAlignment.spaceBetween,
                      crossAxisAlignment: WrapCrossAlignment.center,
                      spacing: 8,
                      children: [
                        SizedBox(
                            width: compact ? double.infinity : 190,
                            child: Row(children: [
                              Icon(Icons.sync,
                                  size: 18, color: colors.onSurfaceVariant),
                              const SizedBox(width: 8),
                              Expanded(
                                  child: Text(
                                      state.data?.refreshedAt == null
                                          ? 'Tarik untuk memperbarui'
                                          : 'Diperbarui ${state.data!.refreshedAt!.hour.toString().padLeft(2, '0')}.${state.data!.refreshedAt!.minute.toString().padLeft(2, '0')}',
                                      style: TextStyle(
                                          fontSize: 12,
                                          color: colors.onSurfaceVariant))),
                            ])),
                        TextButton(
                            onPressed: state.loading ? null : onRefresh,
                            child: const Text('Muat ulang')),
                      ]),
                ],
              ))),
    );
  }
}

class _AttendanceTime extends StatelessWidget {
  const _AttendanceTime(
      {required this.label,
      required this.value,
      required this.icon,
      required this.color});
  final String label, value;
  final IconData icon;
  final Color color;
  @override
  Widget build(BuildContext context) =>
      Row(mainAxisSize: MainAxisSize.min, children: [
        Icon(icon, color: color, size: 26),
        const SizedBox(width: 12),
        Flexible(
            child:
                Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          Text(label, style: const TextStyle(fontSize: 12)),
          const SizedBox(height: 4),
          Text(value,
              style: const TextStyle(
                  fontSize: 24,
                  fontWeight: FontWeight.w700,
                  letterSpacing: -.5))
        ]))
      ]);
}

class _ScheduleLines extends CustomPainter {
  @override
  void paint(Canvas canvas, Size size) {
    final paint = Paint()
      ..color = const Color(0xFF426597)
      ..strokeWidth = .7
      ..style = PaintingStyle.stroke;
    for (var i = 0; i < 3; i++) {
      final offset = i * 23.0;
      canvas.drawPath(
          Path()
            ..moveTo(size.width - 125 + offset, size.height)
            ..lineTo(size.width - 70 + offset, size.height - 55)
            ..lineTo(size.width - 35 + offset, size.height - 55)
            ..lineTo(size.width + 20, size.height - 110 + offset),
          paint);
    }
  }

  @override
  bool shouldRepaint(covariant CustomPainter oldDelegate) => false;
}
