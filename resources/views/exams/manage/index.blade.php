<x-layouts.app title="Examinations">
    <section class="container-page mt-8">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div><p class="eyebrow">CBT examination portal</p><h1 class="h-page mt-1">Examinations</h1></div>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('questions.index') }}" class="btn-ghost no-underline"><x-icon name="list" class="size-4" /> Question bank</a>
                <a href="{{ route('exams.manage.create') }}" class="btn-primary no-underline"><x-icon name="plus" class="size-4" /> New examination</a>
            </div>
        </div>
        <div class="mt-6 grid gap-3 sm:grid-cols-3">
            <x-stat label="Approved questions" :value="$bank['approved']" note="Ready to be drawn into papers" />
            <x-stat label="Waiting for review" :value="$bank['waiting']" />
            <x-stat label="Written answers to mark" :value="$exams->sum('marking_count')" />
        </div>

        @if ($exams->isEmpty())
            <x-empty title="No examinations yet" class="mt-6">Build an examination from the question bank. Choose how many questions come from each category and level, set the time and pass mark, then publish it.</x-empty>
        @else
            <div class="card mt-6 overflow-x-auto">
                <table class="table min-w-[720px]">
                    <thead><tr><th>Examination</th><th>For</th><th>Window</th><th>Status</th><th class="text-right">Sat</th><th class="text-right">To mark</th><th></th></tr></thead>
                    <tbody>
                        @foreach ($exams as $e)
                            <tr>
                                <td><a href="{{ route('exams.manage.show', $e) }}" class="font-semibold">{{ $e->title }}</a><span class="block text-xs text-ink-soft">{{ $e->modeLabel() }} · {{ $e->question_count }} questions · {{ $e->duration_minutes }} min</span></td>
                                <td class="text-sm">{{ $e->scopeLabel() }}</td>
                                <td class="text-sm">{{ $e->windowLabel() }}</td>
                                <td>
                                    <span @class(['badge', 'badge-ok' => $e->status === 'published', 'badge-warn' => $e->status === 'draft', 'badge-neutral' => $e->status === 'archived'])>{{ \App\Models\Exam::STATUSES[$e->status] }}</span>
                                    @if ($e->status === 'published' && ! $e->resultsReleased())<span class="badge badge-info">Results held</span>@endif
                                </td>
                                <td class="text-right">{{ $e->finished_count }}</td>
                                <td class="text-right">{{ $e->marking_count ?: '' }}</td>
                                <td class="whitespace-nowrap text-right"><a href="{{ route('exams.manage.show', $e) }}" class="btn-ghost btn-sm no-underline">Results</a> <a href="{{ route('exams.manage.edit', $e) }}" class="btn-ghost btn-sm no-underline">Settings</a></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>
</x-layouts.app>
