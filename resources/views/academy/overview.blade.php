<x-layouts.app title="Training in my area">
    <section class="container-page mt-8">
        <p class="eyebrow">GODRAM Virtual Academy</p>
        <h1 class="h-page mt-1">Training in {{ $units->map->fullName()->join(', ') }}</h1>
        <p class="mt-1 text-sm text-ink-soft">How members in your area are getting on with the training open to them.</p>

        <div class="mt-6 grid grid-cols-2 gap-3 lg:grid-cols-4">
            <x-stat label="Programmes" :value="$totals['courses']" />
            <x-stat label="Members enrolled" :value="$totals['enrolled']" />
            <x-stat label="Finished all lessons" :value="$totals['completed']" />
            <x-stat label="Assignments not handed in" :value="$totals['outstanding']" />
        </div>

        @if ($upcoming->isNotEmpty())
            <h2 class="h-section mt-10">Upcoming live classes</h2>
            <ul class="mt-3 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($upcoming as $s)
                    <li class="card p-4"><p class="text-xs font-semibold uppercase tracking-wide text-poster">{{ $s->live_at->format('D j M, g:ia') }}</p><p class="mt-0.5 font-semibold">{{ $s->title }}</p><p class="text-sm text-ink-soft">{{ $s->course->title }}</p></li>
                @endforeach
            </ul>
        @endif

        <h2 class="h-section mt-10">Programmes</h2>
        @if ($rows->isEmpty())
            <x-empty title="No training yet" class="mt-3">When the National office, your Region or District opens training for your members, it will appear here.</x-empty>
        @else
            <div class="card mt-3 overflow-x-auto">
                <table class="table min-w-[720px]">
                    <thead><tr><th>Programme</th><th class="text-right">Enrolled</th><th class="text-right">Started</th><th class="text-right">Finished lessons</th><th class="text-right">Live attendance</th><th class="text-right">Assignments outstanding</th></tr></thead>
                    <tbody>
                        @foreach ($rows as $row)
                            <tr x-data="{ open: false }">
                                <td><a href="{{ route('academy.show', $row->course) }}" class="font-semibold">{{ $row->course->title }}</a><span class="block text-xs text-ink-soft">{{ $row->course->levelLabel() }} · {{ $row->lessons }} lessons</span>
                                    @if ($row->enrolled)<button type="button" class="mt-1 text-xs font-semibold text-curtain underline" @click="open = !open" x-text="open ? 'Hide members' : 'Show members'"></button>
                                        <ul x-show="open" x-cloak class="mt-2 space-y-1 text-xs">
                                            @foreach ($row->enrolments as $e)
                                                <li class="flex justify-between gap-3"><span>{{ $e->member->full_name }} <span class="text-ink-soft">· {{ $e->member->currentPlacement?->orgUnit?->name }}</span></span><span class="font-semibold">{{ $e->progress->count() }} / {{ $row->lessons }}</span></li>
                                            @endforeach
                                        </ul>
                                    @endif
                                </td>
                                <td class="text-right">{{ $row->enrolled }}</td>
                                <td class="text-right">{{ $row->started }}</td>
                                <td class="text-right">{{ $row->completed }}@if ($row->enrolled)<span class="block text-xs text-ink-soft">{{ round($row->completed / $row->enrolled * 100) }}%</span>@endif</td>
                                <td class="text-right">@if ($row->attendance){{ $row->attendance[0] }} of {{ $row->attendance[1] }}<span class="block text-xs text-ink-soft">places at past classes</span>@else<span class="text-ink-soft">No classes yet</span>@endif</td>
                                <td class="text-right">{{ $row->outstanding }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <p class="mt-3 text-xs text-ink-soft">Live attendance counts members who opened the class room during the class or were confirmed by a facilitator. Examination results and pass rates are under Examinations.</p>
        @endif
    </section>
</x-layouts.app>
