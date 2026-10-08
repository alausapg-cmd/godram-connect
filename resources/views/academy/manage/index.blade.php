<x-layouts.app title="Manage training">
    <section class="container-page mt-8">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div><p class="eyebrow">GODRAM Virtual Academy</p><h1 class="h-page mt-1">Manage training</h1></div>
            <div class="flex flex-wrap gap-2">
                @if (can_do('training.view'))<a href="{{ route('academy.overview') }}" class="btn-ghost no-underline">Training in my area</a>@endif
                @if ($canCreate)<a href="{{ route('academy.manage.create') }}" class="btn-primary no-underline"><x-icon name="plus" class="size-4" /> New training</a>@endif
            </div>
        </div>
        @if ($toReview || $toAnswer)
            <div class="mt-6 flex flex-wrap gap-3 text-sm">
                @if ($toReview)<span class="badge badge-warn">{{ $toReview }} {{ \Illuminate\Support\Str::plural('assignment', $toReview) }} to review</span>@endif
                @if ($toAnswer)<span class="badge badge-info">{{ $toAnswer }} {{ \Illuminate\Support\Str::plural('question', $toAnswer) }} to answer</span>@endif
            </div>
        @endif

        @if ($courses->isEmpty())
            <x-empty title="No training yet" class="mt-6">Create a programme for your area. You can add sessions, lessons, live classes and assignments, then open it to members when it is ready.</x-empty>
        @else
            <div class="card mt-6 overflow-x-auto">
                <table class="table min-w-[640px]">
                    <thead><tr><th>Training</th><th>Run by</th><th>Status</th><th class="text-right">Lessons</th><th class="text-right">Enrolled</th><th></th></tr></thead>
                    <tbody>
                        @foreach ($courses as $c)
                            <tr>
                                <td><a href="{{ route('academy.manage.build', $c) }}" class="font-semibold">{{ $c->title }}</a><span class="block text-xs text-ink-soft">{{ $c->kindLabel() }}</span></td>
                                <td class="text-sm">{{ $c->orgUnit?->fullName() }}</td>
                                <td><span @class(['badge', 'badge-ok' => $c->status === 'published', 'badge-neutral' => $c->status !== 'published'])>{{ \App\Models\Course::STATUSES[$c->status] }}</span></td>
                                <td class="text-right">{{ $c->lessons_count }}</td>
                                <td class="text-right">{{ $c->enrolments_count }}</td>
                                <td class="text-right"><a href="{{ route('academy.manage.build', $c) }}" class="btn-ghost btn-sm no-underline">Open</a></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>
</x-layouts.app>
