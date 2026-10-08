<details @if($unit->depth < 1) open @endif class="group">
    <summary class="flex cursor-pointer list-none items-center gap-2 rounded-lg px-2 py-2 hover:bg-paper">
        <x-icon name="chevron-right" class="size-4 text-ink-soft transition-transform group-open:rotate-90" />
        <span class="font-semibold {{ $unit->is_active ? '' : 'text-ink-soft line-through' }}">{{ $unit->name }}</span>
        <span class="badge-neutral">{{ $unit->typeLabel() }}</span>
        <a href="{{ route('network', $unit) }}" class="ml-auto text-xs text-ink-soft">View</a>
    </summary>
    <div class="ml-4 border-l border-line pl-4">
        <form method="POST" action="{{ route('admin.units.update', $unit) }}" class="my-2 flex flex-wrap items-center gap-2 text-sm">
            @csrf @method('PUT')
            <input name="name" value="{{ $unit->name }}" class="input min-h-9 w-56 py-1 text-sm" aria-label="Name">
            <input name="city" value="{{ $unit->city }}" placeholder="Town" class="input min-h-9 w-32 py-1 text-sm" aria-label="Town">
            @if ($unit->parent_id)<label class="flex items-center gap-1 text-xs"><input type="hidden" name="is_active" value="0"><input type="checkbox" name="is_active" value="1" @checked($unit->is_active) class="accent-curtain"> Active</label>@endif
            <button class="btn-ghost btn-sm">Save</button>
        </form>
        @foreach ($unit->children as $child)
            {!! $render($child) !!}
        @endforeach
        @if ($unit->childType())
            <form method="POST" action="{{ route('admin.units.store') }}" class="my-2 flex flex-wrap items-center gap-2">
                @csrf
                <input type="hidden" name="parent_id" value="{{ $unit->id }}">
                <input name="name" required placeholder="New {{ $unit->childType() }} name" class="input min-h-9 w-56 py-1 text-sm">
                <button class="btn-dark btn-sm"><x-icon name="plus" class="size-4" />Add {{ $unit->childType() }}</button>
            </form>
        @endif
    </div>
</details>
