<x-layouts.app title="Question bank">
    <section class="container-page mt-8">
        <a href="{{ route('exams.manage.index') }}" class="text-sm font-semibold text-curtain">Examinations</a>
        <div class="mt-2 flex flex-wrap items-end justify-between gap-4">
            <div><p class="eyebrow">CBT examination portal</p><h1 class="h-page mt-1">GODRAM question bank</h1></div>
            @if (can_do('exams.manage'))<a href="{{ route('questions.create') }}" class="btn-primary no-underline"><x-icon name="plus" class="size-4" /> New question</a>@endif
        </div>
        @if ($waiting && $canReview)
            <a href="{{ route('questions.index', ['status' => 'draft']) }}" class="mt-4 inline-flex items-center gap-2 rounded-xl bg-gold/20 px-4 py-2 text-sm font-semibold no-underline"><x-icon name="alert" class="size-4" /> {{ $waiting }} {{ \Illuminate\Support\Str::plural('question', $waiting) }} waiting for review</a>
        @endif

        <div class="mt-6 grid gap-6 lg:grid-cols-[1fr_16rem]">
            <div>
                <form method="GET" class="card-pad grid gap-3 sm:grid-cols-2 xl:grid-cols-[1.4fr_1fr_1fr_1fr_1fr_auto] xl:items-end">
                    <div class="sm:col-span-2 xl:col-span-1"><label for="q" class="label">Search</label><input id="q" type="search" name="q" value="{{ $filters['q'] ?? '' }}" class="input" placeholder="Words in the question"></div>
                    <div><label for="category" class="label">Category</label><select id="category" name="category" class="input"><option value="">All</option>@foreach ($categories as $c)<option value="{{ $c->id }}" @selected(($filters['category'] ?? '') == $c->id)>{{ $c->name }}</option>@endforeach</select></div>
                    <div><label for="type" class="label">Type</label><select id="type" name="type" class="input"><option value="">All</option>@foreach (\App\Models\Question::TYPES as $k => $l)<option value="{{ $k }}" @selected(($filters['type'] ?? '') === $k)>{{ $l }}</option>@endforeach</select></div>
                    <div><label for="difficulty" class="label">Level</label><select id="difficulty" name="difficulty" class="input"><option value="">All</option>@foreach (\App\Models\Question::DIFFICULTIES as $k => $l)<option value="{{ $k }}" @selected(($filters['difficulty'] ?? '') === $k)>{{ $l }}</option>@endforeach</select></div>
                    <div><label for="status" class="label">Status</label><select id="status" name="status" class="input"><option value="">In use</option>@foreach (\App\Models\Question::STATUSES as $k => $l)<option value="{{ $k }}" @selected(($filters['status'] ?? '') === $k)>{{ $l }}</option>@endforeach</select></div><button class="btn-dark">Show</button>
                </form>

                @if ($questions->isEmpty())
                    <x-empty title="No questions match" class="mt-4">Add questions to the bank. Each one is reviewed before it can appear in an examination.</x-empty>
                @else
                    <ul class="mt-4 space-y-3">
                        @foreach ($questions as $q)
                            @php $s = $stats->get($q->id); @endphp
                            <li class="card-pad">
                                <div class="flex flex-wrap items-center gap-2 text-xs">
                                    <span @class(['badge', 'badge-ok' => $q->status === 'approved', 'badge-warn' => $q->status === 'draft', 'badge-neutral' => $q->status === 'retired'])>{{ \App\Models\Question::STATUSES[$q->status] }}</span>
                                    <span class="badge badge-neutral">{{ $q->category->name }}</span>
                                    <span class="badge badge-neutral">{{ \App\Models\Question::DIFFICULTIES[$q->difficulty] }}</span>
                                    <span class="text-ink-soft">{{ $q->typeLabel() }} · {{ rtrim(rtrim(number_format($q->marks, 2), '0'), '.') }} {{ $q->marks == 1 ? 'mark' : 'marks' }} · version {{ $q->version }}</span>
                                    @if ($q->media_kind)<span class="badge badge-info"><x-icon :name="$q->media_kind === 'audio' ? 'headphones' : ($q->media_kind === 'video' ? 'play' : 'image')" class="size-3" /> {{ ucfirst($q->media_kind) }}</span>@endif
                                </div>
                                @if ($q->scenario)<p class="mt-2 line-clamp-2 text-sm italic text-ink-soft">{{ $q->scenario }}</p>@endif
                                <p class="mt-2 font-semibold">{{ $q->stem }}</p>
                                @if ($q->type !== 'open')<p class="mt-1 text-sm"><span class="text-ink-soft">Answer:</span> {{ \App\Models\Question::answerText($q->snapshot(false)) }}</p>@endif
                                <div class="mt-3 flex flex-wrap items-center justify-between gap-3">
                                    <p class="text-xs text-ink-soft">
                                        By {{ $q->author?->name ?? 'unknown' }}
                                        · Used in {{ $q->uses_count }} {{ \Illuminate\Support\Str::plural('paper', $q->uses_count) }}
                                        @if ($s && $s->presented) · {{ round($s->correct / $s->presented * 100) }}% answer correctly @endif
                                    </p>
                                    <div class="flex flex-wrap gap-2">
                                        @if (can_do('exams.manage'))<a href="{{ route('questions.edit', $q) }}" class="btn-ghost btn-sm no-underline"><x-icon name="edit" class="size-4" /> Edit</a>@endif
                                        @if ($canReview)
                                            @foreach (['approve' => ['Approve', 'draft'], 'return' => ['Back to draft', 'approved'], 'retire' => ['Retire', null]] as $decision => [$label, $from])
                                                @continue($from && $q->status !== $from)
                                                @continue($decision === 'retire' && $q->status === 'retired')
                                                <form method="POST" action="{{ route('questions.review', $q) }}">@csrf<input type="hidden" name="decision" value="{{ $decision }}"><button @class(['btn-sm', 'btn-primary' => $decision === 'approve', 'btn-ghost' => $decision !== 'approve'])>{{ $label }}</button></form>
                                            @endforeach
                                        @endif
                                    </div>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                    <div class="mt-4">{{ $questions->links() }}</div>
                @endif
                @error('decision')<p class="error mt-2">{{ $message }}</p>@enderror
            </div>

            <aside class="space-y-4">
                <div class="card-pad">
                    <h2 class="h-section">Categories</h2>
                    <ul class="mt-3 space-y-1.5 text-sm">
                        @foreach ($categories as $c)
                            <li class="flex justify-between gap-2"><a href="{{ route('questions.index', ['category' => $c->id]) }}" class="no-underline hover:text-curtain">{{ $c->name }}</a><span class="text-ink-soft">{{ $c->questions_count }}</span></li>
                        @endforeach
                    </ul>
                    <p class="hint">Counts are approved questions.</p>
                    @if ($canReview)
                        <form method="POST" action="{{ route('questions.categories.store') }}" class="mt-4 flex gap-2">@csrf<label for="cat-name" class="sr-only">New category</label><input id="cat-name" name="name" class="input" placeholder="New category" required maxlength="80"><button class="btn-ghost btn-sm shrink-0">Add</button></form>
                        @error('name')<p class="error">{{ $message }}</p>@enderror
                    @endif
                </div>
                <div class="rounded-xl bg-paper-2 p-4 text-sm text-ink-soft">
                    Editing an approved question saves a new version and sends it back for review. Candidates who already sat it keep the wording they saw.
                </div>
            </aside>
        </div>
    </section>
</x-layouts.app>
