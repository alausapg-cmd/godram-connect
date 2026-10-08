@php
    $lead = $stories->onFirstPage() && ! $type ? $stories->first() : null;
    $rest = $lead ? $stories->slice(1) : $stories;
@endphp
<x-layouts.app title="GODRAM Stories" description="Testimonies, impact stories and behind-the-scenes accounts from the GOFAMINT Drama & Film Ministry.">
    <section class="container-page mt-8 sm:mt-10">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <p class="eyebrow">Testimonies, impact and the journey</p>
                <h1 class="h-page mt-1">GODRAM Stories</h1>
            </div>
            <a href="{{ auth()->check() ? route('stories.create') : route('login') }}" class="btn-primary btn-sm no-underline"><x-icon name="edit" class="size-4" /> Share your story</a>
        </div>
        <nav class="mt-6 flex gap-2 overflow-x-auto pb-1 [scrollbar-width:none]" aria-label="Story types">
            <a href="{{ route('stories') }}" @class(['shrink-0 rounded-full px-3.5 py-1.5 text-sm no-underline', 'bg-stage font-semibold text-paper' => ! $type, 'border border-line bg-white text-ink' => $type])>All stories</a>
            @foreach (\App\Models\Story::TYPES as $key => $label)
                @if ($types[$key] ?? false)
                    <a href="{{ route('stories', ['type' => $key]) }}" @class(['shrink-0 rounded-full px-3.5 py-1.5 text-sm no-underline', 'bg-stage font-semibold text-paper' => $type === $key, 'border border-line bg-white text-ink' => $type !== $key])>{{ $label }}</a>
                @endif
            @endforeach
        </nav>
    </section>

    @if ($lead)
        <section class="container-page mt-8"><x-story-card :story="$lead" lead /></section>
    @endif

    <section class="container-page mt-10">
        @if ($rest->isNotEmpty())
            <div class="grid gap-x-6 gap-y-10 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($rest as $story)<x-story-card :story="$story" />@endforeach
            </div>
            <div class="mt-10">{{ $stories->links() }}</div>
        @elseif (! $lead)
            <x-empty title="No stories here yet">Every GODRAM member has a story. Be the first to share yours.</x-empty>
        @endif
    </section>
</x-layouts.app>
