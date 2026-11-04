@extends('layouts.workspace')
@section('title', 'Tambah reimbursement')
@section('content')
@include('pages.reimbursements.form',['editing'=>false])
@endsection
