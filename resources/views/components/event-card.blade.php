@props(['event'])
<a href="{{ route('events.show', $event) }}" {{ $attributes->merge(['class' => 'card group flex gap-4 p-4 no-underline transition hover:border-ink-soft']) }}>
    <div class="flex w-14 shrink-0 flex-col items-center rounded-xl bg-stage py-2 text-paper">
        <span class="text-[11px] font-semibold uppercase tracking-wider text-gold">{{ $event->starts_at->format('M') }}</span>
        <span class="font-display text-2xl font-semibold leading-none">{{ $event->starts_at->format('j') }}</span>
        <span class="mt-0.5 text-[10px] text-paper/60">{{ $event->starts_at->format('D') }}</span>
    </div>
    <div class="min-w-0">
        <p class="flex flex-wrap items-center gap-1.5 text-xs font-semibold uppercase tracking-wide text-poster">
            {{ $event->typeLabel() }}
            @if ($event->isLive())<span class="badge bg-curtain text-white"><span class="size-1.5 animate-pulse rounded-full bg-white"></span> Live now</span>
            @elseif ($event->isCancelled())<span class="badge-bad">Cancelled</span>
            @elseif ($event->stream_url)<span class="badge-neutral">Streamed</span>@endif
        </p>
        <p class="mt-0.5 font-semibold leading-snug text-ink group-hover:text-curtain">{{ $event->title }}</p>
        <p class="mt-1 flex items-center gap-1 text-sm text-ink-soft"><x-icon name="clock" class="size-3.5" />{{ $event->starts_at->format('g:ia') }}
            <span class="mx-1">·</span><x-icon :name="$event->is_online ? 'globe' : 'map-pin'" class="size-3.5" /><span class="truncate">{{ $event->is_online ? 'Online' : $event->location }}</span></p>
        <p class="text-xs text-ink-soft">{{ $event->organiserName() }}</p>
    </div>
</a>
