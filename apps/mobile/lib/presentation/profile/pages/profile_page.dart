import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import '../../../core/theme/theme_controller.dart';
import '../../../data/datasources/user_remote_datasource.dart';
import '../../../data/datasources/attendance_remote_datasource.dart';
import '../../auth/bloc/logout/logout_bloc.dart';
import '../../auth/pages/login_page.dart';
import '../bloc/profile_workspace/profile_workspace_cubit.dart';
import '../widgets/profile_avatar.dart';
import 'update_profile_page.dart';
import 'face_enrollment_page.dart';

class ProfilePage extends StatefulWidget {
  const ProfilePage({super.key, this.users, this.attendance});
  final UserRemoteDatasource? users;
  final AttendanceRemoteDatasource? attendance;
  @override
  State<ProfilePage> createState() => _ProfilePageState();
}

class _ProfilePageState extends State<ProfilePage> {
  late final ProfileWorkspaceCubit _profile;
  @override
  void initState() {
    super.initState();
    _profile = ProfileWorkspaceCubit(widget.users ?? UserRemoteDatasource(),
        widget.attendance ?? AttendanceRemoteDatasource())
      ..load();
  }

  @override
  void dispose() {
    _profile.close();
    super.dispose();
  }

  Future<void> _edit() async {
    final user = _profile.state.user;
    if (user == null) return;
    final updated = await Navigator.of(context).push<bool>(
        MaterialPageRoute(builder: (_) => UpdateProfilePage(user: user)));
    if (updated == true && mounted) {
      await _profile.load();
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
            const SnackBar(content: Text('Profil berhasil diperbarui.')));
      }
    }
  }

  Future<void> _logout() async {
    final confirmed = await showDialog<bool>(
        context: context,
        builder: (context) => AlertDialog(
                title: const Text('Keluar akun?'),
                content: const Text(
                    'Anda perlu masuk kembali untuk menggunakan aplikasi.'),
                actions: [
                  TextButton(
                      onPressed: () => Navigator.pop(context, false),
                      child: const Text('Batal')),
                  FilledButton(
                      onPressed: () => Navigator.pop(context, true),
                      child: const Text('Keluar'))
                ]));
    if (confirmed == true && mounted) {
      context.read<LogoutBloc>().add(const LogoutEvent.logout());
    }
  }

  @override
  Widget build(BuildContext context) => BlocConsumer<LogoutBloc, LogoutState>(
      listener: (context, state) {
        state.maybeWhen(
            success: () {
              if (ModalRoute.of(context)?.isCurrent == true) {
                Navigator.of(context).pushAndRemoveUntil(
                    MaterialPageRoute<void>(builder: (_) => const LoginPage()),
                    (_) => false);
              }
            },
            error: (message) => ScaffoldMessenger.of(context)
                .showSnackBar(SnackBar(content: Text(message))),
            orElse: () {});
      },
      builder: (context, logout) =>
          BlocBuilder<ProfileWorkspaceCubit, ProfileWorkspaceState>(
              bloc: _profile,
              builder: (context, state) => ProfileDashboard(
                  state: state,
                  busy: logout.maybeWhen(
                      loading: () => true, orElse: () => false),
                  onRefresh: _profile.load,
                  onEdit: _edit,
                  onFaceEnrollment: () => Navigator.of(context).push(
                      MaterialPageRoute<void>(
                          builder: (_) => const FaceEnrollmentPage())),
                  onLogout: _logout)));
}

