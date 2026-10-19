<?php

namespace App\Http\Middleware;

use App\Models\Company;
use App\Support\CompanyContext;
use Closure;
use Illuminate\Http\Request;

class EnsureCompany
{
    public function handle(Request $request, Closure $next)
    {
        $context = app(CompanyContext::class);
        $user = $request->user();
        abort_unless($user?->company_id && Company::withoutGlobalScopes()->whereKey($user->company_id)->exists(),
            403, 'Akun belum terhubung ke perusahaan. Hubungi administrator.');

        $context->id = (int) $user->company_id;
        try {
            return $next($request);
        } finally {
            $context->id = null;
        }
    }
}
