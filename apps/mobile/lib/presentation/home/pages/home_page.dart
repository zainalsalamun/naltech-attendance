import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter/widgets.dart';
import 'package:flutter_absensi_app/core/helper/radius_calculate.dart';
import 'package:flutter_absensi_app/data/datasources/auth_local_datasource.dart';
import 'package:flutter_absensi_app/presentation/home/bloc/get_company/get_company_bloc.dart';
import 'package:flutter_absensi_app/presentation/home/bloc/is_checkedin/is_checkedin_bloc.dart';
import 'package:flutter_absensi_app/presentation/home/pages/attandences/face_detector_checkin_page.dart';
import 'package:flutter_absensi_app/presentation/home/pages/attandences/attendance_result_page.dart';
import 'package:flutter_absensi_app/presentation/home/pages/attandences/scanner_page.dart';
import 'package:flutter_absensi_app/presentation/home/pages/attendance_checkout_page.dart';
import 'package:flutter_absensi_app/presentation/home/pages/face_detector_view.dart';
import 'package:flutter_absensi_app/presentation/home/pages/permission_page.dart';
import 'package:geolocator/geolocator.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:location/location.dart' hide LocationAccuracy;
import 'package:flutter_absensi_app/presentation/home/pages/reimbursements/reimbursement_page.dart';

import '../../../core/constants/variables.dart';
import '../../../core/core.dart';
import '../../profile/bloc/get_user/get_user_bloc.dart';
import '../widgets/menu_button.dart';

class HomePage extends StatefulWidget {
  const HomePage({super.key});

  @override
  State<HomePage> createState() => _HomePageState();
}

class _HomePageState extends State<HomePage> {
  String? faceEmbedding;

  @override
  void initState() {
    _initializeFaceEmbedding();
    context.read<IsCheckedinBloc>().add(const IsCheckedinEvent.isCheckedIn());
    context.read<GetCompanyBloc>().add(const GetCompanyEvent.getCompany());
    context.read<GetUserBloc>().add(const GetUserEvent.getUser());
    super.initState();
    getCurrentPosition();
  }

  double? latitude;
  double? longitude;

  Future<void> getCurrentPosition() async {
    try {
      Location location = Location();

      bool serviceEnabled;
      PermissionStatus permissionGranted;
      LocationData locationData;

      serviceEnabled = await location.serviceEnabled();
      if (!serviceEnabled) {
        serviceEnabled = await location.requestService();
        if (!serviceEnabled) {
          return;
        }
      }

      permissionGranted = await location.hasPermission();
      if (permissionGranted == PermissionStatus.denied) {
        permissionGranted = await location.requestPermission();
        if (permissionGranted != PermissionStatus.granted) {
          return;
        }
      }

      locationData = await location.getLocation();
      latitude = locationData.latitude;
      longitude = locationData.longitude;

      setState(() {});
    } on PlatformException catch (e) {
      if (e.code == 'IO_ERROR') {
        debugPrint(
          'A network error occurred trying to lookup the supplied coordinates: ${e.message}',
        );
      } else {
        debugPrint('Failed to lookup coordinates: ${e.message}');
      }
    } catch (e) {
      debugPrint('An unknown error occurred: $e');
    }
  }

