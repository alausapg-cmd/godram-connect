@php
    $editing = $event->exists;
    $people = old('people', $editing ? $event->people->map(fn ($p) => ['name' => $p->name, 'role' => $p->role])->all() : []);
    $people = array_pad($people, max(3, count($people) + 1), ['name' => '', 'role' => 'performer']);
    $dt = fn ($v) => $v ? \Illuminate\Support\Carbon::parse($v)->format('Y-m-d\TH:i') : '';
@endphp
<x-layouts.app :title="$editing ? 'Edit event' : 'Add an event'">
    <section class="container-page mt-8 max-w-3xl">
        <a href="{{ $editing ? route('events.show', $event) : route('events') }}" class="text-sm font-semibold text-curtain">{{ $editing ? $event->title : 'Events' }}</a>
        <h1 class="h-page mt-2">{{ $editing ? 'Edit event' : 'Add an event' }}</h1>
        <p class="mt-1 text-sm text-ink-soft">Events go on the calendar as soon as you save. Members-only events are hidden from the public website.</p>

        <form method="POST" action="{{ $editing ? route('events.update', $event) : route('events.store') }}" enctype="multipart/form-data" class="mt-6 space-y-6"
              x-data="{ online: {{ old('is_online', $event->is_online) ? 'true' : 'false' }}, stream: {{ old('stream_url', $event->stream_url) ? 'true' : 'false' }}, reg: {{ old('registration_open', $event->registration_open) ? 'true' : 'false' }} }">
            @csrf @if ($editing) @method('PUT') @endif

            <div class="card-pad space-y-5">
                <div><label for="title" class="label">Event name</label><input id="title" name="title" value="{{ old('title', $event->title) }}" class="input" maxlength="150" required>@error('title')<p class="error">{{ $message }}</p>@enderror</div>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div><label for="type" class="label">Kind of event</label>
                        <select id="type" name="type" class="input">@foreach (\App\Models\Event::TYPES as $k => $l)<option value="{{ $k }}" @selected(old('type', $event->type) === $k)>{{ $l }}</option>@endforeach</select></div>
                    <div><label for="org_unit_id" class="label">Organised by</label>
                        <select id="org_unit_id" name="org_unit_id" class="input">@foreach ($units as $u)<option value="{{ $u->id }}" @selected((int) old('org_unit_id', $event->org_unit_id) === $u->id)>{{ $u->fullName() }}</option>@endforeach</select>@error('org_unit_id')<p class="error">{{ $message }}</p>@enderror</div>
                </div>
                <div><label for="description" class="label">What is it about?</label><textarea id="description" name="description" rows="6" class="input" required>{{ old('description', $event->description) }}</textarea>@error('description')<p class="error">{{ $message }}</p>@enderror</div>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div><label for="starts_at" class="label">Starts</label><input id="starts_at" type="datetime-local" name="starts_at" value="{{ old('starts_at', $dt($event->starts_at)) }}" class="input" required>@error('starts_at')<p class="error">{{ $message }}</p>@enderror</div>
                    <div><label for="ends_at" class="label">Ends <span class="font-normal text-ink-soft">(optional)</span></label><input id="ends_at" type="datetime-local" name="ends_at" value="{{ old('ends_at', $dt($event->ends_at)) }}" class="input">@error('ends_at')<p class="error">{{ $message }}</p>@enderror</div>
                </div>
            </div>

            <div class="card-pad space-y-5">
                <h2 class="h-section">Where</h2>
                <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="is_online" value="1" x-model="online" class="size-4 accent-curtain"> Online only</label>
                <div x-show="!online" class="grid gap-4 sm:grid-cols-2">
                    <div><label for="location" class="label">Venue</label><input id="location" name="location" :disabled="online" value="{{ old('location', $event->location) }}" class="input" placeholder="GOFAMINT Ayantuga, Mushin">@error('location')<p class="error">{{ $message }}</p>@enderror</div>
                    <div><label for="address" class="label">Address <span class="font-normal text-ink-soft">(optional)</span></label><input id="address" name="address" :disabled="online" value="{{ old('address', $event->address) }}" class="input"></div>
                </div>
                <label class="flex items-center gap-2 text-sm"><input type="checkbox" x-model="stream" class="size-4 accent-curtain"> It will be streamed live</label>
                <div x-show="stream || online" x-cloak class="grid gap-4 sm:grid-cols-[1fr_2fr]">
                    <div><label for="stream_platform" class="label">Platform</label>
                        <select id="stream_platform" name="stream_platform" class="input" :disabled="!(stream || online)"><option value="">Choose</option>@foreach (\App\Models\Event::PLATFORMS as $k => $l)<option value="{{ $k }}" @selected(old('stream_platform', $event->stream_platform) === $k)>{{ $l }}</option>@endforeach</select>@error('stream_platform')<p class="error">{{ $message }}</p>@enderror</div>
                    <div><label for="stream_url" class="label">Link to the stream</label><input id="stream_url" type="url" name="stream_url" :disabled="!(stream || online)" value="{{ old('stream_url', $event->stream_url) }}" class="input" placeholder="https://www.youtube.com/@GODRAMTV/live">@error('stream_url')<p class="error">{{ $message }}</p>@enderror
                        <p class="hint">For YouTube, a link to the exact live video lets people watch inside GODRAM CONNECT.</p></div>
                </div>
                @if ($editing)
                    <div><label for="replay_url" class="label">Replay on YouTube <span class="font-normal text-ink-soft">(after the event)</span></label><input id="replay_url" name="replay_url" value="{{ old('replay_url', $event->replay_youtube_id ? 'https://youtu.be/'.$event->replay_youtube_id : '') }}" class="input" placeholder="https://youtu.be/...">@error('replay_url')<p class="error">{{ $message }}</p>@enderror</div>
                @endif
            </div>

            <div class="card-pad space-y-5">
                <h2 class="h-section">Who can see it and register</h2>
                <div class="flex flex-wrap gap-4 text-sm">
                    <label class="flex items-center gap-2"><input type="radio" name="visibility" value="public" class="size-4 accent-curtain" @checked(old('visibility', $event->visibility) === 'public')> Everyone, on the public website</label>
                    <label class="flex items-center gap-2"><input type="radio" name="visibility" value="members" class="size-4 accent-curtain" @checked(old('visibility', $event->visibility) === 'members')> Signed-in members only</label>
                </div>
                <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="registration_open" value="1" x-model="reg" class="size-4 accent-curtain"> People should register to attend</label>
                <div x-show="reg" x-cloak class="grid gap-4 sm:grid-cols-2">
                    <div><label for="capacity" class="label">Places available <span class="font-normal text-ink-soft">(optional)</span></label><input id="capacity" type="number" min="1" name="capacity" value="{{ old('capacity', $event->capacity) }}" class="input"></div>
                    <div><label for="registration_closes_at" class="label">Registration closes <span class="font-normal text-ink-soft">(optional)</span></label><input id="registration_closes_at" type="datetime-local" name="registration_closes_at" value="{{ old('registration_closes_at', $dt($event->registration_closes_at)) }}" class="input">@error('registration_closes_at')<p class="error">{{ $message }}</p>@enderror</div>
                </div>
            </div>

            <div class="card-pad space-y-5">
                <h2 class="h-section">People, picture and production</h2>
                <div>
                    <p class="label">Facilitators, performers and speakers</p>
                    <div class="space-y-2">
                        @foreach ($people as $i => $p)
                            <div class="grid grid-cols-[1fr_9rem] gap-2">
                                <input name="people[{{ $i }}][name]" value="{{ $p['name'] }}" class="input" placeholder="Name" aria-label="Name">
                                <select name="people[{{ $i }}][role]" class="input" aria-label="Role">@foreach (\App\Models\Event::ROLES as $k => $l)<option value="{{ $k }}" @selected(($p['role'] ?? 'performer') === $k)>{{ $l }}</option>@endforeach</select>
                            </div>
                        @endforeach
                    </div>
                    <p class="hint">Save to get another empty row.</p>
                </div>
                <div><label for="cover" class="label">Poster or picture</label><input id="cover" type="file" name="cover" accept="image/*" class="block w-full text-sm">@error('cover')<p class="error">{{ $message }}</p>@enderror</div>
                @if ($productions->isNotEmpty())
                    <div><label for="production_id" class="label">Production being staged <span class="font-normal text-ink-soft">(optional)</span></label>
                        <select id="production_id" name="production_id" class="input"><option value="">None</option>@foreach ($productions as $p)<option value="{{ $p->id }}" @selected((int) old('production_id', $event->production_id) === $p->id)>{{ $p->title }}</option>@endforeach</select></div>
                @endif
            </div>

            <div class="flex justify-end gap-2">
                <a href="{{ $editing ? route('events.show', $event) : route('events') }}" class="btn-ghost no-underline">Cancel</a>
                <button class="btn-primary">{{ $editing ? 'Save changes' : 'Put it on the calendar' }}</button>
            </div>
        </form>
    </section>
</x-layouts.app>
