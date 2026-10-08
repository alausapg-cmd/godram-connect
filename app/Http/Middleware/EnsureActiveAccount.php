<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureActiveAccount
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && (! $user->is_active || $user->member?->status === 'archived')) {
            Auth::logout();
            $request->session()->invalidate();

            return redirect()->route('login')->withErrors(['identifier' => 'This account is not active. Please speak to your Assembly Coordinator.']);
        }

        if ($user?->must_change_password) {
            return redirect()->route('password.change');
        }

        return $next($request);
    }
}
