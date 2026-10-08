<x-layouts.app title="My GODRAM">
    <section class="container-page mt-8">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex items-center gap-4">
                @if ($member)<x-avatar :member="$member" size="size-14" class="text-lg" />@endif
                <div>
                    <p class="eyebrow">My GODRAM</p>
                    <h1 class="font-display text-3xl font-semibold uppercase text-stage">Welcome, {{ $member?->first_name ?? $user->name }}</h1>
                    <p class="text-sm text-ink-soft">{{ $user->primaryTitle() }}@if($assembly) · {{ $assembly->fullName() }}@endif</p>
                </div>
            </div>
            @if ($scopes->count() > 1)
                <form method="GET" class="flex items-center gap-2">
                    <label for="scope" class="text-sm text-ink-soft">Viewing</label>
                    <select id="scope" name="scope" onchange="this.form.submit()" class="input min-h-9 w-auto py-1 text-sm">
                        @foreach ($scopes as $s)<option value="{{ $s->id }}" @selected($scope?->id === $s->id)>{{ $s->fullName() }}</option>@endforeach
                    </select>
                </form>
            @endif
        </div>

        @if ($member?->isPending())
            <div class="mt-6 rounded-2xl border border-gold bg-gold/15 p-5">
                <p class="font-semibold">Your membership is waiting for confirmation</p>
                <p class="mt-1 text-sm text-ink-soft">The Assembly Coordinator of {{ $assembly?->fullName() }} will confirm you shortly. You can explore GODRAM CONNECT in the meantime.</p>
            </div>
        @endif

        @isset($stats)
            {{-- Leadership view --}}
            <div class="mt-8">
                <h2 class="h-section">{{ $scope->fullName() }}</h2>
                <p class="text-sm text-ink-soft">Last 90 days, approved reports only.</p>
            </div>

            @if ($attention->isNotEmpty())
                <div class="mt-4 card divide-y divide-line">
                    <p class="px-5 pt-4 text-xs font-semibold uppercase tracking-wide text-ink-soft">Needs your attention</p>
                    @foreach ($attention as $item)
                        <div class="flex flex-col gap-2 px-5 py-3 sm:flex-row sm:items-center sm:justify-between">
                            <p class="flex items-start gap-2 text-sm">
                                <x-icon :name="$item['tone'] === 'warning' ? 'alert' : ($item['tone'] === 'info' ? 'clock' : 'arrow-right')" @class(['mt-0.5 size-4 shrink-0', 'text-curtain' => $item['tone'] === 'warning', 'text-poster' => $item['tone'] === 'action', 'text-ink-soft' => $item['tone'] === 'info']) />
                                {{ $item['text'] }}
                            </p>
                            @if ($item['url'])<a href="{{ $item['url'] }}" class="btn-ghost btn-sm shrink-0 no-underline">{{ $item['action'] }}</a>@endif
                        </div>
                    @endforeach
                </div>
            @endif

            <div class="mt-4 grid grid-cols-2 gap-3 lg:grid-cols-4">
                <x-stat label="Members" :value="$stats['members']" :note="$stats['active'].' active · '.$stats['new'].' new'" />
                <x-stat label="Activities" :value="$stats['reports']" note="approved reports" />
                <x-stat label="Reached" :value="$stats['attendance']" note="total attendance" />
                <x-stat label="Responded" :value="$stats['souls']" note="at outreaches" />
            </div>

            <div class="mt-4 flex flex-wrap gap-2">
                @if (can_do('reports.create'))
                    @foreach (app(\App\Services\Access::class)->grants($user, 'reports.create')->map->orgUnit->filter()->unique('id') as $unit)
                        <form method="POST" action="{{ route('reports.start') }}">@csrf<input type="hidden" name="org_unit_id" value="{{ $unit->id }}"><button class="btn-primary btn-sm"><x-icon name="plus" class="size-4" />Report an activity{{ $loop->count > 1 ? ' ('.$unit->name.')' : '' }}</button></form>
                    @endforeach
                @endif
                @if (can_do('members.create'))<a href="{{ route('members.create') }}" class="btn-ghost btn-sm no-underline"><x-icon name="plus" class="size-4" />Add member</a>@endif
                @if (can_do('reports.approve'))<a href="{{ route('reports.index', ['tab' => 'review']) }}" class="btn-ghost btn-sm no-underline">Review reports</a>@endif
                @if (can_do('announcements.create'))<a href="{{ route('announcements.create') }}" class="btn-ghost btn-sm no-underline"><x-icon name="megaphone" class="size-4" />Announce</a>@endif
                @if (can_do('members.view'))<a href="{{ route('members.index') }}" class="btn-ghost btn-sm no-underline">View members</a>@endif
                @if ($drafts)<a href="{{ route('reports.index', ['tab' => 'mine']) }}" class="btn-ghost btn-sm no-underline">{{ $drafts }} {{ str('draft')->plural($drafts) }}</a>@endif
            </div>

            <div class="mt-6 grid gap-4 lg:grid-cols-3">
                <div class="card-pad lg:col-span-2">
                    <h3 class="font-display text-lg font-semibold uppercase text-stage">{{ $scope->childType() ? str(ucfirst($scope->childType()))->plural() : 'Activity' }}</h3>
                    @if ($children->isNotEmpty())
                        <div class="mt-3 overflow-x-auto">
                            <table class="table">
                                <thead><tr><th>Name</th><th class="text-right">Active members</th><th class="text-right">Reports (90 days)</th><th>Last report</th></tr></thead>
                                <tbody>
                                @foreach ($children as $c)
                                    <tr>
                                        <td><a href="{{ route('network', $c->unit) }}" class="font-semibold text-ink">{{ $c->unit->name }}</a></td>
                                        <td class="text-right tabular-nums">{{ $c->members }}</td>
                                        <td class="text-right tabular-nums">{{ $c->reports }}</td>
                                        <td>@if($c->last_report)<span @class(['text-curtain font-medium' => $c->last_report->lt(now()->subDays(60))])>{{ $c->last_report->diffForHumans() }}</span>@else<span class="text-curtain">None yet</span>@endif</td>
                                    </tr>
                                @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <p class="mt-2 text-sm text-ink-soft">Your Assembly's own activity is shown in the reports below.</p>
                    @endif
                </div>
                <div class="card-pad">
                    <h3 class="font-display text-lg font-semibold uppercase text-stage">Activities by month</h3>
                    @php $max = max(1, max(array_column($trend, 'count'))); @endphp
                    <div class="mt-4 flex h-36 items-end gap-2" role="img" aria-label="Approved activities per month: {{ collect($trend)->map(fn($m) => $m['label'].' '.$m['count'])->join(', ') }}">
                        @foreach ($trend as $m)
                            <div class="flex flex-1 flex-col items-center gap-1">
                                <span class="text-xs font-semibold tabular-nums">{{ $m['count'] }}</span>
                                <div class="w-full rounded-t-md {{ $loop->last ? 'bg-curtain' : 'bg-poster/60' }}" style="height: {{ max(4, round($m['count'] / $max * 100)) }}px"></div>
                                <span class="text-xs text-ink-soft">{{ $m['label'] }}</span>
                            </div>
                        @endforeach
                    </div>
                    @if ($skills->isNotEmpty())
                        <h3 class="mt-6 font-display text-lg font-semibold uppercase text-stage">Top skills</h3>
                        <ul class="mt-2 flex flex-wrap gap-1.5">
                            @foreach ($skills as $s)<li class="badge-neutral">{{ $s->name }} · {{ $s->total }}</li>@endforeach
                        </ul>
                    @endif
                </div>
            </div>

            <div class="mt-6 card-pad">
                <div class="flex items-center justify-between">
                    <h3 class="font-display text-lg font-semibold uppercase text-stage">Recent approved activity</h3>
                    <a href="{{ route('reports.index') }}" class="link text-sm">All reports</a>
                </div>
                @forelse ($recent as $r)
                    <a href="{{ route('reports.show', $r) }}" class="mt-3 flex items-center justify-between gap-3 border-t border-line pt-3 text-sm no-underline first-of-type:border-0">
                        <span><span class="font-semibold text-ink">{{ $r->displayTitle() }}</span><span class="block text-xs text-ink-soft">{{ $r->orgUnit->fullName() }} · {{ $r->activity_date?->format('j M') }}</span></span>
                        <x-status-badge :status="$r->status" :label="$r->statusLabel()" />
                    </a>
                @empty
                    <p class="mt-2 text-sm text-ink-soft">No approved activity yet in the last few months.</p>
                @endforelse
            </div>
        @endisset

        {{-- Everyone: personal area --}}
        @if ($queues->isNotEmpty())
            <div class="mt-8 flex flex-wrap gap-3">
                @foreach ($queues as [$count, $noun, $label, $url])
                    <a href="{{ $url }}" class="card flex items-center gap-3 px-4 py-3 no-underline hover:border-ink-soft"><span class="font-display text-2xl font-semibold text-curtain">{{ $count }}</span><span class="text-sm font-semibold text-ink">{{ $label }}</span><x-icon name="chevron-right" class="size-4 text-ink-soft" /></a>
                @endforeach
            </div>
        @endif
        <div class="mt-8 grid gap-4 lg:grid-cols-3">
            <div class="card-pad lg:col-span-3">
                <div class="flex items-center justify-between">
                    <h2 class="h-section">Coming up</h2>
                    <a href="{{ route('events') }}" class="link text-sm">All events</a>
                </div>
                @if ($events->isNotEmpty())
                    <div class="mt-3 grid gap-3 md:grid-cols-3">
                        @foreach ($events as $event)
                            <div class="relative">
                                <x-event-card :event="$event" class="h-full" />
                                @if (in_array($event->id, $places))<span class="badge-ok absolute right-3 top-3">You are going</span>@endif
                            </div>
                        @endforeach
                    </div>
                @else
                    <p class="mt-2 text-sm text-ink-soft">No events on the calendar yet.@if (can_do('events.create')) <a href="{{ route('events.create') }}" class="link">Add one</a>.@endif</p>
                @endif
            </div>
        </div>
        <div class="mt-4 grid gap-4 lg:grid-cols-3">
            <div class="card-pad lg:col-span-2">
                <div class="flex items-center justify-between">
                    <h2 class="h-section">Announcements for you</h2>
                    <a href="{{ route('announcements.index') }}" class="link text-sm">All</a>
                </div>
                @forelse ($announcements as $a)
                    <a href="{{ route('announcements.show', $a) }}" class="mt-3 block border-t border-line pt-3 no-underline first-of-type:border-0">
                        <p class="font-semibold text-ink">@if($a->is_mandatory)<span class="badge-bad mr-1">Required</span>@endif{{ $a->title }}</p>
                        <p class="line-clamp-2 text-sm text-ink-soft">{{ $a->body }}</p>
                    </a>
                @empty
                    <p class="mt-2 text-sm text-ink-soft">Nothing new. Announcements for your Assembly, District and Region will appear here.</p>
                @endforelse
            </div>
            <div class="card-pad">
                <h2 class="h-section">My record</h2>
                @if ($member)
                    <dl class="mt-3 space-y-2 text-sm">
                        <div class="flex justify-between gap-3"><dt class="text-ink-soft">Member ID</dt><dd class="font-mono font-semibold">{{ $member->isPending() ? 'Awaiting confirmation' : $member->member_no }}</dd></div>
                        @if ($assembly)
                            <div class="flex justify-between gap-3"><dt class="text-ink-soft">Assembly</dt><dd class="text-right">{{ $assembly->name }}</dd></div>
                            <div class="flex justify-between gap-3"><dt class="text-ink-soft">District</dt><dd class="text-right">{{ $assembly->ancestorOfType('district')?->name }}</dd></div>
                            <div class="flex justify-between gap-3"><dt class="text-ink-soft">Region</dt><dd class="text-right">{{ $assembly->ancestorOfType('region')?->name }}</dd></div>
                        @endif
                        <div class="flex justify-between gap-3"><dt class="text-ink-soft">Activities</dt><dd>{{ $member->reports()->counted()->count() }}</dd></div>
                    </dl>
                    <div class="mt-3 flex flex-wrap gap-1.5">
                        @forelse ($member->skills as $skill)<span class="badge-neutral">{{ $skill->name }}</span>@empty<span class="text-xs text-ink-soft">No skills added yet.</span>@endforelse
                    </div>
                    <div class="mt-4 flex gap-2">
                        <a href="{{ route('members.show', $member) }}" class="btn-ghost btn-sm no-underline">My profile</a>
                        <a href="{{ route('profile.edit') }}" class="btn-ghost btn-sm no-underline">Edit skills</a>
                    </div>
                @endif
                <a href="{{ route('academy.mine') }}" class="mt-6 flex items-center gap-3 rounded-xl bg-stage p-4 text-sm text-paper no-underline">
                    <x-icon name="academy" class="size-6 shrink-0 text-gold" />
                    <span class="min-w-0 flex-1"><span class="block font-semibold">My learning</span>
                        @php $learning = \App\Models\Enrolment::where('member_id', auth()->user()->member_id ?? 0)->where('status', 'active')->count(); @endphp
                        <span class="text-paper/70">{{ $learning ? $learning.' '.\Illuminate\Support\Str::plural('training', $learning).' in progress' : 'Explore the GODRAM Virtual Academy' }}</span></span>
                    <x-icon name="arrow-right" class="size-4" />
                </a>
                <a href="{{ route('stories.create') }}" class="mt-3 flex items-center gap-3 rounded-xl border border-line p-4 text-sm no-underline hover:border-ink-soft"><x-icon name="quote" class="size-5 text-poster" /><span><span class="block font-semibold text-ink">Share your story</span><span class="text-ink-soft">A testimony or a production you were part of</span></span></a>
            </div>
        </div>
    </section>
</x-layouts.app>
