<x-layouts.app :title="'Assignments: '.$course->title">
    <section class="container-page mt-8">
        @include('academy.manage.partials.header')
        @if ($submissions->isEmpty())
            <x-empty title="Nothing handed in yet" class="mt-6">Work members hand in appears here for you to read and give feedback.</x-empty>
        @else
            <div class="mt-6 space-y-4">
                @foreach ($submissions as $sub)
                    <details class="card" @if ($sub->status === 'submitted' && $loop->first) open @endif>
                        <summary class="flex cursor-pointer flex-wrap items-center gap-3 p-4">
                            <x-avatar :member="$sub->member" size="size-9" />
                            <span class="min-w-0 flex-1"><span class="block font-semibold">{{ $sub->member->full_name }}</span><span class="text-xs text-ink-soft">{{ $sub->assignment->title }} · {{ $sub->member->currentPlacement?->orgUnit?->name }} · {{ $sub->submitted_at->format('j M, g:ia') }}</span></span>
                            <span @class(['badge', 'badge-ok' => $sub->status === 'accepted', 'badge-warn' => $sub->status === 'returned', 'badge-info' => $sub->status === 'submitted'])>{{ $sub->statusLabel() }}</span>
                        </summary>
                        <div class="border-t border-line p-4">
                            @if ($sub->body)<div class="whitespace-pre-line rounded-xl bg-paper-2 p-4 font-serif">{{ $sub->body }}</div>@endif
                            @if ($sub->file_name)<a href="{{ route('academy.submission-file', $sub) }}" class="btn-ghost btn-sm mt-3 no-underline"><x-icon name="download" class="size-4" /> {{ $sub->file_name }}</a>@endif
                            @if ($sub->link)<p class="mt-3 text-sm"><a href="{{ $sub->link }}" target="_blank" rel="noopener" class="link">{{ $sub->link }}</a></p>@endif
                            <form method="POST" action="{{ route('academy.manage.submissions.review', [$course, $sub]) }}" class="mt-4 space-y-3">@csrf
                                <div><label class="label" for="fb-{{ $sub->id }}">Feedback</label><textarea id="fb-{{ $sub->id }}" name="feedback" rows="4" class="input">{{ $sub->feedback }}</textarea></div>
                                @if ($sub->assignment->max_score)<div class="max-w-40"><label class="label" for="sc-{{ $sub->id }}">Score out of {{ $sub->assignment->max_score }}</label><input id="sc-{{ $sub->id }}" type="number" name="score" min="0" max="{{ $sub->assignment->max_score }}" value="{{ $sub->score }}" class="input"></div>@endif
                                <div class="flex flex-wrap gap-2"><button name="status" value="accepted" class="btn-primary btn-sm">Accept</button><button name="status" value="returned" class="btn-ghost btn-sm">Return for more work</button></div>
                                @if ($sub->reviewed_at)<p class="text-xs text-ink-soft">Last reviewed by {{ $sub->reviewer?->member?->full_name }} on {{ $sub->reviewed_at->format('j M') }}</p>@endif
                            </form>
                        </div>
                    </details>
                @endforeach
            </div>
        @endif
    </section>
</x-layouts.app>
