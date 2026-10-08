<div class="mt-4 flex gap-1 overflow-x-auto border-b border-line [scrollbar-width:none]">
    @foreach ([
        ['Outline', route('academy.manage.build', $course), 'academy.manage.build'],
        ['Participants', route('academy.manage.people', $course), 'academy.manage.people'],
        ['Assignments', route('academy.manage.submissions', $course), 'academy.manage.submissions'],
        ['Questions', route('academy.manage.questions', $course), 'academy.manage.questions'],
    ] as [$label, $url, $name])
        <a href="{{ $url }}" @class(['shrink-0 border-b-2 px-3 py-2 text-sm font-semibold no-underline', 'border-curtain text-stage' => request()->routeIs($name), 'border-transparent text-ink-soft hover:text-ink' => ! request()->routeIs($name)])>{{ $label }}</a>
    @endforeach
    <a href="{{ route('academy.show', $course) }}" class="ml-auto shrink-0 px-3 py-2 text-sm font-semibold text-ink-soft no-underline hover:text-ink">See it as a member <x-icon name="external" class="inline size-3.5" /></a>
</div>
