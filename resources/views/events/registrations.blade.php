<x-layouts.app :title="'Registrations · '.$event->title">
    <section class="container-page mt-8">
        <a href="{{ route('events.show', $event) }}" class="text-sm font-semibold text-curtain">{{ $event->title }}</a>
        <div class="mt-2 flex flex-wrap items-end justify-between gap-3">
            <div>
                <h1 class="h-page">Registrations</h1>
                <p class="text-sm text-ink-soft">{{ $registrations->where('status', '!=', 'cancelled')->count() }} registered · {{ $registrations->whereNotNull('attended_at')->count() }} marked present{{ $event->capacity ? ' · '.$event->capacity.' places' : '' }}</p>
            </div>
            <a href="{{ route('events.registrations', [$event, 'format' => 'csv']) }}" class="btn-ghost btn-sm no-underline"><x-icon name="download" class="size-4" /> Download list</a>
        </div>

        <div class="card mt-6 overflow-x-auto" x-data="filterList">
            <div class="border-b border-line p-3"><input x-model="term" type="search" class="input" placeholder="Find a name or reference"></div>
            <table class="table">
                <thead><tr><th>Name</th><th>Assembly</th><th>Phone</th><th>Reference</th><th class="text-right">Present</th></tr></thead>
                <tbody>
                    @forelse ($registrations as $r)
                        <tr x-show="matches(@js($r->name.' '.$r->reference))" @class(['opacity-50' => $r->status === 'cancelled'])>
                            <td class="font-semibold">{{ $r->name }}@if ($r->member)<span class="block text-xs font-normal text-ink-soft">{{ $r->member->member_no }}</span>@endif @if ($r->status === 'cancelled')<span class="badge-bad">Cancelled</span>@endif</td>
                            <td>{{ $r->member?->currentPlacement?->orgUnit?->name ?? 'Guest' }}</td>
                            <td>{{ \App\Support\Phone::display($r->phone) }}</td>
                            <td class="font-mono text-xs">{{ $r->reference }}</td>
                            <td class="text-right">
                                @if ($r->status !== 'cancelled')
                                    <form method="POST" action="{{ route('events.attended', [$event, $r]) }}">@csrf
                                        <button @class(['inline-flex size-9 items-center justify-center rounded-full border', 'border-ok bg-ok text-white' => $r->attended_at, 'border-line bg-white text-ink-soft' => ! $r->attended_at]) title="{{ $r->attended_at ? 'Present' : 'Mark present' }}"><x-icon name="check" class="size-4" /></button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="py-8 text-center text-ink-soft">No one has registered yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
</x-layouts.app>
