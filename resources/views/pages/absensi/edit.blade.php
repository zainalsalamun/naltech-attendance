@extends('layouts.app')

@section('title', 'Edit Attendance')

@push('style')
    <!-- CSS Libraries -->
@endpush

@section('main')
    <div class="main-content">
        <section class="section">
            <div class="section-header">
                <h1>Edit Attendance</h1>
                <div class="section-header-breadcrumb">
                    <div class="breadcrumb-item active"><a href="#">Dashboard</a></div>
                    <div class="breadcrumb-item"><a href="{{ route('attendances.index') }}">Attendances</a></div>
                    <div class="breadcrumb-item">Edit Attendance</div>
                </div>
            </div>

            <div class="section-body">
                <h2 class="section-title">Edit Attendance</h2>
                <p class="section-lead">
                    Update attendance information here.
                </p>

                <div class="row mt-sm-4">
                    <div class="col-12 col-md-12 col-lg-12">
                        <div class="card">
                            <form method="POST" action="{{ route('attendances.update', $attendance->id) }}">
                                @csrf
                                @method('PUT')
                                <div class="card-body">
                                    <div class="row">
                                        <div class="form-group col-md-6 col-12">
                                            <label>Name</label>
                                            <input type="text" class="form-control" value="{{ $attendance->user->name ?? 'Unknown' }}" disabled>
                                        </div>
                                        <div class="form-group col-md-6 col-12">
                                            <label>Date</label>
                                            <input type="date" class="form-control" value="{{ $attendance->date }}" disabled>
                                        </div>
                                    </div>
                                    <div class="row">
                                        <div class="form-group col-md-6 col-12">
                                            <label>Time In</label>
                                            <input type="time" name="time_in" class="form-control @error('time_in') is-invalid @enderror"
                                                value="{{ $attendance->time_in }}">
                                            @error('time_in')
                                                <div class="invalid-feedback">
                                                    {{ $message }}
                                                </div>
                                            @enderror
                                        </div>
                                        <div class="form-group col-md-6 col-12">
                                            <label>Time Out</label>
                                            <input type="time" name="time_out" class="form-control @error('time_out') is-invalid @enderror"
                                                value="{{ $attendance->time_out }}">
                                            @error('time_out')
                                                <div class="invalid-feedback">
                                                    {{ $message }}
                                                </div>
                                            @enderror
                                        </div>
                                    </div>
                                    <div class="row">
                                        <div class="form-group col-md-6 col-12">
                                            <label>Latlong In</label>
                                            <input type="text" name="latlon_in" class="form-control @error('latlon_in') is-invalid @enderror"
                                                value="{{ $attendance->latlon_in }}">
                                            @error('latlon_in')
                                                <div class="invalid-feedback">
                                                    {{ $message }}
                                                </div>
                                            @enderror
                                        </div>
                                        <div class="form-group col-md-6 col-12">
                                            <label>Latlong Out</label>
                                            <input type="text" name="latlon_out" class="form-control @error('latlon_out') is-invalid @enderror"
                                                value="{{ $attendance->latlon_out }}">
                                            @error('latlon_out')
                                                <div class="invalid-feedback">
                                                    {{ $message }}
                                                </div>
                                            @enderror
                                        </div>
                                    </div>
                                </div>
                                <div class="card-footer text-right">
                                    <a href="{{ route('attendances.index') }}" class="btn btn-secondary">Cancel</a>
                                    <button type="submit" class="btn btn-primary ml-2">Save Changes</button>
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
@endpush
