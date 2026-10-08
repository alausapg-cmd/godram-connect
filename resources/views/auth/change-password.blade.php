<x-layouts.app title="Change password">
    <x-auth-card title="Choose your own password" :lead="auth()->user()->must_change_password ? 'You signed in with a temporary password. Please choose your own before continuing.' : null">
        <form method="POST" action="{{ route('password.change.update') }}" class="space-y-4">
            @csrf @method('PUT')
            <div>
                <label for="current_password" class="label">Current (temporary) password</label>
                <input id="current_password" type="password" name="current_password" class="input" required autocomplete="current-password">
            </div>
            <div>
                <label for="password" class="label">New password</label>
                <input id="password" type="password" name="password" class="input" autocomplete="new-password" required minlength="8">
                <p class="hint">At least 8 characters.</p>
            </div>
            <div>
                <label for="password_confirmation" class="label">Repeat it</label>
                <input id="password_confirmation" type="password" name="password_confirmation" class="input" autocomplete="new-password" required>
            </div>
            <button class="btn-primary w-full">Save and continue</button>
        </form>
    </x-auth-card>
</x-layouts.app>