  Future<void> _initializeFaceEmbedding() async {
    try {
      final authData = await AuthLocalDatasource().getAuthData();
      setState(() {
        faceEmbedding = authData?.user?.faceEmbedding;
      });
    } catch (e) {
      // Tangani error di sini jika ada masalah dalam mendapatkan authData
      print('Error fetching auth data: $e');
      setState(() {
        faceEmbedding = null; // Atur faceEmbedding ke null jika ada kesalahan
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: const Color(0xFFF5F7FA),
      body: SingleChildScrollView(
        child: Column(
          children: [
            Stack(
              children: [
                Container(
                  height: 240,
                  decoration: const BoxDecoration(
                    color: AppColors.primary,
                    borderRadius: BorderRadius.only(
                      bottomLeft: Radius.circular(30),
                      bottomRight: Radius.circular(30),
                    ),
                  ),
                ),
                SafeArea(
                  child: Padding(
                    padding: const EdgeInsets.all(16.0),
                    child: Column(
                      children: [
                        BlocBuilder<GetUserBloc, GetUserState>(
                          builder: (context, state) {
                            return state.maybeWhen(
                              orElse: () => const SizedBox.shrink(),
                              loading: () => const Center(
                                  child: CircularProgressIndicator()),
                              success: (user) {
                                return Row(
                                  children: [
                                    ClipRRect(
                                      borderRadius: BorderRadius.circular(50.0),
                                      child: Image.network(
                                        user.imageUrl != null
                                            ? (user.imageUrl!.startsWith('http')
                                                ? user.imageUrl!
                                                : user.imageUrl!
                                                        .startsWith('storage/')
                                                    ? '${Variables.baseUrl}/${user.imageUrl}'
                                                    : '${Variables.baseUrl}/storage/${user.imageUrl}')
                                            : 'https://i.pinimg.com/originals/1b/14/53/1b14536a5f7e70664550df4ccaa5b231.jpg',
                                        width: 48.0,
                                        height: 48.0,
                                        fit: BoxFit.cover,
                                        errorBuilder:
                                            (context, error, stackTrace) {
                                          return Container(
                                            width: 48.0,
                                            height: 48.0,
                                            color: AppColors.white
                                                .withValues(alpha: 0.2),
                                            child: const Icon(
                                              Icons.person,
                                              color: AppColors.white,
                                              size: 24,
                                            ),
                                          );
                                        },
                                      ),
                                    ),
                                    const SpaceWidth(12.0),
                                    Expanded(
                                      child: Text(
                                        'Hello, ${user.name}',
                                        style: const TextStyle(
                                          fontSize: 18.0,
                                          color: AppColors.white,
                                          fontWeight: FontWeight.bold,
                                        ),
                                        maxLines: 2,
                                      ),
                                    ),
                                    IconButton(
                                      onPressed: () {
                                        context.read<GetCompanyBloc>().add(
                                              const GetCompanyEvent
                                                  .getCompany(),
                                            );
                                      },
                                      icon: const Icon(Icons.refresh,
                                          color: AppColors.white),
                                    ),
                                  ],
                                );
                              },
                            );
                          },
                        ),
                        const SpaceHeight(12.0),
                        Container(
                          padding: const EdgeInsets.all(16.0),
                          decoration: BoxDecoration(
                            color: AppColors.white,
                            borderRadius: BorderRadius.circular(24.0),
                            boxShadow: [
                              BoxShadow(
                                color: AppColors.black.withOpacity(0.08),
                                blurRadius: 20.0,
                                offset: const Offset(0, 8),
                              ),
                            ],
                          ),
                          child: Column(
                            children: [
                              Row(
                                mainAxisAlignment:
                                    MainAxisAlignment.spaceBetween,
                                children: [
                                  StreamBuilder<DateTime>(
                                    stream: Stream.periodic(
                                      const Duration(seconds: 1),
                                      (_) => DateTime.now(),
                                    ),
                                    builder: (context, snapshot) {
                                      final currentTime =
                                          snapshot.data ?? DateTime.now();
                                      return Column(
                                        crossAxisAlignment:
                                            CrossAxisAlignment.start,
                                        children: [
                                          Text(
                                            'Waktu Saat Ini',
                                            style: TextStyle(
                                              color: AppColors.black
                                                  .withOpacity(0.6),
                                              fontSize: 13.0,
                                              fontWeight: FontWeight.w700,
                                            ),
                                          ),
                                          const SpaceHeight(4.0),
                                          Text(
                                            currentTime.toFormattedTime(),
                                            style: const TextStyle(
                                              fontWeight: FontWeight.bold,
                                              fontSize: 30.0,
                                              color: AppColors.primary,
                                              letterSpacing: -1.0,
                                            ),
                                          ),
                                          const SpaceHeight(4.0),
                                          Text(
                                            currentTime.toFormattedDate(),
                                            style: TextStyle(
                                              color: AppColors.black
                                                  .withOpacity(0.5),
                                              fontSize: 14.0,
                                              fontWeight: FontWeight.w600,
                                            ),
                                          ),
                                        ],
                                      );
                                    },
                                  ),
                                  Container(
                                    padding: const EdgeInsets.all(16.0),
                                    decoration: BoxDecoration(
                                      color: AppColors.primary.withOpacity(0.1),
                                      borderRadius: BorderRadius.circular(20.0),
                                    ),
                                    child: const Icon(
                                      Icons.access_time_filled_rounded,
                                      color: AppColors.primary,
                                      size: 36.0,
                                    ),
                                  ),
                                ],
                              ),
                              const SpaceHeight(12.0),
                              Container(
                                padding: const EdgeInsets.symmetric(
                                  horizontal: 16.0,
                                  vertical: 14.0,
                                ),
                                decoration: BoxDecoration(
                                  color: AppColors.grey.withOpacity(0.05),
                                  borderRadius: BorderRadius.circular(16.0),
                                  border: Border.all(
                                    color: AppColors.grey.withOpacity(0.1),
                                  ),
                                ),
                                child: Row(
                                  children: [
                                    Container(
                                      padding: const EdgeInsets.all(10.0),
                                      decoration: BoxDecoration(
                                        color: AppColors.white,
                                        borderRadius:
                                            BorderRadius.circular(12.0),
                                        boxShadow: [
                                          BoxShadow(
                                            color: AppColors.black
                                                .withOpacity(0.05),
                                            blurRadius: 10.0,
                                            offset: const Offset(0, 2),
                                          ),
                                        ],
                                      ),
                                      child: const Icon(
                                        Icons.calendar_month_rounded,
                                        color: AppColors.primary,
                                        size: 20.0,
                                      ),
                                    ),
                                    const SpaceWidth(16.0),
                                    Expanded(
                                      child: Column(
                                        crossAxisAlignment:
                                            CrossAxisAlignment.start,
                                        children: [
                                          const Text(
                                            'Jadwal Shift Hari Ini',
                                            style: TextStyle(
                                              fontSize: 12.0,
                                              color: Colors.black54,
                                              fontWeight: FontWeight.w800,
                                            ),
                                          ),
                                          const SpaceHeight(2.0),
                                          Text(
                                            '${DateTime(2024, 3, 14, 8, 0).toFormattedTime()} - ${DateTime(2024, 3, 14, 16, 0).toFormattedTime()}',
                                            style: const TextStyle(
                                              fontWeight: FontWeight.w700,
                                              fontSize: 15.0,
                                              color: AppColors.primary,
                                            ),
                                          ),
                                        ],
                                      ),
                                    ),
                                  ],
                                ),
                              ),
                            ],
                          ),
                        ),
                      ],
                    ),
                  ),
                ),
              ],
            ),
            const SpaceHeight(12.0),
            GridView(
              padding: const EdgeInsets.symmetric(horizontal: 16.0),
              gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
                crossAxisCount: 2,
                crossAxisSpacing: 16.0,
                mainAxisSpacing: 16.0,
                childAspectRatio: 1.25,
              ),
              shrinkWrap: true,
              physics: const NeverScrollableScrollPhysics(),
              children: [
                BlocBuilder<GetCompanyBloc, GetCompanyState>(
                  builder: (context, state) {
                    final latitudePoint = state.maybeWhen(
                      orElse: () => 0.0,
                      success: (data) => double.parse(data.latitude!),
                    );
                    final longitudePoint = state.maybeWhen(
                      orElse: () => 0.0,
                      success: (data) => double.parse(data.longitude!),
                    );

                    final radiusPoint = state.maybeWhen(
                      orElse: () => 0.0,
                      success: (data) => double.parse(data.radiusKm!),
                    );

                    final attendanceType = state.maybeWhen(
                      orElse: () => 'Location',
                      success: (data) => data.attendanceType!,
                    );
                    return BlocConsumer<IsCheckedinBloc, IsCheckedinState>(
                      listener: (context, state) {
                        //
                      },
                      builder: (context, state) {
                        final isCheckin = state.maybeWhen(
                          orElse: () => false,
                          success: (data) => data.isCheckedin,
                        );

                        return MenuButton(
                          label: 'Datang',
                          iconData: Icons.login_rounded,
                          foregroundColor: AppColors.primary,
                          onPressed: () async {
                            final position =
                                await Geolocator.getCurrentPosition();

                            if (position.isMocked) {
                              _showWarningDialog(
                                'Anda menggunakan lokasi palsu',
                              );
                              return;
                            }

                            final distanceKm =
                                RadiusCalculate.calculateDistance(
                              position.latitude,
                              position.longitude,
                              latitudePoint,
                              longitudePoint,
                            );

                            if (distanceKm > (radiusPoint + 0.05)) {
                              _showWarningDialog(
                                'Anda diluar jangkauan absen ($distanceKm km dari kantor)',
                              );
                              return;
                            }

                            if (isCheckin) {
                              _showWarningDialog('Anda sudah checkin');
                            } else {
                              if (attendanceType == 'Face') {
                                context.push(
                                  const FaceDetectorCheckinPage(
                                    isCheckedIn: true,
                                  ),
                                );
                              } else if (attendanceType == 'QR') {
                                context.push(
                                  const ScannerPage(isCheckin: true),
                                );
                              } else {
                                context.push(
                                  AttendanceResultPage(
                                    isCheckin: true,
                                    isMatch: true,
                                    attendanceType: attendanceType,
                                  ),
                                );
                              }
                            }
                          },
                        );
                      },
                    );
                  },
                ),
                BlocBuilder<GetCompanyBloc, GetCompanyState>(
                  builder: (context, state) {
                    final latitudePoint = state.maybeWhen(
                      orElse: () => 0.0,
                      success: (data) => double.parse(data.latitude!),
                    );
                    final longitudePoint = state.maybeWhen(
                      orElse: () => 0.0,
                      success: (data) => double.parse(data.longitude!),
                    );

                    final radiusPoint = state.maybeWhen(
                      orElse: () => 0.0,
                      success: (data) => double.parse(data.radiusKm!),
                    );

                    final attendanceType = state.maybeWhen(
                      orElse: () => 'Location',
                      success: (data) => data.attendanceType!,
                    );
                    return BlocBuilder<IsCheckedinBloc, IsCheckedinState>(
                      builder: (context, state) {
                        final isCheckout = state.maybeWhen(
                          orElse: () => false,
                          success: (data) => data.isCheckedout,
                        );
                        final isCheckIn = state.maybeWhen(
                          orElse: () => false,
                          success: (data) => data.isCheckedin,
                        );
                        return MenuButton(
                          label: 'Pulang',
                          iconData: Icons.logout_rounded,
                          foregroundColor: AppColors.red,
                          onPressed: () async {
                            final position =
                                await Geolocator.getCurrentPosition();

                            if (position.isMocked) {
                              _showWarningDialog(
                                'Anda menggunakan lokasi palsu',
                              );
                              return;
                            }

                            final distanceKm =
                                RadiusCalculate.calculateDistance(
                              position.latitude,
                              position.longitude,
                              latitudePoint,
                              longitudePoint,
                            );

                            if (distanceKm > (radiusPoint + 0.05)) {
                              _showWarningDialog(
                                'Anda diluar jangkauan absen ($distanceKm km dari kantor)',
                              );
                              return;
                            }
                            if (!isCheckIn) {
                              _showWarningDialog(
                                'Anda belum checkin, silahkan checkin terlebih dahulu',
                              );
                            } else if (isCheckout) {
                              _showWarningDialog('Anda sudah checkout');
                            } else {
                              if (attendanceType == 'Face') {
                                context.push(
                                  const FaceDetectorCheckinPage(
                                    isCheckedIn: false,
                                  ),
                                );
                              } else if (attendanceType == 'QR') {
                                context.push(
                                  const ScannerPage(isCheckin: false),
                                );
                              } else {
                                context.push(
                                  AttendanceResultPage(
                                    isCheckin: false,
                                    isMatch: true,
                                    attendanceType: attendanceType,
                                  ),
                                );
                              }
                            }
                          },
                        );
                      },
                    );
                  },
                ),
                MenuButton(
                  label: 'Izin',
                  iconData: Icons.edit_document,
                  foregroundColor: Colors.orange,
                  onPressed: () {
                    context.push(const PermissionPage());
                  },
                ),
                // MenuButton(
                //   label: 'Catatan',
                //   iconData: Icons.my_library_books_rounded,
                //   foregroundColor: Colors.orange,
                //   onPressed: () {},
                // ),
                MenuButton(
                  label: 'Reimbursement',
                  iconData: Icons.receipt_long,
                  foregroundColor: AppColors.green,
                  onPressed: () {
                    context.push(const ReimbursementPage());
                  },
                ),
              ],
            ),
            const SpaceHeight(12.0),
            faceEmbedding != null
                ? BlocBuilder<IsCheckedinBloc, IsCheckedinState>(
                    builder: (context, state) {
                      final isCheckout = state.maybeWhen(
                        orElse: () => false,
                        success: (data) => data.isCheckedout,
                      );
                      final isCheckIn = state.maybeWhen(
                        orElse: () => false,
                        success: (data) => data.isCheckedin,
                      );
                      return BlocBuilder<GetCompanyBloc, GetCompanyState>(
                        builder: (context, state) {
                          final latitudePoint = state.maybeWhen(
                            orElse: () => 0.0,
                            success: (data) => double.parse(data.latitude!),
                          );
                          final longitudePoint = state.maybeWhen(
                            orElse: () => 0.0,
                            success: (data) => double.parse(data.longitude!),
                          );

                          final radiusPoint = state.maybeWhen(
                            orElse: () => 0.0,
                            success: (data) => double.parse(data.radiusKm!),
                          );
                          return Container(
                            margin: const EdgeInsets.symmetric(
                              horizontal: 16.0,
                            ),
                            decoration: BoxDecoration(
                              borderRadius: BorderRadius.circular(24.0),
                              gradient: LinearGradient(
                                colors: [
                                  AppColors.primary,
                                  const Color(0xFF2E5BFF),
                                ],
                                begin: Alignment.topLeft,
                                end: Alignment.bottomRight,
                              ),
                              boxShadow: [
                                BoxShadow(
                                  color: AppColors.primary.withOpacity(0.4),
                                  blurRadius: 20.0,
                                  offset: const Offset(0, 8),
                                ),
                              ],
                            ),
                            child: Material(
                              color: Colors.transparent,
                              borderRadius: BorderRadius.circular(24.0),
                              child: InkWell(
                                onTap: () async {
                                  final position =
                                      await Geolocator.getCurrentPosition();

                                  if (position.isMocked) {
                                    ScaffoldMessenger.of(
                                      context,
                                    ).showSnackBar(
                                      const SnackBar(
                                        content: Text(
                                          'Anda menggunakan lokasi palsu',
                                        ),
                                        backgroundColor: AppColors.red,
                                      ),
                                    );
                                    return;
                                  }

                                  final distanceKm =
                                      RadiusCalculate.calculateDistance(
                                    position.latitude,
                                    position.longitude,
                                    latitudePoint,
                                    longitudePoint,
                                  );

                                  print('jarak radius:  $distanceKm');

                                  if (distanceKm > (radiusPoint + 0.05)) {
                                    ScaffoldMessenger.of(
                                      context,
                                    ).showSnackBar(
                                      SnackBar(
                                        content: Text(
                                          'Anda diluar jangkauan absen ($distanceKm km dari kantor)',
                                        ),
                                        backgroundColor: AppColors.red,
                                      ),
                                    );
                                    return;
                                  }

                                  if (!isCheckIn) {
                                    context.push(
                                      const FaceDetectorCheckinPage(
                                        isCheckedIn: true,
                                      ),
                                    );
                                  } else if (!isCheckout) {
                                    context.push(
                                      const AttendanceCheckoutPage(),
                                    );
                                  } else {
                                    ScaffoldMessenger.of(
                                      context,
                                    ).showSnackBar(
                                      const SnackBar(
                                        content: Text('Anda sudah checkout'),
                                        backgroundColor: AppColors.red,
                                      ),
                                    );
                                  }
                                },
                                borderRadius: BorderRadius.circular(24.0),
                                splashColor: Colors.white.withOpacity(0.1),
                                highlightColor: Colors.white.withOpacity(
                                  0.05,
                                ),
                                child: Padding(
                                  padding: const EdgeInsets.symmetric(
                                    horizontal: 24.0,
                                    vertical: 16.0,
                                  ),
                                  child: Row(
                                    mainAxisAlignment:
                                        MainAxisAlignment.spaceBetween,
                                    children: [
                                      Expanded(
                                        child: Column(
                                          crossAxisAlignment:
                                              CrossAxisAlignment.start,
                                          children: [
                                            Text(
                                              isCheckIn
                                                  ? 'Daily Check-out'
                                                  : 'Daily Check-in',
                                              style: const TextStyle(
                                                color: Colors.white70,
                                                fontSize: 14.0,
                                                fontWeight: FontWeight.w600,
                                              ),
                                            ),
                                            const SpaceHeight(4.0),
                                            Text(
                                              isCheckIn
                                                  ? 'Attendance Check-out Using Face ID'
                                                  : 'Attendance Check-in Using Face ID',
                                              style: const TextStyle(
                                                color: Colors.white,
                                                fontSize: 18.0,
                                                fontWeight: FontWeight.w700,
                                                letterSpacing: -0.5,
                                              ),
                                            ),
                                          ],
                                        ),
                                      ),
                                      const SpaceWidth(16.0),
                                      Container(
                                        padding: const EdgeInsets.all(16.0),
                                        decoration: BoxDecoration(
                                          color: Colors.white.withOpacity(
                                            0.2,
                                          ),
                                          shape: BoxShape.circle,
                                        ),
                                        child: Assets.icons.attendance.svg(
                                          width: 32.0,
                                          height: 32.0,
                                          colorFilter: const ColorFilter.mode(
                                            Colors.white,
                                            BlendMode.srcIn,
                                          ),
                                        ),
                                      ),
                                    ],
                                  ),
                                ),
                              ),
                            ),
                          );
                        },
                      );
                    },
                  )
                : Container(
                    margin: const EdgeInsets.symmetric(horizontal: 16.0),
                    decoration: BoxDecoration(
                      borderRadius: BorderRadius.circular(24.0),
                      gradient: LinearGradient(
                        colors: [
                          AppColors.primary,
                          AppColors.primary.withOpacity(0.8),
                        ],
                        begin: Alignment.topLeft,
                        end: Alignment.bottomRight,
                      ),
                      boxShadow: [
                        BoxShadow(
                          color: AppColors.primary.withOpacity(0.3),
                          blurRadius: 20.0,
                          offset: const Offset(0, 8),
                        ),
                      ],
                    ),
                    child: Material(
                      color: Colors.transparent,
                      borderRadius: BorderRadius.circular(24.0),
                      child: InkWell(
                        onTap: () {
                          showBottomSheet(
                            backgroundColor: AppColors.white,
                            context: context,
                            builder: (context) => Container(
                              padding: const EdgeInsets.all(16.0),
                              child: Column(
                                crossAxisAlignment: CrossAxisAlignment.start,
                                mainAxisSize: MainAxisSize.min,
                                children: [
                                  const SizedBox(
                                    width: 60.0,
                                    height: 8.0,
                                    child: Divider(
                                      color: AppColors.lightSheet,
                                    ),
                                  ),
                                  const CloseButton(),
                                  const Center(
                                    child: Text(
                                      'Oops !',
                                      style: TextStyle(
                                        fontWeight: FontWeight.w700,
                                        fontSize: 24.0,
                                      ),
                                    ),
                                  ),
                                  const SpaceHeight(4.0),
                                  const Center(
                                    child: Text(
                                      'Aplikasi ingin mengakses Kamera',
                                      style: TextStyle(
                                        fontWeight: FontWeight.w700,
                                        fontSize: 15.0,
                                      ),
                                    ),
                                  ),
                                  const SpaceHeight(36.0),
                                  Button.filled(
                                    onPressed: () => context.pop(),
                                    label: 'Tolak',
                                    color: AppColors.secondary,
                                  ),
                                  const SpaceHeight(16.0),
                                  Button.filled(
                                    onPressed: () {
                                      context.pop();
                                      context.push(const FaceDetectorView());
                                    },
                                    label: 'Izinkan',
                                  ),
                                ],
                              ),
                            ),
                          );
                        },
                        borderRadius: BorderRadius.circular(24.0),
                        splashColor: Colors.white.withOpacity(0.1),
                        highlightColor: Colors.white.withOpacity(0.05),
                        child: Padding(
                          padding: const EdgeInsets.symmetric(
                            horizontal: 24.0,
                            vertical: 16.0,
                          ),
                          child: Row(
                            mainAxisAlignment: MainAxisAlignment.spaceBetween,
                            children: [
                              const Expanded(
                                child: Column(
                                  crossAxisAlignment: CrossAxisAlignment.start,
                                  children: [
                                    Text(
                                      'New Member?',
                                      style: TextStyle(
                                        color: Colors.white70,
                                        fontSize: 14.0,
                                        fontWeight: FontWeight.w600,
                                      ),
                                    ),
                                    SpaceHeight(4.0),
                                    Text(
                                      'Daftarkan Wajah',
                                      style: TextStyle(
                                        color: Colors.white,
                                        fontSize: 18.0,
                                        fontWeight: FontWeight.w700,
                                        letterSpacing: -0.5,
                                      ),
                                    ),
                                  ],
                                ),
                              ),
                              const SpaceWidth(16.0),
                              Container(
                                padding: const EdgeInsets.all(16.0),
                                decoration: BoxDecoration(
                                  color: Colors.white.withOpacity(0.2),
                                  shape: BoxShape.circle,
                                ),
                                child: Assets.icons.attendance.svg(
                                  width: 32.0,
                                  height: 32.0,
                                  colorFilter: const ColorFilter.mode(
                                    Colors.white,
                                    BlendMode.srcIn,
                                  ),
                                ),
                              ),
                            ],
                          ),
                        ),
                      ),
                    ),
                  ),
            const SpaceHeight(20.0),
          ],
        ),
      ),
    );
  }

  void _showWarningDialog(String message) {
    showDialog(
      context: context,
      builder: (context) {
        return AlertDialog(
          backgroundColor: AppColors.white,
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(24.0),
          ),
          contentPadding: const EdgeInsets.all(32.0),
          content: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              Container(
                padding: const EdgeInsets.all(20.0),
                decoration: BoxDecoration(
                  color: AppColors.red.withOpacity(0.1),
                  shape: BoxShape.circle,
                ),
                child: const Icon(
                  Icons.warning_amber_rounded,
                  color: AppColors.red,
                  size: 48.0,
                ),
              ),
              const SpaceHeight(24.0),
              const Text(
                'Peringatan !',
                style: TextStyle(
                  fontSize: 22.0,
                  fontWeight: FontWeight.bold,
                  color: AppColors.black,
                  letterSpacing: -0.5,
                ),
              ),
              const SpaceHeight(12.0),
              Text(
                message,
                textAlign: TextAlign.center,
                style: const TextStyle(
                  fontSize: 16.0,
                  color: AppColors.grey,
                  fontWeight: FontWeight.w500,
                  height: 1.4,
                ),
              ),
              const SpaceHeight(32.0),
              SizedBox(
                width: double.infinity,
                child: Button.filled(
                  onPressed: () => Navigator.pop(context),
                  label: 'Saya Mengerti',
                ),
              ),
            ],
          ),
        );
      },
    );
  }
}
