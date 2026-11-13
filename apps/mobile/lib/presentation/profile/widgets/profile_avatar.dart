import 'package:flutter/material.dart';
import '../../../core/constants/variables.dart';

class ProfileAvatar extends StatelessWidget {
  const ProfileAvatar(
      {super.key, required this.name, this.imageUrl, this.size = 64});
  final String? name;
  final dynamic imageUrl;
  final double size;
  @override
  Widget build(BuildContext context) {
    final parts = (name ?? '')
        .trim()
        .split(RegExp(r'\s+'))
        .where((part) => part.isNotEmpty)
        .take(2);
    final initials = parts.isEmpty
        ? '?'
        : parts.map((part) => part.characters.first).join().toUpperCase();
    final fallback = ColoredBox(
        color: Theme.of(context).colorScheme.primaryContainer,
        child: Center(
            child: Text(initials,
                style: TextStyle(
                    color: Theme.of(context).colorScheme.onPrimaryContainer,
                    fontSize: 22,
                    fontWeight: FontWeight.w700))));
    String? url;
    if (imageUrl is String && (imageUrl as String).trim().isNotEmpty) {
      final raw = (imageUrl as String).trim();
      url = raw.startsWith('http')
          ? raw
          : '${Variables.baseUrl}/${raw.startsWith('storage/') ? raw : 'storage/$raw'}';
      final uri = Uri.tryParse(url);
      if (uri == null || !['http', 'https'].contains(uri.scheme)) url = null;
    }
    return Semantics(
        label: 'Foto profil ${name ?? ''}',
        child: SizedBox(
            width: size,
            height: size,
            child: ClipOval(
                child: url == null
                    ? fallback
                    : Image.network(url,
                        fit: BoxFit.cover,
                        errorBuilder: (_, __, ___) => fallback))));
  }
}
