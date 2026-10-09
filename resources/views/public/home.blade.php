<x-layouts.app description="GODRAM CONNECT brings together the GOFAMINT Drama & Film Ministry: its members, stages, films, training and stories.">
    {{-- Hero --}}
    <x-hero page="home" size="lg" eyebrow="GOFAMINT Drama & Film Ministry">
        <x-slot:heading>
            <h1 class="hero-title mt-3 text-5xl sm:text-7xl">The Stage.<br>The Story.<br><span class="text-poster">The Mission.</span></h1>
        </x-slot:heading>
        <p class="mt-5 max-w-xl text-lg text-paper/90 [text-shadow:0_1px_8px_rgb(0_0_0/0.5)]">Since 1991, GODRAM has carried the Gospel through drama and film, from Assembly halls to the National Theatre. GODRAM CONNECT brings the whole ministry together in one place.</p>
        <div class="mt-8 flex flex-wrap gap-3">
            <a href="{{ route('about') }}" class="btn-primary no-underline">Explore GODRAM <x-icon name="arrow-right" class="size-4" /></a>
            <a href="{{ route('watch') }}" class="btn border border-paper/30 bg-black/20 text-paper no-underline backdrop-blur-sm hover:bg-white/10"><x-icon name="play" class="size-4" /> Watch GODRAM TV</a>
            @guest<a href="{{ route('register') }}" class="btn-gold no-underline">Join the movement</a>@endguest
        </div>
    </x-hero>

    {{-- Network at a glance --}}
    <section class="border-b border-line bg-white">
        <div class="container-page grid grid-cols-2 divide-line sm:grid-cols-4 sm:divide-x">
            @foreach ([['Regions', $counts['regions']], ['Districts', $counts['districts']], ['Assemblies', $counts['assemblies']], ['Active members', $counts['members']]] as [$label, $value])
                <a href="{{ route('network') }}" class="px-4 py-5 text-center no-underline">
                    <span class="block font-display text-3xl font-semibold text-stage">{{ number_format($value) }}</span>
                    <span class="text-xs font-semibold uppercase tracking-wider text-ink-soft">{{ $label }}</span>
                </a>
            @endforeach
        </div>
    </section>

    {{-- GODRAM Today --}}
    <section class="container-page mt-12">
        <p class="eyebrow">{{ now()->format('l j F') }}</p>
        <h2 class="h-section mt-1 text-2xl">GODRAM Today</h2>
        <div class="mt-5 grid gap-4 md:grid-cols-2 lg:grid-cols-4">
            @if ($live)
                <a href="{{ route('events.show', $live) }}" class="flex flex-col justify-between rounded-[var(--radius-card)] bg-curtain p-5 text-white no-underline lg:col-span-2">
                    <span class="flex items-center gap-2 text-xs font-semibold uppercase tracking-widest"><span class="size-2 animate-pulse rounded-full bg-white"></span> Live now</span>
                    <span class="mt-4 font-display text-2xl font-semibold uppercase leading-tight">{{ $live->title }}</span>
                    <span class="mt-2 text-sm text-white/80">{{ $live->platformLabel() }} · {{ $live->organiserName() }}</span>
                </a>
            @elseif ($upcoming->first())
                @php $next = $upcoming->first(); @endphp
                <a href="{{ route('events.show', $next) }}" class="flex flex-col justify-between rounded-[var(--radius-card)] bg-stage p-5 text-paper no-underline">
                    <span class="text-xs font-semibold uppercase tracking-widest text-gold">Next on the calendar</span>
                    <span class="mt-4 font-display text-xl font-semibold uppercase leading-tight">{{ $next->title }}</span>
                    <span class="mt-2 text-sm text-paper/70">{{ $next->starts_at->format('D j M, g:ia') }}<br>{{ $next->is_online ? 'Online' : $next->location }}</span>
                </a>
            @endif

            @if ($performance)
                <a href="{{ route('watch.show', $performance) }}" class="group relative flex min-h-48 flex-col justify-end overflow-hidden rounded-[var(--radius-card)] bg-stage p-5 text-paper no-underline">
                    <img src="{{ $performance->thumbnailUrl('hqdefault') }}" alt="" class="absolute inset-0 size-full object-cover opacity-50 transition group-hover:opacity-60" loading="lazy">
                    <span class="relative text-xs font-semibold uppercase tracking-widest text-gold">Performance of the Week</span>
                    <span class="relative mt-1 font-display text-xl font-semibold uppercase leading-tight">{{ $performance->title }}</span>
                </a>
            @endif

            @if ($story)
                <a href="{{ route('stories.show', $story) }}" class="card flex flex-col p-5 no-underline hover:border-ink-soft">
                    <span class="eyebrow">{{ $story->typeLabel() }}</span>
                    <span class="mt-2 font-display text-xl font-semibold uppercase leading-tight text-stage">{{ $story->title }}</span>
                    @if ($story->standfirst)<span class="mt-2 line-clamp-3 font-serif text-ink-soft">{{ $story->standfirst }}</span>@endif
                    <span class="mt-auto pt-3 text-sm font-semibold text-curtain">Read the story</span>
                </a>
            @endif

            @if ($fact)
                <div class="rounded-[var(--radius-card)] border border-gold/50 bg-gold/15 p-5">
                    <p class="flex items-center gap-2 text-xs font-semibold uppercase tracking-widest text-warn"><x-icon name="sparkles" class="size-4" /> Did you know?</p>
                    <p class="mt-3 font-serif text-lg leading-snug text-ink">{{ $fact }}</p>
                    <a href="{{ route('about') }}" class="mt-3 inline-block text-sm font-semibold text-curtain">The GODRAM story</a>
                </div>
            @endif
        </div>
    </section>

    @if ($videos->isNotEmpty())
        <section class="mt-12 bg-stage py-10 text-paper">
            <div class="container-page">
                <div class="flex items-end justify-between">
                    <div><p class="eyebrow text-gold">GODRAM TV</p><h2 class="mt-1 font-display text-2xl font-semibold uppercase">Watch next</h2></div>
                    <a href="{{ route('watch') }}" class="text-sm font-semibold text-gold no-underline">The Watch centre</a>
                </div>
                <div class="mt-5 flex gap-4 overflow-x-auto pb-2 [scrollbar-width:none]">
                    @foreach ($videos as $video)<x-video-card :video="$video" dark class="w-64 shrink-0 sm:w-72" />@endforeach
                </div>
            </div>
        </section>
    @elseif (\App\Services\YouTubeChannel::uploadsPlaylistId())
        <section class="mt-12 bg-stage py-10 text-paper">
            <div class="container-page grid items-center gap-6 lg:grid-cols-[1fr_1.4fr]">
                <div><p class="eyebrow text-gold">GODRAM TV</p><h2 class="mt-1 font-display text-3xl font-semibold uppercase">Watch GODRAM TV</h2><p class="mt-2 text-paper/70">Films, drama ministrations and broadcasts from the GODRAM TV channel.</p><a href="{{ route('watch') }}" class="btn-gold mt-5 no-underline">The Watch centre</a></div>
                <x-youtube-channel />
            </div>
        </section>
    @endif

    @if ($upcoming->isNotEmpty())
        <section class="container-page mt-12">
            <div class="flex items-end justify-between">
                <div><p class="eyebrow">Come and see</p><h2 class="h-section mt-1 text-2xl">Coming up</h2></div>
                <a href="{{ route('events') }}" class="link text-sm">All events</a>
            </div>
            <div class="mt-5 grid gap-3 md:grid-cols-3">@foreach ($upcoming as $event)<x-event-card :event="$event" />@endforeach</div>
        </section>
    @endif

    {{-- This week --}}
    <section class="container-page mt-12">
        <div class="flex items-end justify-between gap-4">
            <div>
                <p class="eyebrow">This week in GODRAM</p>
                <h2 class="mt-1 h-section text-2xl">News and announcements</h2>
            </div>
            <a href="{{ route('announcements.index') }}" class="link text-sm">All news</a>
        </div>
        @if ($announcements->isEmpty())
            <x-empty title="No news yet" class="mt-5">Announcements from National, Regional and District coordinators will appear here.</x-empty>
        @else
            <div class="mt-5 grid gap-4 md:grid-cols-3">
                @foreach ($announcements as $a)
                    <a href="{{ route('announcements.show', $a) }}" class="card group flex flex-col p-5 no-underline transition-shadow hover:shadow-lg">
                        <p class="text-xs font-semibold text-ink-soft">{{ $a->published_at->format('j M Y') }} @if($a->is_pinned)<span class="badge-info ml-1">Featured</span>@endif</p>
                        <h3 class="mt-2 font-display text-xl font-semibold uppercase leading-tight text-stage group-hover:text-curtain">{{ $a->title }}</h3>
                        <p class="mt-2 line-clamp-3 text-sm text-ink-soft">{{ $a->body }}</p>
                        <span class="mt-auto pt-4 text-sm font-semibold text-curtain">Read more</span>
                    </a>
                @endforeach
            </div>
        @endif
    </section>

    {{-- Highlights --}}
    <section class="container-page mt-14">
        <div class="flex items-end justify-between gap-4">
            <div>
                <p class="eyebrow">GODRAM in action</p>
                <h2 class="mt-1 h-section text-2xl">From the field</h2>
            </div>
            <a href="{{ route('highlights') }}" class="link text-sm">All highlights</a>
        </div>
        @if ($highlights->isEmpty())
            <x-empty title="Highlights coming soon" class="mt-5">Approved reports of performances and outreaches will be featured here.</x-empty>
        @else
            <div class="mt-5 grid gap-4 md:grid-cols-3">
                @foreach ($highlights as $r)
                    @include('public.partials.highlight-card', ['report' => $r])
                @endforeach
            </div>
        @endif
    </section>

    {{-- Academy --}}
    <section class="container-page mt-14">
        <div class="grid overflow-hidden rounded-3xl bg-stage text-paper md:grid-cols-2">
            <div class="p-8 sm:p-10">
                <p class="eyebrow text-gold">GODRAM Virtual Academy</p>
                <h2 class="mt-2 font-display text-3xl font-semibold uppercase leading-tight">Trained for the stage.<br>Sent with the message.</h2>
                <p class="mt-4 text-paper/80">Courses in Christian drama, acting, directing, scriptwriting and production, with examinations and verifiable certificates. Building on the GODRAM Institute of Christian Drama, whose first 41 drama ministers graduated in 1995.</p>
                <a href="{{ route('academy') }}" class="btn-gold mt-6 no-underline">About the Academy</a>
            </div>
            <div class="relative h-64 md:h-auto">
                <img src="{{ asset('images/gallery/osun-conference-5730.webp') }}" alt="A speaker teaching delegates at the Osun State Conference" class="absolute inset-0 h-full w-full object-cover" loading="lazy">
            </div>
        </div>
    </section>

    {{-- Throwback --}}
    <section class="mt-14">
        <div class="container-page flex items-end justify-between gap-4">
            <div>
                <p class="eyebrow">Throwback</p>
                <h2 class="mt-1 h-section text-2xl">From the GODRAM archive</h2>
            </div>
            <a href="{{ route('archive') }}" class="link text-sm">Open the archive</a>
        </div>
        <div class="container-page mt-5 flex snap-x gap-4 overflow-x-auto pb-4">
            @foreach ($archive as $item)
                <figure class="w-56 shrink-0 snap-start">
                    <img src="{{ asset('images/archive/'.$item['file']) }}" alt="{{ $item['title'] }}: {{ $item['caption'] }}" class="aspect-[3/4] w-full rounded-xl object-cover" loading="lazy">
                    <figcaption class="mt-2 text-sm"><span class="font-semibold">{{ $item['title'] }}</span>@if($item['year']) <span class="text-ink-soft">· {{ $item['year'] }}</span>@endif</figcaption>
                </figure>
            @endforeach
        </div>
    </section>

    {{-- Join --}}
    <section class="container-page mt-14">
        <div class="card-pad flex flex-col items-start gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="font-display text-2xl font-semibold uppercase text-stage">Part of a GODRAM team?</h2>
                <p class="mt-1 text-ink-soft">Join GODRAM CONNECT to keep your record, follow your Assembly and grow your gifts.</p>
            </div>
            @auth
                <a href="{{ route('dashboard') }}" class="btn-primary no-underline">Go to My GODRAM</a>
            @else
                <a href="{{ route('register') }}" class="btn-primary no-underline">Join the movement</a>
            @endauth
        </div>
    </section>
</x-layouts.app>
