@extends('layouts.workspace')
@section('title', 'Edit reimbursement')
@section('content')
@include('pages.reimbursements.form',['editing'=>true])
@include('components.submission-approval',['submission'=>$reimbursement,'kind'=>'reimbursement'])
@endsection
