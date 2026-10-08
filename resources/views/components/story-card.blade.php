@props(['story', 'lead' => false])
<a href="{{ route('stories.show', $story) }}" {{ $attributes->merge(['class' => 'group block no-underline']) }}>
    <div @class(['relative overflow-hidden rounded-2xl bg-stage', 'aspect-[16/10]' => ! $lead, 'aspect-[16/9] lg:aspect-[21/9]' => $lead])>
        @if ($story->coverUrl())
            <img src="{{ $story->coverUrl() }}" alt="" class="size-full object-cover transition duration-500 group-hover:scale-[1.03]" loading="lazy">
        @else
            <div class="poster-stripe absolute inset-x-0 bottom-0 h-2"></div>
            <x-icon name="quote" class="absolute left-6 top-6 size-12 text-gold/40" />
        @endif
        @if ($lead)
            <div class="absolute inset-0 bg-gradient-to-t from-black/85 via-black/30 to-transparent"></div>
            <div class="absolute inset-x-0 bottom-0 p-6 sm:p-10">
                <p class="eyebrow text-gold">{{ $story->typeLabel() }}</p>
                <h2 class="mt-2 max-w-3xl font-display text-3xl font-semibold uppercase leading-tight text-white sm:text-5xl">{{ $story->title }}</h2>
                @if ($story->standfirst)<p class="mt-3 max-w-2xl font-serif text-lg text-paper/85">{{ $story->standfirst }}</p>@endif
            </div>
        @endif
    </div>
    @unless ($lead)
        <p class="eyebrow mt-3">{{ $story->typeLabel() }}</p>
        <p class="mt-1 font-display text-xl font-semibold uppercase leading-tight text-stage group-hover:text-curtain">{{ $story->title }}</p>
        @if ($story->standfirst)<p class="mt-1.5 line-clamp-2 font-serif text-ink-soft">{{ $story->standfirst }}</p>@endif
    @endunless
</a>
