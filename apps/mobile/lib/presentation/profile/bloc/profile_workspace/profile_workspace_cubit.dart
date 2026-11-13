import 'dart:async';
import 'package:flutter_bloc/flutter_bloc.dart';
import '../../../../data/datasources/user_remote_datasource.dart';
import '../../../../data/datasources/attendance_remote_datasource.dart';
import '../../../../data/models/response/auth_response_model.dart';

class ProfileWorkspaceState {
  const ProfileWorkspaceState(
      {this.loading = false,
      this.user,
      this.company,
      this.error,
      this.companyError});
  final bool loading;
  final User? user;
  final String? company, error, companyError;
}

class ProfileWorkspaceCubit extends Cubit<ProfileWorkspaceState> {
  ProfileWorkspaceCubit(this.users, this.attendance)
      : super(const ProfileWorkspaceState());
  final UserRemoteDatasource users;
  final AttendanceRemoteDatasource attendance;
  int _generation = 0;
  Future<void> load() async {
    final generation = ++_generation;
    emit(const ProfileWorkspaceState(loading: true));
    try {
      final result = await (users.getUser(), attendance.getCompanyProfile())
          .wait
          .timeout(const Duration(seconds: 20));
      if (isClosed || generation != _generation) return;
      final user = result.$1.fold((_) => null, (user) => user);
      if (user == null) {
        emit(ProfileWorkspaceState(
            error: result.$1
                .fold((message) => message, (_) => 'Profil belum tersedia.')));
        return;
      }
      final company = result.$2.fold((_) => null, (data) => data.company?.name);
      emit(ProfileWorkspaceState(
          user: user,
          company: company,
          companyError: company == null
              ? 'Informasi perusahaan belum dapat dimuat.'
              : null));
    } catch (_) {
      if (!isClosed && generation == _generation) {
        emit(const ProfileWorkspaceState(
            error:
                'Profil belum dapat dimuat. Periksa koneksi dan coba lagi.'));
      }
    }
  }
}
