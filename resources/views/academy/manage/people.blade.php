<x-layouts.app :title="'Participants: '.$course->title">
    <section class="container-page mt-8">
        @include('academy.manage.partials.header')
        <div class="mt-6 flex flex-wrap items-center justify-between gap-3">
            <p class="text-sm text-ink-soft">{{ $enrolments->count() }} enrolled · {{ $enrolments->where('status', 'completed')->count() }} finished every lesson</p>
            <a href="{{ route('academy.manage.people', [$course, 'format' => 'csv']) }}" class="btn-ghost btn-sm no-underline"><x-icon name="download" class="size-4" /> Download as a spreadsheet</a>
        </div>
        @if ($enrolments->isEmpty())
            <x-empty title="No one has enrolled yet" class="mt-4">Once the training is open, members it is aimed at can enrol from the Academy.</x-empty>
        @else
            <div class="card mt-4 overflow-x-auto" x-data="filterList()">
                <div class="border-b border-line p-3"><input x-model="term" type="search" class="input max-w-sm" placeholder="Find a member"></div>
                <table class="table min-w-[640px]">
                    <thead><tr><th>Member</th><th>Progress</th><th class="text-right">Assignments</th>@foreach ($liveSessions as $s)<th class="text-center">{{ \Illuminate\Support\Str::limit($s->title, 18) }}<span class="block text-[10px] font-normal">{{ $s->live_at->format('j M') }}</span></th>@endforeach</tr></thead>
                    <tbody>
                        @foreach ($enrolments as $e)
                            <tr x-show="matches(@js($e->member->full_name.' '.$e->member->member_no))">
                                <td><span class="font-semibold">{{ $e->member->full_name }}</span><span class="block text-xs text-ink-soft">{{ $e->member->member_no }} · {{ $e->member->currentPlacement?->orgUnit?->name }}</span></td>
                                <td><div class="flex items-center gap-2"><div class="h-1.5 w-24 overflow-hidden rounded-full bg-paper-2"><div class="h-full rounded-full {{ $e->status === 'completed' ? 'bg-ok' : 'bg-curtain' }}" style="width: {{ $total ? round($e->progress->count() / $total * 100) : 0 }}%"></div></div><span class="text-xs">{{ $e->progress->count() }} / {{ $total }}</span></div></td>
                                <td class="text-right">{{ ($submissions[$e->member_id] ?? collect())->count() }} / {{ $course->assignments->count() }}</td>
                                @foreach ($liveSessions as $s)
                                    @php $a = ($attendance[$e->member_id] ?? collect())->firstWhere('course_session_id', $s->id); @endphp
                                    <td class="text-center text-xs">@if ($a?->status === 'attended')<span class="badge badge-ok">Attended</span>@elseif ($a?->status === 'joined')<span class="badge badge-info">Joined</span>@elseif ($a?->status === 'absent')<span class="badge badge-neutral">Absent</span>@else<span class="text-ink-soft">·</span>@endif</td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @foreach ($liveSessions->filter(fn ($s) => $s->live_at->isPast()) as $s)
                <details class="card-pad mt-4">
                    <summary class="cursor-pointer font-semibold">Confirm attendance: {{ $s->title }} ({{ $s->live_at->format('j M') }})</summary>
                    <p class="mt-2 text-sm text-ink-soft">"Joined" means the member opened the class room during the class. Confirm who actually took part, especially for classes held on Zoom or Google Meet.</p>
                    <form method="POST" action="{{ route('academy.manage.sessions.attendance', [$course, $s]) }}" class="mt-3">@csrf
                        <ul class="divide-y divide-line">
                            @foreach ($enrolments as $e)
                                @php $a = ($attendance[$e->member_id] ?? collect())->firstWhere('course_session_id', $s->id); @endphp
                                <li class="flex flex-wrap items-center gap-3 py-2 text-sm">
                                    <span class="min-w-0 flex-1">{{ $e->member->full_name }}@if ($a?->status === 'joined') <span class="text-xs text-ink-soft">· joined {{ $a->joined_at?->format('g:ia') }}</span>@endif</span>
                                    <label class="flex items-center gap-1"><input type="radio" name="status[{{ $e->member_id }}]" value="attended" @checked($a?->status === 'attended' || $a?->status === 'joined') class="accent-curtain"> Attended</label>
                                    <label class="flex items-center gap-1"><input type="radio" name="status[{{ $e->member_id }}]" value="absent" @checked($a?->status === 'absent') class="accent-curtain"> Absent</label>
                                </li>
                            @endforeach
                        </ul>
                        <button class="btn-dark btn-sm mt-3">Save attendance</button>
                    </form>
                </details>
            @endforeach
        @endif
    </section>
</x-layouts.app>
