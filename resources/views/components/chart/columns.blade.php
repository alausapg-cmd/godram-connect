@props(['series', 'label', 'suffix' => ''])
{{-- One series of monthly columns. The peak and the latest month carry labels; hovering or focusing any column shows its value, and a table is one tap away. --}}
@php
    $values = array_column($series, 'value');
    $max = max(1, ...$values);
    $peak = array_search(max($values), $values);
    $last = count($series) - 1;
    $every = count($series) > 12 ? 2 : 1;
@endphp
<figure x-data="{ tip: null }" {{ $attributes->merge(['class' => 'relative']) }}>
    <div class="relative flex h-40 items-end gap-0.5 border-b border-line" role="img" aria-label="{{ $label }}: {{ collect($series)->map(fn ($p) => $p['title'].' '.$p['value'])->join(', ') }}">
        @foreach ($series as $i => $p)
            @php $h = round(100 * $p['value'] / $max, 1); @endphp
            <div class="relative flex h-full flex-1 items-end justify-center outline-none" tabindex="0"
                 @mouseenter="tip = {{ $i }}" @mouseleave="tip = null" @focus="tip = {{ $i }}" @blur="tip = null">
                @if (($i === $peak || $i === $last) && $p['value'] > 0)
                    <span class="pointer-events-none absolute left-1/2 -translate-x-1/2 text-[11px] font-semibold text-ink tabular-nums" style="bottom: calc({{ $h }}% + 3px)">{{ number_format($p['value']) }}</span>
                @endif
                <div class="w-full max-w-6 rounded-t-[4px] bg-curtain transition-colors" :class="tip === {{ $i }} && 'bg-curtain-dark'" style="height: {{ $h }}%; {{ $p['value'] > 0 ? 'min-height: 2px;' : '' }}"></div>
                <div x-cloak x-show="tip === {{ $i }}" class="pointer-events-none absolute bottom-full z-10 mb-1 whitespace-nowrap rounded-lg bg-stage px-2.5 py-1.5 text-xs text-paper shadow-lg">
                    <span class="block text-paper/70">{{ $p['title'] }}</span><span class="font-semibold tabular-nums">{{ number_format($p['value']) }}{{ $suffix }}</span>
                </div>
            </div>
        @endforeach
    </div>
    <div class="mt-1 flex gap-0.5 text-center text-[10px] text-ink-soft" aria-hidden="true">
        @foreach ($series as $i => $p)<span class="flex-1 truncate">{{ $i % $every === 0 || $i === $last ? $p['label'] : '' }}</span>@endforeach
    </div>
    <details class="mt-2 text-xs">
        <summary class="cursor-pointer text-ink-soft">Show as a table</summary>
        <table class="table mt-2"><thead><tr><th>Month</th><th class="text-right">{{ $label }}</th></tr></thead>
            <tbody>@foreach ($series as $p)<tr><td>{{ $p['title'] }}</td><td class="text-right tabular-nums">{{ number_format($p['value']) }}{{ $suffix }}</td></tr>@endforeach</tbody>
        </table>
    </details>
</figure>
