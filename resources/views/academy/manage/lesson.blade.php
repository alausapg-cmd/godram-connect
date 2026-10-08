@php $editing = $lesson->exists; @endphp
<x-layouts.app :title="$editing ? 'Edit lesson' : 'New lesson'">
    <section class="container-page mt-8 max-w-4xl" x-data="{ kind: '{{ old('kind', $lesson->kind) }}' }">
        <a href="{{ route('academy.manage.build', $course) }}" class="text-sm font-semibold text-curtain">{{ $course->title }}</a>
        <div class="mt-2 flex flex-wrap items-end justify-between gap-3">
            <h1 class="h-page">{{ $editing ? $lesson->title : 'New lesson' }}</h1>
            @if ($editing)<a href="{{ route('academy.lesson', [$course, $lesson]) }}" class="btn-ghost btn-sm no-underline">See the lesson <x-icon name="external" class="size-3.5" /></a>@endif
        </div>

        <form method="POST" action="{{ $editing ? route('academy.manage.lessons.update', [$course, $lesson]) : route('academy.manage.lessons.store', $course) }}" enctype="multipart/form-data" class="mt-6 space-y-6">
            @csrf @if ($editing) @method('PUT') @endif
            <div class="card-pad space-y-5">
                <div><label for="title" class="label">Lesson title</label><input id="title" name="title" value="{{ old('title', $lesson->title) }}" class="input" maxlength="150" required>@error('title')<p class="error">{{ $message }}</p>@enderror</div>
                <div class="grid gap-4 sm:grid-cols-3">
                    <div><label for="course_session_id" class="label">Session</label><select id="course_session_id" name="course_session_id" class="input">@foreach ($course->sessions as $s)<option value="{{ $s->id }}" @selected((int) old('course_session_id', $lesson->course_session_id) === $s->id)>{{ $loop->iteration }}. {{ $s->title }}</option>@endforeach</select></div>
                    <div><label for="kind" class="label">Kind of lesson</label><select id="kind" name="kind" x-model="kind" class="input">@foreach (\App\Models\Lesson::KINDS as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach</select></div>
                    <div><label for="minutes" class="label">Minutes</label><input id="minutes" type="number" min="1" name="minutes" value="{{ old('minutes', $lesson->minutes) }}" class="input"></div>
                </div>
                <div><label for="summary" class="label">One-line summary <span class="font-normal text-ink-soft">(optional)</span></label><input id="summary" name="summary" value="{{ old('summary', $lesson->summary) }}" class="input" maxlength="300"></div>
                <div x-show="kind === 'video'"><label for="youtube_url" class="label">YouTube link</label><input id="youtube_url" name="youtube_url" value="{{ old('youtube_url', $lesson->youtube_id ? 'https://youtu.be/'.$lesson->youtube_id : '') }}" class="input" placeholder="https://youtu.be/..."><p class="hint">Upload the video to the GODRAM TV channel (it can be unlisted). YouTube adjusts the quality to each member's connection.</p>@error('youtube_url')<p class="error">{{ $message }}</p>@enderror</div>
                <div x-show="kind === 'audio'"><label for="audio" class="label">Audio file @if ($lesson->audio_path)<span class="font-normal text-ok">(one is attached; choose another to replace it)</span>@endif</label><input id="audio" type="file" name="audio" accept="audio/*" class="input"><p class="hint">MP3 at 64 kbps keeps an hour-long talk under 30 MB.</p>@error('audio')<p class="error">{{ $message }}</p>@enderror</div>
                <div><label for="scripture" class="label">Scripture <span class="font-normal text-ink-soft">(optional)</span></label><input id="scripture" name="scripture" value="{{ old('scripture', $lesson->scripture) }}" class="input" maxlength="300" placeholder="“Write the vision, and make it plain.” Habakkuk 2:2"></div>
                <div><label for="body" class="label">Lesson text</label><textarea id="body" name="body" rows="14" class="input font-serif">{{ old('body', $lesson->body) }}</textarea><p class="hint">Leave a blank line between paragraphs. Use ## for a heading, **bold** for emphasis and - for a list.</p></div>
                <div><label for="key_points" class="label">Key points, one per line</label><textarea id="key_points" name="key_points" rows="4" class="input">{{ old('key_points', $lesson->key_points) }}</textarea></div>
                <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="is_preview" value="1" @checked(old('is_preview', $lesson->is_preview)) class="size-4 accent-curtain"> Free preview: anyone who can see the training may open this lesson before enrolling</label>
            </div>
            <div class="flex gap-3"><button class="btn-primary">Save lesson</button><a href="{{ route('academy.manage.build', $course) }}" class="btn-ghost no-underline">Back to the outline</a></div>
        </form>

        @if ($editing)
            <div class="mt-10 grid gap-6 lg:grid-cols-2">
                <div class="card-pad">
                    <h2 class="h-section">Call-and-response</h2>
                    <ul class="mt-3 divide-y divide-line">
                        @forelse ($lesson->prompts as $p)
                            <li class="flex items-start gap-3 py-2 text-sm">
                                <span class="flex-1"><span class="block font-medium">{{ $p->question }}</span><span class="text-xs text-ink-soft">{{ $p->typeLabel() }}@if ($p->answer) · Answer: {{ $p->answer }}@endif · {{ $p->responses()->count() }} responses</span></span>
                                <form method="POST" action="{{ route('academy.manage.prompts.update', [$course, $p]) }}" onsubmit="return confirm('Delete this question?')">@csrf<input type="hidden" name="action" value="delete"><button class="p-1 text-ink-soft hover:text-curtain" aria-label="Delete"><x-icon name="x" class="size-4" /></button></form>
                            </li>
                        @empty
                            <li class="py-2 text-sm text-ink-soft">Add a question or two to keep members engaged as they learn.</li>
                        @endforelse
                    </ul>
                    @include('academy.manage.partials.prompt-form', ['lessonId' => $lesson->id])
                </div>
                <div class="card-pad">
                    <h2 class="h-section">Downloads for this lesson</h2>
                    @if ($lesson->resources->isNotEmpty())@include('academy.partials.resources', ['resources' => $lesson->resources, 'manage' => $course])@else<p class="mt-2 text-sm text-ink-soft">Scripts, worksheets and slides.</p>@endif
                    @include('academy.manage.partials.resource-form', ['lessonId' => $lesson->id])
                </div>
            </div>
            @if ($course->canBeManagedBy(auth()->user()))
                <form method="POST" action="{{ route('academy.manage.lessons.destroy', [$course, $lesson]) }}" class="mt-8" onsubmit="return confirm('Delete this lesson? Members lose its progress tick.')">@csrf @method('DELETE')<button class="text-sm font-semibold text-curtain underline">Delete this lesson</button></form>
            @endif
        @endif
    </section>
</x-layouts.app>
