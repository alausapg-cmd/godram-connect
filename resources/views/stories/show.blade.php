<x-layouts.app :title="$story->title" :description="$story->standfirst ?? $story->typeLabel().' from GODRAM Stories.'"
               :image="$story->status === 'published' ? route('share.card', ['story', $story->slug]) : null">
    <article>
        <header class="container-page mt-8 max-w-3xl sm:mt-12">
            <a href="{{ route('stories') }}" class="text-sm font-semibold text-curtain">GODRAM Stories</a>
            <p class="eyebrow mt-5">{{ $story->typeLabel() }}</p>
            <h1 class="mt-2 font-display text-4xl font-semibold uppercase leading-[1.05] text-stage sm:text-6xl">{{ $story->title }}</h1>
            @if ($story->standfirst)<p class="mt-5 font-serif text-xl leading-relaxed text-ink-soft sm:text-2xl">{{ $story->standfirst }}</p>@endif
            <div class="mt-6 flex flex-wrap items-center gap-x-4 gap-y-3 border-y border-line py-4 text-sm">
                @if ($story->author)
                    <span class="flex items-center gap-2">@if ($story->author->member)<x-avatar :member="$story->author->member" size="size-9" />@endif<span><span class="block font-semibold">{{ $story->author->name }}</span><span class="text-ink-soft">{{ $story->orgUnit?->fullName() }}</span></span></span>
                @endif
                <span class="text-ink-soft">{{ ($story->published_at ?? $story->updated_at)->format('j F Y') }} · {{ $story->readingMinutes() }} min read</span>
                @if ($story->status === 'published')<x-share :url="route('stories.show', $story)" :title="$story->title" class="sm:ml-auto" />@endif
            </div>
        </header>

        @if ($story->status !== 'published')
            <div class="container-page mt-6 max-w-3xl">
                <div class="rounded-2xl border border-line bg-paper-2 p-4 text-sm">
                    <p><x-status-badge :status="$story->status" :label="$story->statusLabel()" /> Only you and the national review team can see this story.</p>
                    @if ($story->reject_reason)<p class="mt-2 text-curtain"><span class="font-semibold">Note from the reviewer:</span> {{ $story->reject_reason }}</p>@endif
                    @if ($canEdit)<a href="{{ route('stories.edit', $story) }}" class="btn-ghost btn-sm mt-3 no-underline">Edit the story</a>@endif
                </div>
            </div>
        @endif

        @if ($story->coverUrl())
            <figure class="container-page mt-8 max-w-5xl"><img src="{{ $story->coverUrl() }}" alt="" class="max-h-[36rem] w-full rounded-3xl object-cover"></figure>
        @endif

        <div class="container-page mt-10 max-w-3xl">
            @if ($story->youtube_id)<x-youtube :id="$story->youtube_id" :title="$story->title" class="mb-8" />@endif
            @if ($story->audio_path)
                <div class="card mb-8 flex items-center gap-4 p-4"><span class="flex size-11 shrink-0 items-center justify-center rounded-full bg-stage text-gold"><x-icon name="mic" /></span>
                    <audio controls preload="none" class="w-full" src="{{ route('stories.audio', $story) }}">Your browser cannot play this recording.</audio></div>
            @endif
            <div class="prose-godram font-serif text-lg leading-relaxed sm:text-xl">{!! $story->bodyHtml() !!}</div>
            @if ($story->quote)
                <blockquote class="my-10 border-l-4 border-poster pl-6">
                    <p class="font-display text-2xl font-semibold uppercase leading-snug text-stage sm:text-3xl">&ldquo;{{ $story->quote }}&rdquo;</p>
                    @if ($story->quote_by)<footer class="mt-2 text-sm font-semibold text-ink-soft">{{ $story->quote_by }}</footer>@endif
                </blockquote>
            @endif

            @if ($story->event || $story->production)
                <div class="mt-10 grid gap-3 sm:grid-cols-2">
                    @if ($story->event)<a href="{{ route('events.show', $story->event) }}" class="card p-4 no-underline hover:border-ink-soft"><p class="eyebrow">Related event</p><p class="mt-1 font-semibold">{{ $story->event->title }}</p><p class="text-sm text-ink-soft">{{ $story->event->starts_at->format('j F Y') }}</p></a>@endif
                    @if ($story->production)<a href="{{ route('showcase.show', $story->production) }}" class="card p-4 no-underline hover:border-ink-soft"><p class="eyebrow">Related production</p><p class="mt-1 font-semibold">{{ $story->production->title }}</p><p class="text-sm text-ink-soft">{{ $story->production->kindLabel() }}</p></a>@endif
                </div>
            @endif

            @if ($canReview)
                <div class="card-pad mt-10" x-data="{ returning: false }">
                    <h2 class="h-section">Review</h2>
                    <p class="mt-1 text-sm text-ink-soft">Status: {{ $story->statusLabel() }}@if ($story->reviewer) · {{ $story->reviewer->name }}@endif</p>
                    <div class="mt-3 flex flex-wrap gap-2">
                        @foreach (['start' => ['Start review', ['submitted']], 'approve' => ['Approve', ['submitted', 'under_review']], 'publish' => ['Publish', ['submitted', 'under_review', 'approved']], 'feature' => [$story->is_featured ? 'Stop featuring' : 'Feature on Stories', ['published']], 'archive' => ['Archive', ['published', 'approved']]] as $decision => [$label, $from])
                            @if (in_array($story->status, $from))
                                <form method="POST" action="{{ route('stories.review', $story) }}">@csrf<input type="hidden" name="decision" value="{{ $decision }}"><button class="{{ $decision === 'publish' ? 'btn-primary' : 'btn-ghost' }} btn-sm">{{ $label }}</button></form>
                            @endif
                        @endforeach
                        @if (in_array($story->status, ['submitted', 'under_review', 'approved']))<button type="button" class="btn-ghost btn-sm" @click="returning = !returning">Return to writer</button>@endif
                    </div>
                    <form x-show="returning" x-cloak method="POST" action="{{ route('stories.review', $story) }}" class="mt-3 space-y-2">@csrf<input type="hidden" name="decision" value="reject">
                        <textarea name="note" rows="3" class="input" placeholder="What should the writer change?" required></textarea>@error('note')<p class="error">{{ $message }}</p>@enderror
                        <button class="btn-dark btn-sm">Send back</button></form>
                </div>
            @endif
        </div>

        @if ($more->isNotEmpty())
            <section class="container-page mt-16">
                <h2 class="h-section">More stories</h2>
                <div class="mt-5 grid gap-6 sm:grid-cols-3">@foreach ($more as $s)<x-story-card :story="$s" />@endforeach</div>
            </section>
        @endif
    </article>
</x-layouts.app>
