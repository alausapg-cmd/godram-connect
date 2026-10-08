<x-layouts.app title="Reports">
    <section class="container-page mt-8">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="eyebrow">Report</p>
                <h1 class="h-page">Activity reports</h1>
            </div>
            <div class="flex flex-wrap gap-2">
                @foreach ($reportUnits as $u)
                    <form method="POST" action="{{ route('reports.start') }}">@csrf<input type="hidden" name="org_unit_id" value="{{ $u->id }}"><button class="btn-primary btn-sm"><x-icon name="plus" class="size-4" />New report{{ $reportUnits->count() > 1 ? ': '.$u->name : '' }}</button></form>
                @endforeach
            </div>
        </div>

        <nav class="mt-5 flex gap-1 overflow-x-auto border-b border-line" aria-label="Report lists">
            @foreach (['all' => 'All in my area', 'review' => 'To review', 'mine' => 'Mine'] as $key => $label)
                @if ($key !== 'review' || can_do('reports.approve'))
                    <a href="{{ route('reports.index', ['tab' => $key]) }}" @class(['-mb-px shrink-0 border-b-2 px-4 py-2.5 text-sm font-semibold no-underline', 'border-curtain text-curtain' => $tab === $key, 'border-transparent text-ink-soft hover:text-ink' => $tab !== $key])>{{ $label }}</a>
                @endif
            @endforeach
        </nav>

        <form method="GET" class="mt-4 flex flex-wrap gap-2">
            <input type="hidden" name="tab" value="{{ $tab }}">
            <input name="q" value="{{ request('q') }}" placeholder="Search title or reference" class="input max-w-xs">
            <select name="type" class="input w-auto">
                <option value="">Any activity</option>
                @foreach (\App\Models\ActivityReport::TYPES as $k => $label)<option value="{{ $k }}" @selected(request('type') === $k)>{{ $label }}</option>@endforeach
            </select>
            <select name="status" class="input w-auto">
                <option value="">Any status</option>
                @foreach (\App\Models\ActivityReport::STATUSES as $k => $label)<option value="{{ $k }}" @selected(request('status') === $k)>{{ $label }}</option>@endforeach
            </select>
            <button class="btn-dark">Filter</button>
        </form>

        @if ($reports->isEmpty())
            <x-empty title="{{ $tab === 'review' ? 'Nothing to review' : 'No reports yet' }}" class="mt-6">
                {{ $tab === 'review' ? 'Reports submitted from the level below you will wait here for your review.' : 'Reports of performances, outreaches and trainings will appear here. Start one with New report.' }}
            </x-empty>
        @else
            <div class="card mt-5 overflow-hidden">
                <ul class="divide-y divide-line">
                    @foreach ($reports as $r)
                        <li>
                            <a href="{{ $r->isEditable() && $r->created_by === auth()->id() ? route('reports.edit', $r) : route('reports.show', $r) }}" class="flex items-center gap-3 px-4 py-3 no-underline hover:bg-paper/60">
                                <div class="hidden w-14 shrink-0 text-center sm:block">
                                    <p class="font-display text-2xl font-semibold leading-none text-stage">{{ $r->activity_date?->format('d') }}</p>
                                    <p class="text-xs uppercase text-ink-soft">{{ $r->activity_date?->format('M') }}</p>
                                </div>
                                <div class="min-w-0 flex-1">
                                    <p class="truncate font-semibold text-ink">{{ $r->displayTitle() }}</p>
                                    <p class="truncate text-xs text-ink-soft">{{ $r->reference }} · {{ $r->typeLabel() }} · {{ $r->orgUnit->fullName() }}</p>
                                </div>
                                <x-status-badge :status="$r->status" :label="$r->statusLabel()" />
                            </a>
                        </li>
                    @endforeach
                </ul>
            </div>
            <div class="mt-4">{{ $reports->links() }}</div>
        @endif
    </section>
</x-layouts.app>
