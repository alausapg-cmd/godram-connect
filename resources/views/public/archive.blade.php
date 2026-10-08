<x-layouts.app title="Archive" description="Posters, programmes and photographs from GODRAM's history.">
    <section class="container-page mt-10">
        <p class="eyebrow">Throwback</p>
        <h1 class="h-page mt-1">From the archive</h1>
        <p class="mt-2 max-w-2xl text-ink-soft">Crusade posters, training programmes and photographs from GODRAM's journey. Have something to add? Send it to the national office so it can be preserved here.</p>
        <form method="GET" action="{{ route('search') }}" class="mt-6 flex max-w-xl gap-2">
            <input type="hidden" name="in" value="">
            <label for="archive-q" class="sr-only">Search the archive</label>
            <input id="archive-q" name="q" type="search" class="input" placeholder="Search past events, productions, stories and reports">
            <input name="year" type="number" min="1985" max="{{ now()->year }}" class="input w-28" placeholder="Year" aria-label="Year">
            <button class="btn-dark shrink-0"><x-icon name="search" class="size-4" /><span class="sr-only">Search</span></button>
        </form>
        <div class="mt-8 columns-2 gap-4 sm:columns-3 lg:columns-4">
            @foreach ($items as $item)
                <figure id="{{ pathinfo($item['file'], PATHINFO_FILENAME) }}" class="mb-4 break-inside-avoid scroll-mt-20">
                    <a href="{{ asset('images/archive/'.$item['file']) }}" target="_blank" rel="noopener">
                        <img src="{{ asset('images/archive/'.$item['file']) }}" alt="{{ $item['title'] }}: {{ $item['caption'] }}" class="w-full rounded-xl" loading="lazy">
                    </a>
                    <figcaption class="mt-2 text-sm"><span class="font-semibold">{{ $item['title'] }}</span>@if($item['year']) · {{ $item['year'] }}@endif<span class="block text-xs text-ink-soft">{{ $item['caption'] }}</span></figcaption>
                </figure>
            @endforeach
        </div>
    </section>
</x-layouts.app>
