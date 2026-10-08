<x-layouts.app title="Transfer {{ $member->full_name }}">
    <section class="container-page mt-8 max-w-xl">
        <a href="{{ route('members.show', $member) }}" class="text-sm font-semibold text-curtain">Back</a>
        <h1 class="h-page mt-2">Transfer a member</h1>
        <p class="mt-1 text-ink-soft">{{ $member->full_name }} keeps the same Member ID and all their history. Their new Assembly accepts the transfer; moves between Districts are approved by the receiving District Coordinator.</p>
        <form method="POST" action="{{ route('transfers.store', $member) }}" class="card-pad mt-6 space-y-5">
            @csrf
            <div>
                <p class="label">From</p>
                <p class="rounded-xl bg-paper-2 px-3.5 py-2.5">{{ $from->fullName() }}</p>
            </div>
            <div>
                <label for="to_unit_id" class="label">To</label>
                <select id="to_unit_id" name="to_unit_id" class="input" required>
                    <option value="">Choose the new Assembly</option>
                    @foreach ($districts as $d)
                        <optgroup label="{{ $d->name }} District ({{ $d->parent?->name }})">
                            @foreach ($d->children as $a)
                                @if ($a->id !== $from->id)<option value="{{ $a->id }}" @selected(old('to_unit_id') == $a->id)>{{ $a->name }}</option>@endif
                            @endforeach
                        </optgroup>
                    @endforeach
                </select>
                @error('to_unit_id')<p class="error">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="reason" class="label">Reason <span class="font-normal text-ink-soft">(optional)</span></label>
                <input id="reason" name="reason" class="input" maxlength="200" value="{{ old('reason') }}" placeholder="For example: relocated for work">
            </div>
            <div class="flex justify-end gap-2 border-t border-line pt-5">
                <a href="{{ route('members.show', $member) }}" class="btn-ghost no-underline">Cancel</a>
                <button class="btn-primary">Request transfer</button>
            </div>
        </form>
    </section>
</x-layouts.app>
