<x-layouts.app :title="$video->title" :description="\Illuminate\Support\Str::limit($video->description ?: 'Watch on GODRAM TV.', 160)" :image="$video->thumbnailUrl('hqdefault')" dark>
    <section class="container-page pt-6 text-paper sm:pt-8">
        <a href="{{ route('watch') }}" class="text-sm font-semibold text-gold no-underline">Watch</a>
        <div class="mt-3 grid gap-8 lg:grid-cols-[1fr_340px]">
            <div>
                <x-youtube :id="$video->youtube_id" :title="$video->title" />
                @if ($video->status !== 'published')<p class="mt-3 rounded-xl bg-gold/20 p-3 text-sm text-gold">This video is {{ $video->status === 'submitted' ? 'waiting for the media team' : $video->status }} and is not yet in the Watch centre.</p>@endif
                <p class="eyebrow mt-5 text-gold">{{ $video->categoryLabel() }}@if ($isSpotlight) · Performance of the Week @endif</p>
                <h1 class="mt-1 font-display text-3xl font-semibold uppercase leading-tight sm:text-4xl">{{ $video->title }}</h1>
                <p class="mt-1 text-sm text-paper/60">{{ collect([$video->recorded_on?->format('Y'), $video->orgUnit?->fullName()])->filter()->implode(' · ') }}</p>
                <x-share :url="route('watch.show', $video)" :title="$video->title" text="Watch on GODRAM TV:" dark class="mt-4" />
                @if ($video->description)<div class="mt-6 max-w-3xl whitespace-pre-line leading-relaxed text-paper/85">{{ $video->description }}</div>@endif

                <div class="mt-6 flex flex-wrap gap-3">
                    @if ($video->event)<a href="{{ route('events.show', $video->event) }}" class="rounded-xl bg-white/5 p-3 text-sm text-paper no-underline ring-1 ring-white/10 hover:bg-white/10"><span class="block text-xs uppercase tracking-wider text-gold">From the event</span>{{ $video->event->title }}</a>@endif
                    @if ($video->production)<a href="{{ route('showcase.show', $video->production) }}" class="rounded-xl bg-white/5 p-3 text-sm text-paper no-underline ring-1 ring-white/10 hover:bg-white/10"><span class="block text-xs uppercase tracking-wider text-gold">The production</span>{{ $video->production->title }}</a>@endif
                    <a href="{{ $video->youtubeUrl() }}" target="_blank" rel="noopener" class="rounded-xl bg-white/5 p-3 text-sm text-paper no-underline ring-1 ring-white/10 hover:bg-white/10"><span class="block text-xs uppercase tracking-wider text-gold">Comment and like</span>Open on YouTube</a>
                </div>

                @if ($canManage)
                    <div class="mt-8 rounded-2xl bg-white/5 p-4 ring-1 ring-white/10">
                        <p class="text-xs font-semibold uppercase tracking-wider text-gold">Media team</p>
                        <div class="mt-3 flex flex-wrap gap-2">
                            <a href="{{ route('videos.edit', $video) }}" class="btn-gold btn-sm no-underline">Edit details</a>
                            @if ($video->status === 'published')
                                <form method="POST" action="{{ route('videos.decide', $video) }}">@csrf<input type="hidden" name="decision" value="spotlight"><button class="btn-sm inline-flex min-h-9 items-center rounded-full bg-white/10 px-4 text-xs font-semibold hover:bg-white/20">Make Performance of the Week</button></form>
                                <form method="POST" action="{{ route('videos.decide', $video) }}">@csrf<input type="hidden" name="decision" value="feature"><button class="btn-sm inline-flex min-h-9 items-center rounded-full bg-white/10 px-4 text-xs font-semibold hover:bg-white/20">{{ $video->is_featured ? 'Stop featuring' : 'Feature' }}</button></form>
                                <form method="POST" action="{{ route('videos.decide', $video) }}">@csrf<input type="hidden" name="decision" value="archive"><button class="btn-sm inline-flex min-h-9 items-center rounded-full bg-white/10 px-4 text-xs font-semibold hover:bg-white/20">Remove from Watch</button></form>
                            @else
                                <form method="POST" action="{{ route('videos.decide', $video) }}">@csrf<input type="hidden" name="decision" value="publish"><button class="btn-primary btn-sm">Publish</button></form>
                            @endif
                        </div>
                    </div>
                @endif
            </div>

            <aside>
                <h2 class="font-display text-lg font-semibold uppercase">Watch next</h2>
                <div class="mt-4 space-y-4">
                    @forelse ($next as $v)
                        <a href="{{ route('watch.show', $v) }}" class="group flex gap-3 text-paper no-underline">
                            <img src="{{ $v->thumbnailUrl('mqdefault') }}" alt="" class="aspect-video w-36 shrink-0 rounded-lg object-cover" loading="lazy">
                            <span><span class="line-clamp-2 text-sm font-semibold group-hover:text-gold">{{ $v->title }}</span><span class="text-xs text-paper/50">{{ $v->categoryLabel() }}</span></span>
                        </a>
                    @empty
                        <p class="text-sm text-paper/60">More videos are on the way.</p>
                    @endforelse
                </div>
            </aside>
        </div>
    </section>
</x-layouts.app>
