@php $editing = $story->exists; @endphp
<x-layouts.app :title="$editing ? 'Edit your story' : 'Share your story'">
    <section class="container-page mt-8 max-w-3xl">
        <a href="{{ route('stories.mine') }}" class="text-sm font-semibold text-curtain">My stories</a>
        <h1 class="h-page mt-2">{{ $editing ? 'Edit your story' : 'Share your story' }}</h1>
        <p class="mt-1 text-ink-soft">Tell it as you would tell a friend: what happened, who was there and what God did. The national team reads every story before it is published, and may suggest small changes.</p>
        @if ($story->reject_reason)<div class="mt-4 rounded-2xl border border-curtain/30 bg-curtain/5 p-4 text-sm"><p class="font-semibold text-curtain">Note from the reviewer</p><p class="mt-1">{{ $story->reject_reason }}</p></div>@endif
        @error('story')<div class="mt-4 rounded-2xl border border-curtain/30 bg-curtain/5 p-4 text-sm text-curtain" role="alert">{{ $message }}</div>@enderror

        <form method="POST" action="{{ $editing ? route('stories.update', $story) : route('stories.store') }}" enctype="multipart/form-data" class="mt-6 space-y-6">
            @csrf @if ($editing) @method('PUT') @endif
            <div class="card-pad space-y-5">
                <div class="grid gap-4 sm:grid-cols-[2fr_1fr]">
                    <div><label for="title" class="label">Headline</label><input id="title" name="title" value="{{ old('title', $story->title) }}" class="input" maxlength="150" required>@error('title')<p class="error">{{ $message }}</p>@enderror</div>
                    <div><label for="type" class="label">Kind of story</label><select id="type" name="type" class="input">@foreach (\App\Models\Story::TYPES as $k => $l)<option value="{{ $k }}" @selected(old('type', $story->type) === $k)>{{ $l }}</option>@endforeach</select></div>
                </div>
                <div><label for="standfirst" class="label">In one or two sentences</label><input id="standfirst" name="standfirst" value="{{ old('standfirst', $story->standfirst) }}" class="input" maxlength="300" placeholder="The short version that draws readers in"></div>
                <div><label for="body" class="label">Your story</label><textarea id="body" name="body" rows="14" class="input font-serif text-lg leading-relaxed">{{ old('body', $story->body) }}</textarea>
                    <p class="hint">Leave a blank line between paragraphs. You can save and come back later.</p>@error('body')<p class="error">{{ $message }}</p>@enderror</div>
                <div class="grid gap-4 sm:grid-cols-[2fr_1fr]">
                    <div><label for="quote" class="label">A line worth quoting <span class="font-normal text-ink-soft">(optional)</span></label><input id="quote" name="quote" value="{{ old('quote', $story->quote) }}" class="input" maxlength="400"></div>
                    <div><label for="quote_by" class="label">Who said it</label><input id="quote_by" name="quote_by" value="{{ old('quote_by', $story->quote_by) }}" class="input" maxlength="120"></div>
                </div>
            </div>

            <div class="card-pad space-y-5">
                <h2 class="h-section">Pictures, video and sound</h2>
                <div><label for="cover" class="label">Main picture</label>
                    @if ($story->coverUrl())<img src="{{ $story->coverUrl() }}" alt="" class="mb-2 h-32 rounded-xl object-cover">@endif
                    <input id="cover" type="file" name="cover" accept="image/*" class="block w-full text-sm">@error('cover')<p class="error">{{ $message }}</p>@enderror</div>
                <div><label for="youtube_url" class="label">YouTube video <span class="font-normal text-ink-soft">(optional)</span></label><input id="youtube_url" name="youtube_url" value="{{ old('youtube_url', $story->youtube_id ? 'https://youtu.be/'.$story->youtube_id : '') }}" class="input" placeholder="https://youtu.be/...">@error('youtube_url')<p class="error">{{ $message }}</p>@enderror</div>
                <div><label for="audio" class="label">Voice recording <span class="font-normal text-ink-soft">(optional, MP3 or M4A, up to 20 MB)</span></label>
                    @if ($story->audio_path)<p class="mb-1 text-sm text-ok">A recording is attached. Choose a new file to replace it.</p>@endif
                    <input id="audio" type="file" name="audio" accept="audio/*" class="block w-full text-sm">@error('audio')<p class="error">{{ $message }}</p>@enderror</div>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div><label for="event_id" class="label">Related event</label><select id="event_id" name="event_id" class="input"><option value="">None</option>@foreach ($events as $e)<option value="{{ $e->id }}" @selected((int) old('event_id', $story->event_id) === $e->id)>{{ $e->title }} ({{ $e->starts_at->format('M Y') }})</option>@endforeach</select></div>
                    <div><label for="production_id" class="label">Related production</label><select id="production_id" name="production_id" class="input"><option value="">None</option>@foreach ($productions as $p)<option value="{{ $p->id }}" @selected((int) old('production_id', $story->production_id) === $p->id)>{{ $p->title }}</option>@endforeach</select></div>
                </div>
            </div>

            <div class="flex flex-wrap justify-end gap-2">
                <button name="action" value="draft" class="btn-ghost">Save draft</button>
                <button name="action" value="submit" class="btn-primary">Send for review</button>
            </div>
        </form>
    </section>
</x-layouts.app>
