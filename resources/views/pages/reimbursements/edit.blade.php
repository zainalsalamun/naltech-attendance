@extends('layouts.app')

@section('title', 'Reimbursements')

@push('style')
    <!-- CSS Libraries -->
    <link rel="stylesheet" href="{{ asset('library/summernote/dist/summernote-bs4.css') }}">
    <link rel="stylesheet" href="{{ asset('library/selectric/public/selectric.css') }}">
    <link rel="stylesheet" href="{{ asset('library/bootstrap-tagsinput/dist/bootstrap-tagsinput.css') }}">
@endpush

@section('main')
    <div class="main-content">
        <section class="section">
            <div class="section-header">
                <h1>Edit Reimbursement</h1>
                <div class="section-header-breadcrumb">
                    <div class="breadcrumb-item active"><a href="#">Dashboard</a></div>
                    <div class="breadcrumb-item"><a href="#">Reimbursements</a></div>
                    <div class="breadcrumb-item">Edit Reimbursement</div>
                </div>
            </div>

            <div class="section-body">
                <h2 class="section-title">Edit Reimbursement</h2>
                <p class="section-lead">
                    You can manage all Reimbursements, such as editing, deleting and more.
                </p>
                <div class="card">
                    <form action="{{ route('reimbursements.update', $reimbursement->id) }}" method="POST" enctype="multipart/form-data">
                        @csrf
                        @method('PUT')
                        <div class="card-header">
                            <h4>Edit Reimbursement</h4>
                        </div>
                        <div class="card-body">
                            <div class="form-group">
                                <label>User</label>
                                <select class="form-control selectric" name="user_id">
                                    <option value="">Select User</option>
                                    @foreach ($users as $user)
                                        <option value="{{ $user->id }}" {{ $reimbursement->user_id == $user->id ? 'selected' : '' }}>
                                            {{ $user->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Date</label>
                                <input type="date" class="form-control" name="date" value="{{ $reimbursement->date }}">
                            </div>
                            <div class="form-group">
                                <label>Description</label>
                                <textarea class="form-control" name="description">{{ $reimbursement->description }}</textarea>
                            </div>
                            <div class="form-group">
                                <label>Amount</label>
                                <input type="number" class="form-control" name="amount" value="{{ $reimbursement->amount }}">
                            </div>
                            <div class="form-group">
                                <label>Image</label>
                                <input type="file" class="form-control" name="image">
                                @if ($reimbursement->image)
                                    <div class="mt-2">
                                        <a href="{{ asset('storage/' . $reimbursement->image) }}" target="_blank">
                                            <img src="{{ asset('storage/' . $reimbursement->image) }}" alt="Image" style="max-width: 200px;">
                                        </a>
                                    </div>
                                @endif
                            </div>
                            <div class="form-group">
                                <label>Status</label>
                                <select class="form-control selectric" name="status">
                                    <option value="pending" {{ $reimbursement->status == 'pending' ? 'selected' : '' }}>Pending</option>
                                    <option value="approved" {{ $reimbursement->status == 'approved' ? 'selected' : '' }}>Approved</option>
                                    <option value="rejected" {{ $reimbursement->status == 'rejected' ? 'selected' : '' }}>Rejected</option>
                                </select>
                            </div>

                        </div>
                        <div class="card-footer text-right">
                            <a href="{{ route('reimbursements.index') }}" class="btn btn-secondary">Cancel</a>
                            <button class="btn btn-primary ml-2">Update</button>
                        </div>
                    </form>
                </div>

            </div>
        </section>
    </div>
@endsection

@push('scripts')
@endpush
