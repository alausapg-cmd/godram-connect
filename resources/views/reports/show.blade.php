<x-layouts.app title="{{ $report->displayTitle() }}" :hide-errors="true">
    <section class="container-page mt-8 max-w-4xl">
        <a href="{{ route('reports.index') }}" class="text-sm font-semibold text-curtain">Reports</a>
        <div class="mt-2 flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <p class="eyebrow">{{ $report->typeLabel() }}</p>
                <h1 class="h-page">{{ $report->displayTitle() }}</h1>
                <p class="text-sm text-ink-soft">{{ $report->reference }} · {{ $report->orgUnit->fullName() }} · by {{ $report->creator?->name ?? 'unknown' }}</p>
            </div>
            <x-status-badge :status="$report->status" :label="$report->statusLabel()" />
        </div>

        @if ($canReview)
            <div class="mt-6 rounded-2xl border-2 border-poster/40 bg-white p-5" x-data="{ returning: {{ $errors->has('note') ? 'true' : 'false' }} }">
                <p class="font-semibold">This report is waiting for your review</p>
                <p class="text-sm text-ink-soft">Approving it adds it to dashboards and to each participant's history. Returning it sends it back with your note.</p>
                <form method="POST" action="{{ route('reports.review', $report) }}" class="mt-4 space-y-3">
                    @csrf
                    <div x-show="returning" x-cloak>
                        <label for="note" class="label">What needs correcting?</label>
                        <textarea id="note" name="note" rows="2" class="input">{{ old('note') }}</textarea>
                        @error('note')<p class="error">{{ $message }}</p>@enderror
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <button name="decision" value="approve" class="btn-primary btn-sm" x-show="!returning"><x-icon name="check" class="size-4" />Approve</button>
                        <button type="button" class="btn-ghost btn-sm" x-show="!returning" @click="returning = true">Return for correction</button>
                        <button name="decision" value="reject" class="btn-primary btn-sm" x-show="returning" x-cloak>Send back</button>
                        <button type="button" class="btn-ghost btn-sm" x-show="returning" x-cloak @click="returning = false">Cancel</button>
                        @if ($report->status === 'submitted')<button name="decision" value="start" class="btn-ghost btn-sm" x-show="!returning">Mark as under review</button>@endif
                    </div>
                </form>
            </div>
        @endif

        @if ($report->status === 'rejected' && $report->reject_reason)
            <div class="mt-6 rounded-2xl border border-curtain/30 bg-curtain/5 p-4 text-sm"><p class="font-semibold text-curtain">Returned for correction</p><p class="mt-1">{{ $report->reject_reason }}</p></div>
        @endif

        <div class="mt-6 flex flex-wrap gap-2">
            @if ($canEdit)<a href="{{ route('reports.edit', $report) }}" class="btn-primary btn-sm no-underline">{{ $report->status === 'rejected' ? 'Correct and resubmit' : 'Continue editing' }}</a>@endif
            @if ($canPublish)<form method="POST" action="{{ route('reports.publish', $report) }}">@csrf<button class="btn-gold btn-sm">Publish as a public highlight</button></form>@endif
            @if ($report->status === 'published')<a href="{{ route('highlights.show', $report) }}" class="btn-ghost btn-sm no-underline"><x-icon name="external" class="size-4" />View public page</a>@endif
            @if ($canArchive)<form method="POST" action="{{ route('reports.archive', $report) }}" onsubmit="return confirm('Archive this report?')">@csrf<button class="btn-ghost btn-sm">Archive</button></form>@endif
        </div>

        <div class="mt-6 grid gap-4 lg:grid-cols-3">
            <div class="space-y-4 lg:col-span-2">
                <div class="card-pad">
                    <dl class="grid gap-4 text-sm sm:grid-cols-2">
                        <div><dt class="text-ink-soft">Date</dt><dd class="font-medium">{{ $report->activity_date?->format('l j F Y') }}</dd></div>
                        <div><dt class="text-ink-soft">Where</dt><dd class="font-medium">{{ $report->location }}</dd></div>
                        @if ($report->event_name)<div><dt class="text-ink-soft">Programme</dt><dd class="font-medium">{{ $report->event_name }}</dd></div>@endif
                    </dl>
                    <div class="mt-4 grid grid-cols-3 gap-3">
                        <div class="rounded-xl bg-paper p-3"><p class="text-xs text-ink-soft">Performers</p><p class="font-display text-2xl font-semibold">{{ $report->performers_count ?? '–' }}</p></div>
                        <div class="rounded-xl bg-paper p-3"><p class="text-xs text-ink-soft">Attendance</p><p class="font-display text-2xl font-semibold">{{ $report->attendance_count !== null ? number_format($report->attendance_count) : '–' }}</p></div>
                        <div class="rounded-xl bg-paper p-3"><p class="text-xs text-ink-soft">Responded</p><p class="font-display text-2xl font-semibold">{{ $report->souls_won ?? '–' }}</p></div>
                    </div>
                    <div class="mt-5 space-y-4 text-sm leading-relaxed">
                        <div><h2 class="font-semibold">What happened</h2><p class="mt-1 whitespace-pre-line text-ink-soft">{{ $report->description }}</p></div>
                        @if ($report->outcome)<div><h2 class="font-semibold">Outcome</h2><p class="mt-1 whitespace-pre-line text-ink-soft">{{ $report->outcome }}</p></div>@endif
                        @if ($report->impact)<div><h2 class="font-semibold">Impact</h2><p class="mt-1 whitespace-pre-line text-ink-soft">{{ $report->impact }}</p></div>@endif
                        @if ($report->remarks)<div><h2 class="font-semibold">Remarks</h2><p class="mt-1 whitespace-pre-line text-ink-soft">{{ $report->remarks }}</p></div>@endif
                        @if ($report->video_url)<p><a href="{{ $report->video_url }}" target="_blank" rel="noopener" class="link">Watch the video</a></p>@endif
                    </div>
                </div>
                @if ($report->media->isNotEmpty())
                    <div class="card-pad">
                        <h2 class="h-section">Photos and files</h2>
                        <div class="mt-3 grid grid-cols-2 gap-3 sm:grid-cols-3">
                            @foreach ($report->media as $media)
                                @if ($media->isImage())
                                    <a href="{{ route('report-media.show', $media) }}" target="_blank"><img src="{{ route('report-media.show', $media) }}" alt="Photo from the activity" class="aspect-square w-full rounded-lg object-cover" loading="lazy"></a>
                                @else
                                    <a href="{{ route('report-media.show', $media) }}" class="flex aspect-square flex-col items-center justify-center rounded-lg bg-paper-2 p-2 text-center text-xs no-underline"><x-icon name="download" class="mb-1 size-6" />{{ \Illuminate\Support\Str::limit($media->original_name, 30) }}</a>
                                @endif
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>
            <div class="space-y-4">
                <div class="card-pad">
                    <h2 class="h-section">Took part</h2>
                    <ul class="mt-3 space-y-2 text-sm">
                        @forelse ($report->participants as $p)
                            <li class="flex items-center gap-2"><x-avatar :member="$p" size="size-7" class="text-[10px]" /><a href="{{ route('members.show', $p) }}" class="text-ink">{{ $p->full_name }}</a></li>
                        @empty
                            <li class="text-ink-soft">No members named.</li>
                        @endforelse
                    </ul>
                </div>
                <div class="card-pad">
                    <h2 class="h-section">History</h2>
                    <ol class="mt-3 space-y-3 text-sm">
                        <li class="text-ink-soft">Started {{ $report->created_at->format('j M Y') }}</li>
                        @foreach ($report->events as $e)
                            <li>
                                <p><span class="font-semibold">{{ ucfirst(str_replace('_', ' ', $e->action)) }}</span> <span class="text-ink-soft">by {{ $e->user?->name ?? 'system' }}</span></p>
                                <p class="text-xs text-ink-soft">{{ $e->created_at->format('j M Y, H:i') }}</p>
                                @if ($e->note)<p class="mt-1 rounded-lg bg-paper px-2 py-1 text-xs">{{ $e->note }}</p>@endif
                            </li>
                        @endforeach
                    </ol>
                    @if ($canComment)
                        <form method="POST" action="{{ route('reports.comment', $report) }}" class="mt-4 border-t border-line pt-4">
                            @csrf
                            <label for="comment" class="label">Add a comment</label>
                            <textarea id="comment" name="comment" rows="2" class="input" maxlength="1000" required placeholder="Encouragement, a question or a note for the coordinator"></textarea>
                            @error('comment')<p class="error">{{ $message }}</p>@enderror
                            <button class="btn-ghost btn-sm mt-2">Post comment</button>
                        </form>
                    @endif
                </div>
            </div>
        </div>
    </section>
</x-layouts.app>
