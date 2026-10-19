// ignore_for_file: public_member_api_docs, sort_constructors_first

import 'package:image_picker/image_picker.dart';

class UserRequestModel {
  final int id;
  final String name;
  final String email;
  final String phone;
  final XFile? image;
  UserRequestModel({
    required this.id,
    required this.name,
    required this.email,
    required this.phone,
    this.image,
  });

  Map<String, String> toMap() {
    return {
      'id': id.toString(),
      'name': name,
      'email': email,
      'phone': phone,
    };
  }
}
