import 'dart:convert';

class ReimbursementResponseModel {
  final String? message;
  final List<Reimbursement>? data;

  ReimbursementResponseModel({
    this.message,
    this.data,
  });

  factory ReimbursementResponseModel.fromJson(String str) =>
      ReimbursementResponseModel.fromMap(json.decode(str));

  String toJson() => json.encode(toMap());

  factory ReimbursementResponseModel.fromMap(Map<String, dynamic> json) =>
      ReimbursementResponseModel(
        message: json["message"],
        data: json["data"] == null
            ? []
            : List<Reimbursement>.from(
                json["data"]!.map((x) => Reimbursement.fromMap(x))),
      );

  Map<String, dynamic> toMap() => {
        "message": message,
        "data":
            data == null ? [] : List<dynamic>.from(data!.map((x) => x.toMap())),
      };
}

class Reimbursement {
  final int? id;
  final int? userId;
  final String? date;
  final String? description;
  final String? amount;
  final String? image;
  final String? status;
  final DateTime? createdAt;
  final DateTime? updatedAt;

  Reimbursement({
    this.id,
    this.userId,
    this.date,
    this.description,
    this.amount,
    this.image,
    this.status,
    this.createdAt,
    this.updatedAt,
  });

  factory Reimbursement.fromJson(String str) =>
      Reimbursement.fromMap(json.decode(str));

  String toJson() => json.encode(toMap());

  factory Reimbursement.fromMap(Map<String, dynamic> json) => Reimbursement(
        id: json["id"],
        userId: json["user_id"],
        date: json["date"],
        description: json["description"],
        amount: json["amount"].toString(),
        image: json["image"],
        status: json["status"],
        createdAt: json["created_at"] == null
            ? null
            : DateTime.parse(json["created_at"]),
        updatedAt: json["updated_at"] == null
            ? null
            : DateTime.parse(json["updated_at"]),
      );

  Map<String, dynamic> toMap() => {
        "id": id,
        "user_id": userId,
        "date": date,
        "description": description,
        "amount": amount,
        "image": image,
        "status": status,
        "created_at": createdAt?.toIso8601String(),
        "updated_at": updatedAt?.toIso8601String(),
      };
}
