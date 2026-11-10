import 'package:flutter/material.dart';
import 'package:shared_preferences/shared_preferences.dart';

class ThemeController extends ChangeNotifier {
  ThemeController(this._preferences)
      : _mode = _preferences.getString(_key) == 'dark'
            ? ThemeMode.dark
            : ThemeMode.light;
  static const _key = 'naltech.theme';
  final SharedPreferences _preferences;
  ThemeMode _mode;
  ThemeMode get mode => _mode;

  Future<bool> setMode(ThemeMode mode) async {
    if (mode == _mode) return true;
    // Persist first: a failed write must not promise a saved preference.
    try {
      final saved = await _preferences.setString(
          _key, mode == ThemeMode.dark ? 'dark' : 'light');
      if (!saved) return false;
      _mode = mode;
      notifyListeners();
      return true;
    } catch (_) {
      return false;
    }
  }
}

class ThemeScope extends InheritedNotifier<ThemeController> {
  const ThemeScope(
      {super.key, required ThemeController controller, required super.child})
      : super(notifier: controller);
  static ThemeController of(BuildContext context) =>
      context.dependOnInheritedWidgetOfExactType<ThemeScope>()!.notifier!;
}

class WorkspaceThemeToggle extends StatelessWidget {
  const WorkspaceThemeToggle({super.key});
  @override
  Widget build(BuildContext context) {
    final controller = ThemeScope.of(context);
    final dark = controller.mode == ThemeMode.dark;
    return IconButton(
      tooltip: dark ? 'Gunakan tema terang' : 'Gunakan tema gelap',
      icon: Icon(dark ? Icons.light_mode_outlined : Icons.dark_mode_outlined),
      onPressed: () async {
        final saved =
            await controller.setMode(dark ? ThemeMode.light : ThemeMode.dark);
        if (!saved && context.mounted) {
          ScaffoldMessenger.of(context).showSnackBar(const SnackBar(
              content: Text('Tema belum dapat disimpan. Coba lagi.')));
        }
      },
    );
  }
}
