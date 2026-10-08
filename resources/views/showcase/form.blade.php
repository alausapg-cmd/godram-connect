@php $editing = $production->exists; @endphp
<x-layouts.app :title="$editing ? 'Edit '.$production->title : 'Add to the showcase'">
    <section class="container-page mt-8 max-w-3xl">
        <a href="{{ $editing ? route('showcase.show', $production) : route('showcase') }}" class="text-sm font-semibold text-curtain">Creative Showcase</a>
        <h1 class="h-page mt-2">{{ $editing ? 'Edit' : 'Add to the showcase' }}</h1>

        <form method="POST" action="{{ $editing ? route('showcase.update', $production) : route('showcase.store') }}" enctype="multipart/form-data" class="card-pad mt-6 space-y-5">
            @csrf @if ($editing) @method('PUT') @endif
            <div><label for="title" class="label">Title</label><input id="title" name="title" value="{{ old('title', $production->title) }}" class="input" required maxlength="150">@error('title')<p class="error">{{ $message }}</p>@enderror</div>
            <div class="grid gap-4 sm:grid-cols-3">
                <div><label for="kind" class="label">Kind</label><select id="kind" name="kind" class="input">@foreach (\App\Models\Production::KINDS as $k => $l)<option value="{{ $k }}" @selected(old('kind', $production->kind) === $k)>{{ $l }}</option>@endforeach</select></div>
                <div><label for="year" class="label">Year</label><input id="year" type="number" name="year" value="{{ old('year', $production->year) }}" class="input" min="1960" max="{{ now()->year + 1 }}">@error('year')<p class="error">{{ $message }}</p>@enderror</div>
                <div><label for="org_unit_id" class="label">Produced by</label><select id="org_unit_id" name="org_unit_id" class="input"><option value="">GODRAM</option>@foreach ($units as $u)<option value="{{ $u->id }}" @selected((int) old('org_unit_id', $production->org_unit_id) === $u->id)>{{ $u->fullName() }}</option>@endforeach</select></div>
            </div>
            <div><label for="summary" class="label">One-line summary</label><input id="summary" name="summary" value="{{ old('summary', $production->summary) }}" class="input" maxlength="300"></div>
            <div><label for="body" class="label">The full story</label><textarea id="body" name="body" rows="8" class="input">{{ old('body', $production->body) }}</textarea><p class="hint">Leave a blank line between paragraphs. **Bold** and *italic* work.</p></div>
            <div><label for="credits" class="label">Credits</label><textarea id="credits" name="credits" rows="4" class="input" placeholder="Director: ...&#10;Writer: ...">{{ old('credits', $production->credits) }}</textarea></div>
            <div class="grid gap-4 sm:grid-cols-2">
                <div><label for="cover" class="label">Cover picture or poster</label><input id="cover" type="file" name="cover" accept="image/*" class="block w-full text-sm">@error('cover')<p class="error">{{ $message }}</p>@enderror</div>
                <div><label for="gallery" class="label">Add photos to the gallery</label><input id="gallery" type="file" name="gallery[]" accept="image/*" multiple class="block w-full text-sm">@error('gallery.*')<p class="error">{{ $message }}</p>@enderror</div>
            </div>
            @if ($editing && $production->images->isNotEmpty())
                <div><p class="label">Gallery (tick to remove)</p><div class="grid grid-cols-4 gap-2 sm:grid-cols-6">@foreach ($production->images as $image)<label class="relative block"><img src="{{ $image->url() }}" alt="" class="aspect-square w-full rounded-lg object-cover"><input type="checkbox" name="remove_images[]" value="{{ $image->id }}" class="absolute right-1 top-1 size-4 accent-curtain"></label>@endforeach</div></div>
            @endif
            <div class="flex flex-wrap items-center gap-4 text-sm">
                <label class="flex items-center gap-2"><input type="radio" name="status" value="published" class="size-4 accent-curtain" @checked(old('status', $production->status ?? 'published') === 'published')> Published</label>
                <label class="flex items-center gap-2"><input type="radio" name="status" value="draft" class="size-4 accent-curtain" @checked(old('status', $production->status) === 'draft')> Draft</label>
                @if ($editing)<label class="flex items-center gap-2"><input type="radio" name="status" value="archived" class="size-4 accent-curtain" @checked(old('status', $production->status) === 'archived')> Archived</label>@endif
                <label class="ml-auto flex items-center gap-2"><input type="checkbox" name="is_featured" value="1" class="size-4 accent-curtain" @checked(old('is_featured', $production->is_featured))> Feature at the top</label>
            </div>
            <div class="flex justify-end gap-2"><a href="{{ $editing ? route('showcase.show', $production) : route('showcase') }}" class="btn-ghost no-underline">Cancel</a><button class="btn-primary">Save</button></div>
        </form>
    </section>
</x-layouts.app>
