@php
    [$ok] = $eligibility;
    $pct = fn ($v) => rtrim(rtrim(number_format((float) $v, 1), '0'), '.');
    $manager = ! auth()->user()->member_id || (int) auth()->user()->member_id !== (int) $attempt->member_id;
@endphp
<x-layouts.app :title="'Result: '.$exam->title">
    <section class="container-page mt-8 max-w-3xl">
        <a href="{{ $manager ? route('exams.manage.show', $exam) : route('exams.index') }}" class="text-sm font-semibold text-curtain">{{ $manager ? $exam->title : 'My examinations' }}</a>
        <p class="eyebrow mt-4">{{ $exam->modeLabel() }} examination · Attempt {{ $attempt->number }}</p>
        <h1 class="h-page mt-1">{{ $exam->title }}</h1>
        @if ($manager)<p class="mt-1 text-ink-soft">{{ $attempt->member->full_name }}</p>@endif

        @if ($attempt->status === 'auto_submitted')
            <p class="mt-4 rounded-xl bg-gold/20 px-4 py-3 text-sm">Time ran out, so the examination was submitted automatically with the answers saved by then.</p>
        @endif

        @if (! $released)
            <div class="card-pad mt-6 text-center">
                <x-icon name="check-circle" class="mx-auto size-12 text-ok" />
                <p class="mt-3 font-display text-2xl font-semibold uppercase text-stage">Your answers are in</p>
                <p class="mx-auto mt-2 max-w-md text-ink-soft">
                    {{ $exam->release === 'after_close' && $exam->closes_at ? 'Results are released when the examination closes on '.$exam->closes_at->format('j F Y \a\t g:ia').'.' : 'Results are released by the examination team. You will see them here.' }}
                </p>
                <p class="mt-4 text-sm">You answered {{ $attempt->questions->count() - $attempt->unanswered }} of {{ $attempt->questions->count() }} questions in {{ $attempt->timeUsedLabel() }}.</p>
            </div>
        @elseif ($attempt->needs_marking)
            <div class="card-pad mt-6 text-center">
                <x-icon name="pen" class="mx-auto size-12 text-poster" />
                <p class="mt-3 font-display text-2xl font-semibold uppercase text-stage">Waiting for marking</p>
                <p class="mx-auto mt-2 max-w-md text-ink-soft">Some of your answers are written answers. An examiner will mark them, and your full result will appear here.</p>
                <p class="mt-4 text-sm">Marked so far: {{ $pct($attempt->score) }} of {{ $pct($attempt->max_score) }} marks.</p>
            </div>
        @else
            <div @class(['mt-6 overflow-hidden rounded-[var(--radius-card)] text-center', 'bg-stage text-paper' => $attempt->passed, 'card' => ! $attempt->passed])>
                <div class="p-6 sm:p-8">
                    <p @class(['text-sm font-semibold uppercase tracking-[0.2em]', 'text-gold' => $attempt->passed, 'text-curtain' => ! $attempt->passed])>{{ $attempt->passed ? 'Passed' : 'Not passed this time' }}</p>
                    <p @class(['mt-2 font-display text-7xl font-semibold', 'text-paper' => $attempt->passed, 'text-stage' => ! $attempt->passed])>{{ $pct($attempt->percent) }}<span class="text-4xl">%</span></p>
                    <p @class(['mt-1 text-sm', 'text-paper/70' => $attempt->passed, 'text-ink-soft' => ! $attempt->passed])>{{ $pct($attempt->score) }} of {{ $pct($attempt->max_score) }} marks · pass mark {{ $exam->pass_mark }}%</p>
                </div>
                <dl @class(['grid grid-cols-2 gap-px text-sm sm:grid-cols-4', 'bg-white/10' => $attempt->passed, 'bg-line' => ! $attempt->passed])>
                    @foreach ([['Correct', $attempt->correct.' / '.$attempt->questions->count()], ['Incorrect', $attempt->incorrect], ['Not answered', $attempt->unanswered], ['Time used', $attempt->timeUsedLabel()]] as [$label, $value])
                        <div @class(['px-3 py-3', 'bg-stage' => $attempt->passed, 'bg-white' => ! $attempt->passed])><dt @class(['text-xs font-semibold uppercase', 'text-paper/60' => $attempt->passed, 'text-ink-soft' => ! $attempt->passed])>{{ $label }}</dt><dd class="mt-0.5 font-semibold">{{ $value }}</dd></div>
                    @endforeach
                </dl>
            </div>

            @if ($overall && $overall->attempts > 1)
                <p class="mt-4 text-sm text-ink-soft">Across {{ $overall->attempts }} attempts your result is {{ $pct($overall->percent) }}% ({{ strtolower(\App\Models\Exam::POLICIES[$exam->result_policy]) }}): <strong class="text-ink">{{ $overall->passed ? 'passed' : 'not passed' }}</strong>.</p>
            @endif

            @if ($certificate)
                <div class="card-pad mt-4 flex flex-wrap items-center gap-4">
                    <x-icon name="award" class="size-10 text-gold" />
                    <div class="flex-1"><p class="font-semibold">{{ $certificate->title }}</p><p class="text-sm text-ink-soft">{{ $certificate->number }}</p></div>
                    <a href="{{ route('certificates.download', $certificate) }}" class="btn-gold no-underline"><x-icon name="download" class="size-4" /> Download certificate</a>
                </div>
            @elseif ($exam->awards_certificate && $overall && ! $overall->passed)
                <p class="mt-4 text-sm text-ink-soft">A certificate is issued when your result reaches the pass mark.</p>
            @endif
        @endif

        @if (! $manager)
            <div class="mt-6 flex flex-wrap gap-2">
                @if ($ok)<a href="{{ route('exams.show', $exam) }}" class="btn-dark no-underline">Try again{{ $left !== null ? ' ('.$left.' left)' : '' }}</a>@endif
                @if ($exam->course)<a href="{{ route('academy.show', $exam->course) }}" class="btn-ghost no-underline">Back to the training</a>@endif
                <a href="{{ route('certificates.mine') }}" class="btn-ghost no-underline">My certificates</a>
            </div>
        @endif

        @if ($review && ! $attempt->needs_marking)
            <h2 class="h-section mt-10">Your answers</h2>
            <ol class="mt-3 space-y-3">
                @foreach ($attempt->questions as $item)
                    <li class="card-pad">
                        <div class="flex items-start gap-3">
                            <span @class(['flex size-8 shrink-0 items-center justify-center rounded-full text-sm font-bold', 'bg-ok/15 text-ok' => $item->is_correct, 'bg-curtain/10 text-curtain' => ! $item->is_correct])>{{ $item->position }}</span>
                            <div class="flex-1">
                                <p class="font-semibold">{{ $item->snapshot['stem'] }}</p>
                                <p class="mt-2 text-sm"><span class="text-ink-soft">Your answer:</span> {{ $item->responseText() }}</p>
                                @unless ($item->is_correct)<p class="text-sm"><span class="text-ink-soft">Correct answer:</span> <strong>{{ \App\Models\Question::answerText($item->snapshot) }}</strong></p>@endunless
                                @if ($item->snapshot['explanation'] ?? null)<p class="mt-2 rounded-lg bg-paper-2 px-3 py-2 text-sm">{{ $item->snapshot['explanation'] }}</p>@endif
                            </div>
                            <span class="shrink-0 text-xs font-semibold text-ink-soft">{{ rtrim(rtrim(number_format((float) $item->marks_awarded, 2), '0'), '.') ?: '0' }}/{{ $item->snapshot['marks'] }}</span>
                        </div>
                    </li>
                @endforeach
            </ol>
        @elseif ($released && ! $attempt->needs_marking)
            <p class="mt-8 text-sm text-ink-soft">For a certification examination the questions and answers are kept private, so the question bank stays fair for other candidates.</p>
        @endif
    </section>
</x-layouts.app>
