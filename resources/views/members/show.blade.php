<x-layouts.app :title="$member->full_name">
    <section class="container-page mt-8">
        <div class="card overflow-hidden">
            <div class="poster-stripe h-2"></div>
            <div class="flex flex-col gap-5 p-6 sm:flex-row sm:items-center sm:justify-between">
                <div class="flex items-center gap-4">
                    <x-avatar :member="$member" size="size-16" class="text-xl" />
                    <div>
                        <h1 class="font-display text-3xl font-semibold uppercase text-stage">{{ $member->full_name }}</h1>
                        <p class="text-sm text-ink-soft"><span class="font-mono font-semibold text-ink">{{ $member->isPending() ? 'Awaiting confirmation' : $member->member_no }}</span>@if($assembly) · {{ $assembly->fullName() }}@endif</p>
                        <div class="mt-2 flex flex-wrap gap-1.5">
                            <x-status-badge :status="$member->status" :label="$member->statusLabel()" />
                            @foreach ($member->roleAssignments->filter(fn($a) => ! $a->ends_on || $a->ends_on->isFuture()) as $a)<span class="badge-info">{{ $a->title() }}</span>@endforeach
                        </div>
                    </div>
                </div>
                <div class="flex flex-wrap gap-2">
                    @if ($canEdit)<a href="{{ route('members.edit', $member) }}" class="btn-ghost btn-sm no-underline">Edit</a>@endif
                    @if ($canTransfer && ! $member->isPending())<a href="{{ route('transfers.create', $member) }}" class="btn-ghost btn-sm no-underline"><x-icon name="transfer" class="size-4" />Transfer</a>@endif
                    @if (auth()->user()->member_id === $member->id)<a href="{{ route('profile.edit') }}" class="btn-ghost btn-sm no-underline">Edit my skills</a>@endif
                </div>
            </div>
        </div>

        <div class="mt-4 grid gap-4 lg:grid-cols-3">
            <div class="space-y-4 lg:col-span-2">
                <div class="card-pad">
                    <h2 class="h-section">Details</h2>
                    <dl class="mt-3 grid gap-x-6 gap-y-3 text-sm sm:grid-cols-2">
                        <div><dt class="text-ink-soft">Phone</dt><dd class="font-medium">{{ \App\Support\Phone::display($member->phone) ?? 'Not given' }}</dd></div>
                        <div><dt class="text-ink-soft">Email</dt><dd class="font-medium">{{ $member->email ?? 'Not given' }}</dd></div>
                        <div><dt class="text-ink-soft">Joined GODRAM</dt><dd class="font-medium">{{ $member->joined_on?->format('j F Y') ?? 'Not recorded' }}</dd></div>
                        <div><dt class="text-ink-soft">Sign-in account</dt><dd class="font-medium">{{ $member->user ? 'Yes'.($member->user->last_login_at ? ', last used '.$member->user->last_login_at->diffForHumans() : '') : 'None' }}</dd></div>
                    </dl>
                    @if ($member->bio)<p class="mt-4 border-t border-line pt-4 text-sm text-ink-soft">{{ $member->bio }}</p>@endif
                    <div class="mt-4 flex flex-wrap gap-1.5 border-t border-line pt-4">
                        @forelse ($member->skills as $skill)<span class="badge-neutral">{{ $skill->name }}</span>@empty<span class="text-sm text-ink-soft">No skills recorded.</span>@endforelse
                    </div>
                </div>

                <div class="card-pad">
                    <h2 class="h-section">Participation</h2>
                    @forelse ($reports as $r)
                        <a href="{{ route('reports.show', $r) }}" class="mt-3 flex items-center justify-between gap-3 border-t border-line pt-3 text-sm no-underline first-of-type:border-0">
                            <span><span class="font-semibold text-ink">{{ $r->displayTitle() }}</span><span class="block text-xs text-ink-soft">{{ $r->typeLabel() }} · {{ $r->activity_date?->format('j M Y') }}</span></span>
                            <x-icon name="chevron-right" class="size-4 text-ink-soft" />
                        </a>
                    @empty
                        <p class="mt-2 text-sm text-ink-soft">No approved activities yet. When an activity report names this member, it appears here.</p>
                    @endforelse
                </div>
            </div>

            <div class="space-y-4">
                <div class="card-pad">
                    <h2 class="h-section">Where they belong</h2>
                    <ol class="mt-3 space-y-3 text-sm">
                        @foreach ($member->placements as $p)
                            <li class="flex gap-3">
                                <span @class(['mt-1.5 size-2.5 shrink-0 rounded-full', 'bg-ok' => ! $p->ends_on, 'bg-line' => $p->ends_on])></span>
                                <span><span class="font-semibold">{{ $p->orgUnit->fullName() }}</span><span class="block text-xs text-ink-soft">{{ $p->starts_on->format('M Y') }} to {{ $p->ends_on?->format('M Y') ?? 'now' }} · {{ $p->reason }}</span></span>
                            </li>
                        @endforeach
                    </ol>
                    @php $pendingTransfer = $member->transferRequests->firstWhere('status', 'pending'); @endphp
                    @if ($pendingTransfer)<p class="mt-3 rounded-lg bg-poster/10 px-3 py-2 text-xs text-poster">Transfer to {{ $pendingTransfer->toUnit->fullName() }} is waiting for approval.</p>@endif
                </div>

                @if ($canEdit)
                    <div class="card-pad space-y-4">
                        <h2 class="h-section">Manage</h2>
                        <form method="POST" action="{{ route('members.status', $member) }}" class="space-y-2">
                            @csrf
                            <label for="status" class="label">Status</label>
                            <select id="status" name="status" class="input">
                                @foreach (\App\Models\Member::STATUSES as $k => $label)<option value="{{ $k }}" @selected($member->status === $k)>{{ $label }}</option>@endforeach
                            </select>
                            <input name="note" class="input" placeholder="Reason (optional)" maxlength="200">
                            <button class="btn-ghost btn-sm w-full">Update status</button>
                        </form>
                        @if ($member->user)
                            <form method="POST" action="{{ route('members.reset-password', $member) }}" onsubmit="return confirm('Issue a temporary password for {{ $member->first_name }}?')">@csrf<button class="btn-ghost btn-sm w-full">Issue temporary password</button></form>
                        @elseif ($member->phone || $member->email)
                            <form method="POST" action="{{ route('members.account', $member) }}">@csrf<button class="btn-ghost btn-sm w-full">Create sign-in account</button></form>
                        @endif
                    </div>
                @endif

                @if ($member->statusChanges->isNotEmpty())
                    <div class="card-pad">
                        <h2 class="h-section">Status history</h2>
                        <ul class="mt-2 space-y-2 text-xs text-ink-soft">
                            @foreach ($member->statusChanges as $c)
                                <li>{{ $c->created_at->format('j M Y') }}: {{ \App\Models\Member::STATUSES[$c->to_status] ?? $c->to_status }}@if($c->note) · {{ $c->note }}@endif</li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            </div>
        </div>
    </section>
</x-layouts.app>
