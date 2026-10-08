<x-layouts.app :title="'Build: '.$course->title">
    <section class="container-page mt-8">
        @include('academy.manage.partials.header')

        <div class="mt-6 grid grid-cols-2 gap-3 lg:grid-cols-4">
            <x-stat label="Enrolled" :value="$enrolled" />
            <x-stat label="Lessons" :value="$course->sessions->sum(fn ($s) => $s->lessons->count())" />
            <x-stat label="Assignments to review" :value="$toReview" />
            <x-stat label="Questions to answer" :value="$toAnswer" />
        </div>

        <div class="mt-8 grid gap-8 lg:grid-cols-[1fr_320px]">
            <div class="space-y-4">
                <h2 class="h-section">Sessions and lessons</h2>
                @foreach ($course->sessions as $s)
                    <div class="card overflow-hidden">
                        <div class="flex flex-wrap items-center gap-3 border-b border-line bg-paper-2/60 px-4 py-3">
                            <div class="min-w-0 flex-1">
                                <p class="text-xs font-semibold uppercase tracking-wide text-poster">Session {{ $loop->iteration }}@if ($s->live_at) · Live {{ $s->live_at->format('D j M, g:ia') }}@endif</p>
                                <p class="font-display text-lg font-semibold uppercase text-stage">{{ $s->title }}</p>
                            </div>
                            <div class="flex items-center gap-1">
                                @if ($s->live_at)<a href="{{ route('academy.room', [$course, $s]) }}" class="btn-ghost btn-sm no-underline"><x-icon name="live" class="size-4" /> Room</a>@endif
                                @unless ($loop->first)<form method="POST" action="{{ route('academy.manage.sessions.move', [$course, $s]) }}">@csrf<input type="hidden" name="direction" value="up"><button class="p-2 text-ink-soft hover:text-ink" aria-label="Move up">↑</button></form>@endunless
                                @unless ($loop->last)<form method="POST" action="{{ route('academy.manage.sessions.move', [$course, $s]) }}">@csrf<input type="hidden" name="direction" value="down"><button class="p-2 text-ink-soft hover:text-ink" aria-label="Move down">↓</button></form>@endunless
                            </div>
                        </div>
                        <ul class="divide-y divide-line">
                            @foreach ($s->lessons as $l)
                                <li class="flex items-center gap-3 px-4 py-2.5">
                                    <x-icon :name="$l->icon()" class="size-4 shrink-0 text-poster" />
                                    <a href="{{ route('academy.manage.lessons.edit', [$course, $l]) }}" class="min-w-0 flex-1 truncate font-medium">{{ $l->title }}</a>
                                    @if ($l->is_preview)<span class="badge badge-info">Preview</span>@endif
                                    @unless ($loop->first)<form method="POST" action="{{ route('academy.manage.lessons.move', [$course, $l]) }}">@csrf<input type="hidden" name="direction" value="up"><button class="px-1 text-ink-soft hover:text-ink" aria-label="Move up">↑</button></form>@endunless
                                    @unless ($loop->last)<form method="POST" action="{{ route('academy.manage.lessons.move', [$course, $l]) }}">@csrf<input type="hidden" name="direction" value="down"><button class="px-1 text-ink-soft hover:text-ink" aria-label="Move down">↓</button></form>@endunless
                                </li>
                            @endforeach
                        </ul>
                        <div class="flex flex-wrap items-center gap-2 border-t border-line px-4 py-3">
                            <a href="{{ route('academy.manage.lessons.create', [$course, 'session' => $s->id]) }}" class="btn-dark btn-sm no-underline"><x-icon name="plus" class="size-4" /> Add a lesson</a>
                            <details class="w-full">
                                <summary class="cursor-pointer text-sm font-semibold text-ink-soft">Edit session and live class</summary>
                                <form method="POST" action="{{ route('academy.manage.sessions.update', [$course, $s]) }}" class="mt-3">@csrf @method('PUT')
                                    @include('academy.manage.partials.session-fields', ['s' => $s])
                                    <div class="mt-3 flex flex-wrap gap-2"><button class="btn-dark btn-sm">Save session</button></div>
                                </form>
                                @if ($canManage && $s->lessons->isEmpty())
                                    <form method="POST" action="{{ route('academy.manage.sessions.destroy', [$course, $s]) }}" class="mt-2" onsubmit="return confirm('Remove this session?')">@csrf @method('DELETE')<button class="text-xs font-semibold text-curtain underline">Remove session</button></form>
                                @endif
                                @if ($s->live_at)
                                    <div class="mt-4">
                                        <p class="text-sm font-semibold">Questions for the live class ({{ $s->prompts->count() }})</p>
                                        @include('academy.manage.partials.prompt-form', ['sessionId' => $s->id])
                                    </div>
                                @endif
                            </details>
                        </div>
                    </div>
                @endforeach

                <details class="card-pad" @if ($course->sessions->isEmpty()) open @endif>
                    <summary class="cursor-pointer font-semibold"><x-icon name="plus" class="inline size-4" /> Add a session</summary>
                    <form method="POST" action="{{ route('academy.manage.sessions.store', $course) }}" class="mt-4">@csrf
                        @include('academy.manage.partials.session-fields', ['s' => null])
                        @error('title')<p class="error">{{ $message }}</p>@enderror @error('live_platform')<p class="error">{{ $message }}</p>@enderror
                        <button class="btn-primary btn-sm mt-3">Add session</button>
                    </form>
                </details>
            </div>

            <div class="space-y-6">
                <div class="card-pad">
                    <h2 class="h-section">Assignments</h2>
                    <ul class="mt-3 space-y-2">
                        @forelse ($course->assignments as $a)
                            <li class="rounded-xl bg-paper-2/60 p-3 text-sm"><p class="font-semibold">{{ $a->title }}</p><p class="text-xs text-ink-soft">{{ $a->due_at ? 'Due '.$a->due_at->format('j M, g:ia') : 'No deadline' }} · {{ collect($a->accepts)->map(fn ($k) => \App\Models\Assignment::ACCEPTS[$k] ?? $k)->join(', ') }}</p></li>
                        @empty
                            <li class="text-sm text-ink-soft">No assignments yet.</li>
                        @endforelse
                    </ul>
                    <details class="mt-4">
                        <summary class="cursor-pointer text-sm font-semibold">Add an assignment</summary>
                        <form method="POST" action="{{ route('academy.manage.assignments.store', $course) }}" class="mt-3 space-y-3">@csrf
                            <div><label class="label" for="a-title">Title</label><input id="a-title" name="title" class="input" required maxlength="150" placeholder="Write a three-minute monologue"></div>
                            <div><label class="label" for="a-brief">What to do</label><textarea id="a-brief" name="brief" rows="5" class="input" required></textarea></div>
                            <div><p class="label">Members hand in</p>@foreach (\App\Models\Assignment::ACCEPTS as $k => $l)<label class="flex items-center gap-2 text-sm"><input type="checkbox" name="accepts[]" value="{{ $k }}" class="size-4 accent-curtain" @checked($k === 'text')> {{ $l }}</label>@endforeach @error('accepts')<p class="error">{{ $message }}</p>@enderror</div>
                            <div class="grid grid-cols-2 gap-2"><div><label class="label" for="a-due">Due</label><input id="a-due" type="datetime-local" name="due_at" class="input"></div><div><label class="label" for="a-max">Marked out of</label><input id="a-max" type="number" name="max_score" min="1" class="input"></div></div>
                            <div><label class="label" for="a-s">Session</label><select id="a-s" name="course_session_id" class="input"><option value="">Whole training</option>@foreach ($course->sessions as $s)<option value="{{ $s->id }}">{{ $s->title }}</option>@endforeach</select></div>
                            <button class="btn-dark btn-sm">Add assignment</button>
                        </form>
                    </details>
                </div>

                <div class="card-pad">
                    <h2 class="h-section">Resources for the whole training</h2>
                    @php $general = $course->resources->whereNull('lesson_id')->whereNull('course_session_id'); @endphp
                    @if ($general->isNotEmpty())@include('academy.partials.resources', ['resources' => $general, 'manage' => $course])@else<p class="mt-2 text-sm text-ink-soft">Handbooks, scripts and reading lists go here.</p>@endif
                    @include('academy.manage.partials.resource-form', [])
                </div>

                @if ($course->facilitators->isNotEmpty())
                    <div class="card-pad">
                        <h2 class="h-section">Facilitators</h2>
                        <ul class="mt-3 space-y-2">@foreach ($course->facilitators as $f)<li class="flex items-center gap-2 text-sm"><x-avatar :member="$f->member" size="size-8" /><span><span class="block font-medium">{{ $f->member->full_name }}</span><span class="text-xs text-ink-soft">{{ $f->title ?? 'Facilitator' }}</span></span></li>@endforeach</ul>
                    </div>
                @endif
            </div>
        </div>
    </section>
</x-layouts.app>
