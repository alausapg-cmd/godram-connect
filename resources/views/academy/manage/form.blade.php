@php
    $editing = $course->exists;
    $targetUnits = old('target_units', $editing ? $course->targets->where('audience', 'org_unit')->pluck('org_unit_id')->all() : []);
    $targetRoles = old('target_roles', $editing ? $course->targets->where('audience', 'role')->pluck('role_key')->all() : []);
    $facilitators = old('facilitators', $editing ? $course->facilitators->map(fn ($f) => $f->member->member_no.($f->title ? ', '.$f->title : ''))->implode("\n") : '');
    $d = fn ($v) => $v ? \Illuminate\Support\Carbon::parse($v)->format('Y-m-d') : '';
@endphp
<x-layouts.app :title="$editing ? 'Edit training' : 'New training'">
    <section class="container-page mt-8 max-w-3xl">
        <a href="{{ $editing ? route('academy.manage.build', $course) : route('academy.manage.index') }}" class="text-sm font-semibold text-curtain">{{ $editing ? $course->title : 'Manage training' }}</a>
        <h1 class="h-page mt-2">{{ $editing ? 'Edit training' : 'New training' }}</h1>
        <p class="mt-1 text-sm text-ink-soft">Training starts as a draft. Members see it only after you open it.</p>

        <form method="POST" action="{{ $editing ? route('academy.manage.update', $course) : route('academy.manage.store') }}" enctype="multipart/form-data" class="mt-6 space-y-6">
            @csrf @if ($editing) @method('PUT') @endif
            <div class="card-pad space-y-5">
                <div><label for="title" class="label">Name of the training</label><input id="title" name="title" value="{{ old('title', $course->title) }}" class="input" maxlength="150" required placeholder="Foundations of Drama Ministry">@error('title')<p class="error">{{ $message }}</p>@enderror</div>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div><label for="org_unit_id" class="label">Run by</label>
                        <select id="org_unit_id" name="org_unit_id" class="input">@foreach ($units as $u)<option value="{{ $u->id }}" @selected((int) old('org_unit_id', $course->org_unit_id) === $u->id)>{{ $u->fullName() }}</option>@endforeach</select>@error('org_unit_id')<p class="error">{{ $message }}</p>@enderror</div>
                    <div><label for="kind" class="label">How it is taught</label>
                        <select id="kind" name="kind" class="input">@foreach (\App\Models\Course::KINDS as $k => $l)<option value="{{ $k }}" @selected(old('kind', $course->kind) === $k)>{{ $l }}</option>@endforeach</select></div>
                </div>
                <div><label for="summary" class="label">In one or two sentences</label><textarea id="summary" name="summary" rows="2" class="input" maxlength="300" required>{{ old('summary', $course->summary) }}</textarea>@error('summary')<p class="error">{{ $message }}</p>@enderror</div>
                <div><label for="description" class="label">About the training <span class="font-normal text-ink-soft">(optional)</span></label><textarea id="description" name="description" rows="5" class="input">{{ old('description', $course->description) }}</textarea></div>
                <div><label for="outcomes" class="label">What members will be able to do, one per line <span class="font-normal text-ink-soft">(optional)</span></label><textarea id="outcomes" name="outcomes" rows="4" class="input" placeholder="Prepare a short evangelistic sketch">{{ old('outcomes', $course->outcomes) }}</textarea></div>
                <div><label for="cover" class="label">Cover picture <span class="font-normal text-ink-soft">(optional)</span></label><input id="cover" type="file" name="cover" accept="image/*" class="input">@error('cover')<p class="error">{{ $message }}</p>@enderror</div>
            </div>

            <div class="card-pad space-y-5">
                <h2 class="h-section">Dates</h2>
                <div class="grid gap-4 sm:grid-cols-3">
                    <div><label for="starts_on" class="label">Starts</label><input id="starts_on" type="date" name="starts_on" value="{{ old('starts_on', $d($course->starts_on)) }}" class="input"></div>
                    <div><label for="ends_on" class="label">Ends</label><input id="ends_on" type="date" name="ends_on" value="{{ old('ends_on', $d($course->ends_on)) }}" class="input">@error('ends_on')<p class="error">{{ $message }}</p>@enderror</div>
                    <div><label for="enrol_by" class="label">Enrol by</label><input id="enrol_by" type="date" name="enrol_by" value="{{ old('enrol_by', $d($course->enrol_by)) }}" class="input"></div>
                </div>
                <p class="hint">Leave the dates empty for a course members can take at any time.</p>
            </div>

            <div class="card-pad space-y-5" x-data="filterList()">
                <h2 class="h-section">Who it is for</h2>
                <p class="text-sm text-ink-soft">With nothing ticked, every member under the organiser can enrol. Tick Districts, Regions or roles to aim it at particular people.</p>
                @if ($units->count() > 1)
                    <div>
                        <p class="label">Only these places</p>
                        @if ($units->count() > 8)<input x-model="term" type="search" class="input mb-2" placeholder="Find a Region or District">@endif
                        <div class="grid max-h-64 gap-1 overflow-y-auto sm:grid-cols-2">
                            @foreach ($units->where('type', '!=', \App\Models\OrgUnit::NATIONAL) as $u)
                                <label class="flex items-center gap-2 text-sm" x-show="matches(@js($u->fullName()))"><input type="checkbox" name="target_units[]" value="{{ $u->id }}" @checked(in_array($u->id, $targetUnits)) class="size-4 accent-curtain"> {{ $u->fullName() }}</label>
                            @endforeach
                        </div>
                        @error('target_units')<p class="error">{{ $message }}</p>@enderror
                    </div>
                @endif
                <div>
                    <p class="label">Only people with these roles</p>
                    <div class="grid gap-1 sm:grid-cols-2">
                        @foreach ($roles as $r)
                            <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="target_roles[]" value="{{ $r->key }}" @checked(in_array($r->key, $targetRoles)) class="size-4 accent-curtain"> {{ $r->name }}s</label>
                        @endforeach
                    </div>
                </div>
                <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="is_public" value="1" @checked(old('is_public', $course->is_public)) class="size-4 accent-curtain"> Show it on the public Academy page, so visitors can see what GODRAM teaches</label>
            </div>

            <div class="card-pad space-y-3">
                <h2 class="h-section">Facilitators</h2>
                <label for="facilitators" class="label">Member IDs, one per line, with an optional title after a comma</label>
                <textarea id="facilitators" name="facilitators" rows="3" class="input font-mono text-sm" placeholder="GDM-000012, Lead facilitator">{{ $facilitators }}</textarea>
                @error('facilitators')<p class="error">{{ $message }}</p>@enderror
                <p class="hint">Facilitators can add lessons, run live classes, answer questions and review assignments for this training.</p>
            </div>

            <div class="flex gap-3"><button class="btn-primary">{{ $editing ? 'Save' : 'Create training' }}</button><a href="{{ $editing ? route('academy.manage.build', $course) : route('academy.manage.index') }}" class="btn-ghost no-underline">Cancel</a></div>
        </form>
    </section>
</x-layouts.app>
