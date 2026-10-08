<x-layouts.app title="Videos">
    <section class="container-page mt-8">
        <div class="flex flex-wrap items-end justify-between gap-3">
            <div><p class="eyebrow">GODRAM TV</p><h1 class="h-page mt-1">Videos</h1></div>
            <div class="flex gap-2"><a href="{{ route('watch') }}" class="btn-ghost btn-sm no-underline">Open the Watch centre</a><a href="{{ route('videos.create') }}" class="btn-primary btn-sm no-underline"><x-icon name="plus" class="size-4" /> {{ $canManage ? 'Add a video' : 'Suggest a video' }}</a></div>
        </div>

        @if ($canManage)
            <h2 class="h-section mt-8">Waiting for you ({{ $pending->count() }})</h2>
            <div class="mt-3 space-y-3">
                @forelse ($pending as $video)
                    <div class="card flex flex-col gap-4 p-4 sm:flex-row">
                        <a href="{{ route('watch.show', $video) }}" class="shrink-0"><img src="{{ $video->thumbnailUrl('mqdefault') }}" alt="" class="aspect-video w-full rounded-lg object-cover sm:w-48"></a>
                        <div class="min-w-0 flex-1">
                            <p class="font-semibold">{{ $video->title }}</p>
                            <p class="text-sm text-ink-soft">{{ $video->categoryLabel() }} · suggested by {{ $video->submitter?->name }} · {{ $video->created_at->diffForHumans() }}</p>
                            <div class="mt-3 flex flex-wrap items-start gap-2" x-data="{ reject: false }">
                                <form method="POST" action="{{ route('videos.decide', $video) }}">@csrf<input type="hidden" name="decision" value="publish"><button class="btn-primary btn-sm"><x-icon name="check" class="size-4" /> Publish</button></form>
                                <a href="{{ route('videos.edit', $video) }}" class="btn-ghost btn-sm no-underline">Edit first</a>
                                <button type="button" class="btn-ghost btn-sm" @click="reject = !reject">Return</button>
                                <form x-show="reject" x-cloak method="POST" action="{{ route('videos.decide', $video) }}" class="flex w-full gap-2">@csrf<input type="hidden" name="decision" value="reject">
                                    <input name="reason" class="input" placeholder="Why it is not suitable" required><button class="btn-dark btn-sm shrink-0">Send</button></form>
                            </div>
                        </div>
                    </div>
                @empty
                    <p class="text-sm text-ink-soft">No suggestions waiting.</p>
                @endforelse
            </div>
        @endif

        <h2 class="h-section mt-10">Your suggestions</h2>
        <div class="card mt-3 overflow-x-auto">
            <table class="table">
                <thead><tr><th>Video</th><th>Category</th><th>Status</th></tr></thead>
                <tbody>
                    @forelse ($mine as $video)
                        <tr><td><a href="{{ route('watch.show', $video) }}" class="link">{{ $video->title }}</a>@if ($video->reject_reason)<span class="block text-xs text-curtain">{{ $video->reject_reason }}</span>@endif</td><td>{{ $video->categoryLabel() }}</td>
                            <td><x-status-badge :status="$video->status" :label="['submitted' => 'Waiting', 'published' => 'In the Watch centre', 'rejected' => 'Returned', 'archived' => 'Removed'][$video->status] ?? $video->status" /></td></tr>
                    @empty
                        <tr><td colspan="3" class="py-6 text-center text-ink-soft">You have not suggested any videos yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($canManage && $recent->isNotEmpty())
            <h2 class="h-section mt-10">In the Watch centre</h2>
            <div class="mt-4 grid gap-6 sm:grid-cols-2 lg:grid-cols-4">@foreach ($recent as $video)<x-video-card :video="$video" />@endforeach</div>
        @endif
    </section>
</x-layouts.app>
