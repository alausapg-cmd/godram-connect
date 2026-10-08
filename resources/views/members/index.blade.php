<x-layouts.app title="Members">
    <section class="container-page mt-8">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="eyebrow">Connect</p>
                <h1 class="h-page">Members</h1>
                <p class="text-sm text-ink-soft">{{ number_format($members->total()) }} {{ str('member')->plural($members->total()) }} {{ $unit ? 'in '.$unit->fullName() : 'in your area' }}</p>
            </div>
            <div class="flex gap-2">
                @if (can_do('members.approve_signup'))<a href="{{ route('signups.index') }}" class="btn-ghost btn-sm no-underline">New sign-ups</a>@endif
                @if ($canExport)<a href="{{ route('members.export', request()->only('q', 'status', 'skill')) }}" class="btn-ghost btn-sm no-underline"><x-icon name="download" class="size-4" />CSV</a>@endif
                @if ($canCreate)<a href="{{ route('members.create') }}" class="btn-primary btn-sm no-underline"><x-icon name="plus" class="size-4" />Add member</a>@endif
            </div>
        </div>

        <form method="GET" class="card mt-5 grid gap-3 p-4 sm:grid-cols-2 lg:grid-cols-5">
            <div class="sm:col-span-2">
                <label for="q" class="sr-only">Search</label>
                <div class="relative">
                    <x-icon name="search" class="pointer-events-none absolute left-3 top-3 size-5 text-ink-soft" />
                    <input id="q" name="q" value="{{ request('q') }}" placeholder="Name, Member ID, phone or email" class="input pl-10">
                </div>
            </div>
            <select name="unit" class="input" aria-label="Area">
                <option value="">All my area</option>
                @foreach ($units as $u)<option value="{{ $u->id }}" @selected($unit?->id === $u->id)>{{ str_repeat('· ', max(0, $u->depth - ($units->first()->depth ?? 0))) }}{{ $u->name }}</option>@endforeach
            </select>
            <select name="skill" class="input" aria-label="Skill">
                <option value="">Any skill</option>
                @foreach ($skills as $s)<option value="{{ $s->id }}" @selected(request('skill') == $s->id)>{{ $s->name }}</option>@endforeach
            </select>
            <div class="flex gap-2">
                <select name="status" class="input" aria-label="Status">
                    <option value="">Current members</option>
                    @foreach (\App\Models\Member::STATUSES as $k => $label)<option value="{{ $k }}" @selected(request('status') === $k)>{{ $label }}</option>@endforeach
                </select>
                <button class="btn-dark shrink-0 px-4" aria-label="Filter"><x-icon name="search" class="size-4" /></button>
            </div>
        </form>

        @if ($members->isEmpty())
            <x-empty title="No members found" class="mt-6">Try a different name or clear the filters. @if($canCreate)New people can be added with <a class="link" href="{{ route('members.create') }}">Add member</a>.@endif</x-empty>
        @else
            <div class="card mt-5 overflow-hidden">
                <ul class="divide-y divide-line">
                    @foreach ($members as $m)
                        <li>
                            <a href="{{ route('members.show', $m) }}" class="flex items-center gap-3 px-4 py-3 no-underline hover:bg-paper/60">
                                <x-avatar :member="$m" />
                                <div class="min-w-0 flex-1">
                                    <p class="truncate font-semibold text-ink">{{ $m->full_name }}</p>
                                    <p class="truncate text-xs text-ink-soft">{{ $m->member_no }} · {{ $m->currentPlacement?->orgUnit?->name }}, {{ $m->currentPlacement?->orgUnit?->parent?->name }}</p>
                                </div>
                                <div class="hidden flex-wrap justify-end gap-1 md:flex">
                                    @foreach ($m->skills->take(3) as $skill)<span class="badge-neutral">{{ $skill->name }}</span>@endforeach
                                </div>
                                @if ($m->status !== 'active')<x-status-badge :status="$m->status" :label="$m->statusLabel()" />@endif
                                <x-icon name="chevron-right" class="size-4 text-ink-soft" />
                            </a>
                        </li>
                    @endforeach
                </ul>
            </div>
            <div class="mt-4">{{ $members->links() }}</div>
        @endif
    </section>
</x-layouts.app>
