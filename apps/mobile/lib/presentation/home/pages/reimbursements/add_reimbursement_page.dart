import 'dart:io';

import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:image_picker/image_picker.dart';
import 'package:intl/intl.dart';

import '../../../../core/core.dart';
import '../../bloc/add_reimbursement/add_reimbursement_bloc.dart';

class AddReimbursementPage extends StatefulWidget {
  const AddReimbursementPage({super.key});

  @override
  State<AddReimbursementPage> createState() => _AddReimbursementPageState();
}

class _AddReimbursementPageState extends State<AddReimbursementPage> {
  final TextEditingController _dateController = TextEditingController();
  final TextEditingController _descriptionController = TextEditingController();
  final TextEditingController _amountController = TextEditingController();
  final _formKey = GlobalKey<FormState>();

  XFile? _imageFile;
  final ImagePicker _picker = ImagePicker();

  Future<void> _pickImage() async {
    final pickedFile = await _picker.pickImage(source: ImageSource.camera);
    if (pickedFile != null) {
      setState(() {
        _imageFile = pickedFile;
      });
    }
  }

  Future<void> _selectDate() async {
    final DateTime? pickedTime = await showDatePicker(
      context: context,
      initialDate: DateTime.now(),
      firstDate: DateTime(2000),
      lastDate: DateTime(2101),
    );

    if (pickedTime != null) {
      setState(() {
        _dateController.text = DateFormat('yyyy-MM-dd').format(pickedTime);
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Add Reimbursement',
            style: TextStyle(fontWeight: FontWeight.bold)),
      ),
      body: SingleChildScrollView(
        padding: const EdgeInsets.all(16.0),
        child: Form(
          key: _formKey,
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              TextFormField(
                controller: _dateController,
                readOnly: true,
                decoration: const InputDecoration(
                  labelText: 'Tanggal',
                  border: OutlineInputBorder(),
                  suffixIcon: Icon(Icons.calendar_month),
                ),
                onTap: _selectDate,
                validator: (value) => value == null || value.isEmpty
                    ? 'Tanggal wajib diisi'
                    : null,
              ),
              const SpaceHeight(16.0),
              TextFormField(
                controller: _amountController,
                keyboardType: TextInputType.number,
                decoration: const InputDecoration(
                  labelText: 'Jumlah',
                  border: OutlineInputBorder(),
                  prefixText: 'Rp ',
                ),
                validator: (value) => value == null || value.isEmpty
                    ? 'Jumlah wajib diisi'
                    : null,
              ),
              const SpaceHeight(16.0),
              TextFormField(
                controller: _descriptionController,
                maxLines: 3,
                decoration: const InputDecoration(
                  labelText: 'Deskripsi',
                  border: OutlineInputBorder(),
                ),
                validator: (value) => value == null || value.isEmpty
                    ? 'Deskripsi wajib diisi'
                    : null,
              ),
              const SpaceHeight(16.0),
              const Text('Bukti Foto (Opsional)',
                  style: TextStyle(fontWeight: FontWeight.bold)),
              const SpaceHeight(8.0),
              GestureDetector(
                onTap: _pickImage,
                child: Container(
                  width: double.infinity,
                  height: 150,
                  decoration: BoxDecoration(
                    color: AppColors.primary.withOpacity(0.1),
                    borderRadius: BorderRadius.circular(10),
                    border: Border.all(color: AppColors.primary, width: 2),
                  ),
                  child: _imageFile != null
                      ? ClipRRect(
                          borderRadius: BorderRadius.circular(8),
                          child: Image.file(
                            File(_imageFile!.path),
                            fit: BoxFit.cover,
                          ),
                        )
                      : const Center(
                          child: Column(
                            mainAxisAlignment: MainAxisAlignment.center,
                            children: [
                              Icon(Icons.camera_alt,
                                  size: 40, color: AppColors.primary),
                              SpaceHeight(8.0),
                              Text('Ambil Bukti Foto',
                                  style: TextStyle(color: AppColors.primary)),
                            ],
                          ),
                        ),
                ),
              ),
              const SpaceHeight(32.0),
              BlocConsumer<AddReimbursementBloc, AddReimbursementState>(
                listener: (context, state) {
                  state.maybeWhen(
                    orElse: () {},
                    success: () {
                      ScaffoldMessenger.of(context).showSnackBar(
                        const SnackBar(
                          content: Text('Berhasil menambah reimbursement'),
                          backgroundColor: AppColors.green,
                        ),
                      );
                      Navigator.pop(context);
                    },
                    error: (msg) {
                      ScaffoldMessenger.of(context).showSnackBar(
                        SnackBar(
                          content: Text(msg),
                          backgroundColor: AppColors.red,
                        ),
                      );
                    },
                  );
                },
                builder: (context, state) {
                  return state.maybeWhen(
                    loading: () =>
                        const Center(child: CircularProgressIndicator()),
                    orElse: () => SizedBox(
                      width: double.infinity,
                      height: 50,
                      child: ElevatedButton(
                        style: ElevatedButton.styleFrom(
                          backgroundColor: AppColors.primary,
                          shape: RoundedRectangleBorder(
                            borderRadius: BorderRadius.circular(10),
                          ),
                        ),
                        onPressed: () {
                          if (_formKey.currentState!.validate()) {
                            context.read<AddReimbursementBloc>().add(
                                  AddReimbursementEvent.addReimbursement(
                                    _dateController.text,
                                    _descriptionController.text,
                                    _amountController.text,
                                    _imageFile,
                                  ),
                                );
                          }
                        },
                        child: const Text(
                          'Simpan Reimbursement',
                          style: TextStyle(
                            fontSize: 16,
                            fontWeight: FontWeight.bold,
                            color: Colors.white,
                          ),
                        ),
                      ),
                    ),
                  );
                },
              ),
            ],
          ),
        ),
      ),
    );
  }
}
