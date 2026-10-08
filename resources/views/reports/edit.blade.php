@php
    $performance = json_encode(\App\Models\ActivityReport::PERFORMANCE_TYPES);
    $outreach = json_encode(\App\Models\ActivityReport::OUTREACH_TYPES);
    $chosen = old('participants', $report->participants->pluck('id')->all());
@endphp
<x-layouts.app title="Report: {{ $report->displayTitle() }}" :hide-errors="true">
    <section class="container-page mt-8 max-w-3xl">
        <div class="flex items-start justify-between gap-3">
            <div>
                <a href="{{ route('reports.index', ['tab' => 'mine']) }}" class="text-sm font-semibold text-curtain">My reports</a>
                <h1 class="h-page mt-2">{{ $report->status === 'rejected' ? 'Correct and resubmit' : 'Report an activity' }}</h1>
                <p class="text-sm text-ink-soft">{{ $report->reference }} · {{ $report->orgUnit->fullName() }}</p>
            </div>
            <x-status-badge :status="$report->status" :label="$report->statusLabel()" />
        </div>

        @if ($report->status === 'rejected' && $report->reject_reason)
            <div class="mt-5 rounded-2xl border border-curtain/30 bg-curtain/5 p-4 text-sm">
                <p class="font-semibold text-curtain">Returned by your reviewer</p>
                <p class="mt-1">{{ $report->reject_reason }}</p>
            </div>
        @endif
        @error('report')<div class="mt-5 rounded-2xl border border-curtain/30 bg-curtain/5 p-4 text-sm text-curtain" role="alert">{{ $message }}</div>@enderror
        @if (collect($errors->keys())->diff(['report', 'files'])->isNotEmpty())
            <div class="mt-5 rounded-2xl border border-curtain/30 bg-curtain/5 p-4 text-sm text-curtain" role="alert">Please check the highlighted fields.</div>
        @endif

        <form id="report-form" method="POST" action="{{ route('reports.update', $report) }}"
              x-data="autosave('{{ route('reports.autosave', $report) }}')"
              class="mt-6 space-y-5">
            @csrf @method('PUT')

            <div class="card-pad space-y-5" x-data="{ type: '{{ old('activity_type', $report->activity_type) }}', perf: {{ $performance }}, outreach: {{ $outreach }} }">
                <div>
                    <label for="activity_type" class="label">What kind of activity?</label>
                    <select id="activity_type" name="activity_type" x-model="type" class="input" required>
                        <option value="">Choose one</option>
                        @foreach (\App\Models\ActivityReport::TYPES as $k => $label)<option value="{{ $k }}">{{ $label }}</option>@endforeach
                    </select>
                    @error('activity_type')<p class="error">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="title" class="label">Title</label>
                    <input id="title" name="title" value="{{ old('title', $report->title) }}" class="input" placeholder="For example: Easter drama at the market square" maxlength="150" required>
                    @error('title')<p class="error">{{ $message }}</p>@enderror
                </div>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label for="activity_date" class="label">Date</label>
                        <input id="activity_date" type="date" name="activity_date" value="{{ old('activity_date', $report->activity_date?->toDateString()) }}" max="{{ now()->toDateString() }}" class="input" required>
                        @error('activity_date')<p class="error">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="location" class="label">Where</label>
                        <input id="location" name="location" value="{{ old('location', $report->location) }}" class="input" placeholder="Venue and town" required>
                        @error('location')<p class="error">{{ $message }}</p>@enderror
                    </div>
                </div>
                <div>
                    <label for="event_name" class="label">Part of a programme or event? <span class="font-normal text-ink-soft">(optional)</span></label>
                    <input id="event_name" name="event_name" value="{{ old('event_name', $report->event_name) }}" class="input" placeholder="For example: Harvest Thanksgiving 2026">
                </div>
                <div>
                    <label for="description" class="label">What happened?</label>
                    <textarea id="description" name="description" rows="4" class="input" required>{{ old('description', $report->description) }}</textarea>
                    @error('description')<p class="error">{{ $message }}</p>@enderror
                </div>
                <div class="grid gap-4 sm:grid-cols-3">
                    <div x-show="perf.includes(type)">
                        <label for="performers_count" class="label">Performers</label>
                        <input id="performers_count" type="number" min="0" inputmode="numeric" name="performers_count" value="{{ old('performers_count', $report->performers_count) }}" class="input">
                    </div>
                    <div>
                        <label for="attendance_count" class="label">Audience / attendance</label>
                        <input id="attendance_count" type="number" min="0" inputmode="numeric" name="attendance_count" value="{{ old('attendance_count', $report->attendance_count) }}" class="input">
                    </div>
                    <div x-show="outreach.includes(type)">
                        <label for="souls_won" class="label">Responded to the altar call</label>
                        <input id="souls_won" type="number" min="0" inputmode="numeric" name="souls_won" value="{{ old('souls_won', $report->souls_won) }}" class="input">
                    </div>
                </div>
            </div>

            <details class="card-pad group" @if($report->outcome || $report->impact || $report->remarks || $report->video_url) open @endif>
                <summary class="cursor-pointer list-none font-semibold">Outcome and impact <span class="font-normal text-ink-soft">(optional)</span></summary>
                <div class="mt-4 space-y-4">
                    <div><label for="outcome" class="label">Outcome</label><textarea id="outcome" name="outcome" rows="2" class="input">{{ old('outcome', $report->outcome) }}</textarea></div>
                    <div><label for="impact" class="label">Impact and testimonies</label><textarea id="impact" name="impact" rows="2" class="input">{{ old('impact', $report->impact) }}</textarea></div>
                    <div><label for="remarks" class="label">Remarks for your reviewer</label><textarea id="remarks" name="remarks" rows="2" class="input">{{ old('remarks', $report->remarks) }}</textarea></div>
                    <div><label for="video_url" class="label">Video link (YouTube or Facebook)</label><input id="video_url" type="url" name="video_url" value="{{ old('video_url', $report->video_url) }}" class="input" placeholder="https://">@error('video_url')<p class="error">{{ $message }}</p>@enderror</div>
                </div>
            </details>

            <div class="card-pad" x-data="filterList()">
                <p class="font-semibold">Who took part?</p>
                <p class="hint">Tick members from {{ $report->orgUnit->fullName() }}. This builds their participation history.</p>
                <input type="hidden" name="participants_present" value="1">
                @if ($members->count() > 8)<input x-model="term" class="input mt-3" placeholder="Search names">@endif
                <div class="mt-3 grid max-h-72 gap-1 overflow-y-auto sm:grid-cols-2">
                    @forelse ($members as $m)
                        <label x-show="matches('{{ addslashes($m->full_name) }}')" class="flex items-center gap-2 rounded-lg px-2 py-1.5 text-sm hover:bg-paper">
                            <input type="checkbox" name="participants[]" value="{{ $m->id }}" class="size-4 accent-curtain" @checked(in_array($m->id, $chosen))>
                            {{ $m->full_name }}
                        </label>
                    @empty
                        <p class="text-sm text-ink-soft">No active members are registered here yet.</p>
                    @endforelse
                </div>
            </div>

            <div class="sticky bottom-20 z-30 flex items-center justify-between gap-3 rounded-2xl border border-line bg-white/95 p-3 shadow-lg backdrop-blur lg:bottom-4">
                <p class="text-xs text-ink-soft" aria-live="polite">
                    <span x-show="state === 'saved'">Draft saved<span x-show="savedAt" x-text="' at ' + savedAt"></span></span>
                    <span x-show="state === 'saving'">Saving…</span>
                    <span x-show="state === 'unsaved'">Unsaved changes</span>
                    <span x-show="state === 'offline'" class="text-warn">Offline: keep this page open, we will save when you reconnect.</span>
                    <span x-show="state === 'error'" class="text-curtain">Could not save just now. Your work is still on this page.</span>
                </p>
                <div class="flex gap-2">
                    <button name="action" value="save" class="btn-ghost btn-sm">Save draft</button>
                    <button name="action" value="submit" class="btn-primary btn-sm">Submit for review</button>
                </div>
            </div>
        </form>

        <div class="card-pad mt-5">
            <p class="font-semibold">Photos and documents</p>
            <p class="hint">Photos are compressed automatically. Up to {{ config('godram.uploads.max_files_per_report') }} files.</p>
            @error('files')<p class="error">{{ $message }}</p>@enderror
            @error('files.*')<p class="error">{{ $message }}</p>@enderror
            @if ($report->media->isNotEmpty())
                <div class="mt-4 grid grid-cols-3 gap-3 sm:grid-cols-4">
                    @foreach ($report->media as $media)
                        <div class="relative">
                            @if ($media->isImage())
                                <img src="{{ route('report-media.show', $media) }}" alt="" class="aspect-square w-full rounded-lg object-cover">
                            @else
                                <a href="{{ route('report-media.show', $media) }}" class="flex aspect-square w-full flex-col items-center justify-center rounded-lg bg-paper-2 p-2 text-center text-xs no-underline"><x-icon name="report" class="mb-1 size-6" />{{ \Illuminate\Support\Str::limit($media->original_name, 24) }}</a>
                            @endif
                            <form method="POST" action="{{ route('report-media.destroy', $media) }}" class="absolute right-1 top-1">@csrf @method('DELETE')<button class="rounded-full bg-stage/80 p-1 text-white" title="Remove"><x-icon name="x" class="size-4" /><span class="sr-only">Remove</span></button></form>
                        </div>
                    @endforeach
                </div>
            @endif
            <form method="POST" action="{{ route('report-media.store', $report) }}" enctype="multipart/form-data" class="mt-4 flex flex-col gap-2 sm:flex-row sm:items-center">
                @csrf
                <label class="btn-ghost btn-sm cursor-pointer">
                    <x-icon name="upload" class="size-4" />Choose photos or files
                    <input type="file" name="files[]" multiple accept="image/*,.pdf,.doc,.docx" class="sr-only" onchange="this.form.querySelector('[data-count]').textContent = this.files.length + ' selected'">
                </label>
                <span data-count class="text-xs text-ink-soft"></span>
                <button class="btn-dark btn-sm sm:ml-auto">Upload</button>
            </form>
            <p class="hint mt-2">Tip: save your draft before uploading so no typing is lost.</p>
        </div>

        @if ($report->status === 'draft')
            <form method="POST" action="{{ route('reports.destroy', $report) }}" class="mt-6 text-center" onsubmit="return confirm('Delete this draft?')">@csrf @method('DELETE')<button class="text-sm font-semibold text-curtain">Delete this draft</button></form>
        @endif
    </section>
</x-layouts.app>
