<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureGymAccountActive
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user() && $request->user()->getRawOriginal('is_active') !== null && ! $request->user()->is_active) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors(['username' => 'Tài khoản đã bị khóa. Vui lòng liên hệ quản lý.']);
        }

        return $next($request);
    }
}
