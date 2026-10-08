<x-layouts.app title="Search" description="Search GODRAM events, videos, stories, productions and the archive.">
    <section class="container-page mt-8 max-w-4xl">
        <h1 class="h-page">Search</h1>
        <form method="GET" action="{{ route('search') }}" class="mt-4">
            <div class="flex gap-2">
                <label for="q" class="sr-only">Search for</label>
                <input id="q" name="q" type="search" value="{{ $term }}" class="input text-lg" placeholder="A production, an event, a name, a year…" autofocus>
                <button class="btn-primary shrink-0"><x-icon name="search" class="size-4" /><span class="hidden sm:inline">Search</span></button>
            </div>
            <div class="mt-3 flex flex-wrap items-center gap-2 text-sm">
                <select name="in" class="input min-h-9 w-auto py-1 text-sm" aria-label="Look in" onchange="this.form.submit()">
                    <option value="">Everywhere</option>
                    @foreach (\App\Http\Controllers\SearchController::SECTIONS as $key => $label)
                        @if (! in_array($key, ['members', 'reports']) || auth()->check())<option value="{{ $key }}" @selected($only === $key)>{{ $label }}</option>@endif
                    @endforeach
                </select>
                <input name="year" type="number" min="1985" max="{{ now()->year }}" value="{{ $year }}" class="input min-h-9 w-28 py-1 text-sm" placeholder="Year" aria-label="Year">
                @auth<span class="text-ink-soft">Members and reports are searched only within your own area.</span>@endauth
            </div>
        </form>

        @if ($term !== '' || $year)
            <p class="mt-8 text-sm text-ink-soft">{{ $total }} {{ \Illuminate\Support\Str::plural('result', $total) }}{{ $term !== '' ? ' for "'.$term.'"' : '' }}{{ $year ? ' in '.$year : '' }}</p>
            <div class="mt-4 space-y-8">
                @forelse ($results as $section => $rows)
                    <div>
                        <h2 class="h-section">{{ \App\Http\Controllers\SearchController::SECTIONS[$section] }}</h2>
                        <ul class="mt-3 divide-y divide-line rounded-[var(--radius-card)] border border-line bg-white">
                            @foreach ($rows as $row)
                                <li><a href="{{ $row->url }}" class="flex items-center gap-3 px-4 py-3 no-underline hover:bg-paper">
                                    <span class="flex size-9 shrink-0 items-center justify-center rounded-full bg-paper-2 text-poster"><x-icon :name="$row->icon" class="size-4" /></span>
                                    <span class="min-w-0"><span class="block truncate font-semibold text-ink">{{ $row->title }}</span>@if ($row->meta)<span class="block truncate text-sm text-ink-soft">{{ $row->meta }}</span>@endif</span>
                                </a></li>
                            @endforeach
                        </ul>
                    </div>
                @empty
                    <x-empty title="Nothing found">Try fewer words, another spelling, or search without a year.</x-empty>
                @endforelse
            </div>
        @else
            <div class="mt-10">
                <p class="eyebrow">Try</p>
                <div class="mt-3 flex flex-wrap gap-2">
                    @foreach (['Majemu', 'GACASA', 'Valley of Baca', 'Mushin', 'training', '1995'] as $suggestion)
                        <a href="{{ route('search', is_numeric($suggestion) ? ['year' => $suggestion] : ['q' => $suggestion]) }}" class="rounded-full border border-line bg-white px-3.5 py-1.5 text-sm no-underline hover:border-ink-soft">{{ $suggestion }}</a>
                    @endforeach
                </div>
            </div>
        @endif
    </section>
</x-layouts.app>
