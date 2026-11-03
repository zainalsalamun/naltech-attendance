@if(session('success'))<div class="notice" role="status">{{ session('success') }}</div>@endif
@if(session('info'))<div class="notice" role="status">{{ session('info') }}</div>@endif
@if(session('conflict'))<div class="notice amber" role="alert" id="user-conflict-notice"><strong>Konflik Data:</strong> {{ session('conflict') }}</div>@endif
@if($errors->any())<div class="notice error" role="alert"><strong>Periksa kembali isian Anda.</strong><ul>@foreach($errors->all() as $message)<li>{{ $message }}</li>@endforeach</ul></div>@endif
@if(session('warning'))<div class="notice amber" role="status">{{ session('warning') }}</div>@endif
