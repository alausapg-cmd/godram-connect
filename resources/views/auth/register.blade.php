<x-layouts.app title="Join GODRAM" :hide-errors="true">
    <x-auth-card title="Join the movement" lead="Create your GODRAM CONNECT account. Your Assembly Coordinator will confirm your membership.">
        <form method="POST" action="{{ route('register') }}" class="space-y-4">
            @csrf
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label for="first_name" class="label">First name</label>
                    <input id="first_name" name="first_name" value="{{ old('first_name') }}" class="input" autocomplete="given-name" required>
                    @error('first_name')<p class="error">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="last_name" class="label">Surname</label>
                    <input id="last_name" name="last_name" value="{{ old('last_name') }}" class="input" autocomplete="family-name" required>
                    @error('last_name')<p class="error">{{ $message }}</p>@enderror
                </div>
            </div>
            <div>
                <label for="phone" class="label">Phone number</label>
                <input id="phone" name="phone" value="{{ old('phone') }}" class="input" type="tel" autocomplete="tel" placeholder="0803 123 4567" required>
                <p class="hint">You will use this to sign in.</p>
                @error('phone')<p class="error">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="email" class="label">Email <span class="font-normal text-ink-soft">(optional)</span></label>
                <input id="email" name="email" value="{{ old('email') }}" class="input" type="email" autocomplete="email">
                <p class="hint">Lets you reset your own password.</p>
                @error('email')<p class="error">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="org_unit_id" class="label">Your Assembly</label>
                <select id="org_unit_id" name="org_unit_id" class="input" required>
                    <option value="">Choose your Assembly</option>
                    @foreach ($assemblies as $district)
                        <optgroup label="{{ $district->name }} District">
                            @foreach ($district->children as $assembly)
                                <option value="{{ $assembly->id }}" @selected(old('org_unit_id') == $assembly->id)>{{ $assembly->name }}</option>
                            @endforeach
                        </optgroup>
                    @endforeach
                </select>
                @error('org_unit_id')<p class="error">{{ $message }}</p>@enderror
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label for="password" class="label">Password</label>
                    <input id="password" type="password" name="password" class="input" autocomplete="new-password" required minlength="8">
                </div>
                <div>
                    <label for="password_confirmation" class="label">Repeat it</label>
                    <input id="password_confirmation" type="password" name="password_confirmation" class="input" autocomplete="new-password" required>
                </div>
            </div>
            @error('password')<p class="error">{{ $message }}</p>@enderror
            <p class="text-xs text-ink-soft">We only collect what GODRAM needs to serve you. Your contact details are visible only to your coordinators.</p>
            <button class="btn-primary w-full">Create my account</button>
        </form>
        <p class="mt-6 text-center text-sm text-ink-soft">Already registered? <a href="{{ route('login') }}" class="link">Sign in</a></p>
    </x-auth-card>
</x-layouts.app>