class ProfileDashboard extends StatelessWidget {
  const ProfileDashboard(
      {super.key,
      required this.state,
      required this.onRefresh,
      required this.onEdit,
      required this.onLogout,
      this.onFaceEnrollment,
      this.busy = false});
  final ProfileWorkspaceState state;
  final Future<void> Function() onRefresh;
  final VoidCallback onEdit, onLogout;
  final VoidCallback? onFaceEnrollment;
  final bool busy;
  Widget _panel(BuildContext context, Widget child) => Container(
      width: double.infinity,
      padding: const EdgeInsets.all(18),
      decoration: BoxDecoration(
          color: Theme.of(context).colorScheme.surface,
          border: Border.all(color: Theme.of(context).colorScheme.outline),
          borderRadius: BorderRadius.circular(16)),
      child: child);
  Widget _row(BuildContext context, String label, dynamic raw) {
    final value = raw?.toString().trim();
    return Padding(
        padding: const EdgeInsets.symmetric(vertical: 9),
        child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          Text(label,
              style: TextStyle(
                  color: Theme.of(context).colorScheme.onSurfaceVariant,
                  fontSize: 12)),
          const SizedBox(height: 4),
          SelectableText(value?.isNotEmpty == true ? value! : 'Belum tersedia',
              style: const TextStyle(fontWeight: FontWeight.w600))
        ]));
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final controller = ThemeScope.of(context);
    final user = state.user;
    Future<void> change(ThemeMode mode) async {
      final saved = await controller.setMode(mode);
      if (!saved && context.mounted) {
        ScaffoldMessenger.of(context).showSnackBar(const SnackBar(
            content: Text('Tema belum dapat disimpan. Coba lagi.')));
      }
    }

    return Scaffold(
        appBar: AppBar(title: const Text('Profil'), actions: [
          IconButton(
              tooltip: 'Muat ulang profil',
              onPressed: busy || state.loading ? null : onRefresh,
              icon: const Icon(Icons.refresh))
        ]),
        body: SafeArea(
            child: RefreshIndicator(
                onRefresh: busy ? () async {} : onRefresh,
                child: ListView(
                    physics: const AlwaysScrollableScrollPhysics(),
                    padding: const EdgeInsets.all(20),
                    children: [
                      if (state.loading)
                        const Padding(
                            padding: EdgeInsets.all(32),
                            child: Center(
                                child: CircularProgressIndicator(
                                    semanticsLabel: 'Memuat profil'))),
                      if (state.error != null)
                        _panel(
                            context,
                            Column(
                                crossAxisAlignment: CrossAxisAlignment.start,
                                children: [
                                  const Icon(Icons.cloud_off_outlined),
                                  const SizedBox(height: 12),
                                  Text(state.error!),
                                  const SizedBox(height: 14),
                                  OutlinedButton(
                                      onPressed: busy ? null : onRefresh,
                                      child: const Text('Coba lagi'))
                                ])),
                      if (user != null) ...[
                        Container(
                            padding: const EdgeInsets.all(20),
                            decoration: BoxDecoration(
                                color: const Color(0xFF192A48),
                                borderRadius: BorderRadius.circular(16)),
                            child: Row(
                                crossAxisAlignment: CrossAxisAlignment.start,
                                children: [
                                  ProfileAvatar(
                                      name: user.name, imageUrl: user.imageUrl),
                                  const SizedBox(width: 16),
                                  Expanded(
                                      child: Column(
                                          crossAxisAlignment:
                                              CrossAxisAlignment.start,
                                          children: [
                                        Text(user.name ?? 'Nama belum tersedia',
                                            style: theme.textTheme.titleLarge
                                                ?.copyWith(
                                                    color: Colors.white,
                                                    fontWeight:
                                                        FontWeight.w700)),
                                        const SizedBox(height: 6),
                                        Text(
                                            state.company ??
                                                state.companyError ??
                                                'Perusahaan belum tersedia',
                                            style: const TextStyle(
                                                color: Color(0xFFB9C6DC))),
                                      ]))
                                ])),
                        const SizedBox(height: 16),
                        _panel(
                            context,
                            Column(
                                crossAxisAlignment: CrossAxisAlignment.stretch,
                                children: [
                                  Text('Informasi akun',
                                      style: theme.textTheme.titleMedium
                                          ?.copyWith(
                                              fontWeight: FontWeight.w700)),
                                  const SizedBox(height: 8),
                                  _row(context, 'Email', user.email),
                                  _row(context, 'Nomor telepon', user.phone),
                                  _row(context, 'Jabatan', user.position),
                                  _row(context, 'Departemen', user.department),
                                  _row(context, 'Peran akun', user.role),
                                  const SizedBox(height: 12),
                                  OutlinedButton.icon(
                                      onPressed: busy ? null : onEdit,
                                      icon: const Icon(Icons.edit_outlined,
                                          size: 20),
                                      label: const Text('Edit profil')),
                                  OutlinedButton.icon(
                                      onPressed: busy ? null : onFaceEnrollment,
                                      icon: const Icon(Icons.face_outlined),
                                      label: const Text('Pendaftaran wajah'))
                                ])),
                      ],
                      const SizedBox(height: 20),
                      _panel(
                          context,
                          Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                Text('Tampilan aplikasi',
                                    style: theme.textTheme.titleMedium
                                        ?.copyWith(
                                            fontWeight: FontWeight.w700)),
                                const SizedBox(height: 6),
                                Text(
                                    'Tema tersimpan untuk penggunaan berikutnya.',
                                    style: TextStyle(
                                        color:
                                            theme.colorScheme.onSurfaceVariant,
                                        fontSize: 12)),
                                const SizedBox(height: 12),
                                Wrap(spacing: 12, runSpacing: 8, children: [
                                  ChoiceChip(
                                      showCheckmark: false,
                                      label: const Text('Terang'),
                                      avatar: const Icon(
                                          Icons.light_mode_outlined,
                                          size: 18),
                                      selected:
                                          controller.mode == ThemeMode.light,
                                      onSelected: busy
                                          ? null
                                          : (_) => change(ThemeMode.light)),
                                  ChoiceChip(
                                      showCheckmark: false,
                                      label: const Text('Gelap'),
                                      avatar: const Icon(
                                          Icons.dark_mode_outlined,
                                          size: 18),
                                      selected:
                                          controller.mode == ThemeMode.dark,
                                      onSelected: busy
                                          ? null
                                          : (_) => change(ThemeMode.dark))
                                ])
                              ])),
                      const SizedBox(height: 20),
                      OutlinedButton.icon(
                          onPressed: busy ? null : onLogout,
                          style: OutlinedButton.styleFrom(
                              foregroundColor: theme.colorScheme.error),
                          icon: const Icon(Icons.logout),
                          label: Text(busy ? 'Sedang keluar…' : 'Keluar akun')),
                      const SizedBox(height: 8),
                    ]))));
  }
}
