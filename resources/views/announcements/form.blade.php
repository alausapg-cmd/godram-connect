@php
    $editing = $announcement->exists;
    $targetUnits = old('audience_units', $editing ? $announcement->targets->where('audience', 'org_unit')->pluck('org_unit_id')->all() : []);
    $targetRoles = old('audience_roles', $editing ? $announcement->targets->where('audience', 'role')->pluck('role_key')->all() : []);
    $isPublic = old('audience_public', $editing && $announcement->targets->contains('audience', 'public'));
@endphp
<x-layouts.app :title="$editing ? 'Edit announcement' : 'New announcement'">
    <section class="container-page mt-8 max-w-2xl">
        <a href="{{ route('announcements.manage') }}" class="text-sm font-semibold text-curtain">Announcements</a>
        <h1 class="h-page mt-2">{{ $editing ? 'Edit announcement' : 'New announcement' }}</h1>
        @unless ($canPublish)<p class="mt-1 text-sm text-ink-soft">Announcements for your own area go out straight away. Public, National and role-wide announcements are checked by the national team first.</p>@endunless

        <form method="POST" action="{{ $editing ? route('announcements.update', $announcement) : route('announcements.store') }}" enctype="multipart/form-data" class="card-pad mt-6 space-y-5">
            @csrf @if ($editing) @method('PUT') @endif
            <div>
                <label for="title" class="label">Headline</label>
                <input id="title" name="title" value="{{ old('title', $announcement->title) }}" class="input" maxlength="150" required>
            </div>
            <div>
                <label for="body" class="label">Message</label>
                <textarea id="body" name="body" rows="6" class="input" required>{{ old('body', $announcement->body) }}</textarea>
            </div>
            <fieldset class="rounded-2xl border border-line p-4">
                <legend class="px-1 text-sm font-semibold">Who should see this?</legend>
                @error('audience')<p class="error">{{ $message }}</p>@enderror
                <label class="flex items-center gap-2 py-1 text-sm"><input type="checkbox" name="audience_public" value="1" class="size-4 accent-curtain" @checked($isPublic)> Everyone, including the public website</label>
                @if ($units->isNotEmpty())
                    <p class="mt-3 text-xs font-semibold uppercase tracking-wide text-ink-soft">Members in</p>
                    <div class="mt-1 max-h-56 overflow-y-auto">
                        @foreach ($units as $u)
                            <label class="flex items-center gap-2 py-1 text-sm" style="padding-left: {{ max(0, $u->depth - $units->min('depth')) * 14 }}px"><input type="checkbox" name="audience_units[]" value="{{ $u->id }}" class="size-4 accent-curtain" @checked(in_array($u->id, $targetUnits))> {{ $u->fullName() }}</label>
                        @endforeach
                    </div>
                    <p class="hint">Choosing a District reaches every Assembly in it.</p>
                @endif
                @if ($canPublish)
                    <p class="mt-3 text-xs font-semibold uppercase tracking-wide text-ink-soft">Everyone holding a role</p>
                    <div class="mt-1 grid sm:grid-cols-2">
                        @foreach ($roles as $role)
                            <label class="flex items-center gap-2 py-1 text-sm"><input type="checkbox" name="audience_roles[]" value="{{ $role->key }}" class="size-4 accent-curtain" @checked(in_array($role->key, $targetRoles))> {{ $role->name }}s</label>
                        @endforeach
                    </div>
                @endif
            </fieldset>
            <details class="rounded-2xl border border-line p-4" @if(old('link_url', $announcement->link_url) || $announcement->image_path) open @endif>
                <summary class="cursor-pointer text-sm font-semibold">Picture, link and options</summary>
                <div class="mt-4 space-y-4">
                    <div>
                        <label for="image" class="label">Picture <span class="font-normal text-ink-soft">(optional)</span></label>
                        <input id="image" type="file" name="image" accept="image/*" class="block w-full text-sm">
                    </div>
                    <div class="grid gap-3 sm:grid-cols-[2fr_1fr]">
                        <div><label for="link_url" class="label">Link</label><input id="link_url" type="url" name="link_url" value="{{ old('link_url', $announcement->link_url) }}" class="input" placeholder="https://"></div>
                        <div><label for="cta_label" class="label">Button text</label><input id="cta_label" name="cta_label" value="{{ old('cta_label', $announcement->cta_label) }}" class="input" placeholder="Register now" maxlength="60"></div>
                    </div>
                    <div><label for="expires_at" class="label">Hide after</label><input id="expires_at" type="date" name="expires_at" value="{{ old('expires_at', $announcement->expires_at?->toDateString()) }}" class="input w-auto"></div>
                    <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="is_mandatory" value="1" class="size-4 accent-curtain" @checked(old('is_mandatory', $announcement->is_mandatory))> Required reading (members cannot switch it off)</label>
                    @if ($canPublish)<label class="flex items-center gap-2 text-sm"><input type="checkbox" name="is_pinned" value="1" class="size-4 accent-curtain" @checked(old('is_pinned', $announcement->is_pinned))> Feature at the top</label>@endif
                </div>
            </details>
            <div class="flex justify-end gap-2 border-t border-line pt-5">
                <button name="action" value="draft" class="btn-ghost">Save draft</button>
                <button name="action" value="send" class="btn-primary">{{ $canPublish ? 'Publish' : 'Publish or send for approval' }}</button>
            </div>
        </form>
    </section>
</x-layouts.app>
