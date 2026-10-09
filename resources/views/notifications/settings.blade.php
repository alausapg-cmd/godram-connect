<x-layouts.app title="Notification settings">
    <section class="container-page mt-8 max-w-3xl">
        <a href="{{ route('notifications.index') }}" class="link text-sm">&larr; Notifications</a>
        <h1 class="h-page mt-2">Notification settings</h1>
        <p class="muted mt-2">Everything appears under Notifications in the app. Choose what should also reach you by email or as a notification on your phone.</p>

        {{-- This device --}}
        <div class="card-pad mt-6">
            <div class="flex items-start gap-3">
                <span class="flex size-10 shrink-0 items-center justify-center rounded-full bg-stage text-gold"><x-icon name="bell" class="size-5" /></span>
                <div class="min-w-0 flex-1">
                    <h2 class="h-section">Notifications on this phone</h2>
                    @if (! $pushReady)
                        <p class="muted mt-1 text-sm">Phone notifications are not switched on for GODRAM CONNECT yet. Your administrator turns them on once for everyone; until then you still get notices in the app and by email.</p>
                    @else
                        <div x-data="pushSwitch(@js($pushKey), @js(route('notifications.devices.store')), @js(route('notifications.devices.destroy')))" class="mt-1 text-sm">
                            <p x-show="state === 'checking' || state === 'working'" class="muted">Checking this device…</p>
                            <div x-cloak x-show="state === 'off'">
                                <p class="muted">Get a notification on this device when something needs you, even when GODRAM CONNECT is closed.</p>
                                <button type="button" @click="turnOn" class="btn-primary btn-sm mt-3"><x-icon name="bell" class="size-4" /> Turn on for this device</button>
                            </div>
                            <div x-cloak x-show="state === 'on'">
                                <p class="font-semibold text-ok"><x-icon name="check-circle" class="inline size-4" /> On for this device.</p>
                                <button type="button" @click="turnOff" class="btn-ghost btn-sm mt-3">Turn off for this device</button>
                            </div>
                            <p x-cloak x-show="state === 'blocked'" class="muted">Notifications are blocked for this site. Open your browser's site settings for GODRAM CONNECT, allow notifications, then come back to this page.</p>
                            <p x-cloak x-show="state === 'ios-install'" class="muted">On iPhone and iPad, first add GODRAM CONNECT to your Home Screen (Share, then Add to Home Screen), open it from there and return to this page.</p>
                            <p x-cloak x-show="state === 'unsupported'" class="muted">This browser cannot receive notifications. Chrome on Android works best.</p>
                            <p x-cloak x-show="error" x-text="error" class="error"></p>
                        </div>
                    @endif
                    @if ($devices->isNotEmpty())
                        <ul class="mt-4 divide-y divide-line rounded-xl border border-line text-sm">
                            @foreach ($devices as $device)
                                <li class="flex items-center justify-between gap-3 px-3 py-2">
                                    <span>{{ $device->device ?? 'Device' }} <span class="text-ink-soft">· added {{ $device->created_at->format('j M Y') }}</span></span>
                                    <form method="POST" action="{{ route('notifications.devices.destroy') }}">@csrf @method('DELETE')<input type="hidden" name="id" value="{{ $device->id }}"><button class="link text-xs">Remove</button></form>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            </div>
        </div>

        {{-- Choices per category --}}
        <form method="POST" action="{{ route('notifications.settings.update') }}" class="card mt-6 overflow-hidden">
            @csrf @method('PUT')
            <table class="table">
                <thead class="bg-paper/60">
                    <tr><th>What</th><th class="w-16 text-center">Email</th><th class="w-16 text-center">Phone</th></tr>
                </thead>
                <tbody>
                    @foreach ($categories as $key => $c)
                        @php $locked = $c['locked'] ?? false; @endphp
                        <tr>
                            <td>
                                <span class="flex items-center gap-2 font-semibold text-ink"><x-icon :name="$c['icon']" class="size-4 text-poster" />{{ $c['label'] }}</span>
                                <span class="mt-0.5 block text-xs text-ink-soft">{{ $c['description'] }}@if ($locked) <span class="font-semibold">Always on.</span>@endif</span>
                            </td>
                            @foreach (['email', 'push'] as $channel)
                                <td class="text-center align-middle">
                                    @if ($locked)
                                        <span title="Always on"><x-icon name="lock" class="mx-auto size-4 text-ink-soft" /><span class="sr-only">Always on</span></span>
                                    @else
                                        <input type="checkbox" name="{{ $channel }}[{{ $key }}]" value="1" @checked($user->wantsNotice($key, $channel)) class="size-5 accent-curtain" aria-label="{{ $c['label'] }} by {{ $channel === 'push' ? 'phone notification' : 'email' }}">
                                    @endif
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
            <div class="flex flex-wrap items-center justify-between gap-3 border-t border-line bg-paper/40 p-4">
                <p class="text-xs text-ink-soft">
                    @if ($user->email) Emails go to {{ $user->email }}. @else You have no email address on your account, so nothing is emailed. @endif
                    Cancellations and results always reach you.
                </p>
                <button class="btn-primary btn-sm">Save choices</button>
            </div>
        </form>
    </section>
</x-layouts.app>
