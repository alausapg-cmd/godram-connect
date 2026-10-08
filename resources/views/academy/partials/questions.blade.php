@if ($canAsk)
    <form method="POST" action="{{ route('academy.ask', $course) }}" class="mt-3 flex gap-2">
        @csrf
        @isset($lessonId)<input type="hidden" name="lesson_id" value="{{ $lessonId }}">@endisset
        <label for="ask-{{ $lessonId ?? 'course' }}" class="sr-only">Your question</label>
        <input id="ask-{{ $lessonId ?? 'course' }}" name="body" class="input" maxlength="1000" placeholder="Ask the facilitators a question" required>
        <button class="btn-dark shrink-0">Ask</button>
    </form>
    @error('body')<p class="error">{{ $message }}</p>@enderror
@endif
@if ($questions->isNotEmpty())
    <ul class="mt-4 space-y-3">
        @foreach ($questions as $q)
            <li class="card p-4">
                <div class="flex items-start gap-2">
                    @if ($q->is_featured)<x-icon name="star" class="mt-0.5 size-4 shrink-0 text-gold" />@endif
                    <div class="min-w-0">
                        <p class="font-medium">{{ $q->body }}</p>
                        <p class="text-xs text-ink-soft">{{ $q->user?->member?->first_name ?? 'A participant' }} · {{ $q->created_at->format('j M') }}@if ($q->lesson && ! isset($lessonId)) · {{ $q->lesson->title }}@endif</p>
                    </div>
                </div>
                @if ($q->answer)
                    <div class="mt-3 rounded-xl bg-paper-2 p-3 text-sm"><p class="text-xs font-semibold uppercase tracking-wide text-poster">Answer</p><p class="mt-1 whitespace-pre-line">{{ $q->answer }}</p></div>
                @else
                    <p class="mt-2 text-xs text-ink-soft">Waiting for an answer. Only you can see this until it is answered.</p>
                @endif
            </li>
        @endforeach
    </ul>
@elseif (! $canAsk)
    <p class="mt-3 text-sm text-ink-soft">No questions yet.</p>
@endif
