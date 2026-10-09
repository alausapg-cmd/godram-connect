<x-layouts.app title="Analytics · {{ $scope->name }}">
    @php
        $pct = fn ($v) => $v === null ? '–' : $v.'%';
        $m = $membership; $r = $reporting; $t = $training; $c = $cbt; $e = $events;
        $childType = str($scope->childType() ?? 'unit')->plural();
        $quiet = collect($children)->filter(fn ($row) => ! $row['last_report'] || $row['last_report']->lt($from));

        // Plain-word findings: what a Coordinator should notice first.
        $notes = collect([
            $r['waiting'] ? [$r['waiting'].' '.str('report')->plural($r['waiting']).' waiting for review.', route('reports.index', ['tab' => 'review']), 'Review'] : null,
            $quiet->isNotEmpty() ? [$quiet->count().' of '.count($children).' '.$childType.' sent no approved report in this period: '.$quiet->take(5)->map(fn ($row) => $row['unit']->name)->join(', ').($quiet->count() > 5 ? ' and others' : '').'.', null, null] : null,
            $m['growth'] !== null ? ['Membership '.($m['new'] ? 'grew by '.number_format($m['new']).' ('.$m['growth'].'%)' : 'did not grow').' in this period.', null, null] : null,
            $t['enrolled'] && $t['completion_rate'] !== null && $t['completion_rate'] < 50 ? ['Only '.$t['completion_rate'].'% of training enrolments are completed. A reminder from Coordinators helps.', null, null] : null,
            $c['awaiting_marking'] ? [$c['awaiting_marking'].' examination '.str('script')->plural($c['awaiting_marking']).' need marking by hand.', route('exams.manage.index'), 'Open'] : null,
        ])->filter();
    @endphp

    <section class="container-page mt-8">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <p class="eyebrow">Analytics</p>
                <h1 class="h-page mt-1">{{ $scope->fullName() }}</h1>
                <p class="muted mt-1 text-sm">{{ $from->format('j F Y') }} to today. Counted from records in GODRAM CONNECT.</p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <nav class="flex rounded-full border border-line bg-white p-1" aria-label="Period">
                    @foreach ([3 => '3 months', 6 => '6 months', 12 => '12 months', 24 => '2 years'] as $n => $label)
                        <a href="{{ route('analytics', ['scope' => $scope->id, 'months' => $n]) }}" @class(['rounded-full px-3 py-1.5 text-xs font-semibold no-underline', 'bg-stage text-paper' => $months === $n, 'text-ink-soft hover:text-ink' => $months !== $n]) @if ($months === $n) aria-current="true" @endif>{{ $label }}</a>
                    @endforeach
                </nav>
                <a href="{{ route('analytics.export', ['scope' => $scope->id, 'months' => $months]) }}" class="btn-ghost btn-sm no-underline"><x-icon name="download" class="size-4" /> CSV</a>
            </div>
        </div>

        @if ($scopes->count() > 1 || $scopes->first()?->id !== $scope->id)
            <div class="mt-4 flex flex-wrap gap-2 text-sm">
                @foreach ($scopes as $s)
                    <a href="{{ route('analytics', ['scope' => $s->id, 'months' => $months]) }}" @class(['badge-neutral no-underline', 'ring-2 ring-poster' => $s->id === $scope->id])>{{ $s->fullName() }}</a>
                @endforeach
                @if ($scope->parent && ! $scopes->contains('id', $scope->id))
                    <a href="{{ route('analytics', ['scope' => $scope->parent_id, 'months' => $months]) }}" class="link text-sm">&larr; Back to {{ $scope->parent->name }}</a>
                @endif
            </div>
        @endif

        @if ($notes->isNotEmpty())
            <div class="card-pad mt-6 border-l-4 border-l-poster">
                <h2 class="h-section">What to notice</h2>
                <ul class="mt-2 space-y-2">
                    @foreach ($notes as [$text, $url, $action])
                        <li class="flex items-start justify-between gap-3 text-sm"><span>{{ $text }}</span>@if ($url)<a href="{{ $url }}" class="btn-ghost btn-sm shrink-0 no-underline">{{ $action }}</a>@endif</li>
                    @endforeach
                </ul>
            </div>
        @endif

        {{-- Membership --}}
        <h2 class="h-section mt-10">Membership</h2>
        <div class="mt-3 grid grid-cols-2 gap-3 lg:grid-cols-4">
            <x-stat label="Members" :value="$m['total']" :note="number_format($m['active']).' active'" />
            <x-stat label="New" :value="$m['new']" note="joined in this period" />
            <x-stat label="Growth" :value="$pct($m['growth'])" note="on the start of the period" />
            <x-stat label="Active" :value="$pct($m['total'] ? (int) round(100 * $m['active'] / $m['total']) : null)" note="of members are active" />
        </div>
        <div class="mt-3 grid grid-cols-1 gap-3 lg:grid-cols-3">
            <div class="card-pad lg:col-span-2"><h3 class="font-semibold text-ink">New members each month</h3><x-chart.columns class="mt-4" :series="$m['joined']" label="New members" /></div>
            <div class="card-pad"><h3 class="font-semibold text-ink">Creative skills</h3><x-chart.bars class="mt-4" :rows="$m['skills']" empty="No skills recorded yet." /></div>
        </div>

        {{-- Comparison --}}
        @if ($children)
            <div class="card mt-3 overflow-x-auto">
                <table class="table min-w-[640px]">
                    <thead class="bg-paper/60"><tr>
                        <th>{{ str($scope->childType() ?? 'Unit')->title() }}</th><th class="text-right">Members</th><th class="text-right">New</th><th class="text-right">Reports</th><th class="text-right">Reached</th><th class="text-right">In training</th><th>Last report</th>
                    </tr></thead>
                    <tbody>
                        @foreach ($children as $row)
                            <tr>
                                <td>@if (can_do('analytics.view', $row['unit']) && $row['unit']->type !== 'assembly')<a class="link" href="{{ route('analytics', ['scope' => $row['unit']->id, 'months' => $months]) }}">{{ $row['unit']->name }}</a>@else{{ $row['unit']->name }}@endif</td>
                                <td class="text-right tabular-nums">{{ number_format($row['members']) }}</td>
                                <td class="text-right tabular-nums">{{ number_format($row['new']) }}</td>
                                <td class="text-right tabular-nums">{{ number_format($row['reports']) }}</td>
                                <td class="text-right tabular-nums">{{ number_format($row['attendance']) }}</td>
                                <td class="text-right tabular-nums">{{ number_format($row['enrolled']) }}</td>
                                <td>@if ($row['last_report'])<span @class(['font-medium text-curtain' => $row['last_report']->lt($from)])>{{ $row['last_report']->format('j M Y') }}</span>@else<span class="text-curtain">None yet</span>@endif</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif

        {{-- Reporting --}}
        <h2 class="h-section mt-10">Activities and reporting</h2>
        <div class="mt-3 grid grid-cols-2 gap-3 lg:grid-cols-4">
            <x-stat label="Reports approved" :value="$r['approved']" :note="number_format($r['submitted']).' submitted · '.number_format($r['waiting']).' waiting'" />
            <x-stat label="Approval rate" :value="$pct($r['approval_rate'])" note="of reports reviewed" />
            <x-stat label="Performances" :value="$r['performances']" :note="number_format($r['performers']).' performers took part'" />
            <x-stat label="People reached" :value="$r['attendance']" :note="number_format($r['souls']).' responded at outreaches'" />
        </div>
        <div class="mt-3 grid grid-cols-1 gap-3 lg:grid-cols-3">
            <div class="card-pad lg:col-span-2"><h3 class="font-semibold text-ink">Approved activities each month</h3><x-chart.columns class="mt-4" :series="$r['trend']" label="Approved activities" /></div>
            <div class="card-pad"><h3 class="font-semibold text-ink">Kinds of activity</h3><x-chart.bars class="mt-4" :rows="array_slice($r['types'], 0, 8)" empty="No approved activities in this period." /></div>
        </div>

        {{-- Training and examinations --}}
        <h2 class="h-section mt-10">Training</h2>
        <div class="mt-3 grid grid-cols-2 gap-3 lg:grid-cols-4">
            <x-stat label="Enrolments" :value="$t['enrolled']" :note="$t['courses'].' '.str('programme')->plural($t['courses']).' published here'" />
            <x-stat label="Completed" :value="$pct($t['completion_rate'])" :note="number_format($t['completed']).' finished every lesson'" />
            <x-stat label="Live classes" :value="$t['joined_live']" :note="number_format($t['attended']).' confirmed by a facilitator'" />
            <x-stat label="Assignments in" :value="$pct($t['assignment_rate'])" note="of assignments set" />
        </div>
        <div class="mt-3 grid grid-cols-1 gap-3 lg:grid-cols-3">
            <div class="card-pad lg:col-span-2"><h3 class="font-semibold text-ink">New enrolments each month</h3><x-chart.columns class="mt-4" :series="$t['trend']" label="Enrolments" /></div>
            <div class="card-pad">
                <h3 class="font-semibold text-ink">Examinations</h3>
                <dl class="mt-3 grid grid-cols-2 gap-3 text-sm">
                    <div><dt class="text-ink-soft">Candidates</dt><dd class="font-display text-2xl font-semibold text-stage">{{ number_format($c['candidates']) }}</dd></div>
                    <div><dt class="text-ink-soft">Attempts</dt><dd class="font-display text-2xl font-semibold text-stage">{{ number_format($c['attempts']) }}</dd></div>
                    <div><dt class="text-ink-soft">Pass rate</dt><dd class="font-display text-2xl font-semibold text-stage">{{ $pct($c['pass_rate']) }}</dd></div>
                    <div><dt class="text-ink-soft">Average score</dt><dd class="font-display text-2xl font-semibold text-stage">{{ $pct($c['average']) }}</dd></div>
                </dl>
                <x-chart.bars class="mt-4" :rows="$c['exams']" empty="No examinations sat in this period." />
            </div>
        </div>

        {{-- Events, recognition, media --}}
        <h2 class="h-section mt-10">Events, recognition and media</h2>
        <div class="mt-3 grid grid-cols-2 gap-3 lg:grid-cols-4">
            <x-stat label="Events held" :value="$e['held']" :note="number_format($e['upcoming']).' coming up'" />
            <x-stat label="Registrations" :value="$e['registrations']" :note="$e['attendance_rate'] === null ? 'attendance not recorded yet' : $e['attendance_rate'].'% attendance recorded'" />
            <x-stat label="Certificates" :value="$recognition['certificates']" :note="number_format($recognition['achievements']).' achievements awarded'" />
            <x-stat label="GODRAM TV" :value="$media['videos']" :note="'videos added · '.$media['stories'].' stories · '.$media['streams'].' livestreams'" />
        </div>
        <p class="mt-3 text-xs text-ink-soft">Video views and watch time are counted by YouTube and are not copied into GODRAM CONNECT; see YouTube Studio for those. Videos and stories are counted nationally.</p>
    </section>
</x-layouts.app>
