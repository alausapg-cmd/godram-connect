@php $pct = fn ($v) => $v === null ? '–' : rtrim(rtrim(number_format((float) $v, 1), '0'), '.').'%'; @endphp
<x-layouts.app :title="'Results: '.$exam->title">
    <section class="container-page mt-8" x-data="{ tab: @js(request('tab', 'candidates')) }">
        <a href="{{ route('exams.manage.index') }}" class="text-sm font-semibold text-curtain">Examinations</a>
        <div class="mt-2 flex flex-wrap items-end justify-between gap-4">
            <div>
                <p class="eyebrow">{{ $exam->modeLabel() }} · {{ $exam->scopeLabel() }}</p>
                <h1 class="h-page mt-1">{{ $exam->title }}</h1>
                <p class="mt-1 text-sm text-ink-soft">{{ $exam->windowLabel() }} · {{ $exam->plannedCount() }} questions · {{ $exam->duration_minutes }} min · pass mark {{ $exam->pass_mark }}%</p>
            </div>
            <div class="flex flex-wrap gap-2">
                @if (! $exam->resultsReleased())
                    <form method="POST" action="{{ route('exams.manage.release', $exam) }}" onsubmit="return confirm('Release results to every candidate now? Certificates due will be issued.')">@csrf<button class="btn-primary">Release results</button></form>
                @endif
                <a href="{{ route('exams.manage.show', [$exam] + $filters + ['format' => 'csv']) }}" class="btn-ghost no-underline"><x-icon name="download" class="size-4" /> Spreadsheet</a>
                <a href="{{ route('exams.manage.edit', $exam) }}" class="btn-ghost no-underline"><x-icon name="settings" class="size-4" /> Settings</a>
            </div>
        </div>
        @if (! $exam->resultsReleased())<p class="mt-3 rounded-xl bg-gold/20 px-4 py-2 text-sm">Results are held. Candidates see "your answers are in" until {{ $exam->release === 'after_close' && $exam->closes_at ? 'the examination closes on '.$exam->closes_at->format('j M, g:ia').', or you release them' : 'you release them' }}.</p>@endif

        <div class="mt-6 grid grid-cols-2 gap-3 md:grid-cols-4 lg:grid-cols-8">
            <x-stat label="Candidates" :value="$stats['registered']" note="Eligible in your area" />
            <x-stat label="Started" :value="$stats['started']" />
            <x-stat label="Completed" :value="$stats['completed']" />
            <x-stat label="In progress" :value="$stats['in_progress']" />
            <x-stat label="Pass rate" :value="$stats['pass_rate'] === null ? '–' : $stats['pass_rate'].'%'" />
            <x-stat label="Average" :value="$pct($stats['average'])" />
            <x-stat label="Highest" :value="$pct($stats['highest'])" />
            <x-stat label="Lowest" :value="$pct($stats['lowest'])" />
        </div>

        <form method="GET" class="card-pad mt-6 grid gap-3 sm:grid-cols-2 lg:grid-cols-[2fr_1fr_1fr_1fr_1fr_auto] lg:items-end">
            <div><label for="unit" class="label">Area</label><select id="unit" name="unit" class="input"><option value="">Everyone in my area</option>@foreach ($units as $u)<option value="{{ $u->id }}" @selected(($filters['unit'] ?? '') == $u->id)>{{ str_repeat('· ', max(0, $u->depth - 1)) }}{{ $u->fullName() }}</option>@endforeach</select></div>
            <div><label for="result" class="label">Result</label><select id="result" name="result" class="input"><option value="">All</option>@foreach (['passed' => 'Passed', 'failed' => 'Not passed', 'marking' => 'To mark', 'in_progress' => 'In progress'] as $k => $l)<option value="{{ $k }}" @selected(($filters['result'] ?? '') === $k)>{{ $l }}</option>@endforeach</select></div>
            <div><label for="attempt" class="label">Attempts</label><select id="attempt" name="attempt" class="input"><option value="">All attempts</option><option value="first" @selected(($filters['attempt'] ?? '') === 'first')>First only</option><option value="latest" @selected(($filters['attempt'] ?? '') === 'latest')>Latest only</option></select></div>
            <div><label for="from" class="label">From</label><input id="from" type="date" name="from" value="{{ $filters['from'] ?? '' }}" class="input"></div>
            <div><label for="to" class="label">To</label><input id="to" type="date" name="to" value="{{ $filters['to'] ?? '' }}" class="input"></div><button class="btn-dark">Show</button>
        </form>

        <div class="mt-6 flex gap-2 border-b border-line" role="tablist">
            <button type="button" role="tab" @click="tab = 'candidates'" :aria-selected="tab === 'candidates'" class="-mb-px border-b-2 px-4 py-2 text-sm font-semibold" :class="tab === 'candidates' ? 'border-curtain text-curtain' : 'border-transparent text-ink-soft'">Candidates ({{ $attempts->count() }})</button>
            <button type="button" role="tab" @click="tab = 'questions'" :aria-selected="tab === 'questions'" class="-mb-px border-b-2 px-4 py-2 text-sm font-semibold" :class="tab === 'questions' ? 'border-curtain text-curtain' : 'border-transparent text-ink-soft'">Question performance ({{ $questions->count() }})</button>
        </div>

        <div x-show="tab === 'candidates'" class="mt-4">
            @if ($attempts->isEmpty())
                <x-empty title="No attempts yet">When candidates sit the examination their scores appear here as they finish.</x-empty>
            @else
                <div class="card overflow-x-auto">
                    <table class="table min-w-[860px]">
                        <thead><tr><th>Candidate</th><th>Assembly</th><th class="text-center">Attempt</th><th>Status</th><th class="text-right">Score</th><th class="text-right">Correct</th><th class="text-right">Time</th><th>Notes</th><th></th></tr></thead>
                        <tbody>
                            @foreach ($attempts as $a)
                                <tr>
                                    <td><span class="font-semibold">{{ $a->member->full_name }}</span><span class="block text-xs text-ink-soft">{{ $a->member->member_no }}</span></td>
                                    <td class="text-sm">{{ $a->member->currentPlacement?->orgUnit?->name }}<span class="block text-xs text-ink-soft">{{ $a->member->currentPlacement?->orgUnit?->parent?->name }}</span></td>
                                    <td class="text-center">{{ $a->number }}</td>
                                    <td><span @class(['badge', 'badge-ok' => $a->passed === true, 'badge-bad' => $a->passed === false, 'badge-warn' => $a->needs_marking, 'badge-info' => $a->status === 'in_progress'])>{{ $a->statusLabel() }}</span>@if ($a->status === 'auto_submitted')<span class="block text-xs text-ink-soft">Time ran out</span>@endif</td>
                                    <td class="text-right font-semibold">{{ $a->status === 'in_progress' ? '' : $pct($a->percent) }}</td>
                                    <td class="text-right">{{ $a->status === 'in_progress' ? '' : $a->correct.'/'.($a->correct + $a->incorrect + $a->unanswered) }}</td>
                                    <td class="text-right text-sm">{{ $a->time_used_seconds ? round($a->time_used_seconds / 60).' min' : '' }}</td>
                                    <td class="text-xs text-warn">{{ implode('; ', $a->concerns()) }}</td>
                                    <td class="text-right"><a href="{{ route('exams.manage.attempt', [$exam, $a]) }}" class="btn-ghost btn-sm no-underline">{{ $a->needs_marking ? 'Mark' : 'Open' }}</a></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

        <div x-show="tab === 'questions'" x-cloak class="mt-4 space-y-3">
            @forelse ($questions as $q)
                <article class="card-pad">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <p class="max-w-2xl font-semibold">{{ $q->stem }}</p>
                        <a href="{{ route('questions.edit', $q->id) }}" class="link shrink-0 text-sm">Open in the bank</a>
                    </div>
                    @foreach ($q->flags as $flag)<span class="badge badge-warn mt-2 mr-1"><x-icon name="alert" class="size-3" /> {{ $flag }}</span>@endforeach
                    <dl class="mt-3 grid grid-cols-2 gap-3 text-sm sm:grid-cols-5">
                        <div><dt class="text-xs font-semibold uppercase text-ink-soft">Presented</dt><dd class="font-semibold">{{ $q->presented }}</dd></div>
                        <div><dt class="text-xs font-semibold uppercase text-ink-soft">Correct</dt><dd class="font-semibold text-ok">{{ $q->correct }}%</dd></div>
                        <div><dt class="text-xs font-semibold uppercase text-ink-soft">Incorrect</dt><dd class="font-semibold text-curtain">{{ $q->incorrect }}%</dd></div>
                        <div><dt class="text-xs font-semibold uppercase text-ink-soft">Skipped</dt><dd class="font-semibold">{{ $q->skipped }}%</dd></div>
                        <div><dt class="text-xs font-semibold uppercase text-ink-soft">Average time</dt><dd class="font-semibold">{{ $q->seconds }} s</dd></div>
                    </dl>
                    @if ($q->choices->isNotEmpty())
                        @php $max = max(1, $q->choices->sum('count')); @endphp
                        <ul class="mt-3 space-y-1.5">
                            @foreach ($q->choices as $c)
                                <li class="text-sm">
                                    <div class="flex justify-between gap-2"><span @class(['font-semibold text-ok' => $c->correct])>{{ $c->text }}{{ $c->correct ? ' (correct)' : '' }}</span><span class="text-ink-soft">{{ $c->count }}</span></div>
                                    <div class="mt-0.5 h-1.5 rounded-full bg-paper-2"><div @class(['h-full rounded-full', 'bg-ok' => $c->correct, 'bg-ink-soft/40' => ! $c->correct]) style="width: {{ round($c->count / $max * 100) }}%"></div></div>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </article>
            @empty
                <x-empty title="No finished papers yet">Question performance appears once candidates have submitted.</x-empty>
            @endforelse
        </div>
    </section>
</x-layouts.app>
