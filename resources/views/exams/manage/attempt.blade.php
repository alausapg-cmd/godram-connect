@php $num = fn ($v) => rtrim(rtrim(number_format((float) $v, 2), '0'), '.') ?: '0'; @endphp
<x-layouts.app :title="$attempt->member->full_name.': '.$exam->title">
    <section class="container-page mt-8 max-w-4xl">
        <a href="{{ route('exams.manage.show', $exam) }}" class="text-sm font-semibold text-curtain">{{ $exam->title }}</a>
        <div class="mt-2 flex flex-wrap items-end justify-between gap-3">
            <div>
                <h1 class="h-page">{{ $attempt->member->full_name }}</h1>
                <p class="text-sm text-ink-soft">{{ $attempt->member->member_no }} · {{ $attempt->member->currentPlacement?->orgUnit?->fullName() }}</p>
            </div>
            <div class="flex gap-1">
                @foreach ($others as $o)
                    <a href="{{ route('exams.manage.attempt', [$exam, $o]) }}" @class(['btn-sm no-underline', 'btn-dark' => $o->id === $attempt->id, 'btn-ghost' => $o->id !== $attempt->id])>Attempt {{ $o->number }}</a>
                @endforeach
            </div>
        </div>

        <div class="mt-6 grid grid-cols-2 gap-3 sm:grid-cols-4">
            <x-stat label="Status" :value="$attempt->statusLabel()" />
            <x-stat label="Score" :value="$attempt->percent === null ? $num($attempt->score).' marks' : $num($attempt->percent).'%'" :note="$num($attempt->score).' of '.$num($attempt->max_score).' marks'" />
            <x-stat label="Time used" :value="$attempt->time_used_seconds ? $attempt->timeUsedLabel() : '–'" :note="'Started '.$attempt->started_at->format('j M, g:ia')" />
            <x-stat label="Connection" :value="$attempt->disconnections.' drops'" :note="$attempt->focus_losses.' times left the screen'" />
        </div>
        @if ($concerns = $attempt->concerns())
            <p class="mt-3 rounded-xl bg-gold/20 px-4 py-2 text-sm"><strong>Worth a look:</strong> {{ implode('; ', $concerns) }}. These are signals, not proof.</p>
        @endif

        @if ($attempt->status === 'in_progress')
            <form method="POST" action="{{ route('exams.manage.extend', [$exam, $attempt]) }}" class="card-pad mt-4 grid gap-3 sm:grid-cols-[8rem_1fr_auto] sm:items-end">@csrf
                <div><label for="minutes" class="label">Extra minutes</label><input id="minutes" type="number" name="minutes" min="1" max="240" value="10" class="input"></div>
                <div><label for="reason" class="label">Reason</label><input id="reason" name="reason" class="input" required maxlength="200" placeholder="Power cut at the venue"></div>
                <button class="btn-dark">Give more time</button>
                <p class="hint sm:col-span-3">Deadline now {{ $attempt->deadline_at->format('g:i:sa') }}. The change is recorded in the audit trail.</p>
            </form>
        @endif

        <form method="POST" action="{{ route('exams.manage.mark', [$exam, $attempt]) }}">@csrf
            <h2 class="h-section mt-8">Answers</h2>
            <ol class="mt-3 space-y-3">
                @foreach ($attempt->questions as $item)
                    <li class="card-pad">
                        <div class="flex items-start gap-3">
                            <span @class(['flex size-8 shrink-0 items-center justify-center rounded-full text-sm font-bold', 'bg-ok/15 text-ok' => $item->is_correct === true, 'bg-curtain/10 text-curtain' => $item->is_correct === false, 'bg-gold/30 text-warn' => $item->is_correct === null])>{{ $item->position }}</span>
                            <div class="min-w-0 flex-1">
                                <p class="font-semibold">{{ $item->snapshot['stem'] }}</p>
                                <p class="mt-1 text-xs text-ink-soft">{{ \App\Models\Question::TYPES[$item->snapshot['type']] ?? '' }} · {{ $item->seconds_spent }} s{{ $item->is_flagged ? ' · flagged by the candidate' : '' }}</p>
                                <p class="mt-2 whitespace-pre-line text-sm"><span class="text-ink-soft">Answer:</span> {{ $item->responseText() }}</p>
                                @if ($item->snapshot['type'] !== 'open')<p class="text-sm"><span class="text-ink-soft">Correct:</span> {{ \App\Models\Question::answerText($item->snapshot) }}</p>
                                @elseif ($item->snapshot['explanation'] ?? null)<p class="mt-2 rounded-lg bg-paper-2 px-3 py-2 text-sm"><span class="font-semibold">Marking guide:</span> {{ $item->snapshot['explanation'] }}</p>@endif
                            </div>
                            <div class="shrink-0 text-right text-sm">
                                @if ($item->snapshot['type'] === 'open' && $attempt->isFinished() && $item->isAnswered())
                                    <label class="sr-only" for="m{{ $item->position }}">Marks for question {{ $item->position }}</label>
                                    <input id="m{{ $item->position }}" type="number" step="0.5" min="0" max="{{ $item->snapshot['marks'] }}" name="marks[{{ $item->position }}]" value="{{ $item->marks_awarded !== null ? $num($item->marks_awarded) : '' }}" class="input w-20 text-right"> <span class="text-ink-soft">/ {{ $item->snapshot['marks'] }}</span>
                                    @if ($item->marker)<p class="mt-1 text-xs text-ink-soft">{{ $item->marker->name }}</p>@endif
                                @else
                                    <span class="font-semibold">{{ $item->marks_awarded === null ? '–' : $num($item->marks_awarded) }}</span><span class="text-ink-soft"> / {{ $item->snapshot['marks'] }}</span>
                                @endif
                            </div>
                        </div>
                    </li>
                @endforeach
            </ol>
            @if ($attempt->isFinished() && $attempt->questions->contains(fn ($i) => $i->snapshot['type'] === 'open' && $i->isAnswered()))
                <div class="sticky bottom-20 mt-4 flex justify-end lg:bottom-4"><button class="btn-primary shadow-lg">Save marks</button></div>
            @endif
        </form>

        <h2 class="h-section mt-10">Audit trail</h2>
        <div class="card mt-3 overflow-x-auto">
            <table class="table min-w-[560px]">
                <thead><tr><th>Time</th><th>Event</th><th>Details</th></tr></thead>
                <tbody>
                    @foreach ($attempt->events->reject(fn ($e) => $e->type === 'presented') as $e)
                        <tr>
                            <td class="whitespace-nowrap text-sm">{{ $e->created_at->format('j M g:i:sa') }}</td>
                            <td class="text-sm font-semibold">{{ $e->label() }}</td>
                            <td class="text-xs text-ink-soft">{{ collect($e->data)->map(fn ($v, $k) => str_replace('_', ' ', $k).': '.(is_bool($v) ? ($v ? 'yes' : 'no') : (is_array($v) ? json_encode($v) : $v)))->join(' · ') }}{{ $e->user && $e->user->member_id !== $attempt->member_id ? ' · by '.$e->user->name : '' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <p class="mt-2 text-xs text-ink-soft">Device: {{ \Illuminate\Support\Str::limit($attempt->user_agent, 120) }} · {{ $attempt->ip }}</p>
    </section>
</x-layouts.app>
