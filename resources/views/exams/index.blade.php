<x-layouts.app title="My examinations">
    <section class="container-page mt-8">
        <p class="eyebrow">GODRAM Virtual Academy</p>
        <div class="mt-1 flex flex-wrap items-end justify-between gap-3">
            <h1 class="h-page">My examinations</h1>
            <a href="{{ route('certificates.mine') }}" class="btn-ghost no-underline"><x-icon name="award" class="size-4" /> My certificates</a>
        </div>

        @if ($items->isEmpty() && $past->isEmpty())
            <x-empty title="No examinations for you yet" class="mt-6">
                Examinations appear here when a training you are enrolled in has one, or when your District, Region or the National office sets one for members.
                <a href="{{ route('academy') }}" class="btn-primary mt-4 no-underline">Explore the Academy</a>
            </x-empty>
        @else
            <div class="mt-6 grid gap-4 md:grid-cols-2">
                @foreach ($items as $item)
                    @php [$ok, $why] = $item->eligibility; $inProgress = $item->attempts->firstWhere('status', 'in_progress'); @endphp
                    <article class="card flex flex-col p-5">
                        <div class="flex flex-wrap items-center gap-2">
                            <span @class(['badge', 'badge-info' => $item->exam->isPractice(), 'badge-neutral' => ! $item->exam->isPractice()])>{{ $item->exam->modeLabel() }}</span>
                            @if ($item->result)<span @class(['badge', 'badge-ok' => $item->result->passed, 'badge-bad' => ! $item->result->passed])>{{ $item->result->passed ? 'Passed' : 'Not passed yet' }} · {{ rtrim(rtrim(number_format($item->result->percent, 1), '0'), '.') }}%</span>
                            @elseif ($item->attempts->where('status', '!=', 'in_progress')->isNotEmpty())<span class="badge badge-warn">Result to come</span>@endif
                        </div>
                        <h2 class="mt-2 font-display text-xl font-semibold uppercase text-stage"><a href="{{ route('exams.show', $item->exam) }}" class="no-underline">{{ $item->exam->title }}</a></h2>
                        <p class="text-sm text-ink-soft">{{ $item->exam->scopeLabel() }}</p>
                        <dl class="mt-3 grid grid-cols-3 gap-2 text-sm">
                            <div><dt class="text-xs font-semibold uppercase text-ink-soft">Questions</dt><dd class="font-medium">{{ $item->exam->plannedCount() }}</dd></div>
                            <div><dt class="text-xs font-semibold uppercase text-ink-soft">Time</dt><dd class="font-medium">{{ $item->exam->duration_minutes }} min</dd></div>
                            <div><dt class="text-xs font-semibold uppercase text-ink-soft">Pass mark</dt><dd class="font-medium">{{ $item->exam->pass_mark }}%</dd></div>
                        </dl>
                        <p class="mt-3 text-sm"><x-icon name="calendar" class="inline size-4 text-ink-soft" /> {{ $item->exam->windowLabel() }}</p>
                        <div class="mt-auto pt-4">
                            @if ($inProgress)
                                <a href="{{ route('exams.sit', $inProgress) }}" class="btn-primary w-full no-underline">Return to your examination</a>
                            @elseif ($ok)
                                <a href="{{ route('exams.show', $item->exam) }}" class="btn-dark w-full no-underline">{{ $item->attempts->isEmpty() ? 'Get ready' : 'Try again' }}</a>
                            @else
                                <p class="rounded-xl bg-paper-2 px-3 py-2 text-sm text-ink-soft">{{ $why }}</p>
                                @if ($item->attempts->isNotEmpty())<a href="{{ route('exams.result', $item->attempts->last()) }}" class="link mt-2 inline-block text-sm">See your result</a>@endif
                            @endif
                        </div>
                    </article>
                @endforeach
            </div>

            @if ($past->isNotEmpty())
                <h2 class="h-section mt-10">Earlier examinations</h2>
                <ul class="card mt-3 divide-y divide-line">
                    @foreach ($past as $exam)
                        <li class="flex items-center justify-between gap-3 px-5 py-3"><span>{{ $exam->title }}</span><a href="{{ route('exams.result', $attempts->get($exam->id)->last()) }}" class="link text-sm">Result</a></li>
                    @endforeach
                </ul>
            @endif
        @endif
    </section>
</x-layouts.app>
