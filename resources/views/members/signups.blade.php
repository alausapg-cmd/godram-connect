<x-layouts.app title="New sign-ups">
    <section class="container-page mt-8 max-w-3xl">
        <p class="eyebrow">Connect</p>
        <h1 class="h-page">New sign-ups</h1>
        <p class="mt-1 text-ink-soft">People who joined online and chose an Assembly in your area. Confirm the ones you know; they then receive a Member ID.</p>
        @forelse ($members as $m)
            <div class="card mt-4 flex flex-col gap-3 p-4 sm:flex-row sm:items-center sm:justify-between">
                <div class="flex items-center gap-3">
                    <x-avatar :member="$m" />
                    <div>
                        <p class="font-semibold">{{ $m->full_name }}</p>
                        <p class="text-xs text-ink-soft">{{ \App\Support\Phone::display($m->phone) }} · {{ $m->currentPlacement?->orgUnit?->name }}, {{ $m->currentPlacement?->orgUnit?->parent?->name }} · joined {{ $m->created_at->diffForHumans() }}</p>
                    </div>
                </div>
                <div class="flex gap-2">
                    <form method="POST" action="{{ route('signups.reject', $m) }}" onsubmit="return confirm('Decline this sign-up?')">@csrf<button class="btn-ghost btn-sm">Decline</button></form>
                    <form method="POST" action="{{ route('signups.approve', $m) }}">@csrf<button class="btn-primary btn-sm"><x-icon name="check" class="size-4" />Confirm</button></form>
                </div>
            </div>
        @empty
            <x-empty title="All caught up" class="mt-6">There are no sign-ups waiting for confirmation.</x-empty>
        @endforelse
    </section>
</x-layouts.app>
