import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import '../../../../core/core.dart';
import '../../bloc/get_reimbursements/get_reimbursements_bloc.dart';
import 'add_reimbursement_page.dart';
import '../../../../../core/constants/variables.dart';
import 'package:intl/intl.dart';

class ReimbursementPage extends StatefulWidget {
  const ReimbursementPage({super.key});

  @override
  State<ReimbursementPage> createState() => _ReimbursementPageState();
}

class _ReimbursementPageState extends State<ReimbursementPage> {
  @override
  void initState() {
    super.initState();
    context
        .read<GetReimbursementsBloc>()
        .add(const GetReimbursementsEvent.getReimbursements());
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Reimbursement',
            style: TextStyle(fontWeight: FontWeight.bold)),
      ),
      body: BlocBuilder<GetReimbursementsBloc, GetReimbursementsState>(
        builder: (context, state) {
          return state.maybeWhen(
            orElse: () =>
                const Center(child: Text('Tidak ada data reimbursement')),
            loading: () => const Center(child: CircularProgressIndicator()),
            error: (message) => Center(child: Text(message)),
            success: (data) {
              if (data.isEmpty) {
                return const Center(
                    child: Text('Tidak ada data reimbursement'));
              }
              return ListView.separated(
                padding: const EdgeInsets.all(16.0),
                itemCount: data.length,
                separatorBuilder: (context, index) => const SpaceHeight(12.0),
                itemBuilder: (context, index) {
                  final reimbursement = data[index];
                  final formatter =
                      NumberFormat.currency(locale: 'id_ID', symbol: 'Rp ');
                  return Container(
                    decoration: BoxDecoration(
                      color: AppColors.white,
                      borderRadius: BorderRadius.circular(16.0),
                      boxShadow: [
                        BoxShadow(
                          color: AppColors.black.withOpacity(0.05),
                          blurRadius: 10.0,
                          offset: const Offset(0, 4),
                        ),
                      ],
                    ),
                    padding: const EdgeInsets.all(16.0),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Row(
                          mainAxisAlignment: MainAxisAlignment.spaceBetween,
                          children: [
                            Text(
                              reimbursement.date ?? '-',
                              style: const TextStyle(
                                fontSize: 12.0,
                                color: AppColors.grey,
                              ),
                            ),
                            Container(
                              padding: const EdgeInsets.symmetric(
                                  horizontal: 8.0, vertical: 4.0),
                              decoration: BoxDecoration(
                                color: reimbursement.status == 'approved'
                                    ? AppColors.green.withOpacity(0.2)
                                    : reimbursement.status == 'rejected'
                                        ? AppColors.red.withOpacity(0.2)
                                        : AppColors.primary.withOpacity(0.2),
                                borderRadius: BorderRadius.circular(8.0),
                              ),
                              child: Text(
                                (reimbursement.status ?? 'Pending')
                                    .toUpperCase(),
                                style: TextStyle(
                                  fontSize: 10.0,
                                  fontWeight: FontWeight.bold,
                                  color: reimbursement.status == 'approved'
                                      ? AppColors.green
                                      : reimbursement.status == 'rejected'
                                          ? AppColors.red
                                          : AppColors.primary,
                                ),
                              ),
                            ),
                          ],
                        ),
                        const SpaceHeight(8.0),
                        Text(
                          reimbursement.description ?? '-',
                          style: const TextStyle(
                            fontSize: 16.0,
                            fontWeight: FontWeight.w600,
                          ),
                        ),
                        const SpaceHeight(8.0),
                        Text(
                          formatter.format(
                              double.tryParse(reimbursement.amount ?? '0') ??
                                  0),
                          style: const TextStyle(
                            fontSize: 14.0,
                            color: AppColors.primary,
                            fontWeight: FontWeight.w700,
                          ),
                        ),
                        if (reimbursement.image != null) ...[
                          const SpaceHeight(12.0),
                          ClipRRect(
                            borderRadius: BorderRadius.circular(8.0),
                            child: Image.network(
                              reimbursement.image!.startsWith('http')
                                  ? reimbursement.image!
                                  : reimbursement.image!.startsWith('assets/')
                                      ? '${Variables.baseUrl}/storage/${reimbursement.image!.replaceFirst('assets/', '')}'
                                      : '${Variables.baseUrl}/storage/${reimbursement.image}',
                              height: 100,
                              width: double.infinity,
                              fit: BoxFit.cover,
                              errorBuilder: (context, error, stackTrace) {
                                return Container(
                                  height: 100,
                                  width: double.infinity,
                                  decoration: BoxDecoration(
                                    color:
                                        AppColors.grey.withValues(alpha: 0.1),
                                    borderRadius: BorderRadius.circular(8.0),
                                    border: Border.all(
                                      color:
                                          AppColors.grey.withValues(alpha: 0.2),
                                    ),
                                  ),
                                  child: Column(
                                    mainAxisAlignment: MainAxisAlignment.center,
                                    children: [
                                      Icon(
                                        Icons.image_not_supported_outlined,
                                        color: AppColors.grey
                                            .withValues(alpha: 0.5),
                                        size: 32,
                                      ),
                                      const SpaceHeight(4.0),
                                      Text(
                                        'Bukti foto tidak ditemukan',
                                        style: TextStyle(
                                          fontSize: 11.0,
                                          color: AppColors.grey
                                              .withValues(alpha: 0.5),
                                        ),
                                      ),
                                    ],
                                  ),
                                );
                              },
                            ),
                          ),
                        ],
                      ],
                    ),
                  );
                },
              );
            },
          );
        },
      ),
      floatingActionButton: FloatingActionButton(
        onPressed: () {
          context.push(const AddReimbursementPage()).then((_) {
            context
                .read<GetReimbursementsBloc>()
                .add(const GetReimbursementsEvent.getReimbursements());
          });
        },
        backgroundColor: AppColors.primary,
        child: const Icon(Icons.add, color: AppColors.white),
      ),
    );
  }
}
