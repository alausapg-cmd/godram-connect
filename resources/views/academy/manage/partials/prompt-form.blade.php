<form method="POST" action="{{ route('academy.manage.prompts.store', $course) }}" class="mt-4 rounded-xl border border-dashed border-line p-4" x-data="{ type: '{{ old('type', 'choice') }}' }">
    @csrf
    @isset($lessonId)<input type="hidden" name="lesson_id" value="{{ $lessonId }}">@endisset
    @isset($sessionId)<input type="hidden" name="course_session_id" value="{{ $sessionId }}">@endisset
    <p class="font-semibold">Add a call-and-response question</p>
    <div class="mt-3 grid gap-3 sm:grid-cols-[180px_1fr]">
        <div><label class="label" for="p-type-{{ $lessonId ?? $sessionId }}">Kind</label>
            <select id="p-type-{{ $lessonId ?? $sessionId }}" name="type" x-model="type" class="input">@foreach (\App\Models\Prompt::TYPES as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach</select></div>
        <div><label class="label" for="p-q-{{ $lessonId ?? $sessionId }}">Question or statement</label><input id="p-q-{{ $lessonId ?? $sessionId }}" name="question" class="input" maxlength="500" required placeholder="The stage is the pulpit and the audience is the ..."></div>
    </div>
    <div class="mt-3" x-show="type === 'choice' || type === 'poll'"><label class="label">Options, one per line</label><textarea name="options" rows="3" class="input" :disabled="!(type === 'choice' || type === 'poll')"></textarea>@error('options')<p class="error">{{ $message }}</p>@enderror</div>
    <div class="mt-3 grid gap-3 sm:grid-cols-2" x-show="type !== 'poll' && type !== 'open'">
        <div><label class="label">Correct answer</label>
            <template x-if="type === 'true_false'"><select name="answer" class="input"><option>True</option><option>False</option></select></template>
            <template x-if="type !== 'true_false'"><input name="answer" class="input" maxlength="300" placeholder="For several accepted answers, separate with |"></template>
            @error('answer')<p class="error">{{ $message }}</p>@enderror</div>
        <div><label class="label">Explain the answer <span class="font-normal text-ink-soft">(optional)</span></label><input name="explanation" class="input" maxlength="500"></div>
    </div>
    <button class="btn-dark btn-sm mt-3"><x-icon name="plus" class="size-4" /> Add question</button>
</form>
