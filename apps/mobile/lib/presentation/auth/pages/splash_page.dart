import 'package:flutter/material.dart';
import 'package:flutter_absensi_app/data/datasources/auth_local_datasource.dart';
import 'package:flutter_absensi_app/presentation/home/pages/main_page.dart';

import '../../../core/utils/connectivity_service.dart';
import '../../../core/core.dart';
import 'login_page.dart';

class SplashPage extends StatefulWidget {
  const SplashPage({super.key});

  @override
  State<SplashPage> createState() => _SplashPageState();
}

class _SplashPageState extends State<SplashPage> {
  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppColors.primary,
      body: FutureBuilder(
        future: Future.wait([
          AuthLocalDatasource().isAuth(),
          ConnectivityService.isConnected(),
        ]),
        builder: (context, snapshot) {
          if (snapshot.connectionState == ConnectionState.waiting) {
            return _buildSplashContent();
          }
          if (snapshot.hasData) {
            final isAuth = snapshot.data![0];
            final isConnected = snapshot.data![1];

            if (!isConnected) {
              return _buildNoInternetContent();
            }

            if (isAuth) {
              Future.delayed(const Duration(seconds: 2), () {
                if (mounted) {
                  context.pushReplacement(const MainPage());
                }
              });
            } else {
              Future.delayed(const Duration(seconds: 2), () {
                if (mounted) {
                  context.pushReplacement(const LoginPage());
                }
              });
            }
          }
          return _buildSplashContent();
        },
      ),
    );
  }

  Widget _buildNoInternetContent() {
    return Center(
      child: Column(
        mainAxisAlignment: MainAxisAlignment.center,
        children: [
          const Icon(
            Icons.wifi_off_rounded,
            size: 80,
            color: AppColors.white,
          ),
          const SpaceHeight(20.0),
          const Text(
            'No Internet Connection',
            style: TextStyle(
              color: AppColors.white,
              fontSize: 20,
              fontWeight: FontWeight.bold,
            ),
          ),
          const SpaceHeight(10.0),
          const Text(
            'Please check your connection and try again.',
            textAlign: TextAlign.center,
            style: TextStyle(
              color: AppColors.white,
              fontSize: 14,
            ),
          ),
          const SpaceHeight(30.0),
          Button.filled(
            onPressed: () {
              setState(() {});
            },
            label: 'Try Again',
            color: AppColors.white,
            textColor: AppColors.primary,
          ),
        ],
      ),
    );
  }

  Widget _buildSplashContent() {
    return Center(
      child: Column(
        mainAxisAlignment: MainAxisAlignment.center,
        children: [
          const Spacer(),
          // Custom logo design with Icon
          Container(
            width: 140,
            height: 140,
            decoration: BoxDecoration(
              borderRadius: BorderRadius.circular(32),
              boxShadow: [
                BoxShadow(
                  color: AppColors.black.withValues(alpha: 0.15),
                  blurRadius: 25,
                  offset: const Offset(0, 12),
                ),
              ],
            ),
            child: ClipRRect(
              borderRadius: BorderRadius.circular(32),
              child: Image.asset(
                'assets/images/smart_attendance_app_icon.png',
                fit: BoxFit.cover,
              ),
            ),
          ),
          const SpaceHeight(40.0),
          // App Title
          Text(
            Variables.appName,
            style: const TextStyle(
              color: AppColors.white,
              fontSize: 36.0,
              fontWeight: FontWeight.bold,
              letterSpacing: 1.0,
            ),
          ),
          const SpaceHeight(10.0),
          const Text(
            'Smart Attendance System',
            textAlign: TextAlign.center,
            style: TextStyle(
              color: AppColors.lightSheet,
              fontSize: 14.0,
              fontWeight: FontWeight.w400,
            ),
          ),
          const Spacer(),
          // Loading Indicator or footer
          const CircularProgressIndicator(
            valueColor: AlwaysStoppedAnimation<Color>(AppColors.white),
            strokeWidth: 3.0,
          ),
          const SpaceHeight(40.0),
          const Text(
            'Version 1.0.0',
            style: TextStyle(
              color: AppColors.white,
              fontSize: 12.0,
              fontWeight: FontWeight.w300,
            ),
          ),
          const SpaceHeight(40.0),
        ],
      ),
    );
  }
}
