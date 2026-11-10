import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

/// Shared Workspace palette. Feature screens migrate to these tokens in stages.
abstract final class WorkspaceTheme {
  static ThemeData get light => _build(Brightness.light);
  static ThemeData get dark => _build(Brightness.dark);

  static ThemeData _build(Brightness brightness) {
    final dark = brightness == Brightness.dark;
    final background = Color(dark ? 0xFF141B28 : 0xFFF5F6F8);
    final panel = Color(dark ? 0xFF1D2635 : 0xFFFFFFFF);
    final ink = Color(dark ? 0xFFE8EDF6 : 0xFF202C40);
    final muted = Color(dark ? 0xFFA1AEC3 : 0xFF626F83);
    final line = Color(dark ? 0xFF344054 : 0xFFE5E9EF);
    final accent = Color(dark ? 0xFFA6BCFF : 0xFF315BCE);
    final scheme =
        ColorScheme.fromSeed(seedColor: accent, brightness: brightness)
            .copyWith(
                primary: accent,
                onPrimary: Color(dark ? 0xFF162747 : 0xFFFFFFFF),
                surface: panel,
                onSurface: ink,
                onSurfaceVariant: muted,
                outline: line,
                error: Color(dark ? 0xFFEDAAA5 : 0xFFAC4B47),
                onError: Color(dark ? 0xFF162747 : 0xFFFFFFFF));
    final border = OutlineInputBorder(
        borderRadius: BorderRadius.circular(8),
        borderSide: BorderSide(color: line));
    return ThemeData(
      useMaterial3: true,
      brightness: brightness,
      colorScheme: scheme,
      scaffoldBackgroundColor: background,
      dividerColor: line,
      textTheme: ThemeData(brightness: brightness)
          .textTheme
          .apply(bodyColor: ink, displayColor: ink),
      appBarTheme: AppBarTheme(
          backgroundColor: panel,
          foregroundColor: ink,
          elevation: 0,
          scrolledUnderElevation: 0,
          centerTitle: false,
          systemOverlayStyle: SystemUiOverlayStyle(
              statusBarColor: Colors.transparent,
              statusBarIconBrightness:
                  dark ? Brightness.light : Brightness.dark,
              statusBarBrightness: dark ? Brightness.dark : Brightness.light,
              systemNavigationBarColor: background,
              systemNavigationBarIconBrightness:
                  dark ? Brightness.light : Brightness.dark)),
      inputDecorationTheme: InputDecorationTheme(
          filled: true,
          fillColor: panel,
          border: border,
          enabledBorder: border,
          focusedBorder:
              border.copyWith(borderSide: BorderSide(color: accent, width: 2)),
          contentPadding:
              const EdgeInsets.symmetric(horizontal: 14, vertical: 16),
          labelStyle: TextStyle(color: muted)),
      filledButtonTheme: FilledButtonThemeData(
          style: FilledButton.styleFrom(
              minimumSize: const Size(48, 48),
              padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 14),
              shape: RoundedRectangleBorder(
                  borderRadius: BorderRadius.circular(8)))),
      outlinedButtonTheme: OutlinedButtonThemeData(
          style: OutlinedButton.styleFrom(
              minimumSize: const Size(48, 48),
              shape: RoundedRectangleBorder(
                  borderRadius: BorderRadius.circular(8)))),
      dialogTheme: DialogThemeData(
          backgroundColor: panel,
          elevation: 0,
          shape:
              RoundedRectangleBorder(borderRadius: BorderRadius.circular(12))),
      snackBarTheme: SnackBarThemeData(
          backgroundColor: ink, contentTextStyle: TextStyle(color: panel)),
    );
  }
}
