import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import '../../../core/theme/theme_controller.dart';
import '../../../data/datasources/auth_local_datasource.dart';
import '../bloc/login/login_bloc.dart';
import '../../home/pages/main_page.dart';

class LoginPage extends StatefulWidget {
  const LoginPage({super.key, this.initialMessage});
  final String? initialMessage;
  @override
  State<LoginPage> createState() => _LoginPageState();
}

class _LoginPageState extends State<LoginPage> {
  final _form = GlobalKey<FormState>();
  final _email = TextEditingController();
  final _password = TextEditingController();
  bool _showPassword = false;
  bool _saving = false;
  String? _error;

  @override
  void initState() {
    super.initState();
    _error = widget.initialMessage;
  }

  @override
  void dispose() {
    _email.dispose();
    _password.dispose();
    super.dispose();
  }

  void _submit() {
    final loading = context
        .read<LoginBloc>()
        .state
        .maybeWhen(loading: () => true, orElse: () => false);
    if (_saving || loading || !_form.currentState!.validate()) return;
    FocusScope.of(context).unfocus();
    setState(() => _error = null);
    context
        .read<LoginBloc>()
        .add(LoginEvent.login(_email.text.trim(), _password.text));
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Scaffold(
      appBar: AppBar(
          title: const Text('Naltech Attendance'),
          actions: const [WorkspaceThemeToggle()]),
      body: SafeArea(
        child: BlocConsumer<LoginBloc, LoginState>(
          listener: (context, state) async {
            await state.maybeWhen(
              success: (data) async {
                setState(() => _saving = true);
                try {
                  await AuthLocalDatasource().saveAuthData(data);
                  if (!context.mounted) {
                    return;
                  }
                  TextInput.finishAutofillContext();
                  Navigator.of(context).pushReplacement(MaterialPageRoute<void>(
                      builder: (_) => const MainPage()));
                } catch (_) {
                  if (mounted) {
                    setState(() {
                      _saving = false;
                      _error =
                          'Sesi belum dapat disimpan. Silakan coba masuk kembali.';
                    });
                  }
                }
              },
              error: (message) async {
                setState(() => _error = message);
              },
              orElse: () async {},
            );
          },
          builder: (context, state) {
            final busy = _saving ||
                state.maybeWhen(loading: () => true, orElse: () => false);
            return SingleChildScrollView(
              padding: const EdgeInsets.all(24),
              child: Center(
                  child: ConstrainedBox(
                constraints: const BoxConstraints(maxWidth: 460),
                child: AutofillGroup(
                    child: Form(
                        key: _form,
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.stretch,
                          children: [
                            const SizedBox(height: 24),
                            Text('WORKSPACE PERUSAHAAN',
                                style: theme.textTheme.labelMedium?.copyWith(
                                    color: theme.colorScheme.onSurfaceVariant)),
                            const SizedBox(height: 12),
                            Text('Selamat datang kembali',
                                style: theme.textTheme.headlineMedium),
                            const SizedBox(height: 12),
                            const Text(
                                'Masuk untuk mengakses kehadiran dan akun perusahaan Anda.'),
                            const SizedBox(height: 32),
                            if (_error != null) ...[
                              Semantics(
                                  liveRegion: true,
                                  child: Text(_error!,
                                      style: TextStyle(
                                          color: theme.colorScheme.error))),
                              const SizedBox(height: 20),
                            ],
                            TextFormField(
                              controller: _email,
                              enabled: !busy,
                              decoration: const InputDecoration(
                                  labelText: 'Email',
                                  prefixIcon: Icon(Icons.mail_outline)),
                              keyboardType: TextInputType.emailAddress,
                              textInputAction: TextInputAction.next,
                              autofillHints: const [AutofillHints.username],
                              autocorrect: false,
                              validator: (value) {
                                final email = value?.trim() ?? '';
                                if (email.isEmpty) {
                                  return 'Masukkan email Anda.';
                                }
                                if (!RegExp(r'^[^\s@]+@[^\s@]+\.[^\s@]+$')
                                    .hasMatch(email)) {
                                  return 'Masukkan email yang valid.';
                                }
                                return null;
                              },
                            ),
                            const SizedBox(height: 20),
                            TextFormField(
                              controller: _password,
                              enabled: !busy,
                              obscureText: !_showPassword,
                              autocorrect: false,
                              enableSuggestions: false,
                              autofillHints: const [AutofillHints.password],
                              textInputAction: TextInputAction.done,
                              onFieldSubmitted: (_) => _submit(),
                              validator: (value) =>
                                  value == null || value.isEmpty
                                      ? 'Masukkan kata sandi Anda.'
                                      : null,
                              decoration: InputDecoration(
                                  labelText: 'Kata sandi',
                                  prefixIcon: const Icon(Icons.lock_outline),
                                  suffixIcon: IconButton(
                                      tooltip: _showPassword
                                          ? 'Sembunyikan kata sandi'
                                          : 'Tampilkan kata sandi',
                                      onPressed: busy
                                          ? null
                                          : () => setState(() =>
                                              _showPassword = !_showPassword),
                                      icon: Icon(_showPassword
                                          ? Icons.visibility_off_outlined
                                          : Icons.visibility_outlined))),
                            ),
                            const SizedBox(height: 28),
                            FilledButton(
                                onPressed: busy ? null : _submit,
                                child: busy
                                    ? const Row(
                                        mainAxisSize: MainAxisSize.min,
                                        children: [
                                            SizedBox(
                                                width: 18,
                                                height: 18,
                                                child:
                                                    CircularProgressIndicator(
                                                        strokeWidth: 2)),
                                            SizedBox(width: 12),
                                            Text('Sedang masuk…'),
                                          ])
                                    : const Text('Masuk')),
                            const SizedBox(height: 24),
                            Text(
                                'Belum memiliki akun atau perlu bantuan masuk? Hubungi pengelola perusahaan Anda.',
                                style: theme.textTheme.bodyMedium?.copyWith(
                                    color: theme.colorScheme.onSurfaceVariant)),
                          ],
                        ))),
              )),
            );
          },
        ),
      ),
    );
  }
}
