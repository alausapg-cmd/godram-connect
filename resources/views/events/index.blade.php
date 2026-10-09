<x-layouts.app title="Events" description="GODRAM performances, workshops, conventions, premieres and live broadcasts.">
    <x-hero page="events" eyebrow="Performances, trainings and programmes" title="Events" lead="Drama crusades, conventions, trainings and programmes across every Assembly, District and Region.">
        <div class="mt-6 flex flex-wrap items-center gap-3">
            @if ($canCreate)<a href="{{ route('events.create') }}" class="btn-gold no-underline"><x-icon name="plus" class="size-4" /> Add an event</a>@endif
        </div>
        <nav class="mt-5 flex gap-2 overflow-x-auto pb-1 [scrollbar-width:none] sm:flex-wrap sm:overflow-visible" aria-label="Event types">
            <a href="{{ route('events') }}" @class(['shrink-0 rounded-full px-3.5 py-1.5 text-sm no-underline backdrop-blur-sm', 'bg-gold font-semibold text-stage' => ! $type, 'bg-black/25 text-paper/85 ring-1 ring-paper/20 hover:text-white' => $type])>All</a>
            @foreach (\App\Models\Event::TYPES as $key => $label)
                <a href="{{ route('events', ['type' => $key]) }}" @class(['shrink-0 rounded-full px-3.5 py-1.5 text-sm no-underline backdrop-blur-sm', 'bg-gold font-semibold text-stage' => $type === $key, 'bg-black/25 text-paper/85 ring-1 ring-paper/20 hover:text-white' => $type !== $key])>{{ $label }}</a>
            @endforeach
        </nav>
    </x-hero>

    @if ($live->isNotEmpty())
        <section class="border-b border-curtain/30 bg-curtain/5">
            <div class="container-page py-6">
                <p class="flex items-center gap-2 font-display text-lg font-semibold uppercase text-curtain"><span class="size-2.5 animate-pulse rounded-full bg-curtain"></span> Live now</p>
                <div class="mt-3 grid gap-3 md:grid-cols-2">
                    @foreach ($live as $event)
                        <a href="{{ route('events.show', $event) }}" class="card flex items-center justify-between gap-4 p-4 no-underline hover:border-curtain">
                            <span><span class="block font-semibold text-ink">{{ $event->title }}</span><span class="text-sm text-ink-soft">{{ $event->platformLabel() }} · {{ $event->organiserName() }}</span></span>
                            <span class="btn-primary btn-sm shrink-0"><x-icon name="live" class="size-4" /> Watch</span>
                        </a>
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    <section class="container-page mt-10 grid gap-10 lg:grid-cols-[1fr_320px]">
        <div>
            <h2 class="h-section">Coming up</h2>
            <div class="mt-4 grid gap-3 sm:grid-cols-2">
                @forelse ($upcoming as $event)
                    <x-event-card :event="$event" />
                @empty
                    <x-empty class="sm:col-span-2" title="Nothing on the calendar yet">New performances and programmes appear here as soon as coordinators add them.</x-empty>
                @endforelse
            </div>

            @if ($past->isNotEmpty())
                <h2 class="h-section mt-12">Recently held</h2>
                <div class="mt-4 grid gap-3 sm:grid-cols-2">
                    @foreach ($past as $event)<x-event-card :event="$event" class="opacity-90" />@endforeach
                </div>
            @endif
        </div>

        <aside class="space-y-6">
            @if ($mine->isNotEmpty())
                <div class="card-pad">
                    <h2 class="h-section">Your places</h2>
                    <ul class="mt-3 space-y-3 text-sm">
                        @foreach ($mine as $r)
                            <li><a href="{{ route('events.show', $r->event) }}" class="font-semibold text-ink">{{ $r->event->title }}</a><br><span class="text-ink-soft">{{ $r->event->starts_at->format('D j M, g:ia') }} · {{ $r->reference }}</span></li>
                        @endforeach
                    </ul>
                </div>
            @endif
            <div class="card-pad">
                <h2 class="h-section">Past broadcasts</h2>
                @forelse ($broadcasts as $event)
                    <a href="{{ route('events.show', $event) }}" class="mt-3 flex items-center gap-3 no-underline">
                        <span class="flex size-10 shrink-0 items-center justify-center rounded-full {{ $event->replay_youtube_id ? 'bg-curtain text-white' : 'bg-paper-2 text-ink-soft' }}"><x-icon name="play" class="size-4" /></span>
                        <span class="min-w-0"><span class="block truncate font-semibold text-ink">{{ $event->title }}</span><span class="text-xs text-ink-soft">{{ $event->starts_at->format('j M Y') }} · {{ $event->replay_youtube_id ? 'Replay available' : 'No replay' }}</span></span>
                    </a>
                @empty
                    <p class="mt-2 text-sm text-ink-soft">Livestreamed events will be listed here with their replays.</p>
                @endforelse
                <a href="{{ config('godram.links.youtube') }}" target="_blank" rel="noopener" class="link mt-4 inline-flex items-center gap-1 text-sm">GODRAM TV on YouTube <x-icon name="external" class="size-3.5" /></a>
            </div>
        </aside>
    </section>
</x-layouts.app>
