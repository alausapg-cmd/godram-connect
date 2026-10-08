<x-layouts.app :title="$production->title" :description="$production->summary ?? $production->kindLabel().' from the GODRAM Creative Showcase.'"
               :image="$production->status === 'published' ? route('share.card', ['showcase', $production->slug]) : null">
    <article>
        <header class="relative bg-stage text-paper">
            <div class="container-page grid gap-8 py-10 lg:grid-cols-[1fr_1.1fr] lg:items-center lg:py-14">
                <div>
                    <a href="{{ route('showcase') }}" class="text-sm font-semibold text-gold no-underline">Creative Showcase</a>
                    <p class="eyebrow mt-5 text-gold">{{ $production->kindLabel() }}{{ $production->year ? ' · '.$production->year : '' }}</p>
                    <h1 class="mt-2 font-display text-4xl font-semibold uppercase leading-none sm:text-6xl">{{ $production->title }}</h1>
                    @if ($production->summary)<p class="mt-5 font-serif text-xl leading-relaxed text-paper/85">{{ $production->summary }}</p>@endif
                    @if ($production->orgUnit)<p class="mt-4 text-sm text-paper/60">{{ $production->orgUnit->fullName() }}</p>@endif
                    <x-share :url="route('showcase.show', $production)" :title="$production->title" dark class="mt-6" />
                    @if ($production->status !== 'published')<p class="mt-4 inline-block rounded-full bg-gold/20 px-3 py-1 text-sm text-gold">{{ ucfirst($production->status) }}: only the media team can see this.</p>@endif
                </div>
                @if ($production->coverUrl())
                    <img src="{{ $production->coverUrl() }}" alt="{{ $production->title }}" class="max-h-[34rem] w-full rounded-2xl object-contain">
                @endif
            </div>
        </header>

        <div class="container-page mt-10 grid gap-10 lg:grid-cols-[1fr_320px]">
            <div class="space-y-10">
                @if ($production->body)
                    <div class="prose-godram max-w-2xl font-serif text-lg leading-relaxed">{!! \Illuminate\Support\Str::markdown($production->body, ['html_input' => 'strip', 'allow_unsafe_links' => false]) !!}</div>
                @endif
                @foreach ($production->videos as $video)
                    <div><x-youtube :id="$video->youtube_id" :title="$video->title" /><p class="mt-2 text-sm text-ink-soft">{{ $video->title }}</p></div>
                @endforeach
                @if ($production->images->isNotEmpty())
                    <div x-data="{ open: null }">
                        <h2 class="h-section">Gallery</h2>
                        <div class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-3">
                            @foreach ($production->images as $i => $image)
                                <button type="button" @click="open = {{ $i }}" class="overflow-hidden rounded-xl"><img src="{{ $image->url() }}" alt="{{ $image->caption ?? $production->title }}" class="aspect-square w-full object-cover transition hover:scale-[1.03]" loading="lazy"></button>
                            @endforeach
                        </div>
                        <div x-cloak x-show="open !== null" @keydown.escape.window="open = null" @click.self="open = null" class="fixed inset-0 z-50 flex items-center justify-center bg-black/90 p-4">
                            @foreach ($production->images as $i => $image)
                                <figure x-show="open === {{ $i }}" class="max-h-full"><img src="{{ $image->url() }}" alt="" class="max-h-[85vh] rounded-lg">@if ($image->caption)<figcaption class="mt-2 text-center text-sm text-paper/80">{{ $image->caption }}</figcaption>@endif</figure>
                            @endforeach
                            <button type="button" @click="open = null" class="absolute right-4 top-4 rounded-full bg-white/10 p-2 text-white"><x-icon name="x" /><span class="sr-only">Close</span></button>
                        </div>
                    </div>
                @endif
                @if ($production->stories->isNotEmpty())
                    <div>
                        <h2 class="h-section">Stories</h2>
                        <div class="mt-4 grid gap-6 sm:grid-cols-2">@foreach ($production->stories as $story)<x-story-card :story="$story" />@endforeach</div>
                    </div>
                @endif
            </div>
            <aside class="space-y-6">
                @if ($production->credits)
                    <div class="card-pad"><h2 class="h-section">Credits</h2><div class="mt-3 whitespace-pre-line text-sm leading-relaxed">{{ $production->credits }}</div></div>
                @endif
                @if ($production->events->isNotEmpty())
                    <div><h2 class="h-section">Performances</h2><div class="mt-3 space-y-3">@foreach ($production->events as $event)<x-event-card :event="$event" />@endforeach</div></div>
                @endif
                @if ($canManage)
                    <div class="card-pad space-y-2">
                        <h2 class="h-section">Media team</h2>
                        <a href="{{ route('showcase.edit', $production) }}" class="btn-ghost btn-sm no-underline"><x-icon name="edit" class="size-4" /> Edit</a>
                        @if ($production->status === 'published')
                            <form method="POST" action="{{ route('showcase.spotlight', $production) }}">@csrf<button class="btn-gold btn-sm"><x-icon name="star" class="size-4" /> Make Creative Spotlight</button></form>
                        @endif
                    </div>
                @endif
            </aside>
        </div>

        @if ($more->isNotEmpty())
            <section class="container-page mt-14">
                <h2 class="h-section">More from the showcase</h2>
                <div class="mt-4 grid gap-6 sm:grid-cols-3">
                    @foreach ($more as $item)
                        <a href="{{ route('showcase.show', $item) }}" class="group no-underline">
                            @if ($item->coverUrl())<img src="{{ $item->coverUrl() }}" alt="" class="aspect-[4/3] w-full rounded-xl object-cover" loading="lazy">@endif
                            <p class="eyebrow mt-3">{{ $item->kindLabel() }}</p>
                            <p class="font-display text-xl font-semibold uppercase text-stage group-hover:text-curtain">{{ $item->title }}</p>
                        </a>
                    @endforeach
                </div>
            </section>
        @endif
    </article>
</x-layouts.app>
