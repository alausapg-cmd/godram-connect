<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AuditLogger;
use App\Support\Phone;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function showLogin()
    {
        return view('auth.login');
    }

    /** Sign in with an email address or a phone number. */
    public function login(Request $request, AuditLogger $audit)
    {
        $data = $request->validate([
            'identifier' => ['required', 'string', 'max:120'],
            'password' => ['required', 'string'],
        ]);

        $identifier = trim($data['identifier']);
        $user = str_contains($identifier, '@')
            ? User::where('email', mb_strtolower($identifier))->first()
            : User::where('phone', Phone::normalize($identifier))->first();

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages(['identifier' => 'That email or phone number and password do not match our records.']);
        }
        if (! $user->is_active) {
            throw ValidationException::withMessages(['identifier' => 'This account is not active. Please speak to your Assembly Coordinator.']);
        }

        Auth::login($user, $request->boolean('remember'));
        $request->session()->regenerate();
        $user->forceFill(['last_login_at' => now()])->save();
        $audit->log('auth.login', $user, $user->name.' signed in', [], null, $user->id);

        return redirect()->intended(route('dashboard'));
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home');
    }
}
