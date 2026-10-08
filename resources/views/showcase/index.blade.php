<x-layouts.app title="Creative Showcase" description="Productions, films, posters, photography and awards from more than thirty years of GODRAM.">
    <section class="container-page mt-8 sm:mt-10">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <p class="eyebrow">Productions, films, posters and awards</p>
                <h1 class="h-page mt-1">Creative Showcase</h1>
                <p class="mt-2 max-w-2xl font-serif text-lg text-ink-soft">More than thirty years of drama ministry, from Assembly halls to the National Theatre.</p>
            </div>
            @if ($canManage)<a href="{{ route('showcase.create') }}" class="btn-primary btn-sm no-underline"><x-icon name="plus" class="size-4" /> Add to the showcase</a>@endif
        </div>
        <nav class="mt-6 flex gap-2 overflow-x-auto pb-1 [scrollbar-width:none]" aria-label="Kinds">
            <a href="{{ route('showcase') }}" @class(['shrink-0 rounded-full px-3.5 py-1.5 text-sm no-underline', 'bg-stage font-semibold text-paper' => ! $kind, 'border border-line bg-white text-ink' => $kind])>Everything</a>
            @foreach (\App\Models\Production::KINDS as $key => $label)
                @if ($kinds[$key] ?? false)
                    <a href="{{ route('showcase', ['kind' => $key]) }}" @class(['shrink-0 rounded-full px-3.5 py-1.5 text-sm no-underline', 'bg-stage font-semibold text-paper' => $kind === $key, 'border border-line bg-white text-ink' => $kind !== $key])>{{ $label }}</a>
                @endif
            @endforeach
        </nav>
    </section>

    @if ($lead)
        <section class="container-page mt-8">
            <a href="{{ route('showcase.show', $lead) }}" class="group grid overflow-hidden rounded-3xl bg-stage text-paper no-underline lg:grid-cols-[1.1fr_1fr]">
                <div class="relative aspect-[4/3] overflow-hidden lg:aspect-auto lg:min-h-[28rem]">
                    @if ($lead->coverUrl())<img src="{{ $lead->coverUrl() }}" alt="" class="absolute inset-0 size-full object-cover transition duration-700 group-hover:scale-[1.02]">@endif
                </div>
                <div class="flex flex-col justify-center p-8 sm:p-12">
                    <p class="eyebrow text-gold">{{ isset($lead->spotlight_note) ? 'Creative Spotlight' : $lead->kindLabel() }}{{ $lead->year ? ' · '.$lead->year : '' }}</p>
                    <h2 class="mt-3 font-display text-4xl font-semibold uppercase leading-none sm:text-6xl">{{ $lead->title }}</h2>
                    @if ($lead->spotlight_note ?? $lead->summary)<p class="mt-5 font-serif text-xl leading-relaxed text-paper/85">{{ $lead->spotlight_note ?? $lead->summary }}</p>@endif
                    <span class="mt-8 inline-flex items-center gap-2 font-semibold text-gold">See the production <x-icon name="arrow-right" class="size-4" /></span>
                </div>
            </a>
        </section>
    @endif

    <section class="container-page mt-12">
        @if ($items->isNotEmpty())
            <div class="columns-1 gap-6 sm:columns-2 lg:columns-3 [&>*]:mb-6 [&>*]:break-inside-avoid">
                @foreach ($items as $item)
                    <a href="{{ route('showcase.show', $item) }}" class="group card block overflow-hidden no-underline">
                        @if ($item->coverUrl())<img src="{{ $item->coverUrl() }}" alt="" class="w-full object-cover transition duration-500 group-hover:opacity-90" loading="lazy">@endif
                        <div class="p-5">
                            <p class="eyebrow">{{ $item->kindLabel() }}{{ $item->year ? ' · '.$item->year : '' }}</p>
                            <p class="mt-1 font-display text-2xl font-semibold uppercase leading-tight text-stage group-hover:text-curtain">{{ $item->title }}</p>
                            @if ($item->summary)<p class="mt-2 font-serif text-ink-soft">{{ $item->summary }}</p>@endif
                        </div>
                    </a>
                @endforeach
            </div>
        @elseif (! $lead)
            <x-empty title="The showcase is being prepared">Productions, films and awards will be added by the national media team.</x-empty>
        @endif
    </section>

    @unless ($kind)
        <section class="container-page mt-12">
            <div class="flex items-end justify-between">
                <div><p class="eyebrow">Throwback</p><h2 class="h-section mt-1 text-2xl">From the GODRAM archive</h2></div>
                <a href="{{ route('archive') }}" class="link text-sm">The whole archive</a>
            </div>
            <div class="mt-5 flex gap-4 overflow-x-auto pb-2 [scrollbar-width:none]">
                @foreach ($archive as $item)
                    <a href="{{ route('archive') }}#{{ pathinfo($item['file'], PATHINFO_FILENAME) }}" class="w-44 shrink-0 no-underline">
                        <img src="{{ asset('images/archive/'.$item['file']) }}" alt="{{ $item['title'] }}" class="aspect-[3/4] w-full rounded-xl object-cover" loading="lazy">
                        <p class="mt-2 text-sm font-semibold text-ink">{{ $item['title'] }}</p>
                        @if ($item['year'])<p class="text-xs text-ink-soft">{{ $item['year'] }}</p>@endif
                    </a>
                @endforeach
            </div>
        </section>
    @endunless
</x-layouts.app>
