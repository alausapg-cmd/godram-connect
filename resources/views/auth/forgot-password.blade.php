<x-layouts.app title="Reset password">
    <x-auth-card title="Reset your password" lead="Enter the email on your account and we will send you a reset link.">
        <form method="POST" action="{{ route('password.email') }}" class="space-y-4">
            @csrf
            <div>
                <label for="email" class="label">Email</label>
                <input id="email" name="email" type="email" value="{{ old('email') }}" class="input" required autofocus>
            </div>
            <button class="btn-primary w-full">Send reset link</button>
        </form>
        <p class="mt-4 text-sm text-ink-soft">Only use a phone number? Your Assembly Coordinator can give you a temporary password.</p>
        <p class="mt-4 text-center text-sm"><a href="{{ route('login') }}" class="link">Back to sign in</a></p>
    </x-auth-card>
</x-layouts.app>
