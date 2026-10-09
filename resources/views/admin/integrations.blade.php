<x-layouts.app title="Integrations">
    <section class="container-page mt-8 max-w-4xl">
        <p class="eyebrow">Administration</p>
        <h1 class="h-page mt-1">Integrations</h1>
        <p class="muted mt-2">What GODRAM CONNECT does with each outside service, and what it does not. Nothing here claims more than the service allows.</p>

        <div class="mt-4 flex flex-wrap gap-2 text-xs">
            <span class="badge-ok">Fully integrated</span><span class="text-ink-soft">works on its own</span>
            <span class="badge-info ml-2">Partly integrated</span><span class="text-ink-soft">works, with limits</span>
            <span class="badge-neutral ml-2">Share and export</span><span class="text-ink-soft">we prepare it, a person sends it</span>
            <span class="badge-warn ml-2">Not set up yet</span><span class="text-ink-soft">needs one step on the server</span>
        </div>

        <div class="mt-6 space-y-3">
            @foreach ($integrations as [$name, $icon, $status, $does, $limits])
                <div class="card flex gap-4 p-4">
                    <span class="flex size-10 shrink-0 items-center justify-center rounded-full bg-stage text-gold"><x-icon :name="$icon" class="size-5" /></span>
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <h2 class="font-semibold text-ink">{{ $name }}</h2>
                            <span @class([
                                'badge-ok' => $status === \App\Http\Controllers\Admin\IntegrationController::FULL,
                                'badge-info' => $status === \App\Http\Controllers\Admin\IntegrationController::PARTIAL,
                                'badge-neutral' => $status === \App\Http\Controllers\Admin\IntegrationController::SHARE,
                                'badge-warn' => $status === \App\Http\Controllers\Admin\IntegrationController::OFF,
                            ])>{{ $status }}</span>
                        </div>
                        <p class="mt-1 text-sm text-ink">{{ $does }}</p>
                        <p class="mt-1 text-sm text-ink-soft">{{ $limits }}</p>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="card-pad mt-8">
            <h2 class="h-section">Sending</h2>
            <dl class="mt-3 grid gap-4 text-sm sm:grid-cols-4">
                <div><dt class="text-ink-soft">Email and push</dt><dd class="font-semibold">{{ $queue['connection'] === 'sync' ? 'Sent straight away' : 'Queued, sent by cron each minute' }}</dd></div>
                <div><dt class="text-ink-soft">Waiting to send</dt><dd class="font-semibold">{{ $queue['waiting'] ?? 'n/a' }}</dd></div>
                <div><dt class="text-ink-soft">Failed</dt><dd @class(['font-semibold', 'text-curtain' => $queue['failed']])>{{ $queue['failed'] ?? 'n/a' }}</dd></div>
                <div><dt class="text-ink-soft">Last reminder</dt><dd class="font-semibold">{{ $queue['last_reminder'] ? \Illuminate\Support\Carbon::parse($queue['last_reminder'])->diffForHumans() : 'None yet' }}</dd></div>
            </dl>
            @if (($queue['waiting'] ?? 0) > 50)
                <p class="mt-3 text-sm text-curtain">Many messages are waiting. Check that the cron job runs every minute (see the install guide).</p>
            @endif
        </div>
    </section>
</x-layouts.app>
