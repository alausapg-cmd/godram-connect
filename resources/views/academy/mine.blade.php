<x-layouts.app title="My learning">
    <section class="container-page mt-8">
        <p class="eyebrow">GODRAM Virtual Academy</p>
        <h1 class="h-page mt-1">My learning</h1>

        @if ($items->isEmpty())
            <x-empty title="No training enrolled yet" class="mt-6">
                Explore available GODRAM Virtual Academy programmes.
                <a href="{{ route('academy') }}" class="btn-primary mt-4 no-underline">Explore the Academy</a>
            </x-empty>
        @else
            <div class="mt-6 space-y-5">
                @foreach ($items as $item)
                    <article class="card overflow-hidden sm:flex">
                        <a href="{{ route('academy.show', $item->course) }}" class="relative block aspect-[16/9] shrink-0 bg-stage sm:aspect-auto sm:w-64">
                            @if ($item->course->coverUrl())<img src="{{ $item->course->coverUrl() }}" alt="" class="absolute inset-0 size-full object-cover" loading="lazy">@else<x-icon name="academy" class="absolute bottom-4 left-4 size-10 text-gold" />@endif
                        </a>
                        <div class="flex-1 p-5">
                            <p class="eyebrow">{{ $item->course->levelLabel() }}</p>
                            <h2 class="mt-1 font-display text-xl font-semibold uppercase text-stage"><a href="{{ route('academy.show', $item->course) }}" class="no-underline">{{ $item->course->title }}</a></h2>
                            <div class="mt-3 flex items-center gap-3 text-sm">
                                <div class="h-2 flex-1 overflow-hidden rounded-full bg-paper-2"><div class="h-full rounded-full {{ $item->enrolment->status === 'completed' ? 'bg-ok' : 'bg-curtain' }}" style="width: {{ $item->progress['percent'] }}%"></div></div>
                                <span class="shrink-0 font-semibold">{{ $item->progress['done'] }} / {{ $item->progress['total'] }} lessons completed</span>
                            </div>
                            <dl class="mt-4 grid gap-3 text-sm sm:grid-cols-3">
                                <div><dt class="text-xs font-semibold uppercase tracking-wide text-ink-soft">Status</dt><dd class="mt-0.5 font-medium">{{ $item->enrolment->statusLabel() }}</dd></div>
                                <div><dt class="text-xs font-semibold uppercase tracking-wide text-ink-soft">Assignments</dt><dd class="mt-0.5 font-medium">
                                    @if ($item->assignments->isEmpty()) None
                                    @else {{ $item->assignments->filter(fn ($a) => $a->submission)->count() }} of {{ $item->assignments->count() }} handed in
                                        @if ($returned = $item->assignments->filter(fn ($a) => $a->submission?->status === 'returned')->count())<span class="block text-curtain">{{ $returned }} returned for more work</span>@endif
                                    @endif
                                </dd></div>
                                <div><dt class="text-xs font-semibold uppercase tracking-wide text-ink-soft">Next live class</dt><dd class="mt-0.5 font-medium">{{ $item->nextLive ? $item->nextLive->live_at->format('D j M, g:ia') : 'None scheduled' }}</dd></div>
                            </dl>
                            <div class="mt-4 flex flex-wrap gap-2">
                                @if ($item->nextLive?->isLive())<a href="{{ route('academy.room', [$item->course, $item->nextLive]) }}" class="btn-primary btn-sm no-underline"><x-icon name="live" class="size-4" /> Join the live class</a>@endif
                                @if ($item->progress['next'])<a href="{{ route('academy.lesson', [$item->course, $item->progress['next']]) }}" class="btn-dark btn-sm no-underline">{{ $item->progress['done'] ? 'Continue' : 'Start' }}: {{ \Illuminate\Support\Str::limit($item->progress['next']->title, 40) }}</a>@endif
                                @foreach ($item->assignments->filter(fn ($a) => ! $a->submission || $a->submission->status === 'returned')->take(2) as $a)
                                    <a href="{{ route('academy.assignment', [$item->course, $a->assignment]) }}" class="btn-ghost btn-sm no-underline"><x-icon name="pen" class="size-4" /> {{ \Illuminate\Support\Str::limit($a->assignment->title, 32) }}</a>
                                @endforeach
                            </div>
                        </div>
                    </article>
                @endforeach
            </div>
        @endif

        <div class="mt-8 grid gap-3 sm:grid-cols-2">
            <a href="{{ route('exams.index') }}" class="card-pad flex items-center gap-3 no-underline hover:border-ink-soft"><x-icon name="pen" class="size-8 text-curtain" /><span><span class="block font-semibold">My examinations</span><span class="text-sm text-ink-soft">Practice and certification examinations open to you</span></span></a>
            <a href="{{ route('certificates.mine') }}" class="card-pad flex items-center gap-3 no-underline hover:border-ink-soft"><x-icon name="award" class="size-8 text-gold" /><span><span class="block font-semibold">My certificates</span><span class="text-sm text-ink-soft">Certificates and your achievement timeline</span></span></a>
        </div>
    </section>
</x-layouts.app>
