<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class EnsureActiveEmployee
{
    public function handle(Request $request, Closure $next)
    {
        if ($actor = $request->user()) {
            $current = User::withoutGlobalScopes()->find($actor->id);
            $stale = false;
            if ($current && $request->hasSession() && (int) Auth::guard('web')->id() === (int) $current->id) {
                $key = 'employee_auth_epoch.'.$current->id;
                $epoch = (int) $current->auth_epoch;
                if (! $request->session()->has($key) && $epoch === 0) {
                    $request->session()->put($key, 0);
                }
                $stale = $request->session()->get($key) !== $epoch;
            }
            if (! $current || ! $current->isEmploymentActive() || $stale) {
                if ($current && ! $current->isEmploymentActive()) {
                    $current->tokens()->delete();
                }
                if ($request->hasSession()) {
                    Auth::guard('web')->logout();
                    $request->session()->invalidate();
                    $request->session()->regenerateToken();
                }
                if ($request->expectsJson() || $request->is('api/*')) {
                    return response()->json(['message' => 'Akun nonaktif. Hubungi HR/admin.'], 401);
                }

                return redirect()->route('login')->withErrors(['email' => 'Akun nonaktif. Hubungi HR/admin.']);
            }
            Auth::setUser($current->withAccessToken($actor->currentAccessToken()));
        }

        return $next($request);
    }
}
