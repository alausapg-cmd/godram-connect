@php $editing = $member->exists; $duplicates = collect(session('duplicates', [])); @endphp
<x-layouts.app :title="$editing ? 'Edit '.$member->full_name : 'Add member'" :hide-errors="true">
    <section class="container-page mt-8 max-w-2xl">
        <a href="{{ $editing ? route('members.show', $member) : route('members.index') }}" class="text-sm font-semibold text-curtain">Back</a>
        <h1 class="h-page mt-2">{{ $editing ? 'Edit member' : 'Add a member' }}</h1>
        @unless ($editing)<p class="mt-1 text-ink-soft">They will get a GODRAM Member ID and appear in District, Region and National views straight away.</p>@endunless

        @if ($duplicates->isNotEmpty())
            <div class="mt-6 rounded-2xl border border-gold bg-gold/15 p-5" role="alert">
                <p class="font-semibold">Is this person already registered?</p>
                <p class="mt-1 text-sm text-ink-soft">We found members with a similar name in the same District. Please check before adding someone twice.</p>
                <ul class="mt-3 space-y-2">
                    @foreach ($duplicates as $d)
                        <li class="flex items-center justify-between gap-3 rounded-xl bg-white px-3 py-2 text-sm">
                            <span><span class="font-semibold">{{ $d->full_name }}</span> <span class="text-ink-soft">· {{ $d->member_no }} · {{ $d->currentPlacement?->orgUnit?->name }}</span></span>
                            <a href="{{ route('members.show', $d) }}" class="link shrink-0" target="_blank">View</a>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ $editing ? route('members.update', $member) : route('members.store') }}" class="card-pad mt-6 space-y-5">
            @csrf
            @if ($editing) @method('PUT') @endif
            @if ($duplicates->isNotEmpty())
                <label class="flex items-start gap-2 rounded-xl bg-paper-2 p-3 text-sm font-medium"><input type="checkbox" name="confirm_not_duplicate" value="1" class="mt-0.5 size-4 accent-curtain" required> I have checked: this is a different person.</label>
            @endif

            <div class="grid gap-4 sm:grid-cols-3">
                <div>
                    <label for="first_name" class="label">First name</label>
                    <input id="first_name" name="first_name" value="{{ old('first_name', $member->first_name) }}" class="input" required>
                    @error('first_name')<p class="error">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="other_names" class="label">Other names</label>
                    <input id="other_names" name="other_names" value="{{ old('other_names', $member->other_names) }}" class="input">
                </div>
                <div>
                    <label for="last_name" class="label">Surname</label>
                    <input id="last_name" name="last_name" value="{{ old('last_name', $member->last_name) }}" class="input" required>
                    @error('last_name')<p class="error">{{ $message }}</p>@enderror
                </div>
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label for="phone" class="label">Phone number</label>
                    <input id="phone" name="phone" type="tel" value="{{ old('phone', \App\Support\Phone::display($member->phone)) }}" class="input" placeholder="0803 123 4567">
                    @error('phone')<p class="error">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="email" class="label">Email <span class="font-normal text-ink-soft">(optional)</span></label>
                    <input id="email" name="email" type="email" value="{{ old('email', $member->email) }}" class="input">
                    @error('email')<p class="error">{{ $message }}</p>@enderror
                </div>
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label for="gender" class="label">Gender</label>
                    <select id="gender" name="gender" class="input">
                        <option value="">Prefer not to say</option>
                        @foreach (\App\Models\Member::GENDERS as $k => $label)<option value="{{ $k }}" @selected(old('gender', $member->gender) === $k)>{{ $label }}</option>@endforeach
                    </select>
                </div>
                @unless ($editing)
                    <div>
                        <label for="joined_on" class="label">Joined GODRAM</label>
                        <input id="joined_on" name="joined_on" type="date" value="{{ old('joined_on', $member->joined_on?->toDateString()) }}" max="{{ now()->toDateString() }}" class="input">
                    </div>
                @endunless
            </div>

            @unless ($editing)
                <div>
                    <label for="org_unit_id" class="label">Assembly</label>
                    <select id="org_unit_id" name="org_unit_id" class="input" required>
                        @if ($assemblies->count() > 1)<option value="">Choose an Assembly</option>@endif
                        @foreach ($assemblies as $a)<option value="{{ $a->id }}" @selected(old('org_unit_id') == $a->id)>{{ $a->name }}</option>@endforeach
                    </select>
                    @error('org_unit_id')<p class="error">{{ $message }}</p>@enderror
                </div>
            @endunless

            <fieldset>
                <legend class="label">Creative skills</legend>
                <div class="flex flex-wrap gap-2">
                    @php $chosen = old('skills', $member->exists ? $member->skills->pluck('id')->all() : []); @endphp
                    @foreach ($skills as $skill)
                        <label class="cursor-pointer">
                            <input type="checkbox" name="skills[]" value="{{ $skill->id }}" class="peer sr-only" @checked(in_array($skill->id, $chosen))>
                            <span class="inline-block rounded-full border border-line bg-white px-3 py-1.5 text-sm peer-checked:border-curtain peer-checked:bg-curtain peer-checked:text-white peer-focus-visible:ring-2 peer-focus-visible:ring-poster">{{ $skill->name }}</span>
                        </label>
                    @endforeach
                </div>
            </fieldset>

            <div>
                <label for="bio" class="label">Short note <span class="font-normal text-ink-soft">(optional)</span></label>
                <textarea id="bio" name="bio" rows="3" class="input">{{ old('bio', $member->bio) }}</textarea>
            </div>

            <div class="flex justify-end gap-2 border-t border-line pt-5">
                <a href="{{ $editing ? route('members.show', $member) : route('members.index') }}" class="btn-ghost no-underline">Cancel</a>
                <button class="btn-primary">{{ $editing ? 'Save changes' : 'Register member' }}</button>
            </div>
        </form>
    </section>
</x-layouts.app>
