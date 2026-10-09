@php [$ok, $why] = $eligibility; @endphp
<x-layouts.app :title="$exam->title">
    <section class="container-page mt-8 max-w-3xl">
        <a href="{{ route('exams.index') }}" class="text-sm font-semibold text-curtain">My examinations</a>
        <p class="eyebrow mt-4">{{ $exam->modeLabel() }} examination</p>
        <h1 class="h-page mt-1">{{ $exam->title }}</h1>
        <p class="mt-1 text-ink-soft">{{ $exam->scopeLabel() }}</p>

        <div class="card-pad mt-6">
            <dl class="grid grid-cols-2 gap-4 sm:grid-cols-4">
                <div><dt class="text-xs font-semibold uppercase text-ink-soft">Questions</dt><dd class="font-display text-2xl font-semibold text-stage">{{ $exam->plannedCount() }}</dd></div>
                <div><dt class="text-xs font-semibold uppercase text-ink-soft">Time allowed</dt><dd class="font-display text-2xl font-semibold text-stage">{{ $exam->duration_minutes }} min</dd></div>
                <div><dt class="text-xs font-semibold uppercase text-ink-soft">Pass mark</dt><dd class="font-display text-2xl font-semibold text-stage">{{ $exam->pass_mark }}%</dd></div>
                <div><dt class="text-xs font-semibold uppercase text-ink-soft">Attempts</dt><dd class="font-display text-2xl font-semibold text-stage">{{ $exam->max_attempts ? $attempts->count().' of '.$exam->max_attempts : 'Unlimited' }}</dd></div>
            </dl>
            <p class="mt-4 text-sm"><x-icon name="calendar" class="inline size-4 text-ink-soft" /> {{ $exam->windowLabel() }}</p>
            @if ($exam->awards_certificate)<p class="mt-1 text-sm"><x-icon name="award" class="inline size-4 text-ink-soft" /> A certificate is issued when you pass{{ $exam->requires_course_completion ? ', after completing every lesson' : '' }}.</p>@endif
            @if ($exam->max_attempts && $attempts->count())<p class="mt-1 text-sm text-ink-soft">Your result counts the {{ strtolower(\App\Models\Exam::POLICIES[$exam->result_policy]) }} of your attempts.</p>@endif
        </div>

        @if ($exam->instructions)
            <div class="card-pad mt-4"><h2 class="h-section">Instructions</h2><p class="mt-2 whitespace-pre-line">{{ $exam->instructions }}</p></div>
        @endif

        <div class="card-pad mt-4">
            <h2 class="h-section">Before you begin</h2>
            <ul class="mt-3 space-y-2 text-sm">
                <li class="flex gap-2"><x-icon name="clock" class="mt-0.5 size-4 shrink-0 text-curtain" /> The timer starts when you press Begin and keeps running even if you close the page. It follows the server's clock, not your phone's.</li>
                <li class="flex gap-2"><x-icon name="check-circle" class="mt-0.5 size-4 shrink-0 text-curtain" /> Every answer is saved as you go. If your data drops, keep going: answers wait on your phone and are sent when the connection returns.</li>
                <li class="flex gap-2"><x-icon name="flag" class="mt-0.5 size-4 shrink-0 text-curtain" /> Flag any question you want to come back to. The numbered grid shows what you have answered.</li>
                <li class="flex gap-2"><x-icon name="alert" class="mt-0.5 size-4 shrink-0 text-curtain" /> When time runs out, the examination is submitted automatically with the answers saved so far.</li>
                @unless ($exam->isPractice())<li class="flex gap-2"><x-icon name="lock" class="mt-0.5 size-4 shrink-0 text-curtain" /> Stay on the examination screen. Leaving it is recorded.</li>@endunless
            </ul>
        </div>

        @error('exam')<p class="mt-4 rounded-xl bg-curtain/10 px-4 py-3 text-sm font-semibold text-curtain">{{ $message }}</p>@enderror

        <div class="mt-6">
            @if ($current)
                <a href="{{ route('exams.sit', $current) }}" class="btn-primary w-full no-underline sm:w-auto">Return to your examination</a>
            @elseif ($ok)
                <form method="POST" action="{{ route('exams.start', $exam) }}">@csrf
                    <button class="btn-primary w-full sm:w-auto">Begin {{ $attempts->isEmpty() ? 'the examination' : 'attempt '.($attempts->count() + 1) }}</button>
                    @if ($left !== null)<p class="hint">After this you will have {{ max(0, $left - 1) }} {{ \Illuminate\Support\Str::plural('attempt', max(0, $left - 1)) }} left.</p>@endif
                </form>
            @else
                <p class="rounded-xl bg-paper-2 px-4 py-3 text-sm">{{ $why }}</p>
            @endif
        </div>

        @if ($attempts->where('status', '!=', 'in_progress')->isNotEmpty())
            <h2 class="h-section mt-10">Your attempts</h2>
            <ul class="card mt-3 divide-y divide-line">
                @foreach ($attempts->where('status', '!=', 'in_progress') as $a)
                    <li class="flex items-center justify-between gap-3 px-5 py-3">
                        <span>Attempt {{ $a->number }} · {{ $a->submitted_at?->format('j M Y, g:ia') }}</span>
                        <a href="{{ route('exams.result', $a) }}" class="link text-sm">{{ $exam->resultsReleased() && ! $a->needs_marking ? rtrim(rtrim(number_format($a->percent, 1), '0'), '.').'%' : 'Result to come' }}</a>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>
</x-layouts.app>
