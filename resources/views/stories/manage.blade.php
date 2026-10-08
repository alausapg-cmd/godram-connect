<x-layouts.app title="Story review">
    <section class="container-page mt-8">
        <div class="flex flex-wrap items-end justify-between gap-3">
            <div><p class="eyebrow">GODRAM Stories</p><h1 class="h-page mt-1">Review queue</h1></div>
            <a href="{{ route('stories.mine') }}" class="btn-ghost btn-sm no-underline">My own stories</a>
        </div>
        <div class="card mt-6 overflow-x-auto">
            <table class="table">
                <thead><tr><th>Story</th><th>Writer</th><th>Sent</th><th>Status</th></tr></thead>
                <tbody>
                    @forelse ($queue as $story)
                        <tr><td><a href="{{ route('stories.show', $story) }}" class="link">{{ $story->title }}</a><span class="block text-xs text-ink-soft">{{ $story->typeLabel() }}</span></td>
                            <td>{{ $story->author?->name }}</td><td>{{ $story->submitted_at?->diffForHumans() }}</td>
                            <td><x-status-badge :status="$story->status" :label="$story->statusLabel()" /></td></tr>
                    @empty
                        <tr><td colspan="4" class="py-8 text-center text-ink-soft">Nothing waiting. Well done.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <h2 class="h-section mt-10">Recently published</h2>
        <ul class="mt-3 divide-y divide-line rounded-[var(--radius-card)] border border-line bg-white">
            @forelse ($published as $story)
                <li class="flex items-center justify-between gap-3 px-4 py-3"><a href="{{ route('stories.show', $story) }}" class="font-semibold text-ink">{{ $story->title }}</a><span class="flex items-center gap-2 text-sm text-ink-soft">@if ($story->is_featured)<span class="badge-info">Featured</span>@endif{{ $story->published_at?->format('j M Y') }}</span></li>
            @empty
                <li class="px-4 py-6 text-center text-sm text-ink-soft">No stories published yet.</li>
            @endforelse
        </ul>
    </section>
</x-layouts.app>
