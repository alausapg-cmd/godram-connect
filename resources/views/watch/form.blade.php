@php $editing = $video->exists; @endphp
<x-layouts.app :title="$editing ? 'Edit video' : ($canManage ? 'Add a video' : 'Suggest a video')">
    <section class="container-page mt-8 max-w-2xl">
        <a href="{{ route('videos.manage') }}" class="text-sm font-semibold text-curtain">Videos</a>
        <h1 class="h-page mt-2">{{ $editing ? 'Edit video' : ($canManage ? 'Add a video' : 'Suggest a video') }}</h1>
        @unless ($editing || $canManage)<p class="mt-1 text-sm text-ink-soft">Upload the video to YouTube first (GODRAM TV or your own channel), then paste the link here. The national media team checks every suggestion before it appears in the Watch centre.</p>@endunless

        <form method="POST" action="{{ $editing ? route('videos.update', $video) : route('videos.store') }}" class="card-pad mt-6 space-y-5">
            @csrf @if ($editing) @method('PUT') @endif
            @if ($editing)<x-youtube :id="$video->youtube_id" :title="$video->title" />@endif
            <div>
                <label for="youtube_url" class="label">YouTube link</label>
                <input id="youtube_url" name="youtube_url" value="{{ old('youtube_url', $editing ? $video->youtubeUrl() : '') }}" class="input" placeholder="https://youtu.be/..." @unless($editing) required @endunless inputmode="url">
                @error('youtube_url')<p class="error">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="title" class="label">Title @unless($editing)<span class="font-normal text-ink-soft">(leave empty to use the YouTube title)</span>@endunless</label>
                <input id="title" name="title" value="{{ old('title', $video->title) }}" class="input" maxlength="150">
                @error('title')<p class="error">{{ $message }}</p>@enderror
            </div>
            <div class="grid gap-4 sm:grid-cols-2">
                <div><label for="category" class="label">Category</label>
                    <select id="category" name="category" class="input">@foreach (\App\Models\Video::CATEGORIES as $k => $l)<option value="{{ $k }}" @selected(old('category', $video->category) === $k)>{{ $l }}</option>@endforeach</select></div>
                <div><label for="recorded_on" class="label">Recorded on <span class="font-normal text-ink-soft">(optional)</span></label><input id="recorded_on" type="date" name="recorded_on" value="{{ old('recorded_on', $video->recorded_on?->toDateString()) }}" class="input">@error('recorded_on')<p class="error">{{ $message }}</p>@enderror</div>
            </div>
            <div><label for="description" class="label">Description <span class="font-normal text-ink-soft">(optional)</span></label><textarea id="description" name="description" rows="4" class="input">{{ old('description', $video->description) }}</textarea></div>
            <div class="grid gap-4 sm:grid-cols-2">
                <div><label for="event_id" class="label">From an event</label>
                    <select id="event_id" name="event_id" class="input"><option value="">None</option>@foreach ($events as $e)<option value="{{ $e->id }}" @selected((int) old('event_id', $video->event_id) === $e->id)>{{ $e->title }} ({{ $e->starts_at->format('M Y') }})</option>@endforeach</select></div>
                <div><label for="production_id" class="label">Part of a production</label>
                    <select id="production_id" name="production_id" class="input"><option value="">None</option>@foreach ($productions as $p)<option value="{{ $p->id }}" @selected((int) old('production_id', $video->production_id) === $p->id)>{{ $p->title }}</option>@endforeach</select></div>
            </div>
            <div class="flex justify-end gap-2">
                <a href="{{ $editing ? route('watch.show', $video) : route('videos.manage') }}" class="btn-ghost no-underline">Cancel</a>
                <button class="btn-primary">{{ $editing ? 'Save' : ($canManage ? 'Add to the Watch centre' : 'Send to the media team') }}</button>
            </div>
        </form>
    </section>
</x-layouts.app>
