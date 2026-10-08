<details class="mt-4" x-data="{ link: false }">
    <summary class="cursor-pointer text-sm font-semibold">Add a file or link</summary>
    <form method="POST" action="{{ route('academy.manage.resources.store', $course) }}" enctype="multipart/form-data" class="mt-3 space-y-3">@csrf
        @isset($lessonId)<input type="hidden" name="lesson_id" value="{{ $lessonId }}">@endisset
        @isset($sessionId)<input type="hidden" name="course_session_id" value="{{ $sessionId }}">@endisset
        <div><label class="label" for="r-title-{{ $lessonId ?? 'c' }}">Title <span class="font-normal text-ink-soft">(optional)</span></label><input id="r-title-{{ $lessonId ?? 'c' }}" name="title" class="input" maxlength="150"></div>
        <div class="flex gap-4 text-sm"><label class="flex items-center gap-2"><input type="radio" :checked="!link" @click="link = false" class="accent-curtain"> File</label><label class="flex items-center gap-2"><input type="radio" :checked="link" @click="link = true" class="accent-curtain"> Link</label></div>
        <div x-show="!link"><input type="file" name="file" class="input" :disabled="link"><p class="hint">PDF, Word, PowerPoint, pictures or audio, up to 25 MB.</p>@error('file')<p class="error">{{ $message }}</p>@enderror</div>
        <div x-show="link" x-cloak><input type="url" name="url" class="input" :disabled="!link" placeholder="https://"></div>
        <button class="btn-dark btn-sm">Add</button>
    </form>
</details>
