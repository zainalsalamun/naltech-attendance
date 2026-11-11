import 'package:flutter/material.dart';
import '../../../data/datasources/auth_remote_datasource.dart';
import '../../home/pages/main_page.dart';
import 'login_page.dart';

class SplashPage extends StatefulWidget {
  const SplashPage({super.key, this.source, this.signedIn, this.signedOut});
  final AuthRemoteDatasource? source;
  final Widget? signedIn, signedOut;
  @override
  State<SplashPage> createState() => _SplashPageState();
}

class _SplashPageState extends State<SplashPage> {
  bool _busy = true;
  SessionCheck? _status;
  String? _error;
  AuthRemoteDatasource get _source => widget.source ?? AuthRemoteDatasource();
  @override
  void initState() {
    super.initState();
    _check();
  }

  Future<void> _check() async {
    setState(() {
      _busy = true;
      _error = null;
    });
    final result = await _source.restoreSession();
    if (!mounted) return;
    if (result == SessionCheck.active || result == SessionCheck.signedOut) {
      Navigator.of(context).pushReplacement(MaterialPageRoute<void>(
          builder: (_) => result == SessionCheck.active
              ? (widget.signedIn ?? const MainPage())
              : (widget.signedOut ?? const LoginPage())));
      return;
    }
    setState(() {
      _busy = false;
      _status = result;
    });
  }

  Future<void> _logout() async {
    if (_busy) return;
    setState(() => _busy = true);
    final result = await _source.logout();
    if (!mounted) return;
    result.fold(
        (message) => setState(() {
              _busy = false;
              _error = message;
            }),
        (_) => Navigator.of(context).pushAndRemoveUntil(
            MaterialPageRoute<void>(
                builder: (_) => widget.signedOut ?? const LoginPage()),
            (_) => false));
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Scaffold(
        body: SafeArea(
            child: Center(
                child: SingleChildScrollView(
                    padding: const EdgeInsets.all(28),
                    child: ConstrainedBox(
                        constraints: const BoxConstraints(maxWidth: 440),
                        child: Column(
                            mainAxisSize: MainAxisSize.min,
                            crossAxisAlignment: CrossAxisAlignment.stretch,
                            children: [
                              Text('naltech',
                                  textAlign: TextAlign.center,
                                  style: theme.textTheme.displaySmall?.copyWith(
                                      fontWeight: FontWeight.w800,
                                      letterSpacing: -1)),
                              const SizedBox(height: 4),
                              const Text('ATTENDANCE',
                                  textAlign: TextAlign.center,
                                  style: TextStyle(
                                      letterSpacing: 3, fontSize: 11)),
                              const SizedBox(height: 32),
                              if (_busy) ...[
                                const Center(
                                    child: CircularProgressIndicator(
                                        semanticsLabel: 'Memeriksa sesi akun')),
                                const SizedBox(height: 16),
                                const Text('Memeriksa akun Anda…',
                                    textAlign: TextAlign.center)
                              ] else ...[
                                Icon(
                                    _status == SessionCheck.companyMissing
                                        ? Icons.business_outlined
                                        : Icons.cloud_off_outlined,
                                    size: 44,
                                    color: theme.colorScheme.primary),
                                const SizedBox(height: 20),
                                Text(
                                    _status == SessionCheck.companyMissing
                                        ? 'Akun belum terhubung perusahaan'
                                        : 'Akun belum dapat diperiksa',
                                    textAlign: TextAlign.center,
                                    style: theme.textTheme.titleLarge),
                                const SizedBox(height: 12),
                                Text(
                                    _status == SessionCheck.companyMissing
                                        ? 'Hubungi pengelola untuk memeriksa hubungan akun dengan perusahaan.'
                                        : 'Periksa koneksi Anda, lalu coba lagi. Sesi tersimpan tidak dihapus karena kegagalan koneksi.',
                                    textAlign: TextAlign.center),
                                if (_error != null) ...[
                                  const SizedBox(height: 12),
                                  Text(_error!,
                                      style: TextStyle(
                                          color: theme.colorScheme.error))
                                ],
                                const SizedBox(height: 24),
                                FilledButton(
                                    onPressed: _check,
                                    child: const Text('Coba lagi')),
                                const SizedBox(height: 10),
                                TextButton(
                                    onPressed: _logout,
                                    child: const Text('Keluar akun')),
                              ],
                            ]))))));
  }
}
