@php
    $lessons = $course->orderedLessons();
    $position = $lessons->search(fn ($l) => $l->id === $lesson->id) + 1;
    $done = in_array($lesson->id, $progress['doneIds']);
    $facilitator = $course->facilitators->first();
    $sessionNumber = $course->sessions->search(fn ($s) => $s->id === $lesson->course_session_id) + 1;
@endphp
<x-layouts.app :title="$lesson->title.' · '.$course->title" :description="$lesson->summary ?? $course->summary">
    {{-- Progress bar across the top of the lesson --}}
    <div class="border-b border-line bg-white">
        <div class="container-page flex items-center gap-4 py-3">
            <a href="{{ route('academy.show', $course) }}" class="flex min-w-0 items-center gap-2 text-sm font-semibold text-ink no-underline"><x-icon name="arrow-left" class="size-4 shrink-0" /><span class="truncate">{{ $course->title }}</span></a>
            <div class="ml-auto flex shrink-0 items-center gap-3 text-xs font-semibold text-ink-soft">
                <span class="hidden sm:inline">Lesson {{ $position }} of {{ $lessons->count() }}</span>
                @if ($enrolment)
                    <span class="hidden h-1.5 w-32 overflow-hidden rounded-full bg-paper-2 sm:block"><span class="block h-full rounded-full bg-curtain" style="width: {{ $progress['percent'] }}%"></span></span>
                    <span>{{ $progress['done'] }} / {{ $progress['total'] }} done</span>
                @endif
            </div>
        </div>
    </div>

    <div class="container-page mt-6 grid gap-8 lg:grid-cols-[280px_1fr]">
        {{-- Outline --}}
        <aside class="order-2 lg:order-1">
            <details class="card lg:sticky lg:top-24" x-data x-init="if (window.innerWidth >= 1024) $el.open = true">
                <summary class="flex cursor-pointer items-center justify-between px-4 py-3 font-semibold lg:pointer-events-none"><span class="flex items-center gap-2"><x-icon name="list" class="size-4 text-poster" /> Programme</span><x-icon name="chevron-right" class="size-4 lg:hidden" /></summary>
                <nav class="max-h-[70vh] overflow-y-auto border-t border-line pb-2" aria-label="Lessons">
                    @foreach ($course->sessions as $session)
                        <p class="px-4 pb-1 pt-3 text-[11px] font-semibold uppercase tracking-wide text-ink-soft">{{ $loop->iteration }}. {{ $session->title }}</p>
                        @foreach ($session->lessons as $l)
                            @php $isDone = in_array($l->id, $progress['doneIds']); $locked = ! $enrolment && ! $canTeach && ! $l->is_preview; @endphp
                            <a href="{{ $locked ? route('academy.show', $course).'#enrol' : route('academy.lesson', [$course, $l]) }}" @class(['flex items-center gap-2 px-4 py-2 text-sm no-underline', 'bg-gold/15 font-semibold text-stage' => $l->id === $lesson->id, 'text-ink hover:bg-paper-2/60' => $l->id !== $lesson->id])>
                                <span @class(['flex size-5 shrink-0 items-center justify-center rounded-full', 'bg-ok text-white' => $isDone, 'border border-line text-ink-soft' => ! $isDone])>@if ($isDone)<x-icon name="check" class="size-3" />@elseif ($locked)<x-icon name="lock" class="size-3" />@endif</span>
                                <span class="min-w-0 flex-1 truncate">{{ $l->title }}</span>
                            </a>
                        @endforeach
                    @endforeach
                </nav>
            </details>
        </aside>

        <article class="order-1 min-w-0 lg:order-2">
            <header>
                <p class="eyebrow">Session {{ $sessionNumber }} · {{ $lesson->session->title }}</p>
                <h1 class="mt-2 font-display text-3xl font-semibold uppercase leading-tight text-stage sm:text-5xl">{{ $lesson->title }}</h1>
                @if ($lesson->summary)<p class="mt-3 max-w-3xl font-serif text-xl text-ink-soft">{{ $lesson->summary }}</p>@endif
                <div class="mt-5 flex flex-wrap items-center gap-x-5 gap-y-3 border-y border-line py-3 text-sm">
                    @if ($facilitator)
                        <span class="flex items-center gap-2"><x-avatar :member="$facilitator->member" size="size-9" /><span><span class="block font-semibold leading-tight">{{ $facilitator->member->full_name }}</span><span class="text-xs text-ink-soft">{{ $facilitator->title ?? 'Facilitator' }}</span></span></span>
                    @endif
                    <span class="flex items-center gap-1.5 text-ink-soft"><x-icon :name="$lesson->icon()" class="size-4" /> {{ $lesson->kindLabel() }}</span>
                    @if ($lesson->minutes)<span class="flex items-center gap-1.5 text-ink-soft"><x-icon name="clock" class="size-4" /> {{ $lesson->minutes }} min</span>@endif
                    @if ($done)<span class="ml-auto badge badge-ok"><x-icon name="check" class="size-3.5" /> Completed</span>@endif
                </div>
            </header>

            @if ($lesson->youtube_id)
                <x-youtube :id="$lesson->youtube_id" :title="$lesson->title" class="mt-6" />
                <p class="mt-2 text-xs text-ink-soft">The video loads only when you press play, to save data. On a weak connection, choose a lower quality in the player's settings.</p>
            @endif

            @if ($lesson->audio_path)
                <div class="mt-6 flex items-center gap-4 rounded-2xl bg-stage p-4 text-paper sm:p-5" x-data="resumeAudio('{{ $lesson->id }}')">
                    <span class="flex size-12 shrink-0 items-center justify-center rounded-full bg-gold text-stage"><x-icon name="headphones" class="size-6" /></span>
                    <div class="min-w-0 flex-1">
                        <p class="text-xs font-semibold uppercase tracking-wide text-gold">Listen</p>
                        <audio controls preload="none" class="mt-1 w-full" src="{{ route('academy.audio', [$course, $lesson]) }}"></audio>
                        <p class="mt-1 text-xs text-paper/60">Picks up where you left off.</p>
                    </div>
                </div>
            @endif

            @if ($lesson->scripture)
                <blockquote class="mt-8 border-l-4 border-gold bg-gold/10 px-5 py-4 font-serif text-lg italic">
                    <x-icon name="book" class="mb-1 size-4 text-poster" />
                    {{ $lesson->scripture }}
                </blockquote>
            @endif

            @if ($lesson->body)
                <div class="prose-godram mt-8 max-w-3xl">{!! $lesson->bodyHtml() !!}</div>
            @endif

            @if ($lesson->keyPointList())
                <section class="mt-10 rounded-2xl bg-stage p-6 text-paper">
                    <h2 class="font-display text-xl font-semibold uppercase text-gold">Key points</h2>
                    <ul class="mt-4 space-y-3">
                        @foreach ($lesson->keyPointList() as $point)
                            <li class="flex gap-3"><span class="mt-1 flex size-5 shrink-0 items-center justify-center rounded-full bg-gold font-display text-xs font-semibold text-stage">{{ $loop->iteration }}</span><span>{{ $point }}</span></li>
                        @endforeach
                    </ul>
                </section>
            @endif

            @if ($lesson->prompts->isNotEmpty())
                <section class="mt-10">
                    <h2 class="h-section flex items-center gap-2"><x-icon name="hand" class="size-5 text-poster" /> Your turn</h2>
                    <div class="mt-4 space-y-4">
                        @foreach ($lesson->prompts as $prompt)
                            @php $mine = $responses[$prompt->id] ?? null; @endphp
                            <div class="card-pad" x-data="callResponse(@js(route('academy.respond', [$course, $prompt])), @js($mine?->response))">
                                <p class="text-xs font-semibold uppercase tracking-wide text-poster">{{ $prompt->typeLabel() }}</p>
                                <p class="mt-1 text-lg font-semibold">{{ $prompt->question }}</p>
                                @auth
                                    <form class="mt-4" @submit.prevent="send()">
                                        @if ($prompt->hasChoices())
                                            <div class="grid gap-2 sm:grid-cols-2">
                                                @foreach ($prompt->choices() as $choice)
                                                    <label class="flex cursor-pointer items-center gap-3 rounded-xl border border-line px-4 py-3 has-[:checked]:border-stage has-[:checked]:bg-paper-2">
                                                        <input type="radio" value="{{ $choice }}" x-model="answer" class="size-4 accent-curtain" :disabled="sent && result?.correct !== false"> {{ $choice }}
                                                    </label>
                                                @endforeach
                                            </div>
                                        @elseif ($prompt->type === 'open')
                                            <textarea x-model="answer" rows="3" class="input" placeholder="Write your response" maxlength="1000"></textarea>
                                        @else
                                            <input x-model="answer" class="input" placeholder="{{ $prompt->type === 'complete' ? 'Complete the statement' : 'Your answer' }}" maxlength="300">
                                        @endif
                                        <div class="mt-3 flex flex-wrap items-center gap-3">
                                            <button class="btn-dark btn-sm" :disabled="busy" x-text="sent ? 'Answer again' : 'Send'">Send</button>
                                            <p class="error !mt-0" x-show="error" x-text="error"></p>
                                        </div>
                                    </form>
                                    <div x-show="result" x-cloak class="mt-4 rounded-xl p-3 text-sm" :class="result?.correct === true ? 'bg-ok/10 text-ok' : (result?.correct === false ? 'bg-curtain/10 text-curtain' : 'bg-paper-2 text-ink')">
                                        <p class="font-semibold" x-text="result?.correct === true ? 'Correct.' : (result?.correct === false ? 'Not quite. The answer is: ' + result.answer + '.' : 'Thank you. Your response is recorded.')"></p>
                                        @if ($prompt->explanation)<p class="mt-1 text-ink" x-show="result?.correct !== null">{{ $prompt->explanation }}</p>@endif
                                        <template x-if="result?.tally">
                                            <ul class="mt-3 space-y-1.5 text-ink">
                                                <template x-for="(n, choice) in result.tally" :key="choice">
                                                    <li><div class="flex justify-between text-xs"><span x-text="choice"></span><span x-text="n"></span></div><div class="h-1.5 rounded-full bg-white"><div class="h-full rounded-full bg-poster" :style="`width: ${Math.round(n / Math.max(1, Object.values(result.tally).reduce((a, b) => a + b, 0)) * 100)}%`"></div></div></li>
                                                </template>
                                            </ul>
                                        </template>
                                    </div>
                                    @if ($mine)<p class="mt-3 text-xs text-ink-soft" x-show="!result">You answered: <span class="font-semibold">{{ $mine->response }}</span>@if ($mine->is_correct === true) · correct @elseif ($mine->is_correct === false) · not quite @endif</p>@endif
                                @else
                                    <p class="mt-3 text-sm text-ink-soft"><a href="{{ route('login') }}" class="link">Sign in</a> to respond.</p>
                                @endauth
                            </div>
                        @endforeach
                    </div>
                </section>
            @endif

            @if ($lesson->resources->isNotEmpty())
                <section class="mt-10">
                    <h2 class="h-section">Download for this lesson</h2>
                    @include('academy.partials.resources', ['resources' => $lesson->resources])
                    <p class="mt-2 text-xs text-ink-soft">Download scripts and worksheets when you have a good connection; they open without data afterwards.</p>
                </section>
            @endif

            {{-- Continue --}}
            <nav class="mt-12 flex flex-col gap-3 border-t border-line pt-6 sm:flex-row sm:items-center" aria-label="Lesson navigation">
                @if ($previous)
                    <a href="{{ route('academy.lesson', [$course, $previous]) }}" class="btn-ghost no-underline sm:mr-auto"><x-icon name="arrow-left" class="size-4" /> Previous</a>
                @else
                    <span class="sm:mr-auto"></span>
                @endif
                @if ($enrolment)
                    <form method="POST" action="{{ route('academy.complete', [$course, $lesson]) }}">
                        @csrf
                        <button class="btn-primary w-full sm:w-auto">{{ $done ? ($next ? 'Next lesson' : 'Back to the programme') : ($next ? 'Complete and continue' : 'Complete the last lesson') }} <x-icon name="arrow-right" class="size-4" /></button>
                    </form>
                @elseif ($next && ($canTeach || $next->is_preview))
                    <a href="{{ route('academy.lesson', [$course, $next]) }}" class="btn-primary no-underline">Next lesson <x-icon name="arrow-right" class="size-4" /></a>
                @elseif (! $canTeach)
                    <a href="{{ route('academy.show', $course) }}#enrol" class="btn-primary no-underline">Enrol to continue</a>
                @endif
            </nav>

            @if ($enrolment || $canTeach || $questions->isNotEmpty())
                <section class="mt-12">
                    <h2 class="h-section">Questions about this lesson</h2>
                    @include('academy.partials.questions', ['questions' => $questions, 'canAsk' => (bool) ($enrolment || $canTeach), 'lessonId' => $lesson->id])
                </section>
            @endif
        </article>
    </div>
</x-layouts.app>
