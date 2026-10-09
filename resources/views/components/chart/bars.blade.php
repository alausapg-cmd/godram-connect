@props(['rows', 'empty' => 'Nothing recorded yet.'])
{{-- Ranked horizontal bars, one series. Each value sits at the end of its bar. --}}
@php $max = max(1, ...array_map(fn ($r) => $r['value'], $rows ?: [['value' => 1]])); @endphp
@if (empty($rows))
    <p {{ $attributes->merge(['class' => 'text-sm text-ink-soft']) }}>{{ $empty }}</p>
@else
    <ul {{ $attributes->merge(['class' => 'space-y-2.5']) }}>
        @foreach ($rows as $r)
            <li>
                <div class="flex items-baseline justify-between gap-3 text-sm"><span class="truncate text-ink">{{ $r['label'] }}</span>@isset($r['note'])<span class="shrink-0 text-xs text-ink-soft">{{ $r['note'] }}</span>@endisset</div>
                <div class="mt-1 flex items-center gap-2">
                    <div class="h-2.5 rounded-r-[4px] bg-curtain" style="width: {{ max(1, round(88 * $r['value'] / $max)) }}%"></div>
                    <span class="text-xs font-semibold text-ink tabular-nums">{{ number_format($r['value']) }}</span>
                </div>
            </li>
        @endforeach
    </ul>
@endif
