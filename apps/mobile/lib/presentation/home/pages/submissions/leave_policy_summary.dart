import 'package:flutter/material.dart';
import '../../../../data/models/response/leave_policy_record.dart';

class LeavePolicySummary extends StatelessWidget {
  const LeavePolicySummary(
      {super.key, required this.policy, this.historical = false});
  final LeavePolicyRecord policy;
  final bool historical;
  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
          color: theme.colorScheme.surface,
          border: Border.all(color: theme.colorScheme.outline),
          borderRadius: BorderRadius.circular(12)),
      child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
        Text(historical ? 'Kebijakan cuti saat diajukan' : 'Kebijakan cuti',
            style: theme.textTheme.titleMedium),
        const SizedBox(height: 8),
        Text('${policy.code} · ${policy.name}',
            style: const TextStyle(fontWeight: FontWeight.w700)),
        const SizedBox(height: 8),
        Text(
            policy.requiresAttachment ? 'Lampiran wajib' : 'Lampiran opsional'),
        Text(policy.deductsBalance
            ? 'Aturan mengurangi saldo; pemotongan belum aktif.'
            : 'Tidak mengurangi saldo.'),
        if (policy.annualQuotaDays != null)
          Text('Kuota kebijakan: ${policy.annualQuotaDays} hari / tahun.'),
        const Text('Saldo karyawan belum dihitung.'),
        if (policy.requirements?.trim().isNotEmpty == true) ...[
          const SizedBox(height: 8),
          SelectableText(policy.requirements!),
        ],
      ]),
    );
  }
}
