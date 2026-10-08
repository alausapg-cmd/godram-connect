@props(['label', 'value', 'note' => null])
<div class="card-pad">
    <p class="text-xs font-semibold uppercase tracking-wide text-ink-soft">{{ $label }}</p>
    <p class="mt-1 font-display text-3xl font-semibold text-stage">{{ is_numeric($value) ? number_format($value) : $value }}</p>
    @if ($note)<p class="mt-0.5 text-xs text-ink-soft">{{ $note }}</p>@endif
</div>
