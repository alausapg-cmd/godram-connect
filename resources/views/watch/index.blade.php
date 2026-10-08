<x-layouts.app title="Watch" description="GODRAM TV: films, drama performances, live broadcasts and behind-the-scenes stories." dark>
    <section class="text-paper">
        <div class="container-page pt-8 sm:pt-10">
            <div class="flex flex-wrap items-end justify-between gap-4">
                <div>
                    <p class="eyebrow text-gold">GODRAM TV</p>
                    <h1 class="mt-1 font-display text-4xl font-semibold uppercase sm:text-5xl">Watch</h1>
                </div>
                <div class="flex gap-2">
                    @if ($canSubmit)<a href="{{ route('videos.create') }}" class="btn-sm inline-flex min-h-9 items-center gap-1.5 rounded-full bg-white/10 px-4 text-xs font-semibold text-paper no-underline hover:bg-white/20"><x-icon name="plus" class="size-4" /> {{ $canManage ? 'Add a video' : 'Suggest a video' }}</a>@endif
                    <a href="{{ config('godram.links.youtube') }}" target="_blank" rel="noopener" class="btn-primary btn-sm no-underline">Subscribe on YouTube</a>
                </div>
            </div>

            @foreach ($live as $event)
                <a href="{{ route('events.show', $event) }}" class="mt-6 flex items-center justify-between gap-4 rounded-2xl bg-curtain p-4 text-white no-underline sm:p-5">
                    <span class="flex items-center gap-3"><span class="relative flex size-3"><span class="absolute inline-flex size-full animate-ping rounded-full bg-white opacity-60"></span><span class="relative inline-flex size-3 rounded-full bg-white"></span></span>
                        <span><span class="block text-xs font-semibold uppercase tracking-widest">Live now</span><span class="font-display text-xl font-semibold uppercase">{{ $event->title }}</span></span></span>
                    <span class="btn-sm inline-flex min-h-9 items-center rounded-full bg-white px-4 text-xs font-semibold text-curtain">Watch</span>
                </a>
            @endforeach

            @if ($featured)
                <div class="mt-8 grid gap-6 lg:grid-cols-[1.6fr_1fr] lg:items-center">
                    <x-youtube :id="$featured->youtube_id" :title="$featured->title" />
                    <div>
                        <p class="eyebrow text-gold">{{ isset($featured->spotlight_note) ? 'Performance of the Week' : 'Featured' }}</p>
                        <h2 class="mt-2 font-display text-3xl font-semibold uppercase leading-tight">{{ $featured->title }}</h2>
                        <p class="mt-1 text-sm text-paper/60">{{ $featured->categoryLabel() }}{{ $featured->recorded_on ? ' · '.$featured->recorded_on->format('Y') : '' }}</p>
                        @if ($featured->spotlight_note ?? $featured->description)<p class="mt-4 line-clamp-4 text-paper/80">{{ $featured->spotlight_note ?? $featured->description }}</p>@endif
                        <a href="{{ route('watch.show', $featured) }}" class="btn-gold btn-sm mt-5 no-underline">Details and sharing</a>
                    </div>
                </div>
            @endif

            <nav class="mt-10 flex gap-2 overflow-x-auto pb-1 [scrollbar-width:none]" aria-label="Categories">
                <a href="{{ route('watch') }}" @class(['shrink-0 rounded-full px-3.5 py-1.5 text-sm no-underline', 'bg-gold font-semibold text-stage' => ! $category, 'bg-white/10 text-paper/80 hover:text-white' => $category])>Everything</a>
                @foreach (\App\Models\Video::CATEGORIES as $key => $label)
                    @if ($counts[$key] ?? false)
                        <a href="{{ route('watch', ['category' => $key]) }}" @class(['shrink-0 rounded-full px-3.5 py-1.5 text-sm no-underline', 'bg-gold font-semibold text-stage' => $category === $key, 'bg-white/10 text-paper/80 hover:text-white' => $category !== $key])>{{ $label }}</a>
                    @endif
                @endforeach
            </nav>
        </div>

        <div class="container-page mt-8 space-y-12 pb-6">
            @if ($upcomingLive->isNotEmpty() && ! $category)
                <div>
                    <h2 class="font-display text-xl font-semibold uppercase">Upcoming live</h2>
                    <div class="mt-4 flex gap-4 overflow-x-auto pb-2 [scrollbar-width:none]">
                        @foreach ($upcomingLive as $event)
                            <a href="{{ route('events.show', $event) }}" class="w-72 shrink-0 rounded-2xl bg-white/5 p-4 text-paper no-underline ring-1 ring-white/10 hover:bg-white/10">
                                <p class="text-xs font-semibold uppercase tracking-wider text-gold">{{ $event->starts_at->format('D j M · g:ia') }}</p>
                                <p class="mt-1 font-semibold">{{ $event->title }}</p>
                                <p class="mt-1 text-sm text-paper/60">{{ $event->platformLabel() }} · {{ $event->organiserName() }}</p>
                            </a>
                        @endforeach
                    </div>
                </div>
            @endif

            @forelse ($rows as $key => $videos)
                <div>
                    <div class="flex items-end justify-between">
                        <h2 class="font-display text-xl font-semibold uppercase">{{ \App\Models\Video::CATEGORIES[$key] ?? $key }}</h2>
                        @unless ($category)<a href="{{ route('watch', ['category' => $key]) }}" class="text-sm font-semibold text-gold no-underline">See all</a>@endunless
                    </div>
                    <div @class(['mt-4', 'grid gap-6 sm:grid-cols-2 lg:grid-cols-4' => $category, 'flex gap-4 overflow-x-auto pb-2 [scrollbar-width:none]' => ! $category])>
                        @foreach ($category ? $videos : $videos->take(10) as $video)
                            <x-video-card :video="$video" dark class="{{ $category ? '' : 'w-64 shrink-0 sm:w-72' }}" />
                        @endforeach
                    </div>
                </div>
            @empty
                <div class="rounded-3xl bg-white/5 px-6 py-14 text-center ring-1 ring-white/10">
                    <p class="font-display text-2xl font-semibold uppercase">The GODRAM TV library is being stocked</p>
                    <p class="mx-auto mt-2 max-w-md text-paper/70">Films, drama performances and live broadcasts will appear here. Until then, every GODRAM TV video is on YouTube.</p>
                    <a href="{{ config('godram.links.youtube') }}" target="_blank" rel="noopener" class="btn-gold mt-6 no-underline">Watch GODRAM TV on YouTube</a>
                </div>
            @endforelse

            @if ($replays->isNotEmpty() && ! $category)
                <div>
                    <h2 class="font-display text-xl font-semibold uppercase">Past broadcasts</h2>
                    <div class="mt-4 grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
                        @foreach ($replays as $event)
                            <a href="{{ route('events.show', $event) }}" class="group block text-paper no-underline">
                                <div class="relative aspect-video overflow-hidden rounded-xl bg-stage-2"><img src="https://i.ytimg.com/vi/{{ $event->replay_youtube_id }}/mqdefault.jpg" alt="" class="size-full object-cover" loading="lazy"><span class="absolute bottom-2 left-2 rounded-full bg-black/70 px-2 py-0.5 text-[11px] font-semibold">Replay</span></div>
                                <p class="mt-2 font-semibold group-hover:text-gold">{{ $event->title }}</p>
                                <p class="text-xs text-paper/50">{{ $event->starts_at->format('j M Y') }}</p>
                            </a>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>
    </section>
</x-layouts.app>
