@php
    $lessonNumber = 0;
    $enrolled = (bool) $enrolment;
@endphp
<x-layouts.app :title="$course->title" :description="$course->summary">
    <section class="relative isolate overflow-hidden bg-stage text-paper">
        @if ($course->coverUrl())
            <img src="{{ $course->coverUrl() }}" alt="" class="absolute inset-0 -z-10 size-full object-cover">
        @endif
        <div class="hero-shade absolute inset-0 -z-10" aria-hidden="true"></div>
        <div class="poster-stripe absolute inset-x-0 bottom-0 h-1.5" aria-hidden="true"></div>
        <div class="container-page relative py-10 sm:py-14">
            <a href="{{ route('academy') }}" class="text-sm font-semibold text-gold no-underline">GODRAM Virtual Academy</a>
            <p class="eyebrow mt-4 flex flex-wrap items-center gap-2 text-gold">{{ $course->levelLabel() }} · {{ $course->kindLabel() }}
                @if ($course->status !== 'published')<span class="badge bg-white/10 text-paper">{{ \App\Models\Course::STATUSES[$course->status] }}</span>@endif
            </p>
            <h1 class="mt-2 max-w-3xl font-display text-4xl font-semibold uppercase leading-tight sm:text-5xl">{{ $course->title }}</h1>
            <p class="mt-3 max-w-2xl text-lg text-paper/80">{{ $course->summary }}</p>
            <dl class="mt-6 grid max-w-3xl gap-4 text-sm sm:grid-cols-3">
                <div><dt class="text-paper/60">Run by</dt><dd class="font-semibold">{{ $course->orgUnit?->fullName() }}</dd></div>
                <div><dt class="text-paper/60">For</dt><dd class="font-semibold">{{ $course->audienceLabel() }}</dd></div>
                <div><dt class="text-paper/60">When</dt><dd class="font-semibold">
                    @if ($course->starts_on){{ $course->starts_on->format('j M Y') }}@if ($course->ends_on) to {{ $course->ends_on->format('j M Y') }}@endif @else Start any time @endif
                </dd></div>
            </dl>
            @if ($canTeach)
                <a href="{{ route('academy.manage.build', $course) }}" class="btn-sm mt-6 inline-flex min-h-9 items-center gap-2 rounded-full bg-white/10 px-4 text-xs font-semibold text-paper no-underline hover:bg-white/20"><x-icon name="edit" class="size-4" /> Manage this training</a>
            @endif
        </div>
    </section>

    <section class="container-page mt-8 grid gap-8 lg:grid-cols-[1fr_340px]">
        <div class="order-2 space-y-8 lg:order-1">
            @if ($course->description || $course->outcomeList())
                <div class="card-pad">
                    @if ($course->description)
                        <h2 class="h-section">About this training</h2>
                        <div class="mt-3 whitespace-pre-line leading-relaxed">{{ $course->description }}</div>
                    @endif
                    @if ($course->outcomeList())
                        <h3 class="mt-6 font-semibold">By the end you will be able to</h3>
                        <ul class="mt-3 grid gap-2 sm:grid-cols-2">
                            @foreach ($course->outcomeList() as $outcome)
                                <li class="flex gap-2 text-sm"><x-icon name="check" class="mt-0.5 size-4 shrink-0 text-ok" />{{ $outcome }}</li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            @endif

            <div>
                <h2 class="h-section">Programme</h2>
                <ol class="mt-4 space-y-4">
                    @foreach ($course->sessions as $session)
                        <li class="card overflow-hidden">
                            <div class="flex flex-wrap items-start justify-between gap-3 border-b border-line bg-paper-2/60 px-4 py-3 sm:px-5">
                                <div>
                                    <p class="text-xs font-semibold uppercase tracking-wide text-poster">Session {{ $loop->iteration }}</p>
                                    <h3 class="font-display text-lg font-semibold uppercase text-stage">{{ $session->title }}</h3>
                                    @if ($session->summary)<p class="text-sm text-ink-soft">{{ $session->summary }}</p>@endif
                                </div>
                                @if ($session->isLiveSession())
                                    <div class="text-right text-sm">
                                        @if ($session->isLive())
                                            <span class="badge bg-curtain text-white"><span class="size-1.5 animate-pulse rounded-full bg-white"></span> Live now</span>
                                        @else
                                            <span class="inline-flex items-center gap-1 font-semibold"><x-icon name="live" class="size-4 text-curtain" /> {{ $session->live_at->format('D j M, g:ia') }}</span>
                                        @endif
                                        @if ($enrolled || $canTeach)
                                            <a href="{{ route('academy.room', [$course, $session]) }}" class="{{ $session->isLive() ? 'btn-primary' : 'btn-ghost' }} btn-sm mt-2 no-underline">{{ $session->isLive() ? 'Join the live class' : ($session->isOver() ? ($session->replay_youtube_id ? 'Watch the recording' : 'Class notes') : 'Open the class room') }}</a>
                                        @endif
                                    </div>
                                @endif
                            </div>
                            @if ($session->lessons->isNotEmpty())
                                <ul class="divide-y divide-line">
                                    @foreach ($session->lessons as $lesson)
                                        @php
                                            $lessonNumber++;
                                            $done = in_array($lesson->id, $progress['doneIds']);
                                            $open = $enrolled || $canTeach || $lesson->is_preview;
                                            $isNext = $progress['next']?->id === $lesson->id && $enrolled;
                                        @endphp
                                        <li>
                                            <a href="{{ $open ? route('academy.lesson', [$course, $lesson]) : '#enrol' }}" @class(['flex items-center gap-3 px-4 py-3 no-underline sm:px-5', 'hover:bg-paper-2/50' => $open, 'bg-gold/10' => $isNext])>
                                                <span @class(['flex size-8 shrink-0 items-center justify-center rounded-full text-xs font-semibold', 'bg-ok text-white' => $done, 'bg-stage text-gold' => ! $done && $open, 'bg-paper-2 text-ink-soft' => ! $open])>
                                                    @if ($done)<x-icon name="check" class="size-4" />@elseif (! $open)<x-icon name="lock" class="size-3.5" />@else{{ $lessonNumber }}@endif
                                                </span>
                                                <span class="min-w-0 flex-1">
                                                    <span class="block font-medium text-ink">{{ $lesson->title }}</span>
                                                    <span class="flex flex-wrap items-center gap-x-2 text-xs text-ink-soft"><x-icon :name="$lesson->icon()" class="size-3.5" /> {{ $lesson->kindLabel() }}@if ($lesson->minutes) · {{ $lesson->minutes }} min @endif @if ($lesson->is_preview && ! $enrolled) · <span class="font-semibold text-poster">Free preview</span>@endif</span>
                                                </span>
                                                @if ($isNext)<span class="badge badge-warn">Up next</span>@endif
                                            </a>
                                        </li>
                                    @endforeach
                                </ul>
                            @elseif (! $session->isLiveSession())
                                <p class="px-5 py-4 text-sm text-ink-soft">Lessons for this session are being prepared.</p>
                            @endif
                        </li>
                    @endforeach
                    @php $exams = $course->exams()->published()->get(); @endphp
                    @forelse ($exams as $exam)
                        <li class="flex flex-wrap items-center gap-3 rounded-[var(--radius-card)] border border-line bg-white px-4 py-3 text-sm sm:px-5">
                            <x-icon name="award" class="size-5 shrink-0 text-poster" />
                            <span class="min-w-0 flex-1"><span class="font-semibold text-ink">{{ $exam->title }}</span><span class="block text-ink-soft">{{ $exam->modeLabel() }} · {{ $exam->plannedCount() }} questions · {{ $exam->duration_minutes }} min · pass mark {{ $exam->pass_mark }}%{{ $exam->awards_certificate ? ' · certificate for a pass' : '' }}{{ $exam->requires_course_completion ? ' · after every lesson' : '' }}</span></span>
                            @if ($enrolment)<a href="{{ route('exams.show', $exam) }}" class="btn-dark btn-sm no-underline">Open</a>@endif
                        </li>
                    @empty
                        <li class="flex items-center gap-3 rounded-[var(--radius-card)] border border-dashed border-line px-4 py-3 text-sm text-ink-soft sm:px-5">
                            <x-icon name="award" class="size-5 shrink-0 text-poster" />
                            <span><span class="font-semibold text-ink">Examination and certificate.</span> {{ $canTeach ? 'This training has no examination yet.' : 'Any examination for this training will appear here.' }}</span>
                            @if ($canTeach && can_do('exams.manage', $course->orgUnit))<a href="{{ route('exams.manage.create', ['course' => $course->slug]) }}" class="btn-ghost btn-sm ml-auto no-underline">Add one</a>@endif
                        </li>
                    @endforelse
                </ol>
            </div>

            @if ($course->assignments->isNotEmpty())
                <div>
                    <h2 class="h-section">Assignments</h2>
                    <ul class="mt-4 space-y-3">
                        @foreach ($course->assignments as $assignment)
                            @php $sub = $submissions[$assignment->id] ?? null; @endphp
                            <li>
                                <a href="{{ $enrolled || $canTeach ? route('academy.assignment', [$course, $assignment]) : '#enrol' }}" class="card flex items-center gap-4 p-4 no-underline">
                                    <x-icon name="pen" class="size-6 shrink-0 text-poster" />
                                    <span class="min-w-0 flex-1">
                                        <span class="block font-semibold text-ink">{{ $assignment->title }}</span>
                                        <span class="text-sm text-ink-soft">@if ($assignment->due_at)Hand in by {{ $assignment->due_at->format('D j M, g:ia') }}@else No deadline @endif</span>
                                    </span>
                                    @if ($sub)
                                        <span @class(['badge', 'badge-ok' => $sub->status === 'accepted', 'badge-warn' => $sub->status === 'returned', 'badge-info' => $sub->status === 'submitted'])>{{ $sub->statusLabel() }}</span>
                                    @elseif ($enrolled)
                                        <span class="badge badge-neutral">To do</span>
                                    @endif
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @if ($course->resources->isNotEmpty())
                <div>
                    <h2 class="h-section">Resources</h2>
                    @include('academy.partials.resources', ['resources' => $course->resources, 'locked' => ! ($enrolled || $canTeach)])
                </div>
            @endif

            @if ($enrolled || $canTeach || $questions->isNotEmpty())
                <div id="questions">
                    <h2 class="h-section">Questions and answers</h2>
                    @include('academy.partials.questions', ['questions' => $questions, 'canAsk' => $enrolled || $canTeach])
                </div>
            @endif
        </div>

        <aside id="enrol" class="order-1 lg:order-2">
            <div class="card-pad lg:sticky lg:top-24">
                @if ($enrolled)
                    <p class="text-xs font-semibold uppercase tracking-wide text-ink-soft">Your progress</p>
                    <p class="mt-1 font-display text-3xl font-semibold text-stage">{{ $progress['done'] }} / {{ $progress['total'] }}</p>
                    <p class="text-sm text-ink-soft">lessons completed</p>
                    <div class="mt-3 h-2 overflow-hidden rounded-full bg-paper-2"><div class="h-full rounded-full bg-curtain" style="width: {{ $progress['percent'] }}%"></div></div>
                    @if ($progress['next'])
                        <a href="{{ route('academy.lesson', [$course, $progress['next']]) }}" class="btn-primary mt-5 w-full no-underline">{{ $progress['done'] ? 'Continue' : 'Start the first lesson' }}<x-icon name="arrow-right" class="size-4" /></a>
                        <p class="mt-2 text-center text-xs text-ink-soft">Next: {{ $progress['next']->title }}</p>
                    @elseif ($progress['total'])
                        <p class="mt-4 flex items-center gap-2 rounded-xl bg-ok/10 p-3 text-sm font-semibold text-ok"><x-icon name="check-circle" class="size-5" /> Every lesson completed</p>
                    @endif
                    <form method="POST" action="{{ route('academy.withdraw', $course) }}" class="mt-4 text-center" onsubmit="return confirm('Leave this training? Your progress is kept if you come back.')">@csrf @method('DELETE')<button class="text-xs text-ink-soft underline">Leave this training</button></form>
                @elseif (auth()->user()?->member_id)
                    <p class="font-display text-xl font-semibold uppercase text-stage">Join this training</p>
                    <p class="mt-1 text-sm text-ink-soft">{{ $progress['total'] }} {{ \Illuminate\Support\Str::plural('lesson', $progress['total']) }}@if ($course->sessions->filter->isLiveSession()->count()) and {{ $course->sessions->filter->isLiveSession()->count() }} live {{ \Illuminate\Support\Str::plural('class', $course->sessions->filter->isLiveSession()->count()) }}@endif. Free for GODRAM members.</p>
                    @if ($course->acceptsEnrolment())
                        <form method="POST" action="{{ route('academy.enrol', $course) }}" class="mt-5">@csrf<button class="btn-primary w-full">Enrol now</button></form>
                        @if ($course->enrol_by)<p class="mt-2 text-center text-xs text-ink-soft">Enrol by {{ $course->enrol_by->format('j F Y') }}</p>@endif
                    @else
                        <p class="mt-4 rounded-xl bg-paper-2 p-3 text-sm">Enrolment has closed.</p>
                    @endif
                    @error('enrol')<p class="error">{{ $message }}</p>@enderror
                @elseif (! auth()->check())
                    <p class="font-display text-xl font-semibold uppercase text-stage">For GODRAM members</p>
                    <p class="mt-1 text-sm text-ink-soft">Sign in with your Member ID or phone number to enrol and open the lessons.</p>
                    <a href="{{ route('login') }}" class="btn-primary mt-5 w-full no-underline">Sign in to enrol</a>
                    <a href="{{ route('register') }}" class="btn-ghost mt-2 w-full no-underline">Not a member yet? Join GODRAM</a>
                @else
                    <p class="text-sm text-ink-soft">Training is for GODRAM members.</p>
                @endif

                <dl class="mt-6 space-y-2 border-t border-line pt-4 text-sm">
                    <div class="flex justify-between gap-3"><dt class="text-ink-soft">Enrolled</dt><dd class="font-semibold">{{ number_format($enrolledCount) }}</dd></div>
                    @if ($course->assignments->count())<div class="flex justify-between gap-3"><dt class="text-ink-soft">Assignments</dt><dd class="font-semibold">{{ $course->assignments->count() }}</dd></div>@endif
                </dl>

                @if ($course->facilitators->isNotEmpty())
                    <div class="mt-6 border-t border-line pt-4">
                        <p class="text-xs font-semibold uppercase tracking-wide text-ink-soft">{{ \Illuminate\Support\Str::plural('Facilitator', $course->facilitators->count()) }}</p>
                        <ul class="mt-3 space-y-3">
                            @foreach ($course->facilitators as $f)
                                <li class="flex items-center gap-3"><x-avatar :member="$f->member" /><span><span class="block font-semibold">{{ $f->member->full_name }}</span><span class="text-sm text-ink-soft">{{ $f->title ?? 'Facilitator' }}</span></span></li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            </div>
        </aside>
    </section>
</x-layouts.app>
