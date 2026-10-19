@extends('layouts.app')

@section('title', 'Edit Overtime')

@push('style')
    <link rel="stylesheet" href="{{ asset('library/selectric/public/selectric.css') }}">
@endpush

@section('main')
    <div class="main-content">
        <section class="section">
            <div class="section-header">
                <h1>Edit Overtime Request</h1>
                <div class="section-header-breadcrumb">
                    <div class="breadcrumb-item active"><a href="{{ route('home') }}">Dashboard</a></div>
                    <div class="breadcrumb-item"><a href="{{ route('overtimes.index') }}">Overtime</a></div>
                    <div class="breadcrumb-item">Edit</div>
                </div>
            </div>

            <div class="section-body">
                <h2 class="section-title">Manage Request</h2>
                <p class="section-lead">Lakukan review atau koreksi pada data pengajuan lembur.</p>

                <div class="row">
                    <div class="col-12 col-md-8 col-lg-8">
                        <div class="card card-info">
                            <form action="{{ route('overtimes.update', $overtime->id) }}" method="POST">
                                @csrf
                                @method('PUT')
                                <div class="card-header">
                                    <h4>Update Request #{{ $overtime->id }}</h4>
                                </div>
                                <div class="card-body">
                                    <div class="form-group">
                                        <label>User / Employee</label>
                                        <div class="input-group">
                                            <div class="input-group-prepend">
                                                <div class="input-group-text">
                                                    <i class="fas fa-user-circle"></i>
                                                </div>
                                            </div>
                                            <input type="text" class="form-control" value="{{ $overtime->user->name }}" disabled>
                                        </div>
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
                                                <input type="date" name="date" class="form-control" value="{{ $overtime->date }}" required>
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
                                                <input type="number" name="duration" class="form-control" value="{{ $overtime->duration }}" required min="1">
                                            </div>
                                        </div>
                                    </div>
                                    <div class="form-group">
                                        <label>Work Description / Note</label>
                                        <textarea name="description" class="form-control" style="height: 100px;">{{ $overtime->description }}</textarea>
                                    </div>
                                    <div class="form-group">
                                        <label>Status Persetujuan</label>
                                        <select class="form-control selectric" name="status">
                                            <option value="pending" {{ $overtime->status == 'pending' ? 'selected' : '' }}>Pending (Menunggu)</option>
                                            <option value="approved" {{ $overtime->status == 'approved' ? 'selected' : '' }}>Approved (Disetujui)</option>
                                            <option value="rejected" {{ $overtime->status == 'rejected' ? 'selected' : '' }}>Rejected (Ditolak)</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="card-footer text-right">
                                    <a href="{{ route('overtimes.index') }}" class="btn btn-secondary">Back</a>
                                    <button class="btn btn-info ml-2">Update Changes</button>
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
    <script src="{{ asset('library/selectric/public/jquery.selectric.min.js') }}"></script>
@endpush

