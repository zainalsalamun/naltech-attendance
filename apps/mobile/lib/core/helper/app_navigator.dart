import 'package:flutter/material.dart';

class AppNavigator {
  static GlobalKey<NavigatorState> navigatorKey =
      GlobalKey<NavigatorState>();

  static void resetForTesting() {
    navigatorKey = GlobalKey<NavigatorState>();
  }
}
