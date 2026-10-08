@props(['course', 'progress' => null])
<a href="{{ route('academy.show', $course) }}" {{ $attributes->merge(['class' => 'group card flex flex-col overflow-hidden no-underline transition hover:-translate-y-0.5 hover:shadow-lg']) }}>
    <div class="relative aspect-[16/9] overflow-hidden bg-stage">
        @if ($course->coverUrl())
            <img src="{{ $course->coverUrl() }}" alt="" class="size-full object-cover transition duration-500 group-hover:scale-105" loading="lazy">
        @else
            <div class="flex size-full items-end bg-[radial-gradient(circle_at_20%_20%,var(--color-curtain),transparent_60%)] p-4"><x-icon name="academy" class="size-10 text-gold" /></div>
        @endif
        <span class="absolute left-3 top-3 rounded-full bg-stage/85 px-2.5 py-1 text-[11px] font-semibold uppercase tracking-wide text-gold backdrop-blur">{{ $course->levelLabel() }}</span>
    </div>
    <div class="flex flex-1 flex-col p-4">
        <p class="eyebrow">{{ $course->kindLabel() }}</p>
        <h3 class="mt-1 font-display text-lg font-semibold uppercase leading-snug text-stage">{{ $course->title }}</h3>
        <p class="mt-1 line-clamp-3 text-sm text-ink-soft">{{ $course->summary }}</p>
        <div class="mt-auto pt-4">
            @if ($progress)
                <div class="flex items-center justify-between text-xs font-semibold text-ink-soft"><span>{{ $progress['done'] }} / {{ $progress['total'] }} lessons completed</span><span>{{ $progress['percent'] }}%</span></div>
                <div class="mt-1.5 h-1.5 overflow-hidden rounded-full bg-paper-2"><div class="h-full rounded-full bg-curtain" style="width: {{ $progress['percent'] }}%"></div></div>
            @else
                <p class="flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-ink-soft">
                    @isset($course->lessons_count)<span class="inline-flex items-center gap-1"><x-icon name="book" class="size-3.5" /> {{ $course->lessons_count }} {{ \Illuminate\Support\Str::plural('lesson', $course->lessons_count) }}</span>@endisset
                    @if ($course->starts_on)<span class="inline-flex items-center gap-1"><x-icon name="calendar" class="size-3.5" /> Starts {{ $course->starts_on->format('j M') }}</span>@endif
                    <span>{{ $course->orgUnit?->fullName() }}</span>
                </p>
            @endif
        </div>
    </div>
</a>
