<x-layouts.app title="Sign in" :hide-errors="true">
    <x-auth-card title="Welcome back" lead="Sign in with the email address or phone number you registered with.">
        <form method="POST" action="{{ route('login') }}" class="space-y-4">
            @csrf
            <div>
                <label for="identifier" class="label">Email or phone number</label>
                <input id="identifier" name="identifier" value="{{ old('identifier') }}" class="input" autocomplete="username" required autofocus inputmode="email">
                @error('identifier')<p class="error">{{ $message }}</p>@enderror
            </div>
            <div>
                <div class="flex items-center justify-between">
                    <label for="password" class="label">Password</label>
                    <a href="{{ route('password.request') }}" class="mb-1.5 text-xs font-semibold text-curtain">Forgotten it?</a>
                </div>
                <input id="password" type="password" name="password" class="input" autocomplete="current-password" required>
                @error('password')<p class="error">{{ $message }}</p>@enderror
            </div>
            <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="remember" value="1" class="size-4 accent-curtain"> Keep me signed in on this device</label>
            <button class="btn-primary w-full">Sign in</button>
        </form>
        <p class="mt-6 text-center text-sm text-ink-soft">New to GODRAM CONNECT? <a href="{{ route('register') }}" class="link">Join here</a></p>
        @if (config('godram.demo_mode'))
            <div class="mt-6 rounded-xl bg-paper-2 p-4 text-xs text-ink-soft">
                <p class="font-semibold text-ink">Preview accounts (password: {{ \Database\Seeders\DemoSeeder::PASSWORD }})</p>
                <ul class="mt-1 space-y-0.5 font-mono">
                    <li>national@demo.godram.test</li>
                    <li>region1@demo.godram.test</li>
                    <li>lagos.district@demo.godram.test</li>
                    <li>ayantuga.assembly@demo.godram.test</li>
                    <li>ayantuga.member@demo.godram.test</li>
                    <li>admin@demo.godram.test</li>
                </ul>
            </div>
        @endif
    </x-auth-card>
</x-layouts.app>
