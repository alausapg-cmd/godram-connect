<x-layouts.app title="Edit my profile">
    <section class="container-page mt-8 max-w-2xl">
        <a href="{{ route('members.show', $member) }}" class="text-sm font-semibold text-curtain">Back</a>
        <h1 class="h-page mt-2">My skills and note</h1>
        <p class="mt-1 text-ink-soft">To change your name, phone number or Assembly, please ask your Assembly Coordinator.</p>
        <form method="POST" action="{{ route('profile.update') }}" class="card-pad mt-6 space-y-5">
            @csrf @method('PUT')
            <fieldset>
                <legend class="label">Creative skills</legend>
                <div class="flex flex-wrap gap-2">
                    @php $chosen = old('skills', $member->skills->pluck('id')->all()); @endphp
                    @foreach ($skills as $skill)
                        <label class="cursor-pointer">
                            <input type="checkbox" name="skills[]" value="{{ $skill->id }}" class="peer sr-only" @checked(in_array($skill->id, $chosen))>
                            <span class="inline-block rounded-full border border-line bg-white px-3 py-1.5 text-sm peer-checked:border-curtain peer-checked:bg-curtain peer-checked:text-white peer-focus-visible:ring-2 peer-focus-visible:ring-poster">{{ $skill->name }}</span>
                        </label>
                    @endforeach
                </div>
            </fieldset>
            <div>
                <label for="bio" class="label">About me</label>
                <textarea id="bio" name="bio" rows="4" class="input" maxlength="1000">{{ old('bio', $member->bio) }}</textarea>
            </div>
            <div class="flex justify-end"><button class="btn-primary">Save</button></div>
        </form>
    </section>
</x-layouts.app>
