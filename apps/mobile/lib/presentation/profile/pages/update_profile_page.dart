import 'dart:typed_data';
import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:image_picker/image_picker.dart';
import '../../../data/models/response/auth_response_model.dart';
import '../../../data/models/request/user_request_model.dart';
import '../bloc/update_user/update_user_bloc.dart';
import '../widgets/profile_avatar.dart';

class UpdateProfilePage extends StatefulWidget {
  const UpdateProfilePage({super.key, required this.user, this.pickImage});
  final User user;
  final Future<XFile?> Function()? pickImage;
  @override
  State<UpdateProfilePage> createState() => _UpdateProfilePageState();
}

class _UpdateProfilePageState extends State<UpdateProfilePage> {
  final _form = GlobalKey<FormState>();
  late final _name = TextEditingController(text: widget.user.name ?? '');
  late final _email = TextEditingController(text: widget.user.email ?? '');
  late final _phone =
      TextEditingController(text: widget.user.phone?.toString() ?? '');
  XFile? _photo;
  Uint8List? _preview;
  bool _picking = false, _submitting = false;
  String? _error;
  @override
  void dispose() {
    _name.dispose();
    _email.dispose();
    _phone.dispose();
    super.dispose();
  }

  Future<void> _pick() async {
    if (_picking || _submitting) return;
    setState(() => _picking = true);
    try {
      final photo = await (widget.pickImage?.call() ??
          ImagePicker().pickImage(
              source: ImageSource.gallery, imageQuality: 85, maxWidth: 1600));
      if (photo == null) return;
      final extension = photo.name.toLowerCase().split('.').last;
      if (!['jpg', 'jpeg', 'png'].contains(extension) ||
          await photo.length() > 2 * 1024 * 1024) {
        if (mounted) {
          setState(() => _error = 'Gunakan foto JPG/PNG maksimal 2 MB.');
        }
        return;
      }
      final bytes = await photo.readAsBytes();
      if (mounted) {
        setState(() {
          _photo = photo;
          _preview = bytes;
          _error = null;
        });
      }
    } catch (_) {
      if (mounted) {
        setState(() => _error =
            'Foto belum dapat dipilih. Periksa izin galeri dan coba lagi.');
      }
    } finally {
      if (mounted) setState(() => _picking = false);
    }
  }

  void _save() {
    final busy = context
        .read<UpdateUserBloc>()
        .state
        .maybeWhen(loading: () => true, orElse: () => false);
    if (_submitting || _picking || busy || !_form.currentState!.validate()) {
      return;
    }
    if (widget.user.id == null) {
      setState(
          () => _error = 'Identitas akun belum tersedia. Muat ulang profil.');
      return;
    }
    FocusScope.of(context).unfocus();
    setState(() {
      _submitting = true;
      _error = null;
    });
    context.read<UpdateUserBloc>().add(UpdateUserEvent.updateUser(
        UserRequestModel(
            id: widget.user.id!,
            name: _name.text.trim(),
            email: _email.text.trim(),
            phone: _phone.text.trim(),
            image: _photo),
        widget.user.id!));
  }

  String? _required(String? value, String label) {
    if (value == null || value.trim().isEmpty) return '$label wajib diisi.';
    if (value.trim().length > 255) return '$label maksimal 255 karakter.';
    return null;
  }

