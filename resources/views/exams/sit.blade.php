@php
    $exam = $attempt->exam;
    $config = [
        'attempt' => $attempt->id,
        'questions' => $questions,
        'now' => now()->getTimestampMs(),
        'deadline' => $attempt->deadline_at->getTimestampMs(),
        'csrf' => csrf_token(),
        'urls' => [
            'save' => route('exams.save', $attempt),
            'state' => route('exams.state', $attempt),
            'signal' => route('exams.signal', $attempt),
            'submit' => route('exams.submit', $attempt),
            'result' => route('exams.result', $attempt),
        ],
    ];
@endphp
<x-layouts.exam :title="$exam->title">
<div x-data="cbt(@js($config))" x-cloak class="flex min-h-screen flex-col">
    {{-- Top bar: title, timer and connection. Stays in view. --}}
    <header class="sticky top-0 z-30 bg-stage text-paper shadow-lg">
        <div class="mx-auto flex max-w-5xl items-center gap-3 px-4 py-2.5">
            <x-logo class="size-10" />
            <div class="min-w-0 flex-1">
                <p class="truncate font-display text-base font-semibold uppercase leading-tight sm:text-lg">{{ $exam->title }}</p>
                <p class="truncate text-xs text-paper/60">{{ $exam->course?->title ?? $exam->modeLabel().' examination' }} · Attempt {{ $attempt->number }}</p>
            </div>
            <div class="flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-semibold" :class="{ 'bg-ok/20 text-[#9be2b5]': state === 'saved', 'bg-white/10 text-paper/80': state === 'saving', 'bg-curtain text-white': state === 'offline' || state === 'retrying' }" role="status" aria-live="polite">
                <template x-if="state === 'offline'"><span class="flex items-center gap-1"><x-icon name="wifi-off" class="size-4" /> Offline, saved on phone</span></template>
                <template x-if="state === 'retrying'"><span class="flex items-center gap-1"><x-icon name="wifi-off" class="size-4" /> Reconnecting</span></template>
                <template x-if="state === 'saving'"><span class="flex items-center gap-1"><x-icon name="wifi" class="size-4" /> Saving</span></template>
                <template x-if="state === 'saved'"><span class="flex items-center gap-1"><x-icon name="check" class="size-4" /> Saved</span></template>
            </div>
            <div class="flex items-center gap-1.5 rounded-xl px-3 py-1.5 font-display text-xl font-semibold tabular-nums sm:text-2xl" :class="left <= 300 ? 'bg-curtain text-white' : 'bg-white/10 text-gold'" role="timer" :aria-label="'Time left ' + clock">
                <x-icon name="clock" class="size-5" /><span x-text="clock"></span>
            </div>
        </div>
        <div class="h-1 bg-white/10"><div class="h-full bg-gold transition-all" :style="'width:' + (answeredCount / total * 100) + '%'"></div></div>
    </header>

    <div x-show="notice" x-transition class="sticky top-[60px] z-20 mx-auto mt-2 w-full max-w-5xl px-4" role="alert">
        <div class="flex items-start gap-2 rounded-xl bg-gold px-4 py-3 text-sm font-semibold text-stage shadow"><x-icon name="alert" class="mt-0.5 size-4 shrink-0" /><span x-text="notice" class="flex-1"></span><button type="button" @click="notice = ''" class="shrink-0" aria-label="Close"><x-icon name="x" class="size-4" /></button></div>
    </div>

    <main class="mx-auto w-full max-w-5xl flex-1 px-4 pb-32 pt-5 lg:grid lg:grid-cols-[1fr_17rem] lg:gap-8">
        <section aria-labelledby="question-top">
            <div class="flex items-center justify-between gap-3">
                <h1 id="question-top" tabindex="-1" class="text-sm font-semibold uppercase tracking-wide text-ink-soft focus:outline-none">Question <span x-text="i + 1"></span> of <span x-text="total"></span></h1>
                <span class="text-xs text-ink-soft"><span x-text="q.marks"></span> <span x-text="q.marks == 1 ? 'mark' : 'marks'"></span></span>
            </div>

            <article class="card-pad mt-3">
                <template x-if="q.scenario"><p class="mb-4 whitespace-pre-line rounded-xl bg-paper-2 p-4 text-[15px] leading-relaxed" x-text="q.scenario"></p></template>
                <template x-if="q.media_kind === 'image' && q.media_url"><img :src="q.media_url" alt="Picture for this question" class="mb-4 max-h-80 w-full rounded-xl object-contain bg-paper-2"></template>
                <template x-if="q.media_kind === 'audio' && q.media_url"><audio :src="q.media_url" controls preload="none" class="mb-4 w-full"></audio></template>
                <template x-if="q.youtube_id"><div class="mb-4 aspect-video overflow-hidden rounded-xl bg-stage"><iframe :src="'https://www.youtube-nocookie.com/embed/' + q.youtube_id + '?rel=0'" title="Video for this question" class="size-full" allow="encrypted-media; picture-in-picture" allowfullscreen loading="lazy"></iframe></div></template>
                <p class="whitespace-pre-line text-lg font-semibold leading-snug text-ink" x-text="q.stem" id="stem"></p>

                <div class="mt-5">
                    {{-- One answer --}}
                    <template x-if="q.type === 'single'">
                        <div role="radiogroup" aria-labelledby="stem" class="space-y-2.5">
                            <template x-for="(opt, n) in q.options" :key="q.position + '-' + opt.key">
                                <label class="flex min-h-14 cursor-pointer items-center gap-3 rounded-xl border-2 px-4 py-3 transition-colors" :class="q.response === opt.key ? 'border-curtain bg-curtain/5' : 'border-line bg-white hover:border-ink-soft'">
                                    <input type="radio" :name="'q' + q.position" :value="opt.key" :checked="q.response === opt.key" @change="set(opt.key)" class="size-5 shrink-0 accent-curtain">
                                    <span class="flex size-7 shrink-0 items-center justify-center rounded-full bg-paper-2 text-sm font-bold uppercase" x-text="String.fromCharCode(65 + n)"></span>
                                    <span class="text-base" x-text="opt.text"></span>
                                </label>
                            </template>
                        </div>
                    </template>

                    {{-- Several answers --}}
                    <template x-if="q.type === 'multiple'">
                        <div>
                            <p class="mb-2 text-sm font-semibold text-poster">Choose all that apply.</p>
                            <div class="space-y-2.5" role="group" aria-labelledby="stem">
                                <template x-for="(opt, n) in q.options" :key="q.position + '-' + opt.key">
                                    <label class="flex min-h-14 cursor-pointer items-center gap-3 rounded-xl border-2 px-4 py-3 transition-colors" :class="(q.response || []).includes(opt.key) ? 'border-curtain bg-curtain/5' : 'border-line bg-white hover:border-ink-soft'">
                                        <input type="checkbox" :checked="(q.response || []).includes(opt.key)" @change="toggle(opt.key)" class="size-5 shrink-0 accent-curtain">
                                        <span class="text-base" x-text="opt.text"></span>
                                    </label>
                                </template>
                            </div>
                        </div>
                    </template>

                    {{-- True or false --}}
                    <template x-if="q.type === 'true_false'">
                        <div role="radiogroup" aria-labelledby="stem" class="grid grid-cols-2 gap-3">
                            <template x-for="choice in [true, false]" :key="q.position + '-' + choice">
                                <label class="flex min-h-16 cursor-pointer items-center justify-center gap-2 rounded-xl border-2 text-lg font-semibold" :class="q.response === choice ? 'border-curtain bg-curtain/5 text-curtain' : 'border-line bg-white'">
                                    <input type="radio" :name="'q' + q.position" :checked="q.response === choice" @change="set(choice)" class="sr-only">
                                    <span x-text="choice ? 'True' : 'False'"></span>
                                </label>
                            </template>
                        </div>
                    </template>

                    {{-- Typed answers --}}
                    <template x-if="q.type === 'short' || q.type === 'fill_blank'">
                        <div>
                            <label :for="'a' + q.position" class="label">Your answer</label>
                            <input :id="'a' + q.position" type="text" class="input text-lg" maxlength="300" autocomplete="off" :value="q.response ?? ''" @input.debounce.600ms="set($event.target.value)">
                        </div>
                    </template>
                    <template x-if="q.type === 'open'">
                        <div>
                            <label :for="'a' + q.position" class="label">Your answer</label>
                            <textarea :id="'a' + q.position" rows="8" class="input py-3 text-base" maxlength="5000" :value="q.response ?? ''" @input.debounce.800ms="set($event.target.value)"></textarea>
                            <p class="hint">An examiner will mark this answer.</p>
                        </div>
                    </template>

                    {{-- Put in order --}}
                    <template x-if="q.type === 'ordering'">
                        <div>
                            <p class="mb-2 text-sm font-semibold text-poster">Use the arrows to put these in the right order, first at the top.</p>
                            <ol class="space-y-2">
                                <template x-for="(key, n) in order()" :key="q.position + '-' + key">
                                    <li class="flex items-center gap-2 rounded-xl border-2 border-line bg-white py-2 pl-4 pr-2">
                                        <span class="w-6 shrink-0 font-display text-lg font-semibold text-curtain" x-text="n + 1"></span>
                                        <span class="flex-1 text-base" x-text="optionText(key)"></span>
                                        <button type="button" class="flex size-11 items-center justify-center rounded-lg bg-paper-2 disabled:opacity-30" :disabled="n === 0" @click="move(n, -1)" :aria-label="'Move ' + optionText(key) + ' up'">▲</button>
                                        <button type="button" class="flex size-11 items-center justify-center rounded-lg bg-paper-2 disabled:opacity-30" :disabled="n === order().length - 1" @click="move(n, 1)" :aria-label="'Move ' + optionText(key) + ' down'">▼</button>
                                    </li>
                                </template>
                            </ol>
                        </div>
                    </template>

                    {{-- Matching --}}
                    <template x-if="q.type === 'matching'">
                        <div class="space-y-3">
                            <p class="text-sm font-semibold text-poster">Choose the match for each item.</p>
                            <template x-for="left in q.options.left" :key="q.position + '-' + left.key">
                                <div class="rounded-xl border-2 border-line bg-white p-3">
                                    <label :for="'m' + q.position + left.key" class="block font-semibold" x-text="left.text"></label>
                                    <select :id="'m' + q.position + left.key" class="input mt-2" @change="match(left.key, $event.target.value)">
                                        <option value="">Choose…</option>
                                        <template x-for="right in q.options.right" :key="right.key"><option :value="right.key" x-text="right.text" :selected="(q.response || {})[left.key] === right.key"></option></template>
                                    </select>
                                </div>
                            </template>
                        </div>
                    </template>
                </div>

                <div class="mt-5 flex flex-wrap items-center gap-3 border-t border-line pt-4">
                    <button type="button" @click="flag()" class="btn-ghost btn-sm" :class="q.flagged && '!border-gold !bg-gold/20'" :aria-pressed="q.flagged">
                        <x-icon name="flag" class="size-4" /> <span x-text="q.flagged ? 'Flagged for review' : 'Flag for review'"></span>
                    </button>
                    <button type="button" x-show="isAnswered(q)" @click="clear()" class="text-sm font-semibold text-ink-soft underline">Clear my answer</button>
                </div>
            </article>
        </section>

        {{-- Question palette (side panel on large screens) --}}
        <aside class="mt-6 hidden lg:mt-0 lg:block">
            <div class="card-pad sticky top-24">@include('exams.partials.palette')</div>
        </aside>
    </main>

    {{-- Bottom bar: previous, palette, next or finish --}}
    <nav class="fixed inset-x-0 bottom-0 z-30 border-t border-line bg-white pb-[env(safe-area-inset-bottom)] shadow-[0_-4px_16px_rgba(0,0,0,0.06)]" aria-label="Questions">
        <div class="mx-auto flex max-w-5xl items-center gap-2 px-4 py-3">
            <button type="button" @click="go(i - 1)" :disabled="i === 0" class="btn-ghost flex-1 sm:flex-none"><x-icon name="arrow-left" class="size-4" /> Previous</button>
            <button type="button" @click="palette = true" class="btn-ghost lg:hidden" aria-label="All questions"><x-icon name="grid" class="size-4" /> <span x-text="answeredCount + '/' + total"></span></button>
            <div class="hidden flex-1 lg:block"></div>
            <button type="button" x-show="i < total - 1" @click="go(i + 1)" class="btn-dark flex-1 sm:flex-none">Next <x-icon name="arrow-right" class="size-4" /></button>
            <button type="button" x-show="i === total - 1" @click="confirming = true" class="btn-primary flex-1 sm:flex-none">Finish</button>
            <button type="button" x-show="i < total - 1" @click="confirming = true" class="btn-ghost hidden sm:inline-flex">Finish</button>
        </div>
    </nav>

    {{-- Palette sheet on phones --}}
    <div x-show="palette" x-transition.opacity class="fixed inset-0 z-40 bg-stage/60 lg:hidden" @click.self="palette = false" @keydown.escape.window="palette = false">
        <div class="absolute inset-x-0 bottom-0 max-h-[80vh] overflow-y-auto rounded-t-3xl bg-white p-5 pb-[calc(1.25rem+env(safe-area-inset-bottom))]" role="dialog" aria-modal="true" aria-label="All questions">
            <div class="mb-3 flex items-center justify-between"><p class="h-section">All questions</p><button type="button" @click="palette = false" class="rounded-full p-2" aria-label="Close"><x-icon name="x" /></button></div>
            @include('exams.partials.palette')
        </div>
    </div>

    {{-- Confirm finish --}}
    <div x-show="confirming" x-transition.opacity class="fixed inset-0 z-50 flex items-end justify-center bg-stage/70 p-4 sm:items-center" @keydown.escape.window="confirming = false">
        <div class="w-full max-w-md rounded-3xl bg-white p-6" role="alertdialog" aria-modal="true" aria-labelledby="finish-title">
            <h2 id="finish-title" class="h-section">Finish the examination?</h2>
            <dl class="mt-4 grid grid-cols-3 gap-2 text-center">
                <div class="rounded-xl bg-ok/10 p-3"><dt class="text-xs font-semibold text-ok">Answered</dt><dd class="font-display text-2xl font-semibold" x-text="answeredCount"></dd></div>
                <div class="rounded-xl p-3" :class="unansweredCount ? 'bg-curtain/10' : 'bg-paper-2'"><dt class="text-xs font-semibold" :class="unansweredCount ? 'text-curtain' : 'text-ink-soft'">Not answered</dt><dd class="font-display text-2xl font-semibold" x-text="unansweredCount"></dd></div>
                <div class="rounded-xl bg-gold/20 p-3"><dt class="text-xs font-semibold text-warn">Flagged</dt><dd class="font-display text-2xl font-semibold" x-text="flaggedCount"></dd></div>
            </dl>
            <p class="mt-4 text-sm text-ink-soft">Once you finish you cannot change your answers. You still have <strong x-text="clock"></strong>.</p>
            <div class="mt-5 flex flex-col gap-2 sm:flex-row-reverse">
                <button type="button" @click="submit(false)" :disabled="submitting" class="btn-primary flex-1"><span x-text="submitting ? 'Sending…' : 'Yes, finish now'"></span></button>
                <button type="button" @click="confirming = false" class="btn-ghost flex-1">Keep working</button>
            </div>
        </div>
    </div>

    <noscript><div class="fixed inset-0 z-50 flex items-center justify-center bg-paper p-6 text-center"><p>This examination needs JavaScript. Please turn it on in your browser, or use Chrome on your phone.</p></div></noscript>
</div>
</x-layouts.exam>
