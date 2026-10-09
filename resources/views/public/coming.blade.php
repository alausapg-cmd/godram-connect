<x-layouts.app :title="$title">
    <x-hero page="coming" size="sm" :eyebrow="$eyebrow" :title="$title" :lead="$lead" />
    <section class="container-page mt-10">
        <x-empty title="Opening soon">
            <p>{{ $body }}</p>
            <a href="{{ $cta['url'] }}" class="btn-primary mt-5 no-underline" @if(str_starts_with($cta['url'], 'http') && ! str_starts_with($cta['url'], config('app.url'))) target="_blank" rel="noopener" @endif>{{ $cta['label'] }}</a>
        </x-empty>
    </section>
</x-layouts.app>
