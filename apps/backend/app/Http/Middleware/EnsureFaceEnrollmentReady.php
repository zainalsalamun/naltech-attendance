<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class EnsureFaceEnrollmentReady
{
    public function handle(Request $request, Closure $next)
    {
        abort_unless(Schema::hasTable('face_enrollments') && Schema::hasTable('face_enrollment_events'),
            503, 'Pendaftaran wajah belum tersedia. Hubungi pengelola aplikasi.');

        return $next($request);
    }
}
