@extends('layouts.app')

@section('title', 'Overtime Requests')

@push('style')
    <link rel="stylesheet" href="{{ asset('library/selectric/public/selectric.css') }}">
@endpush

@section('main')
    <div class="main-content">
        <section class="section">
            <div class="section-header">
                <h1>Overtime Requests</h1>
                <div class="section-header-button">
                    <a href="{{ route('overtimes.create') }}" class="btn btn-primary">Add New Request</a>
                </div>
                <div class="section-header-breadcrumb">
                    <div class="breadcrumb-item active"><a href="{{ route('home') }}">Dashboard</a></div>
                    <div class="breadcrumb-item"><a href="{{ route('overtimes.index') }}">Overtime</a></div>
                    <div class="breadcrumb-item">All Requests</div>
                </div>
            </div>

            <div class="section-body">
                <div class="row mt-4">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <h4>History & Status Lembur</h4>
                                <div class="card-header-form">
                                    <form method="GET" action="{{ route('overtimes.index') }}">
                                        <div class="input-group">
                                            <input type="text" class="form-control" placeholder="Search employee..." name="name" value="{{ request('name') }}">
                                            <div class="input-group-btn">
                                                <button class="btn btn-primary"><i class="fas fa-search"></i></button>
                                            </div>
                                        </div>
                                    </form>
                                </div>
                            </div>
                            <div class="card-body p-0">
                                <div class="table-responsive">
                                    <table class="table-striped table">
                                        <thead>
                                            <tr>
                                                <th class="text-center">#</th>
                                                <th>Employee Name</th>
                                                <th>Date</th>
                                                <th>Duration</th>
                                                <th>Status</th>
                                                <th>Description</th>
                                                <th class="text-center">Action</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @forelse ($overtimes as $index => $overtime)
                                                <tr>
                                                    <td class="text-center">{{ $overtimes->firstItem() + $index }}</td>
                                                    <td class="font-weight-600">{{ $overtime->user->name }}</td>
                                                    <td>{{ \Carbon\Carbon::parse($overtime->date)->format('d M Y') }}</td>
                                                    <td>
                                                        <div class="badge badge-info">
                                                            <i class="fas fa-clock mr-1"></i> {{ $overtime->duration }} Min
                                                        </div>
                                                    </td>
                                                    <td>
                                                        @if($overtime->status == 'pending')
                                                            <div class="badge badge-primary">Pending</div>
                                                        @elseif($overtime->status == 'approved')
                                                            <div class="badge badge-success">Approved</div>
                                                        @else
                                                            <div class="badge badge-danger">Rejected</div>
                                                        @endif
                                                    </td>
                                                    <td>
                                                        <small class="text-muted">{{ Str::limit($overtime->description, 30) ?: '-' }}</small>
                                                    </td>
                                                    <td class="text-center">
                                                        <div class="d-flex justify-content-center">
                                                            <a href="{{ route('overtimes.edit', $overtime->id) }}"
                                                                class="btn btn-sm btn-info btn-icon mr-2"
                                                                data-toggle="tooltip" title="Edit/Review">
                                                                <i class="fas fa-edit"></i>
                                                            </a>
                                                            <form action="{{ route('overtimes.destroy', $overtime->id) }}"
                                                                method="POST" class="d-inline">
                                                                @csrf
                                                                @method('DELETE')
                                                                <button class="btn btn-sm btn-danger btn-icon confirm-delete"
                                                                    data-toggle="tooltip" title="Delete">
                                                                    <i class="fas fa-trash"></i>
                                                                </button>
                                                            </form>
                                                        </div>
                                                    </td>
                                                </tr>
                                            @empty
                                                <tr>
                                                    <td colspan="7" class="text-center p-4">
                                                        <img src="{{ asset('img/drawkit/drawkit-nature-man-colour.svg') }}" alt="" width="150" class="mb-3">
                                                        <p class="text-muted">No overtime requests found.</p>
                                                    </td>
                                                </tr>
                                            @endforelse
                                        </tbody>
                                    </table>
                                </div>
                                <div class="card-footer text-right">
                                    {{ $overtimes->withQueryString()->links() }}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </div>
@endsection

@push('scripts')
    <script src="{{ asset('library/selectric/public/jquery.selectric.min.js') }}"></script>
    <script>
        $(document).ready(function() {
            $('[data-toggle="tooltip"]').tooltip();
        });
    </script>
@endpush

