<x-layouts.app :title="$assignment->title.' · '.$course->title">
    <section class="container-page mt-8 max-w-3xl">
        <a href="{{ route('academy.show', $course) }}" class="inline-flex items-center gap-1 text-sm font-semibold text-curtain no-underline"><x-icon name="arrow-left" class="size-4" /> {{ $course->title }}</a>
        <p class="eyebrow mt-4">Assignment</p>
        <h1 class="h-page mt-1">{{ $assignment->title }}</h1>
        <p class="mt-2 flex flex-wrap gap-x-4 text-sm text-ink-soft">
            @if ($assignment->due_at)<span @class(['font-semibold text-curtain' => $assignment->isOverdue() && ! $submission])>Hand in by {{ $assignment->due_at->format('l j F, g:ia') }}</span>@else<span>No deadline</span>@endif
            @if ($assignment->max_score)<span>Marked out of {{ $assignment->max_score }}</span>@endif
        </p>

        <div class="prose-godram card-pad mt-6">{!! $assignment->briefHtml() !!}</div>

        @if ($submission)
            <div class="card-pad mt-6">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <h2 class="h-section">Your work</h2>
                    <span @class(['badge', 'badge-ok' => $submission->status === 'accepted', 'badge-warn' => $submission->status === 'returned', 'badge-info' => $submission->status === 'submitted'])>{{ $submission->statusLabel() }}</span>
                </div>
                <p class="mt-1 text-xs text-ink-soft">Handed in {{ $submission->submitted_at->format('j M Y, g:ia') }}</p>
                @if ($submission->body)<div class="mt-3 whitespace-pre-line rounded-xl bg-paper-2 p-4 font-serif">{{ $submission->body }}</div>@endif
                @if ($submission->file_name)<a href="{{ route('academy.submission-file', $submission) }}" class="btn-ghost btn-sm mt-3 no-underline"><x-icon name="download" class="size-4" /> {{ $submission->file_name }}</a>@endif
                @if ($submission->link)<p class="mt-3 text-sm"><a href="{{ $submission->link }}" target="_blank" rel="noopener" class="link">{{ $submission->link }}</a></p>@endif
                @if ($submission->feedback || $submission->score !== null)
                    <div class="mt-5 rounded-xl border-l-4 border-poster bg-poster/5 p-4">
                        <p class="text-xs font-semibold uppercase tracking-wide text-poster">Feedback from {{ $submission->reviewer?->member?->full_name ?? 'your facilitator' }}</p>
                        @if ($submission->score !== null)<p class="mt-1 font-display text-2xl font-semibold text-stage">{{ $submission->score }}@if ($assignment->max_score) / {{ $assignment->max_score }}@endif</p>@endif
                        @if ($submission->feedback)<p class="mt-1 whitespace-pre-line">{{ $submission->feedback }}</p>@endif
                    </div>
                @endif
            </div>
        @endif

        @if (! $submission || $submission->status !== 'accepted')
            @if (auth()->user()->member_id && $course->enrolmentFor(auth()->user()))
                <form method="POST" action="{{ route('academy.submit', [$course, $assignment]) }}" enctype="multipart/form-data" class="card-pad mt-6 space-y-5">
                    @csrf
                    <h2 class="h-section">{{ $submission ? 'Hand in again' : 'Hand in your work' }}</h2>
                    @if ($assignment->accepts('text'))
                        <div><label for="body" class="label">Your answer</label><textarea id="body" name="body" rows="10" class="input font-serif" maxlength="20000">{{ old('body', $submission?->body) }}</textarea>@error('body')<p class="error">{{ $message }}</p>@enderror</div>
                    @endif
                    @if ($assignment->accepts('file'))
                        <div><label for="file" class="label">Attach a file <span class="font-normal text-ink-soft">(Word, PDF, picture or audio, up to 20 MB)</span></label><input id="file" type="file" name="file" class="input" accept=".pdf,.doc,.docx,.ppt,.pptx,.txt,.rtf,image/*,audio/*">@error('file')<p class="error">{{ $message }}</p>@enderror</div>
                    @endif
                    @if ($assignment->accepts('link'))
                        <div><label for="link" class="label">Link to your video</label><input id="link" type="url" name="link" value="{{ old('link', $submission?->link) }}" class="input" placeholder="https://youtu.be/... or a Google Drive link">
                            <p class="hint">Upload the video to YouTube (it can be unlisted) or Google Drive, then paste the link. This saves your data and ours.</p>@error('link')<p class="error">{{ $message }}</p>@enderror</div>
                    @endif
                    <button class="btn-primary">Hand in</button>
                </form>
            @endif
        @endif
    </section>
</x-layouts.app>
