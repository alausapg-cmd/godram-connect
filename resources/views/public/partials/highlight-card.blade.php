@php $image = $report->media->firstWhere('kind', 'image'); @endphp
<a href="{{ route('highlights.show', $report) }}" class="card group flex flex-col overflow-hidden no-underline transition-shadow hover:shadow-lg">
    @if ($image)
        <img src="{{ route('report-media.show', $image) }}" alt="" class="aspect-[16/10] w-full object-cover" loading="lazy">
    @else
        <div class="poster-stripe aspect-[16/10] w-full opacity-80"></div>
    @endif
    <div class="flex flex-1 flex-col p-5">
        <p class="text-xs font-semibold uppercase tracking-wide text-poster">{{ $report->typeLabel() }}</p>
        <h3 class="mt-1 font-display text-xl font-semibold uppercase leading-tight text-stage group-hover:text-curtain">{{ $report->displayTitle() }}</h3>
        <p class="mt-2 flex items-center gap-1 text-sm text-ink-soft"><x-icon name="map-pin" class="size-4" />{{ $report->orgUnit->fullName() }}</p>
        <p class="mt-auto pt-3 text-xs text-ink-soft">{{ $report->activity_date?->format('j M Y') }}@if($report->attendance_count) · {{ number_format($report->attendance_count) }} attended @endif</p>
    </div>
</a>
