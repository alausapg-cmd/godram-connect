@php
    $ytLive = $session->streamYoutubeId();
    $moderateBase = $canTeach ? route('academy.manage.questions.moderate', [$course, 0]) : null;
@endphp
<x-layouts.app :title="'Live class: '.$session->title" :description="$course->title">
    <section class="relative isolate overflow-hidden bg-stage text-paper">
        <div class="hero-shade absolute inset-0 -z-10" aria-hidden="true"></div>
        <div class="container-page py-6">
            <a href="{{ route('academy.show', $course) }}" class="inline-flex items-center gap-1 text-sm font-semibold text-gold no-underline"><x-icon name="arrow-left" class="size-4" /> {{ $course->title }}</a>
            <div class="mt-3 flex flex-wrap items-end justify-between gap-4">
                <div>
                    <p class="eyebrow flex items-center gap-2 text-gold">Live Training Room
                        @if ($session->isLive())<span class="badge bg-curtain text-white"><span class="size-1.5 animate-pulse rounded-full bg-white"></span> Live now</span>
                        @elseif ($session->isOver())<span class="badge bg-white/10 text-paper">Ended</span>@endif
                    </p>
                    <h1 class="mt-1 font-display text-3xl font-semibold uppercase leading-tight sm:text-4xl">{{ $session->title }}</h1>
                    <p class="mt-1 text-sm text-paper/70">{{ $session->live_at?->format('l j F, g:ia') }}@if ($session->platformLabel()) · {{ $session->platformLabel() }}@endif</p>
                </div>
                <div class="flex items-center gap-4 text-sm" x-data>
                    @if ($attendance)
                        <span class="flex items-center gap-1.5 text-paper/80"><x-icon name="check-circle" class="size-4 text-gold" /> {{ $attendance->status === 'attended' ? 'Attendance confirmed' : 'You are marked as joined' }}</span>
                    @endif
                </div>
            </div>
        </div>
    </section>

    <section class="container-page mt-6 grid gap-6 lg:grid-cols-[1fr_380px]"
             x-data="liveRoom(@js(route('academy.feed', [$course, $session])), @js(route('academy.ask', $course)), {{ $session->id }})">
        <div class="min-w-0 space-y-6">
            {{-- The class itself --}}
            @if ($session->isOver() && $session->replay_youtube_id)
                <div><h2 class="h-section">Recording</h2><x-youtube :id="$session->replay_youtube_id" :title="$session->title" class="mt-3" /></div>
            @elseif ($ytLive && ($session->isLive() || $canTeach))
                <x-youtube :id="$ytLive" :title="$session->title" />
            @elseif ($session->live_url && ! $session->isOver())
                <div class="card-pad flex flex-wrap items-center justify-between gap-4 {{ $session->isLive() ? 'border-curtain' : '' }}">
                    <div>
                        <p class="font-semibold">{{ $session->isLive() ? 'The class has started' : 'The class opens '.$session->live_at->diffForHumans() }}</p>
                        <p class="text-sm text-ink-soft">It is held on {{ $session->platformLabel() ?? 'another platform' }}. Keep this page open beside it to respond and ask questions.</p>
                    </div>
                    <a href="{{ $session->live_url }}" target="_blank" rel="noopener" class="{{ $session->isLive() ? 'btn-primary' : 'btn-ghost' }} no-underline"><x-icon name="live" class="size-4" /> Join on {{ $session->platformLabel() ?? 'the platform' }}</a>
                </div>
            @elseif ($session->isOver())
                <x-empty title="This class has ended">The recording will appear here if the facilitators add one. The questions and answers below are kept for reference.</x-empty>
            @else
                <x-empty title="Class link coming soon">The facilitators will add the link before the class starts on {{ $session->live_at?->format('l j F') }}.</x-empty>
            @endif

            @if ($session->summary)
                <div class="card-pad"><h2 class="h-section">About this class</h2><p class="mt-2">{{ $session->summary }}</p></div>
            @endif

            @if ($session->resources->isNotEmpty())
                <div><h2 class="h-section">Resources and downloads</h2>@include('academy.partials.resources', ['resources' => $session->resources])</div>
            @endif

            @if ($session->lessons->isNotEmpty())
                <div>
                    <h2 class="h-section">Lessons for this session</h2>
                    <ul class="mt-3 grid gap-2 sm:grid-cols-2">
                        @foreach ($session->lessons as $l)
                            <li><a href="{{ route('academy.lesson', [$course, $l]) }}" class="card flex items-center gap-3 p-3 no-underline"><x-icon :name="$l->icon()" class="size-5 text-poster" /><span class="font-medium text-ink">{{ $l->title }}</span></a></li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @if ($canTeach)
                <div class="card-pad">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <h2 class="h-section">Facilitator controls</h2>
                        <span class="text-sm text-ink-soft"><span class="font-semibold text-ink" x-text="joined">0</span> joined so far</span>
                    </div>
                    <p class="mt-1 text-sm text-ink-soft">Put a question to the class, then show the results when everyone has answered. Attendance here counts people who opened this room during the class; confirm it on the participants page afterwards.</p>
                    <ul class="mt-4 divide-y divide-line">
                        @forelse ($session->prompts as $prompt)
                            <li class="flex flex-wrap items-center gap-3 py-3">
                                <span class="min-w-0 flex-1"><span class="block font-medium">{{ $prompt->question }}</span><span class="text-xs text-ink-soft">{{ $prompt->typeLabel() }} · {{ $prompt->responses()->count() }} responses</span></span>
                                <form method="POST" action="{{ route('academy.manage.prompts.update', [$course, $prompt]) }}">@csrf<input type="hidden" name="action" value="live"><button class="{{ $prompt->is_live ? 'btn-primary' : 'btn-ghost' }} btn-sm">{{ $prompt->is_live ? 'Close' : 'Ask now' }}</button></form>
                                <form method="POST" action="{{ route('academy.manage.prompts.update', [$course, $prompt]) }}">@csrf<input type="hidden" name="action" value="results"><button class="btn-ghost btn-sm">{{ $prompt->show_results ? 'Hide results' : 'Show results' }}</button></form>
                            </li>
                        @empty
                            <li class="py-3 text-sm text-ink-soft">No questions prepared yet. Add one below.</li>
                        @endforelse
                    </ul>
                    @include('academy.manage.partials.prompt-form', ['sessionId' => $session->id, 'compact' => true])
                </div>
            @endif
        </div>

        {{-- Respond and ask --}}
        <aside class="space-y-4 lg:sticky lg:top-24 lg:self-start">
            <div class="card-pad">
                <h2 class="h-section flex items-center gap-2"><x-icon name="hand" class="size-5 text-poster" /> Respond</h2>
                <template x-if="prompts.length === 0"><p class="mt-2 text-sm text-ink-soft">When the facilitator asks the class something, it appears here.</p></template>
                <template x-for="prompt in prompts" :key="prompt.id">
                    <div class="mt-4 rounded-xl border border-line p-3">
                        <p class="text-[11px] font-semibold uppercase tracking-wide text-poster" x-text="prompt.typeLabel"></p>
                        <p class="mt-0.5 font-semibold" x-text="prompt.question"></p>
                        <template x-if="prompt.open && prompt.choices.length">
                            <div class="mt-3 grid gap-2">
                                <template x-for="choice in prompt.choices" :key="choice">
                                    <button type="button" @click="respond(prompt, choice)" class="rounded-lg border px-3 py-2 text-left text-sm" :class="prompt.mine === choice ? 'border-stage bg-stage text-paper' : 'border-line hover:border-ink-soft'" x-text="choice"></button>
                                </template>
                            </div>
                        </template>
                        <template x-if="prompt.open && !prompt.choices.length">
                            <form class="mt-3 flex gap-2" @submit.prevent="respond(prompt)">
                                <input class="input" x-model="drafts[prompt.id]" :placeholder="prompt.mine ? 'You said: ' + prompt.mine : 'Your response'" maxlength="300">
                                <button class="btn-dark btn-sm shrink-0">Send</button>
                            </form>
                        </template>
                        <template x-if="prompt.tally">
                            <ul class="mt-3 space-y-1.5">
                                <template x-for="choice in prompt.choices" :key="choice">
                                    <li><div class="flex justify-between text-xs"><span x-text="choice"></span><span x-text="pct(prompt.tally, choice) + '%'"></span></div><div class="h-1.5 rounded-full bg-paper-2"><div class="h-full rounded-full bg-poster" :style="`width: ${pct(prompt.tally, choice)}%`"></div></div></li>
                                </template>
                            </ul>
                        </template>
                        <p class="mt-2 text-xs text-ink-soft"><span x-text="prompt.responses"></span> responded<span x-show="prompt.answer"> · Answer: <span class="font-semibold text-ok" x-text="prompt.answer"></span></span><span x-show="prompt.mine && !prompt.open"> · You said <span class="font-semibold" x-text="prompt.mine"></span></span></p>
                    </div>
                </template>
            </div>

            <div class="card-pad">
                <h2 class="h-section flex items-center gap-2"><x-icon name="message" class="size-5 text-poster" /> Questions</h2>
                <form class="mt-3 flex gap-2" @submit.prevent="ask()">
                    <label for="room-q" class="sr-only">Your question</label>
                    <input id="room-q" x-model="question" class="input" maxlength="1000" placeholder="Ask the facilitators">
                    <button class="btn-dark btn-sm shrink-0" :disabled="asking">Ask</button>
                </form>
                <p class="mt-2 text-xs text-ink-soft" x-show="note" x-text="note"></p>
                <ul class="mt-4 max-h-[50vh] space-y-3 overflow-y-auto">
                    <template x-for="q in questions" :key="q.id">
                        <li class="rounded-xl bg-paper-2/70 p-3 text-sm" :class="q.featured && 'ring-1 ring-gold'">
                            <p><span class="font-semibold" x-text="q.who"></span> <span class="text-xs text-ink-soft" x-text="q.at"></span></p>
                            <p class="mt-0.5" x-text="q.body"></p>
                            <p x-show="q.answer" class="mt-2 border-l-2 border-poster pl-2"><span class="text-xs font-semibold uppercase text-poster">Answer</span><br><span x-text="q.answer"></span></p>
                            @if ($canTeach)
                                <div class="mt-2 flex flex-wrap gap-2" x-data="{ reply: '' }">
                                    <template x-if="!q.answer">
                                        <form class="flex w-full gap-2" @submit.prevent="postJson('{{ $moderateBase }}'.replace(/0$/, q.id), { action: 'answer', answer: reply }).then(() => { reply = ''; load(); })">
                                            <input x-model="reply" class="input !min-h-9 text-sm" placeholder="Answer">
                                            <button class="btn-dark btn-sm shrink-0">Reply</button>
                                        </form>
                                    </template>
                                    <button type="button" class="text-xs font-semibold text-ink-soft underline" @click="postJson('{{ $moderateBase }}'.replace(/0$/, q.id), { action: 'feature' }).then(() => load())" x-text="q.featured ? 'Unfeature' : 'Feature'"></button>
                                    <button type="button" class="text-xs font-semibold text-ink-soft underline" @click="postJson('{{ $moderateBase }}'.replace(/0$/, q.id), { action: 'hide' }).then(() => load())">Hide</button>
                                </div>
                            @endif
                        </li>
                    </template>
                </ul>
            </div>
        </aside>
    </section>
</x-layouts.app>
