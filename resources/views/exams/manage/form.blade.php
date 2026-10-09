@php
    $editing = $exam->exists;
    $dt = fn ($v) => $v ? \Illuminate\Support\Carbon::parse($v)->format('Y-m-d\TH:i') : '';
    $rows = old('rows', $editing ? $exam->blueprint->map(fn ($r) => ['category' => $r->question_category_id, 'difficulty' => $r->difficulty, 'count' => $r->count])->all() : []);
    if (! $rows) { $rows = [['category' => '', 'difficulty' => '', 'count' => '']]; }
    $poolData = $categories->mapWithKeys(fn ($c) => [$c->id => $c->pool])->all();
@endphp
<x-layouts.app :title="$editing ? 'Examination settings' : 'New examination'">
    <section class="container-page mt-8 max-w-3xl">
        <a href="{{ $editing ? route('exams.manage.show', $exam) : route('exams.manage.index') }}" class="text-sm font-semibold text-curtain">{{ $editing ? $exam->title : 'Examinations' }}</a>
        <div class="mt-2 flex flex-wrap items-center justify-between gap-3">
            <h1 class="h-page">{{ $editing ? 'Examination settings' : 'New examination' }}</h1>
            @if ($editing)<span @class(['badge', 'badge-ok' => $exam->status === 'published', 'badge-warn' => $exam->status === 'draft', 'badge-neutral' => $exam->status === 'archived'])>{{ \App\Models\Exam::STATUSES[$exam->status] }}</span>@endif
        </div>
        @error('exam')<p class="mt-4 rounded-xl bg-curtain/10 px-4 py-3 text-sm font-semibold text-curtain">{{ $message }}</p>@enderror

        @if ($editing)
            <div class="card-pad mt-4 flex flex-wrap items-center gap-3">
                <p class="flex-1 text-sm">
                    @if ($exam->status === 'draft') Members cannot see this examination until you publish it. Publishing checks the question bank has enough approved questions.
                    @elseif ($exam->status === 'published') Published. Eligible members see it under My examinations.
                    @else Archived. Results stay available. @endif
                </p>
                @foreach (['published' => 'Publish', 'draft' => 'Back to draft', 'archived' => 'Archive'] as $to => $label)
                    @continue($exam->status === $to)
                    <form method="POST" action="{{ route('exams.manage.status', $exam) }}">@csrf<input type="hidden" name="status" value="{{ $to }}"><button @class(['btn-sm', 'btn-primary' => $to === 'published', 'btn-ghost' => $to !== 'published'])>{{ $label }}</button></form>
                @endforeach
            </div>
        @endif

        <form method="POST" action="{{ $editing ? route('exams.manage.update', $exam) : route('exams.manage.store') }}" class="mt-6 space-y-6" x-data="{ mode: @js(old('mode', $exam->mode)), course: @js((string) old('course_id', $exam->course_id)) }">
            @csrf @if ($editing) @method('PUT') @endif
            <div class="card-pad space-y-5">
                <div><label for="title" class="label">Name of the examination</label><input id="title" name="title" value="{{ old('title', $exam->title) }}" class="input" maxlength="150" required placeholder="Foundations of Drama Ministry: Final examination">@error('title')<p class="error">{{ $message }}</p>@enderror</div>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div><label for="course_id" class="label">Training it belongs to</label>
                        <select id="course_id" name="course_id" class="input" x-model="course"><option value="">None: open to members of an area</option>@foreach ($courses as $c)<option value="{{ $c->id }}" @selected((int) old('course_id', $exam->course_id) === $c->id)>{{ $c->title }}</option>@endforeach</select>
                        <p class="hint">With a training, only its enrolled members can sit it.</p></div>
                    <div x-show="!course"><label for="org_unit_id" class="label">For members in</label>
                        <select id="org_unit_id" name="org_unit_id" class="input">@foreach ($units as $u)<option value="{{ $u->id }}" @selected((int) old('org_unit_id', $exam->org_unit_id) === $u->id)>{{ $u->fullName() }}</option>@endforeach</select>@error('org_unit_id')<p class="error">{{ $message }}</p>@enderror</div>
                </div>
                <label class="flex items-start gap-2 text-sm" x-show="course"><input type="checkbox" name="requires_course_completion" value="1" @checked(old('requires_course_completion', $exam->requires_course_completion)) class="mt-0.5 size-4 accent-curtain"> Members must complete every lesson before they can sit it</label>
                <div><label for="instructions" class="label">Instructions for candidates <span class="font-normal text-ink-soft">(optional)</span></label><textarea id="instructions" name="instructions" rows="3" class="input py-2">{{ old('instructions', $exam->instructions) }}</textarea></div>
            </div>

            <div class="card-pad space-y-5">
                <h2 class="h-section">Type, time and marking</h2>
                <div class="grid gap-3 sm:grid-cols-2">
                    @foreach (['practice' => 'Practice: candidates can retry and see answers and explanations.', 'certification' => 'Certification: strict attempts, answers kept private, a certificate for a pass.'] as $m => $text)
                        <label class="flex cursor-pointer gap-3 rounded-xl border-2 p-3" :class="mode === '{{ $m }}' ? 'border-curtain bg-curtain/5' : 'border-line'"><input type="radio" name="mode" value="{{ $m }}" x-model="mode" class="mt-1 size-4 accent-curtain"><span><span class="block font-semibold">{{ \App\Models\Exam::MODES[$m] }}</span><span class="text-sm text-ink-soft">{{ ucfirst(\Illuminate\Support\Str::after($text, ": ")) }}</span></span></label>
                    @endforeach
                </div>
                <div class="grid gap-4 sm:grid-cols-3">
                    <div><label for="duration_minutes" class="label">Time allowed (minutes)</label><input id="duration_minutes" type="number" name="duration_minutes" min="1" max="600" value="{{ old('duration_minutes', $exam->duration_minutes) }}" class="input" required></div>
                    <div><label for="pass_mark" class="label">Pass mark (%)</label><input id="pass_mark" type="number" name="pass_mark" min="1" max="100" value="{{ old('pass_mark', $exam->pass_mark) }}" class="input" required></div>
                    <div><label for="max_attempts" class="label">Attempts allowed</label><input id="max_attempts" type="number" name="max_attempts" min="1" max="50" value="{{ old('max_attempts', $exam->max_attempts) }}" class="input" placeholder="Unlimited"></div>
                </div>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div><label for="opens_at" class="label">Opens</label><input id="opens_at" type="datetime-local" name="opens_at" value="{{ old('opens_at', $dt($exam->opens_at)) }}" class="input"></div>
                    <div><label for="closes_at" class="label">Closes</label><input id="closes_at" type="datetime-local" name="closes_at" value="{{ old('closes_at', $dt($exam->closes_at)) }}" class="input">@error('closes_at')<p class="error">{{ $message }}</p>@enderror</div>
                </div>
                <p class="hint -mt-3">Leave empty to keep it open. A candidate who starts close to the closing time gets only the time left.</p>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div><label for="result_policy" class="label">With several attempts, count the</label><select id="result_policy" name="result_policy" class="input">@foreach (\App\Models\Exam::POLICIES as $k => $l)<option value="{{ $k }}" @selected(old('result_policy', $exam->result_policy) === $k)>{{ $l }}</option>@endforeach</select></div>
                    <div><label for="release" class="label">Show results</label><select id="release" name="release" class="input">@foreach (\App\Models\Exam::RELEASES as $k => $l)<option value="{{ $k }}" @selected(old('release', $exam->release) === $k)>{{ $l }}</option>@endforeach</select></div>
                </div>
                <div class="space-y-2 text-sm">
                    <label class="flex items-center gap-2"><input type="checkbox" name="shuffle_questions" value="1" @checked(old('shuffle_questions', $exam->shuffle_questions)) class="size-4 accent-curtain"> Give each candidate the questions in a different order</label>
                    <label class="flex items-center gap-2"><input type="checkbox" name="shuffle_options" value="1" @checked(old('shuffle_options', $exam->shuffle_options)) class="size-4 accent-curtain"> Shuffle answer choices (questions marked "keep order" are never shuffled)</label>
                    <label class="flex items-center gap-2"><input type="checkbox" name="show_review" value="1" @checked(old('show_review', $exam->show_review)) class="size-4 accent-curtain"> Show correct answers and explanations after the result <span class="text-ink-soft">(always on for practice)</span></label>
                    <label class="flex items-center gap-2" x-show="mode === 'certification'"><input type="checkbox" name="awards_certificate" value="1" @checked(old('awards_certificate', $exam->awards_certificate)) class="size-4 accent-curtain"> Issue a certificate when a candidate passes</label>
                </div>
            </div>

            <div class="card-pad space-y-4" x-data="{ rows: @js(array_values($rows)), pools: @js($poolData), available(r) { if (!r.category) return null; const p = this.pools[r.category] || {}; return r.difficulty ? (p[r.difficulty] || 0) : Object.values(p).reduce((a, b) => a + b, 0); }, get total() { return this.rows.reduce((a, r) => a + (parseInt(r.count) || 0), 0); } }">
                <h2 class="h-section">Blueprint: which questions</h2>
                <p class="text-sm text-ink-soft">Say how many questions to draw from each category and level. Each candidate gets a different random set that follows this plan. {{ $approved }} approved questions are in the bank.</p>
                <template x-for="(r, n) in rows" :key="n">
                    <div class="grid grid-cols-[1fr_auto] gap-2 rounded-xl bg-paper-2 p-3 sm:grid-cols-[1.4fr_1fr_6rem_auto] sm:items-end">
                        <div class="col-span-2 sm:col-span-1"><label class="text-xs font-semibold text-ink-soft" :for="'cat' + n">Category</label>
                            <select :id="'cat' + n" :name="'rows[' + n + '][category]'" x-model="r.category" class="input"><option value="">Any category</option>@foreach ($categories as $c)<option value="{{ $c->id }}">{{ $c->name }}</option>@endforeach</select></div>
                        <div><label class="text-xs font-semibold text-ink-soft" :for="'dif' + n">Level</label>
                            <select :id="'dif' + n" :name="'rows[' + n + '][difficulty]'" x-model="r.difficulty" class="input"><option value="">Any level</option>@foreach (\App\Models\Question::DIFFICULTIES as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach</select></div>
                        <div><label class="text-xs font-semibold text-ink-soft" :for="'cnt' + n">Questions</label>
                            <input :id="'cnt' + n" type="number" min="0" max="300" :name="'rows[' + n + '][count]'" x-model="r.count" class="input"></div>
                        <button type="button" @click="rows.splice(n, 1)" class="mb-1 rounded-full p-2 text-ink-soft hover:text-curtain" aria-label="Remove this line"><x-icon name="x" class="size-5" /></button>
                        <p class="col-span-2 text-xs sm:col-span-4" x-show="available(r) !== null" :class="available(r) < (parseInt(r.count) || 0) ? 'font-semibold text-curtain' : 'text-ink-soft'" x-text="available(r) + ' approved questions available' + (available(r) < (parseInt(r.count) || 0) ? ': not enough' : '')"></p>
                    </div>
                </template>
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <button type="button" @click="rows.push({ category: '', difficulty: '', count: '' })" class="btn-ghost btn-sm"><x-icon name="plus" class="size-4" /> Add a line</button>
                    <p class="text-sm font-semibold" x-show="total > 0">Total: <span x-text="total"></span> questions</p>
                </div>
                <div x-show="total === 0"><label for="question_count" class="label">Or simply: number of questions from the whole bank</label><input id="question_count" type="number" name="question_count" min="1" max="300" value="{{ old('question_count', $exam->question_count) }}" class="input max-w-40"></div>
                <template x-if="total > 0"><input type="hidden" name="question_count" :value="total"></template>
                @error('options')<p class="error">{{ $message }}</p>@enderror
            </div>

            <div class="flex gap-3"><button class="btn-primary">{{ $editing ? 'Save settings' : 'Save as draft' }}</button><a href="{{ route('exams.manage.index') }}" class="btn-ghost no-underline">Cancel</a></div>
        </form>
    </section>
</x-layouts.app>
