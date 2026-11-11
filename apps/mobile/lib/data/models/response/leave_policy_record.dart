class LeavePolicyRecord {
  const LeavePolicyRecord(
      {required this.id,
      required this.code,
      required this.name,
      required this.version,
      required this.deductsBalance,
      required this.requiresAttachment,
      this.annualQuotaDays,
      this.requirements});
  final int id, version;
  final String code, name;
  final bool deductsBalance, requiresAttachment;
  final int? annualQuotaDays;
  final String? requirements;
  static LeavePolicyRecord parse(dynamic raw) {
    if (raw is! Map ||
        raw['id'] is! int ||
        raw['id'] <= 0 ||
        raw['version'] is! int ||
        raw['version'] <= 0 ||
        raw['code'] is! String ||
        (raw['code'] as String).trim().isEmpty ||
        raw['name'] is! String ||
        (raw['name'] as String).trim().isEmpty ||
        raw['deducts_balance'] is! bool ||
        raw['requires_attachment'] is! bool ||
        (raw['requirements'] != null && raw['requirements'] is! String)) {
      throw const FormatException('Invalid leave policy');
    }
    final quota = raw['annual_quota_days'];
    if ((raw['deducts_balance'] == true &&
            (quota is! int || quota < 0 || quota > 366)) ||
        (raw['deducts_balance'] == false && quota != null)) {
      throw const FormatException('Invalid leave quota');
    }
    return LeavePolicyRecord(
        id: raw['id'],
        code: raw['code'],
        name: raw['name'],
        version: raw['version'],
        deductsBalance: raw['deducts_balance'],
        requiresAttachment: raw['requires_attachment'],
        annualQuotaDays: quota as int?,
        requirements: raw['requirements'] as String?);
  }
}
