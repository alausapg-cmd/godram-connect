<x-layouts.app title="News">
    <section class="container-page mt-10 max-w-3xl">
        <div class="flex items-end justify-between gap-3">
            <div>
                <p class="eyebrow">Communicate</p>
                <h1 class="h-page">News and announcements</h1>
                @auth<p class="mt-1 text-sm text-ink-soft">Including announcements for your Assembly, District, Region and roles.</p>@endauth
            </div>
            @if ($canCreate)<a href="{{ route('announcements.create') }}" class="btn-primary btn-sm shrink-0 no-underline"><x-icon name="plus" class="size-4" />Announce</a>@endif
        </div>
        @forelse ($announcements as $a)
            <a href="{{ route('announcements.show', $a) }}" class="card mt-4 block p-5 no-underline transition-shadow hover:shadow-lg">
                <div class="flex flex-wrap items-center gap-2 text-xs text-ink-soft">
                    <span>{{ $a->published_at->format('j M Y') }}</span>
                    @if ($a->is_pinned)<span class="badge-info"><x-icon name="pin" class="size-3" />Featured</span>@endif
                    @if ($a->is_mandatory)<span class="badge-bad">Required</span>@endif
                    @auth<span>· For {{ $a->audienceLabel() }}</span>@endauth
                </div>
                <h2 class="mt-2 font-display text-2xl font-semibold uppercase leading-tight text-stage">{{ $a->title }}</h2>
                <p class="mt-2 line-clamp-3 text-ink-soft">{{ $a->body }}</p>
            </a>
        @empty
            <x-empty title="No news yet" class="mt-6">Announcements will appear here as soon as they are published.</x-empty>
        @endforelse
        <div class="mt-6">{{ $announcements->links() }}</div>
    </section>
</x-layouts.app>
