<a href="{{ route('academy.manage.index') }}" class="text-sm font-semibold text-curtain">Manage training</a>
<div class="mt-2 flex flex-wrap items-start justify-between gap-4">
    <div>
        <h1 class="h-page">{{ $course->title }}</h1>
        <p class="mt-1 flex flex-wrap items-center gap-2 text-sm text-ink-soft">
            <span @class(['badge', 'badge-ok' => $course->status === 'published', 'badge-neutral' => $course->status !== 'published'])>{{ \App\Models\Course::STATUSES[$course->status] }}</span>
            {{ $course->levelLabel() }} · {{ $course->orgUnit?->fullName() }}
        </p>
    </div>
    @if ($course->canBeManagedBy(auth()->user()))
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('academy.manage.edit', $course) }}" class="btn-ghost btn-sm no-underline"><x-icon name="edit" class="size-4" /> Details</a>
            @if ($course->status !== 'published')
                <form method="POST" action="{{ route('academy.manage.status', $course) }}">@csrf<input type="hidden" name="status" value="published"><button class="btn-primary btn-sm">Open to members</button></form>
            @else
                <form method="POST" action="{{ route('academy.manage.status', $course) }}" onsubmit="return confirm('Archive this training? Members keep their progress, and it stays in the archive.')">@csrf<input type="hidden" name="status" value="archived"><button class="btn-ghost btn-sm">Archive</button></form>
            @endif
        </div>
    @endif
</div>
@error('status')<p class="error">{{ $message }}</p>@enderror
@include('academy.manage.partials.tabs')
