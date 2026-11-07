@extends('layouts.workspace')
@section('title', 'Edit karyawan')
@section('content')
@include('pages.users.form', ['editing'=>true])
<section class="panel"><div class="panel-heading"><h2>Riwayat kepegawaian</h2><p class="muted">Sepuluh perubahan terakhir, waktu aplikasi.</p></div><div class="table-scroll" tabindex="0" role="region" aria-label="Riwayat perubahan kepegawaian"><table><thead><tr><th>Waktu</th><th>Perubahan</th><th>Pengelola</th><th>Alasan</th></tr></thead><tbody>@forelse($lifecycleEvents as $event)<tr><td>{{ $event->created_at }}</td><td>{{ ['created'=>'Dibuat','updated'=>'Data diperbarui','archived'=>'Diarsipkan','reactivated'=>'Diaktifkan kembali','deactivated'=>'Dinonaktifkan'][$event->action] ?? 'Perubahan' }}</td><td>{{ $event->actor_name ?: 'Pengelola tidak tersedia' }}</td><td>{{ $event->reason ?: 'Tanpa catatan' }}</td></tr>@empty<tr><td colspan="4" class="empty-state">Belum ada riwayat perubahan kepegawaian.</td></tr>@endforelse</tbody></table></div></section>
@endsection
