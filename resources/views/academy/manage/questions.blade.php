<x-layouts.app :title="'Questions: '.$course->title">
    <section class="container-page mt-8">
        @include('academy.manage.partials.header')
        @if ($questions->isEmpty())
            <x-empty title="No questions yet" class="mt-6">Questions members ask in lessons and live classes come here.</x-empty>
        @else
            <ul class="mt-6 space-y-3">
                @foreach ($questions as $q)
                    <li @class(['card p-4', 'opacity-60' => $q->is_hidden])>
                        <div class="flex flex-wrap items-start gap-3">
                            <div class="min-w-0 flex-1">
                                <p class="font-medium">{{ $q->body }}</p>
                                <p class="text-xs text-ink-soft">{{ $q->user?->member?->full_name }} · {{ $q->created_at->format('j M, g:ia') }}@if ($q->lesson) · {{ $q->lesson->title }}@elseif ($q->session) · Live: {{ $q->session->title }}@endif @if ($q->is_hidden) · Hidden @endif</p>
                            </div>
                            <form method="POST" action="{{ route('academy.manage.questions.moderate', [$course, $q]) }}">@csrf<input type="hidden" name="action" value="feature"><button class="btn-ghost btn-sm"><x-icon name="star" class="size-4 {{ $q->is_featured ? 'text-gold' : '' }}" /> {{ $q->is_featured ? 'Featured' : 'Feature' }}</button></form>
                            <form method="POST" action="{{ route('academy.manage.questions.moderate', [$course, $q]) }}">@csrf<input type="hidden" name="action" value="hide"><button class="btn-ghost btn-sm">{{ $q->is_hidden ? 'Show' : 'Hide' }}</button></form>
                        </div>
                        <form method="POST" action="{{ route('academy.manage.questions.moderate', [$course, $q]) }}" class="mt-3 flex flex-col gap-2 sm:flex-row">@csrf<input type="hidden" name="action" value="answer">
                            <textarea name="answer" rows="2" class="input" placeholder="Your answer" required>{{ $q->answer }}</textarea>
                            <button class="btn-dark btn-sm shrink-0 self-start">{{ $q->answer ? 'Update' : 'Answer' }}</button>
                        </form>
                        @if ($q->answered_at)<p class="mt-1 text-xs text-ink-soft">Answered by {{ $q->answerer?->member?->full_name }} on {{ $q->answered_at->format('j M') }}</p>@endif
                    </li>
                @endforeach
            </ul>
        @endif
    </section>
</x-layouts.app>
