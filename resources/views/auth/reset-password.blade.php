<x-layouts.app title="Choose a new password">
    <x-auth-card title="Choose a new password">
        <form method="POST" action="{{ route('password.update') }}" class="space-y-4">
            @csrf
            <input type="hidden" name="token" value="{{ $token }}">
            <div>
                <label for="email" class="label">Email</label>
                <input id="email" name="email" type="email" value="{{ old('email', $email) }}" class="input" required>
            </div>
            <div>
                <label for="password" class="label">New password</label>
                <input id="password" type="password" name="password" class="input" autocomplete="new-password" required minlength="8">
            </div>
            <div>
                <label for="password_confirmation" class="label">Repeat it</label>
                <input id="password_confirmation" type="password" name="password_confirmation" class="input" autocomplete="new-password" required>
            </div>
            <button class="btn-primary w-full">Save password</button>
        </form>
    </x-auth-card>
</x-layouts.app>
