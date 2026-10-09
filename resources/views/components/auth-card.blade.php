@props(['title', 'lead' => null])
<div class="relative overflow-hidden bg-stage">
    <img src="{{ asset('images/archive/godram-11.webp') }}" alt="" class="absolute inset-0 h-full w-full object-cover opacity-25" loading="lazy">
    <div class="absolute inset-0 bg-gradient-to-b from-stage/40 to-stage"></div>
    <div class="container-page relative flex min-h-[calc(100vh-4rem)] items-start justify-center py-10 sm:items-center">
        <div class="w-full max-w-md rounded-3xl bg-paper p-6 shadow-2xl sm:p-8">
            <x-logo class="mb-3 size-20" />
            <p class="eyebrow">GODRAM CONNECT</p>
            <h1 class="mt-1 font-display text-3xl font-semibold uppercase text-stage">{{ $title }}</h1>
            @if ($lead)<p class="mt-2 text-sm text-ink-soft">{{ $lead }}</p>@endif
            <div class="mt-6">{{ $slot }}</div>
        </div>
    </div>
</div>
