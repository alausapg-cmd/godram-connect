<x-layouts.app :title="$title">
    <section class="bg-stage text-paper">
        <div class="container-page py-16 sm:py-20">
            <p class="eyebrow text-gold">{{ $eyebrow }}</p>
            <h1 class="mt-2 font-display text-4xl font-bold uppercase sm:text-6xl">{{ $title }}</h1>
            <p class="mt-4 max-w-2xl text-lg text-paper/85">{{ $lead }}</p>
        </div>
    </section>
    <section class="container-page mt-10">
        <x-empty title="Opening soon">
            <p>{{ $body }}</p>
            <a href="{{ $cta['url'] }}" class="btn-primary mt-5 no-underline" @if(str_starts_with($cta['url'], 'http') && ! str_starts_with($cta['url'], config('app.url'))) target="_blank" rel="noopener" @endif>{{ $cta['label'] }}</a>
        </x-empty>
    </section>
</x-layouts.app>
