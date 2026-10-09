@php
    $editing = $question->exists;
    $opts = $question->options ?? [];
    $choiceRows = old('options', in_array($question->type, ['single', 'multiple']) && $opts ? array_column($opts, 'text') : ['', '', '', '']);
    $answerKeys = $question->type === 'single' ? [$question->answer['key'] ?? null] : ($question->answer['keys'] ?? []);
    $correct = old('correct', collect($opts)->keys()->filter(fn ($i) => in_array($opts[$i]['key'] ?? null, $answerKeys, true))->map(fn ($i) => (string) $i)->values()->all());
    $pairs = $question->type === 'matching' && $opts ? $opts : [['left' => '', 'right' => ''], ['left' => '', 'right' => ''], ['left' => '', 'right' => '']];
    $lefts = old('lefts', array_column($pairs, 'left'));
    $rights = old('rights', array_column($pairs, 'right'));
@endphp
<x-layouts.app :title="$editing ? 'Edit question' : 'New question'">
    <section class="container-page mt-8 max-w-3xl">
        <a href="{{ route('questions.index') }}" class="text-sm font-semibold text-curtain">Question bank</a>
        <h1 class="h-page mt-2">{{ $editing ? 'Edit question' : 'New question' }}</h1>
        @if ($editing && $question->status === 'approved')
            <p class="mt-2 rounded-xl bg-gold/20 px-4 py-2 text-sm">This question is approved{{ ($used ?? 0) ? ' and has been on '.$used.' '.\Illuminate\Support\Str::plural('paper', $used) : '' }}. Saving a change makes version {{ $question->version + 1 }}, which needs review before it is used again.</p>
        @endif

        <form method="POST" action="{{ $editing ? route('questions.update', $question) : route('questions.store') }}" enctype="multipart/form-data" class="mt-6 space-y-6"
              x-data="{ type: @js(old('type', $question->type)), choices: @js(array_values($choiceRows)), correct: @js(array_map('strval', (array) $correct)), lefts: @js(array_values($lefts)), rights: @js(array_values($rights)) }">
            @csrf @if ($editing) @method('PUT') @endif
            <div class="card-pad space-y-5">
                <div class="grid gap-4 sm:grid-cols-3">
                    <div class="sm:col-span-3"><label for="type" class="label">Type of question</label>
                        <select id="type" name="type" x-model="type" class="input">@foreach (\App\Models\Question::TYPES as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach</select></div>
                    <div><label for="question_category_id" class="label">Category</label>
                        <select id="question_category_id" name="question_category_id" class="input" required>@foreach ($categories as $c)<option value="{{ $c->id }}" @selected((int) old('question_category_id', $question->question_category_id) === $c->id)>{{ $c->name }}</option>@endforeach</select></div>
                    <div><label for="difficulty" class="label">Level</label>
                        <select id="difficulty" name="difficulty" class="input">@foreach (\App\Models\Question::DIFFICULTIES as $k => $l)<option value="{{ $k }}" @selected(old('difficulty', $question->difficulty) === $k)>{{ $l }}</option>@endforeach</select></div>
                    <div><label for="marks" class="label">Marks</label><input id="marks" type="number" name="marks" step="0.5" min="0.5" max="100" value="{{ old('marks', rtrim(rtrim(number_format((float) $question->marks, 2), '0'), '.')) }}" class="input" required></div>
                </div>
                <div><label for="scenario" class="label">Scenario <span class="font-normal text-ink-soft">(optional: a situation the question is about)</span></label><textarea id="scenario" name="scenario" rows="3" class="input py-2" placeholder="During an outdoor crusade the sound system fails in the middle of the second act…">{{ old('scenario', $question->scenario) }}</textarea></div>
                <div><label for="stem" class="label">Question</label><textarea id="stem" name="stem" rows="3" class="input py-2" required>{{ old('stem', $question->stem) }}</textarea>
                    <p class="hint" x-show="type === 'fill_blank'">Mark the blank with three underscores, for example: A short drama that opens a crusade is called a ___.</p>
                    @error('stem')<p class="error">{{ $message }}</p>@enderror</div>
            </div>

            <div class="card-pad space-y-4">
                <h2 class="h-section">Answer</h2>

                <div x-show="type === 'single' || type === 'multiple'" class="space-y-2">
                    <p class="text-sm text-ink-soft" x-text="type === 'single' ? 'Write the choices and tick the one correct answer.' : 'Write the choices and tick every correct answer.'"></p>
                    <template x-for="(c, n) in choices" :key="n">
                        <div class="flex items-center gap-2">
                            <input :type="type === 'single' ? 'radio' : 'checkbox'" name="correct[]" :value="String(n)" :checked="correct.includes(String(n))" class="size-5 shrink-0 accent-curtain" :aria-label="'Choice ' + (n + 1) + ' is correct'">
                            <input :name="'options[' + n + ']'" x-model="choices[n]" class="input" :placeholder="'Choice ' + String.fromCharCode(65 + n)" maxlength="500">
                            <button type="button" @click="choices.splice(n, 1)" x-show="choices.length > 2" class="rounded-full p-2 text-ink-soft" aria-label="Remove"><x-icon name="x" class="size-4" /></button>
                        </div>
                    </template>
                    <button type="button" @click="choices.push('')" x-show="choices.length < 8" class="btn-ghost btn-sm"><x-icon name="plus" class="size-4" /> Add a choice</button>
                    <label class="mt-2 flex items-center gap-2 text-sm"><input type="checkbox" name="shuffle_options" value="1" @checked(old('shuffle_options', $question->shuffle_options)) class="size-4 accent-curtain"> Shuffle these choices for each candidate</label>
                    <p class="hint">Untick for choices such as "All of the above", where the order carries meaning.</p>
                </div>

                <div x-show="type === 'true_false'" class="flex gap-3">
                    @foreach (['true' => 'True', 'false' => 'False'] as $v => $l)
                        <label class="flex flex-1 items-center gap-2 rounded-xl border-2 border-line p-3 font-semibold"><input type="radio" name="tf" value="{{ $v }}" @checked(old('tf', isset($question->answer['value']) ? ($question->answer['value'] ? 'true' : 'false') : null) === $v) class="size-5 accent-curtain"> {{ $l }}</label>
                    @endforeach
                </div>

                <div x-show="type === 'short' || type === 'fill_blank'">
                    <label for="accepted" class="label">Accepted answers, one per line</label>
                    <textarea id="accepted" name="accepted" rows="3" class="input py-2" placeholder="curtain raiser&#10;curtain-raiser">{{ old('accepted', implode("\n", $question->answer['accepted'] ?? [])) }}</textarea>
                    <p class="hint">Capital letters, spaces and punctuation are ignored when marking.</p>
                </div>

                <div x-show="type === 'ordering'">
                    <label for="items" class="label">The steps in the correct order, one per line</label>
                    <textarea id="items" name="items" rows="5" class="input py-2">{{ old('items', $question->type === 'ordering' ? collect($opts)->pluck('text')->join("\n") : '') }}</textarea>
                    <p class="hint">Candidates see them mixed up and put them back in order.</p>
                </div>

                <div x-show="type === 'matching'" class="space-y-2">
                    <p class="text-sm text-ink-soft">Write pairs that belong together. Candidates match each left item to its right item.</p>
                    <template x-for="(l, n) in lefts" :key="n">
                        <div class="grid grid-cols-[1fr_1fr_auto] gap-2">
                            <input :name="'lefts[' + n + ']'" x-model="lefts[n]" class="input" placeholder="Role" :aria-label="'Left item ' + (n + 1)">
                            <input :name="'rights[' + n + ']'" x-model="rights[n]" class="input" placeholder="What they do" :aria-label="'Right item ' + (n + 1)">
                            <button type="button" @click="lefts.splice(n, 1); rights.splice(n, 1)" x-show="lefts.length > 3" class="rounded-full p-2 text-ink-soft" aria-label="Remove pair"><x-icon name="x" class="size-4" /></button>
                        </div>
                    </template>
                    <button type="button" @click="lefts.push(''); rights.push('')" x-show="lefts.length < 10" class="btn-ghost btn-sm"><x-icon name="plus" class="size-4" /> Add a pair</button>
                </div>

                <p x-show="type === 'open'" class="text-sm text-ink-soft">Candidates write a longer answer and an examiner marks it. Put the marking guide in the box below.</p>
                @error('options')<p class="error">{{ $message }}</p>@enderror

                <div><label for="explanation" class="label"><span x-text="type === 'open' ? 'Marking guide' : 'Explanation'"></span> <span class="font-normal text-ink-soft">(shown after practice examinations)</span></label><textarea id="explanation" name="explanation" rows="3" class="input py-2">{{ old('explanation', $question->explanation) }}</textarea></div>
            </div>

            <div class="card-pad space-y-4">
                <h2 class="h-section">Picture, recording or video <span class="text-sm font-normal normal-case text-ink-soft">(optional)</span></h2>
                @if ($question->media_kind)
                    <div class="rounded-xl bg-paper-2 p-3 text-sm">
                        @if ($question->media_kind === 'image')<img src="{{ route('questions.media', $question) }}" alt="" class="max-h-48 rounded-lg">
                        @elseif ($question->media_kind === 'audio')<audio src="{{ route('questions.media', $question) }}" controls class="w-full"></audio>
                        @else <p>YouTube video {{ $question->youtube_id }}</p>@endif
                        <label class="mt-2 flex items-center gap-2"><input type="checkbox" name="remove_media" value="1" class="size-4 accent-curtain"> Remove it</label>
                    </div>
                @endif
                <div><label for="media" class="label">Upload a picture or an audio recording</label><input id="media" type="file" name="media" accept="image/*,audio/*" class="input py-2">@error('media')<p class="error">{{ $message }}</p>@enderror</div>
                <div><label for="youtube_url" class="label">Or a YouTube link for a video clip</label><input id="youtube_url" name="youtube_url" value="{{ old('youtube_url') }}" class="input" placeholder="https://youtu.be/…">@error('youtube_url')<p class="error">{{ $message }}</p>@enderror</div>
            </div>

            <div class="flex gap-3"><button class="btn-primary">Save question</button><a href="{{ route('questions.index') }}" class="btn-ghost no-underline">Cancel</a></div>
        </form>
    </section>
</x-layouts.app>
