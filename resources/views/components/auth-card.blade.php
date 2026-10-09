@props(['title', 'lead' => null])
<x-hero page="auth" size="full" centred>
    <div class="rounded-3xl bg-paper p-6 text-ink shadow-2xl sm:p-8">
        <x-logo class="mb-3 size-20" />
        <p class="eyebrow">GODRAM CONNECT</p>
        <h1 class="mt-1 font-display text-3xl font-semibold uppercase text-stage">{{ $title }}</h1>
        @if ($lead)<p class="mt-2 text-sm text-ink-soft">{{ $lead }}</p>@endif
        <div class="mt-6">{{ $slot }}</div>
    </div>
</x-hero>
