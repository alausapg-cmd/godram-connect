@php $dt = fn ($v) => $v ? \Illuminate\Support\Carbon::parse($v)->format('Y-m-d\TH:i') : ''; $sid = $s?->id ?? 'new'; @endphp
<div class="grid gap-3 sm:grid-cols-2">
    <div class="sm:col-span-2"><label class="label" for="s-title-{{ $sid }}">Session title</label><input id="s-title-{{ $sid }}" name="title" value="{{ $s?->title }}" class="input" maxlength="150" required placeholder="The minister behind the character"></div>
    <div class="sm:col-span-2"><label class="label" for="s-sum-{{ $sid }}">Short description <span class="font-normal text-ink-soft">(optional)</span></label><input id="s-sum-{{ $sid }}" name="summary" value="{{ $s?->summary }}" class="input" maxlength="300"></div>
    <div><label class="label" for="s-at-{{ $sid }}">Live class starts <span class="font-normal text-ink-soft">(leave empty if there is no live class)</span></label><input id="s-at-{{ $sid }}" type="datetime-local" name="live_at" value="{{ $dt($s?->live_at) }}" class="input"></div>
    <div><label class="label" for="s-end-{{ $sid }}">Ends</label><input id="s-end-{{ $sid }}" type="datetime-local" name="live_ends_at" value="{{ $dt($s?->live_ends_at) }}" class="input"></div>
    <div><label class="label" for="s-pl-{{ $sid }}">Held on</label><select id="s-pl-{{ $sid }}" name="live_platform" class="input"><option value="">Choose</option>@foreach (\App\Models\Event::PLATFORMS as $k => $l)<option value="{{ $k }}" @selected($s?->live_platform === $k)>{{ $l }}</option>@endforeach</select></div>
    <div><label class="label" for="s-url-{{ $sid }}">Class link</label><input id="s-url-{{ $sid }}" type="url" name="live_url" value="{{ $s?->live_url }}" class="input" placeholder="https://meet.google.com/... or a YouTube live link"></div>
    @if ($s)
        <div class="sm:col-span-2"><label class="label" for="s-rep-{{ $sid }}">Recording on YouTube <span class="font-normal text-ink-soft">(after the class)</span></label><input id="s-rep-{{ $sid }}" name="replay_url" value="{{ $s->replay_youtube_id ? 'https://youtu.be/'.$s->replay_youtube_id : '' }}" class="input"></div>
    @endif
</div>
