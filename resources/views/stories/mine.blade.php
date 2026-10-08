<x-layouts.app title="My stories">
    <section class="container-page mt-8 max-w-4xl">
        <div class="flex flex-wrap items-end justify-between gap-3">
            <div><p class="eyebrow">GODRAM Stories</p><h1 class="h-page mt-1">My stories</h1></div>
            <div class="flex gap-2">
                @if (can_do('stories.review'))<a href="{{ route('stories.manage') }}" class="btn-ghost btn-sm no-underline">Review queue</a>@endif
                <a href="{{ route('stories.create') }}" class="btn-primary btn-sm no-underline"><x-icon name="edit" class="size-4" /> Share a story</a>
            </div>
        </div>
        <div class="mt-6 space-y-3">
            @forelse ($stories as $story)
                <a href="{{ $story->isEditable() ? route('stories.edit', $story) : route('stories.show', $story) }}" class="card flex items-center justify-between gap-4 p-4 no-underline hover:border-ink-soft">
                    <span class="min-w-0"><span class="block truncate font-semibold text-ink">{{ $story->title }}</span><span class="text-sm text-ink-soft">{{ $story->typeLabel() }} · {{ $story->updated_at->diffForHumans() }}</span>
                        @if ($story->status === 'rejected')<span class="block text-sm text-curtain">Returned with a note. Tap to edit.</span>@endif</span>
                    <x-status-badge :status="$story->status" :label="$story->statusLabel()" />
                </a>
            @empty
                <x-empty title="You have not shared a story yet">A testimony, a production you were part of, or how drama ministry changed your life: every story encourages someone.</x-empty>
            @endforelse
        </div>
    </section>
</x-layouts.app>
