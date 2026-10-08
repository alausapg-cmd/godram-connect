@props(['title', 'icon' => 'sparkles'])
<div {{ $attributes->merge(['class' => 'rounded-[var(--radius-card)] border border-dashed border-line bg-white/60 px-6 py-10 text-center']) }}>
    <p class="font-display text-lg font-semibold uppercase text-stage">{{ $title }}</p>
    <div class="mx-auto mt-2 max-w-md text-sm text-ink-soft">{{ $slot }}</div>
</div>
