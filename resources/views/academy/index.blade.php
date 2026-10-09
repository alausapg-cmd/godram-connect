<x-layouts.app title="GODRAM Virtual Academy" description="Training, live classes and practical assignments for drama ministers across GODRAM.">
    <x-hero page="academy" eyebrow="Learn · Practise · Minister" title="GODRAM Virtual Academy" lead="Carrying forward the GODRAM Institute of Christian Drama, whose first 41 drama ministers graduated in 1995. Training from the National office, your Region and your District, in one place.">
        <div class="mt-6 flex flex-wrap gap-2">
                @auth
                    @if (auth()->user()->member_id)<a href="{{ route('academy.mine') }}" class="btn-gold no-underline"><x-icon name="academy" class="size-4" /> My learning</a>@endif
                    @if ($canManage)<a href="{{ route('academy.manage.index') }}" class="btn-sm inline-flex min-h-11 items-center gap-2 rounded-full bg-white/10 px-5 text-sm font-semibold text-paper no-underline hover:bg-white/20"><x-icon name="list" class="size-4" /> Manage training</a>@endif
                    @if ($canOverview)<a href="{{ route('academy.overview') }}" class="btn-sm inline-flex min-h-11 items-center gap-2 rounded-full bg-white/10 px-5 text-sm font-semibold text-paper no-underline hover:bg-white/20">Training in my area</a>@endif
                @else
                    <a href="{{ route('login') }}" class="btn-gold no-underline">Sign in to enrol</a>
                    <a href="{{ route('register') }}" class="btn-sm inline-flex min-h-11 items-center rounded-full bg-white/10 px-5 text-sm font-semibold text-paper no-underline hover:bg-white/20">Join GODRAM</a>
                @endauth
        </div>
    </x-hero>

    @if ($live->isNotEmpty())
        <section class="container-page mt-8">
            <h2 class="h-section">Live training</h2>
            <div class="mt-3 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($live as $session)
                    <a href="{{ auth()->check() ? route('academy.room', [$session->course, $session]) : route('academy.show', $session->course) }}" @class(['card flex items-center gap-4 p-4 no-underline', 'border-curtain ring-1 ring-curtain' => $session->isLive()])>
                        <span @class(['flex w-14 shrink-0 flex-col items-center rounded-xl py-2 text-center', 'bg-curtain text-white' => $session->isLive(), 'bg-stage text-paper' => ! $session->isLive()])>
                            <span class="text-[11px] font-semibold uppercase">{{ $session->live_at->format('M') }}</span>
                            <span class="font-display text-2xl font-semibold leading-none">{{ $session->live_at->format('j') }}</span>
                        </span>
                        <span class="min-w-0">
                            @if ($session->isLive())<span class="badge bg-curtain text-white"><span class="size-1.5 animate-pulse rounded-full bg-white"></span> Live now</span>@else<span class="text-xs font-semibold uppercase tracking-wide text-poster">{{ $session->live_at->format('D g:ia') }}</span>@endif
                            <span class="mt-0.5 block truncate font-semibold text-ink">{{ $session->title }}</span>
                            <span class="block truncate text-sm text-ink-soft">{{ $session->course->title }}</span>
                        </span>
                    </a>
                @endforeach
            </div>
        </section>
    @endif

    @if ($mine->isNotEmpty())
        <section class="container-page mt-10">
            <div class="flex items-end justify-between gap-4"><h2 class="h-section">Continue learning</h2><a href="{{ route('academy.mine') }}" class="link text-sm">All my training</a></div>
            <div class="mt-4 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($mine->take(3) as $item)
                    <x-course-card :course="$item->course" :progress="$item->progress" />
                @endforeach
            </div>
        </section>
    @endif

    <section class="container-page mt-10">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <h2 class="h-section">Training library</h2>
            <form method="GET" class="flex w-full max-w-sm gap-2 sm:w-auto">
                @if ($level)<input type="hidden" name="level" value="{{ $level }}">@endif
                <label for="academy-q" class="sr-only">Search training</label>
                <input id="academy-q" name="q" value="{{ $term }}" type="search" class="input" placeholder="Search training">
                <button class="btn-dark shrink-0"><x-icon name="search" class="size-4" /><span class="sr-only">Search</span></button>
            </form>
        </div>
        <div class="mt-4 flex flex-wrap gap-2">
            @foreach ([null => 'Everything', 'national' => 'National', 'regional' => 'Regional', 'district' => 'District'] as $key => $label)
                <a href="{{ route('academy', array_filter(['level' => $key ?: null, 'q' => $term ?: null])) }}" @class(['rounded-full px-4 py-1.5 text-sm font-semibold no-underline', 'bg-stage text-paper' => $level === ($key ?: null), 'border border-line bg-white text-ink hover:border-ink-soft' => $level !== ($key ?: null)])>{{ $label }}</a>
            @endforeach
        </div>

        @if ($courses->isEmpty())
            <x-empty title="No training here yet" class="mt-6">
                {{ $term ? 'Nothing matches "'.$term.'". Try another word.' : 'New programmes from the National office, Regions and Districts will appear here.' }}
            </x-empty>
        @else
            <div class="mt-6 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($courses as $course)<x-course-card :course="$course" />@endforeach
            </div>
        @endif
    </section>

    <section class="container-page mt-12">
        <div class="grid gap-4 rounded-[var(--radius-card)] bg-paper-2 p-6 sm:grid-cols-3 sm:p-8">
            @foreach ([['book', 'Learn at your pace', 'Video, audio and reading lessons that work on a phone, even on a weak connection.'], ['live', 'Join live classes', 'Facilitators teach live, take your questions and ask the class to respond.'], ['pen', 'Practise and get feedback', 'Write a scene, record a monologue, hand it in and hear back from your facilitator.']] as [$icon, $title, $text])
                <div class="flex gap-3"><x-icon :name="$icon" class="size-6 shrink-0 text-poster" /><div><p class="font-semibold">{{ $title }}</p><p class="mt-1 text-sm text-ink-soft">{{ $text }}</p></div></div>
            @endforeach
        </div>
    </section>
</x-layouts.app>
