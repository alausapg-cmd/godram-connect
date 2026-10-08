<x-layouts.app title="Transfers">
    <section class="container-page mt-8 max-w-4xl">
        <p class="eyebrow">Connect</p>
        <h1 class="h-page">Transfers</h1>
        <p class="mt-1 text-ink-soft">A transfer moves a member to a new Assembly. They stay one person with one record.</p>

        <h2 class="h-section mt-8">Waiting for a decision</h2>
        @forelse ($incoming as $t)
            <div class="card mt-3 p-4">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <p class="font-semibold"><a href="{{ route('members.show', $t->member) }}" class="text-ink">{{ $t->member->full_name }}</a> <span class="font-mono text-xs text-ink-soft">{{ $t->member->member_no }}</span></p>
                        <p class="text-sm text-ink-soft">{{ $t->fromUnit->name }} ({{ $t->fromUnit->parent?->name }}) <x-icon name="arrow-right" class="inline size-4" /> <span class="font-semibold text-ink">{{ $t->toUnit->name }}</span> ({{ $t->toUnit->parent?->name }})</p>
                        <p class="text-xs text-ink-soft">Requested by {{ $t->requester?->name }} {{ $t->created_at->diffForHumans() }}@if($t->reason) · {{ $t->reason }}@endif</p>
                    </div>
                    @if ($t->can_decide)
                        <form method="POST" action="{{ route('transfers.decide', $t) }}" class="flex shrink-0 gap-2">
                            @csrf
                            <button name="decision" value="reject" class="btn-ghost btn-sm">Decline</button>
                            <button name="decision" value="approve" class="btn-primary btn-sm"><x-icon name="check" class="size-4" />Accept</button>
                        </form>
                    @else
                        <span class="badge-neutral shrink-0">{{ $t->crossesDistricts() ? 'District Coordinator decides' : 'Receiving Assembly decides' }}</span>
                    @endif
                </div>
            </div>
        @empty
            <x-empty title="Nothing waiting" class="mt-3">No transfers into your area need a decision.</x-empty>
        @endforelse

        @if ($outgoing->isNotEmpty())
            <h2 class="h-section mt-10">Requested from your area</h2>
            <div class="card mt-3 overflow-x-auto">
                <table class="table">
                    <thead><tr><th>Member</th><th>To</th><th>Status</th><th>Date</th></tr></thead>
                    <tbody>
                    @foreach ($outgoing as $t)
                        <tr>
                            <td><a href="{{ route('members.show', $t->member) }}" class="font-semibold text-ink">{{ $t->member->full_name }}</a></td>
                            <td>{{ $t->toUnit->name }}, {{ $t->toUnit->parent?->name }}</td>
                            <td><x-status-badge :status="$t->status" :label="ucfirst($t->status)" /></td>
                            <td class="text-ink-soft">{{ $t->created_at->format('j M Y') }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>
</x-layouts.app>
