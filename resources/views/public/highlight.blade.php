<x-layouts.app :title="$report->displayTitle()" :description="\Illuminate\Support\Str::limit($report->description, 150)">
    <article class="container-page mt-10 max-w-3xl">
        <a href="{{ route('highlights') }}" class="text-sm font-semibold text-curtain">All highlights</a>
        <p class="eyebrow mt-4">{{ $report->typeLabel() }}</p>
        <h1 class="h-page mt-1">{{ $report->displayTitle() }}</h1>
        <p class="mt-3 flex flex-wrap gap-x-4 gap-y-1 text-sm text-ink-soft">
            <span class="flex items-center gap-1"><x-icon name="map-pin" class="size-4" />{{ $report->location }}, {{ $report->orgUnit->fullName() }}</span>
            <span class="flex items-center gap-1"><x-icon name="calendar" class="size-4" />{{ $report->activity_date?->format('j F Y') }}</span>
        </p>
        @foreach ($report->media->where('kind', 'image') as $image)
            <img src="{{ route('report-media.show', $image) }}" alt="Photo from {{ $report->displayTitle() }}" class="mt-6 w-full rounded-2xl" loading="lazy">
        @endforeach
        <div class="mt-6 grid grid-cols-3 gap-3">
            @if($report->performers_count)<x-stat label="Performers" :value="$report->performers_count" />@endif
            @if($report->attendance_count)<x-stat label="Attended" :value="$report->attendance_count" />@endif
            @if($report->souls_won)<x-stat label="Responded" :value="$report->souls_won" />@endif
        </div>
        <div class="prose mt-6 space-y-4 text-lg leading-relaxed text-ink">
            <p>{{ $report->description }}</p>
            @if ($report->impact)<p class="font-serif italic text-ink-soft">{{ $report->impact }}</p>@endif
        </div>
        @php $shareText = urlencode($report->displayTitle().' - '.route('highlights.show', $report)); @endphp
        <div class="mt-8 flex flex-wrap gap-2">
            <a class="btn-ghost btn-sm no-underline" href="https://wa.me/?text={{ $shareText }}" target="_blank" rel="noopener">Share on WhatsApp</a>
            <a class="btn-ghost btn-sm no-underline" href="https://www.facebook.com/sharer/sharer.php?u={{ urlencode(route('highlights.show', $report)) }}" target="_blank" rel="noopener">Share on Facebook</a>
        </div>
    </article>
</x-layouts.app>
