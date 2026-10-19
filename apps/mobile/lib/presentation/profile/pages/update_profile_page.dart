import 'package:flutter/material.dart';

import 'package:flutter_absensi_app/data/models/response/auth_response_model.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:image_picker/image_picker.dart';

import '../../../core/components/image_picker_widget.dart';
import '../../../core/core.dart';
import '../../../data/datasources/auth_local_datasource.dart';
import '../../../data/models/request/user_request_model.dart';
import '../bloc/get_user/get_user_bloc.dart';
import '../bloc/update_user/update_user_bloc.dart';
import '../../../data/models/response/user_response_model.dart';

class UpdateProfilePage extends StatefulWidget {
  final User user;
  const UpdateProfilePage({
    super.key,
    required this.user,
  });

  @override
  State<UpdateProfilePage> createState() => _UpdateProfilePageState();
}

class _UpdateProfilePageState extends State<UpdateProfilePage> {
  TextEditingController? nameController;
  TextEditingController? emailController;
  TextEditingController? phoneController;
  XFile? imageFile;
  AuthResponseModel? authData;
  @override
  void initState() {
    super.initState();
    loadData();
    nameController = TextEditingController(text: widget.user.name ?? '');
    emailController = TextEditingController(text: widget.user.email ?? '');
    phoneController = TextEditingController(text: widget.user.phone ?? '');
  }

  loadData() async {
    authData = await AuthLocalDatasource().getAuthData();
    setState(() {});
  }

  @override
  void dispose() {
    nameController?.dispose();
    emailController?.dispose();
    phoneController?.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppColors.white,
      appBar: AppBar(
        elevation: 0,
        backgroundColor: AppColors.white,
        centerTitle: true,
        title: const Text(
          "Update Profile",
          style: TextStyle(
            color: AppColors.black,
            fontSize: 18.0,
            fontWeight: FontWeight.w700,
          ),
        ),
        iconTheme: const IconThemeData(color: AppColors.black),
      ),
      bottomNavigationBar: Container(
        padding: const EdgeInsets.all(24.0),
        decoration: BoxDecoration(
          color: AppColors.white,
          boxShadow: [
            BoxShadow(
              color: AppColors.black.withOpacity(0.04),
              blurRadius: 24.0,
              offset: const Offset(0, -8),
            ),
          ],
        ),
        child: SafeArea(
          child: BlocConsumer<UpdateUserBloc, UpdateUserState>(
            listener: (context, state) {
              state.maybeMap(
                orElse: () {},
                success: (value) async {
                  await AuthLocalDatasource()
                      .updateAuthData(UserResponseModel(user: value.user));
                  if (context.mounted) {
                    context
                        .read<GetUserBloc>()
                        .add(const GetUserEvent.getUser());
                    ScaffoldMessenger.of(context).showSnackBar(
                      const SnackBar(
                        content: Text('Profile berhasil diperbarui'),
                        backgroundColor: AppColors.primary,
                      ),
                    );
                    context.pop(true);
                  }
                },
                error: (e) {
                  ScaffoldMessenger.of(context).showSnackBar(
                    const SnackBar(
                      content: Text('Gagal memperbarui profil'),
                      backgroundColor: Colors.red,
                    ),
                  );
                },
              );
            },
            builder: (context, state) {
              return state.maybeWhen(
                orElse: () {
                  return Button.filled(
                    onPressed: () {
                      final String name = nameController!.text;
                      final String email = emailController!.text;
                      final String phone = phoneController!.text;
                      final UserRequestModel user = UserRequestModel(
                        id: widget.user.id!,
                        name: name,
                        email: email,
                        phone: phone,
                        image: imageFile,
                      );
                      context.read<UpdateUserBloc>().add(
                          UpdateUserEvent.updateUser(user, widget.user.id!));
                    },
                    label: 'Simpan Perubahan',
                  );
                },
                loading: () {
                  return const Center(
                    child: CircularProgressIndicator(),
                  );
                },
              );
            },
          ),
        ),
      ),
      body: ListView(
        padding: const EdgeInsets.all(24.0),
        children: [
          Center(
            child: ImagePickerWidget(
              label: 'Foto Profil',
              onChanged: (file) {
                if (file == null) {
                  return;
                }
                imageFile = file;
              },
              imageUrl: widget.user.imageUrl,
            ),
          ),
          const SpaceHeight(32.0),
          const Text(
            'Informasi Pribadi',
            style: TextStyle(
              fontSize: 16.0,
              fontWeight: FontWeight.w600,
              color: AppColors.black,
            ),
          ),
          const SpaceHeight(16.0),
          Container(
            padding: const EdgeInsets.all(20.0),
            decoration: BoxDecoration(
              color: AppColors.white,
              borderRadius: BorderRadius.circular(20.0),
              border: Border.all(color: AppColors.grey.withOpacity(0.1)),
              boxShadow: [
                BoxShadow(
                  color: AppColors.black.withOpacity(0.04),
                  blurRadius: 15.0,
                  offset: const Offset(0, 5),
                ),
              ],
            ),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                CustomTextField(
                  label: 'Nama Lengkap',
                  controller: nameController!,
                ),
                const SpaceHeight(20.0),
                CustomTextField(
                  label: 'Alamat Email',
                  controller: emailController!,
                  keyboardType: TextInputType.emailAddress,
                ),
                const SpaceHeight(20.0),
                CustomTextField(
                  label: 'Nomor Telepon',
                  controller: phoneController!,
                  keyboardType: TextInputType.phone,
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}
