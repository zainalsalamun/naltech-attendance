@component('mail::message')
# {{ $notificationTitle }}

Halo **{{ $recipientName }}**,

{{ $summaryLine }}

@component('mail::panel')
- **Jenis Pengajuan:** {{ $kindLabel }}
- **Pemohon:** {{ $applicantName }}
- **Status Saat Ini:** {{ $statusLabel }}
@if($reasonText)
- **Catatan / Alasan:** {{ $reasonText }}
@endif
@endcomponent

@if($actionUrl)
@component('mail::button', ['url' => $actionUrl])
Buka Detail Pengajuan
@endcomponent
@endif

Terima kasih,  
{{ config('app.name', 'Naltech Attendance') }}
@endcomponent
