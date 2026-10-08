<ul class="mt-3 divide-y divide-line overflow-hidden rounded-[var(--radius-card)] border border-line bg-white">
    @foreach ($resources as $resource)
        <li class="flex items-center gap-3 px-4 py-3">
            <span class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-paper-2 text-[10px] font-bold uppercase text-poster">{{ \Illuminate\Support\Str::limit($resource->typeLabel(), 4, '') }}</span>
            <span class="min-w-0 flex-1">
                <span class="block truncate font-medium">{{ $resource->title }}</span>
                <span class="text-xs text-ink-soft">{{ $resource->typeLabel() }}@if ($resource->sizeLabel()) · {{ $resource->sizeLabel() }}@endif</span>
            </span>
            @if ($locked ?? false)
                <x-icon name="lock" class="size-4 text-ink-soft" />
            @else
                <a href="{{ $resource->href() }}" @if ($resource->isLink()) target="_blank" rel="noopener" @endif class="btn-ghost btn-sm no-underline"><x-icon :name="$resource->isLink() ? 'external' : 'download'" class="size-4" /><span class="hidden sm:inline">{{ $resource->isLink() ? 'Open' : 'Download' }}</span></a>
            @endif
            @isset($manage)
                <form method="POST" action="{{ route('academy.manage.resources.destroy', [$manage, $resource]) }}" onsubmit="return confirm('Remove this resource?')">@csrf @method('DELETE')<button class="p-2 text-ink-soft hover:text-curtain" aria-label="Remove {{ $resource->title }}"><x-icon name="x" class="size-4" /></button></form>
            @endisset
        </li>
    @endforeach
</ul>
