@extends('layouts.workspace')
@section('title', 'Edit dan persetujuan lembur')
@section('content')
@include('pages.overtime.form',['editing'=>true])
@include('components.submission-approval',['submission'=>$overtime,'kind'=>'overtime'])
@endsection