  @override
  Widget build(BuildContext context) =>
      BlocConsumer<UpdateUserBloc, UpdateUserState>(listener: (context, state) {
        if (!_submitting) return;
        state.maybeWhen(
            success: (_) => Navigator.pop(context, true),
            error: (message) => setState(() {
                  _submitting = false;
                  _error = message;
                }),
            orElse: () {});
      }, builder: (context, state) {
        final theme = Theme.of(context);
        final busy = _submitting ||
            _picking ||
            state.maybeWhen(loading: () => true, orElse: () => false);
        return PopScope(
            canPop: !busy,
            child: Scaffold(
                appBar: AppBar(title: const Text('Edit profil')),
                body: SafeArea(
                    child: Form(
                        key: _form,
                        child: ListView(
                            padding: const EdgeInsets.all(24),
                            children: [
                              Text('Informasi pribadi',
                                  style: theme.textTheme.headlineSmall
                                      ?.copyWith(fontWeight: FontWeight.w700)),
                              const SizedBox(height: 8),
                              Text('Perbarui data dan foto akun Anda.',
                                  style: TextStyle(
                                      color:
                                          theme.colorScheme.onSurfaceVariant)),
                              const SizedBox(height: 24),
                              Center(
                                  child: _preview == null
                                      ? ProfileAvatar(
                                          name: widget.user.name,
                                          imageUrl: widget.user.imageUrl,
                                          size: 84)
                                      : ClipOval(
                                          child: Image.memory(_preview!,
                                              width: 84,
                                              height: 84,
                                              fit: BoxFit.cover,
                                              errorBuilder: (_, __, ___) =>
                                                  ProfileAvatar(
                                                      name: widget.user.name,
                                                      size: 84)))),
                              const SizedBox(height: 12),
                              Center(
                                  child: OutlinedButton.icon(
                                      onPressed: busy ? null : _pick,
                                      icon: const Icon(
                                          Icons.photo_library_outlined),
                                      label: Text(_picking
                                          ? 'Memilih foto…'
                                          : 'Pilih foto'))),
                              if (_photo != null)
                                Center(
                                    child: TextButton(
                                        onPressed: busy
                                            ? null
                                            : () => setState(() {
                                                  _photo = null;
                                                  _preview = null;
                                                }),
                                        child:
                                            const Text('Batalkan foto baru'))),
                              Text(
                                  'JPG/PNG, maksimal 2 MB. Tanpa foto baru, foto lama tetap digunakan.',
                                  textAlign: TextAlign.center,
                                  style: TextStyle(
                                      color: theme.colorScheme.onSurfaceVariant,
                                      fontSize: 12)),
                              const SizedBox(height: 24),
                              TextFormField(
                                  controller: _name,
                                  enabled: !busy,
                                  decoration: const InputDecoration(
                                      labelText: 'Nama lengkap'),
                                  textInputAction: TextInputAction.next,
                                  validator: (value) =>
                                      _required(value, 'Nama lengkap')),
                              const SizedBox(height: 18),
                              TextFormField(
                                  controller: _email,
                                  enabled: !busy,
                                  decoration:
                                      const InputDecoration(labelText: 'Email'),
                                  keyboardType: TextInputType.emailAddress,
                                  textInputAction: TextInputAction.next,
                                  autocorrect: false,
                                  validator: (value) {
                                    final required = _required(value, 'Email');
                                    if (required != null) return required;
                                    if (!RegExp(r'^[^\s@]+@[^\s@]+\.[^\s@]+$')
                                        .hasMatch(value!.trim())) {
                                      return 'Masukkan email yang valid.';
                                    }
                                    return null;
                                  }),
                              const SizedBox(height: 18),
                              TextFormField(
                                  controller: _phone,
                                  enabled: !busy,
                                  decoration: const InputDecoration(
                                      labelText: 'Nomor telepon'),
                                  keyboardType: TextInputType.phone,
                                  textInputAction: TextInputAction.done,
                                  onFieldSubmitted: (_) => _save(),
                                  validator: (value) =>
                                      _required(value, 'Nomor telepon')),
                              if (_error != null) ...[
                                const SizedBox(height: 18),
                                Semantics(
                                    liveRegion: true,
                                    child: Text(_error!,
                                        style: TextStyle(
                                            color: theme.colorScheme.error)))
                              ],
                              const SizedBox(height: 26),
                              FilledButton(
                                  onPressed: busy ? null : _save,
                                  child: Text(_submitting
                                      ? 'Menyimpan…'
                                      : 'Simpan perubahan')),
                              const SizedBox(height: 10),
                              TextButton(
                                  onPressed: busy
                                      ? null
                                      : () => Navigator.pop(context),
                                  child: const Text('Batal')),
                            ])))));
      });
}
