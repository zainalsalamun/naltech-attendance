@extends('layouts.app')

@section('title', 'Create Overtime')

@push('style')
    <link rel="stylesheet" href="{{ asset('library/select2/dist/css/select2.min.css') }}">
@endpush

@section('main')
    <div class="main-content">
        <section class="section">
            <div class="section-header">
                <h1>Create Overtime Request</h1>
                <div class="section-header-breadcrumb">
                    <div class="breadcrumb-item active"><a href="{{ route('home') }}">Dashboard</a></div>
                    <div class="breadcrumb-item"><a href="{{ route('overtimes.index') }}">Overtime</a></div>
                    <div class="breadcrumb-item">Create</div>
                </div>
            </div>

            <div class="section-body">
                <h2 class="section-title">New Request</h2>
                <p class="section-lead">Pencatatan lembur manual untuk karyawan.</p>

                <div class="row">
                    <div class="col-12 col-md-8 col-lg-8">
                        <div class="card card-primary">
                            <form action="{{ route('overtimes.store') }}" method="POST">
                                @csrf
                                <div class="card-header">
                                    <h4>Overtime Details</h4>
                                </div>
                                <div class="card-body">
                                    <div class="form-group">
                                        <label>User / Employee</label>
                                        <select class="form-control select2" name="user_id" required>
                                            <option value="">Select Employee</option>
                                            @foreach ($users as $user)
                                                <option value="{{ $user->id }}">{{ $user->name }} - {{ $user->role }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="row">
                                        <div class="form-group col-md-6">
                                            <label>Date</label>
                                            <div class="input-group">
                                                <div class="input-group-prepend">
                                                    <div class="input-group-text">
                                                        <i class="fas fa-calendar"></i>
                                                    </div>
                                                </div>
                                                <input type="date" name="date" class="form-control" value="{{ date('Y-m-d') }}" required>
                                            </div>
                                        </div>
                                        <div class="form-group col-md-6">
                                            <label>Duration (Minutes)</label>
                                            <div class="input-group">
                                                <div class="input-group-prepend">
                                                    <div class="input-group-text">
                                                        <i class="fas fa-stopwatch"></i>
                                                    </div>
                                                </div>
                                                <input type="number" name="duration" class="form-control" placeholder="e.g. 60" required min="1">
                                            </div>
                                            <small class="form-text text-muted">Durasi dalam hitungan menit.</small>
                                        </div>
                                    </div>
                                    <div class="form-group">
                                        <label>Work Description / Note</label>
                                        <textarea name="description" class="form-control" style="height: 100px;" placeholder="Apa yang dikerjakan selama lembur?"></textarea>
                                    </div>
                                </div>
                                <div class="card-footer text-right">
                                    <a href="{{ route('overtimes.index') }}" class="btn btn-secondary">Cancel</a>
                                    <button class="btn btn-primary ml-2">Submit Request</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </div>
@endsection

@push('scripts')
    <script src="{{ asset('library/select2/dist/js/select2.full.min.js') }}"></script>
    <script>
        $(document).ready(function() {
            $('.select2').select2({
                placeholder: "Cari karyawan...",
                allowClear: true
            });
        });
    </script>
@endpush

