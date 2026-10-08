<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\Rules\Password as PasswordRule;

class PasswordController extends Controller
{
    public function showForgot()
    {
        return view('auth.forgot-password');
    }

    public function sendLink(Request $request)
    {
        $request->validate(['email' => ['required', 'email']]);
        Password::sendResetLink(['email' => mb_strtolower($request->input('email'))]);

        // Same answer whether or not the email exists, so accounts cannot be discovered.
        return back()->with('status', 'If that email belongs to an account, a reset link is on its way. If you only use a phone number, ask your Assembly Coordinator to reset your password.');
    }

    public function showReset(Request $request, string $token)
    {
        return view('auth.reset-password', ['token' => $token, 'email' => $request->query('email')]);
    }

    public function reset(Request $request)
    {
        $request->validate([
            'token' => ['required'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', PasswordRule::min(8)],
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password) {
                $user->forceFill(['password' => $password, 'must_change_password' => false])->save();
                event(new PasswordReset($user));
            }
        );

        return $status === Password::PASSWORD_RESET
            ? redirect()->route('login')->with('status', 'Your password has been changed. Please sign in.')
            : back()->withErrors(['email' => 'This reset link is no longer valid. Please request a new one.']);
    }

    public function showChange()
    {
        return view('auth.change-password');
    }

    public function change(Request $request)
    {
        $user = $request->user();
        $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', PasswordRule::min(8)],
        ]);
        if (Hash::check($request->input('password'), $user->password)) {
            return back()->withErrors(['password' => 'Please choose a password different from the current one.']);
        }
        $user->forceFill(['password' => $request->input('password'), 'must_change_password' => false])->save();

        return redirect()->route('dashboard')->with('status', 'Your password has been updated.');
    }
}
