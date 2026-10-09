<x-layouts.app title="Certificates">
    <section class="container-page mt-8" x-data="{ tab: @js(request('tab', 'issued')) }">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div><p class="eyebrow">GODRAM Virtual Academy</p><h1 class="h-page mt-1">Certificates</h1></div>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('certificates.lookup') }}" class="btn-ghost no-underline" target="_blank"><x-icon name="shield" class="size-4" /> Public verification page</a>
                <a href="{{ route('certificates.manage.create') }}" class="btn-primary no-underline"><x-icon name="plus" class="size-4" /> Special recognition</a>
            </div>
        </div>

        <div class="mt-6 flex gap-2 border-b border-line" role="tablist">
            @foreach (['issued' => 'Issued', 'designs' => 'Designs', 'signatures' => 'Signatures', 'rules' => 'Achievement rules'] as $k => $l)
                <button type="button" role="tab" @click="tab = '{{ $k }}'" :aria-selected="tab === '{{ $k }}'" class="-mb-px border-b-2 px-4 py-2 text-sm font-semibold" :class="tab === '{{ $k }}' ? 'border-curtain text-curtain' : 'border-transparent text-ink-soft'">{{ $l }}</button>
            @endforeach
        </div>

        <div x-show="tab === 'issued'" class="mt-4">
            <form method="GET" class="flex flex-wrap gap-2">
                <label for="q" class="sr-only">Search</label><input id="q" type="search" name="q" value="{{ $q }}" class="input max-w-xs" placeholder="Certificate ID or name">
                <select name="kind" class="input max-w-48" aria-label="Kind"><option value="">All kinds</option>@foreach (\App\Models\Certificate::KINDS as $k => $l)<option value="{{ $k }}" @selected(request('kind') === $k)>{{ $l }}</option>@endforeach</select>
                <select name="status" class="input max-w-40" aria-label="Status"><option value="">Valid and revoked</option><option value="valid" @selected(request('status') === 'valid')>Valid</option><option value="revoked" @selected(request('status') === 'revoked')>Revoked</option></select>
                <button class="btn-dark">Search</button>
            </form>
            @if ($certificates->isEmpty())
                <x-empty title="No certificates yet" class="mt-4">Certificates are issued automatically when a candidate passes a certification examination and results are released, or when a member reaches an achievement that carries one.</x-empty>
            @else
                <div class="card mt-4 overflow-x-auto">
                    <table class="table min-w-[820px]">
                        <thead><tr><th>Certificate</th><th>Recipient</th><th>For</th><th>Issued</th><th>Status</th><th></th></tr></thead>
                        <tbody>
                            @foreach ($certificates as $c)
                                <tr>
                                    <td><span class="font-mono text-sm font-semibold">{{ $c->number }}</span><span class="block text-xs text-ink-soft">{{ $c->kindLabel() }}</span></td>
                                    <td>{{ $c->recipient_name }}<span class="block text-xs text-ink-soft">{{ $c->member?->member_no }}</span></td>
                                    <td class="text-sm">{{ $c->title }}<span class="block text-xs text-ink-soft" title="{{ $c->eligibility_rule }}">{{ \Illuminate\Support\Str::limit($c->programme ?? $c->eligibility_rule, 60) }}</span></td>
                                    <td class="whitespace-nowrap text-sm">{{ $c->issued_on->format('j M Y') }}</td>
                                    <td><span @class(['badge', 'badge-ok' => $c->isValid(), 'badge-bad' => ! $c->isValid()])>{{ $c->isValid() ? 'Valid' : 'Revoked' }}</span></td>
                                    <td class="whitespace-nowrap text-right" x-data="{ open: false }">
                                        <a href="{{ route('certificates.download', [$c, 'view' => 1]) }}" class="btn-ghost btn-sm no-underline" target="_blank">PDF</a>
                                        @if ($c->isValid())
                                            <button type="button" @click="open = !open" class="btn-ghost btn-sm">Revoke</button>
                                            <form x-show="open" x-cloak method="POST" action="{{ route('certificates.manage.revoke', $c) }}" class="mt-2 flex gap-2">@csrf<input name="reason" class="input" required maxlength="250" placeholder="Reason" aria-label="Reason for revoking"><button class="btn-primary btn-sm">Revoke</button></form>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="mt-4">{{ $certificates->links() }}</div>
            @endif
        </div>

        <div x-show="tab === 'designs'" x-cloak class="mt-4">
            <p class="text-sm text-ink-soft">Each kind of certificate has its own design. Open one to see a sample with today's signatures.</p>
            <div class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                @foreach (['training' => ['Academy red, with a "Trained" seal', 'bg-curtain'], 'examination' => ['Formal black frame, "Certified" stamp', 'bg-stage'], 'achievement' => ['Gold border and star medal', 'bg-gold'], 'recognition' => ['Dark stage with gold lettering', 'bg-stage-2']] as $kind => [$text, $swatch])
                    <a href="{{ route('certificates.manage.specimen', $kind) }}" target="_blank" class="card-pad block no-underline hover:border-ink-soft">
                        <span class="block h-2 w-12 rounded-full {{ $swatch }}"></span>
                        <span class="mt-3 block font-semibold">{{ \App\Models\Certificate::KINDS[$kind] }}</span>
                        <span class="text-sm text-ink-soft">{{ $text }}</span>
                        <span class="link mt-2 block text-sm">View sample</span>
                    </a>
                @endforeach
            </div>
        </div>

        <div x-show="tab === 'signatures'" x-cloak class="mt-4 grid gap-4 md:grid-cols-2">
            @foreach ($signatories as $s)
                <div class="card-pad">
                    <p class="eyebrow">{{ $s['title'] }}</p>
                    <p class="mt-1 font-semibold">{{ $s['name'] ?? 'No one holds this role yet' }}</p>
                    <div class="mt-3 flex h-24 items-center justify-center rounded-xl bg-paper-2">
                        @if ($s['signature'])<span class="text-sm text-ok"><x-icon name="check-circle" class="inline size-4" /> Signature on file</span>@else<span class="text-sm text-ink-soft">No signature yet: certificates show a blank line</span>@endif
                    </div>
                    <form method="POST" action="{{ route('certificates.manage.signature') }}" enctype="multipart/form-data" class="mt-3 flex gap-2">@csrf
                        <input type="hidden" name="role" value="{{ $s['role'] }}">
                        <label class="sr-only" for="sig-{{ $s['role'] }}">Signature picture</label><input id="sig-{{ $s['role'] }}" type="file" name="signature" accept="image/*" class="input py-2" required>
                        <button class="btn-dark btn-sm shrink-0">Upload</button>
                    </form>
                    <p class="hint">A clear picture of the signature on white paper, or a PNG with a see-through background.</p>
                </div>
            @endforeach
            @error('signature')<p class="error md:col-span-2">{{ $message }}</p>@enderror
            <p class="text-sm text-ink-soft md:col-span-2">Each certificate keeps the names of the people who held these roles on the day it was issued.</p>
        </div>

        <div x-show="tab === 'rules'" x-cloak class="mt-4 space-y-3">
            <p class="text-sm text-ink-soft">Members are checked against these rules every night, and straight after an examination result. Each award is recorded once.</p>
            @foreach ($rules as $rule)
                <form method="POST" action="{{ route('certificates.manage.rules.update', $rule) }}" class="card-pad grid gap-3 lg:grid-cols-[1.4fr_1fr_1.2fr_6rem_auto] lg:items-end" x-data="{ edit: false }">@csrf @method('PUT')
                    <div><label class="text-xs font-semibold text-ink-soft" for="rn{{ $rule->id }}">Achievement</label><input id="rn{{ $rule->id }}" name="name" value="{{ $rule->name }}" class="input" required maxlength="120"></div>
                    <div><label class="text-xs font-semibold text-ink-soft" for="rk{{ $rule->id }}">Kind</label><select id="rk{{ $rule->id }}" name="kind" class="input">@foreach (\App\Models\AchievementRule::KINDS as $k => $l)<option value="{{ $k }}" @selected($rule->kind === $k)>{{ $l }}</option>@endforeach</select></div>
                    <div><label class="text-xs font-semibold text-ink-soft" for="rm{{ $rule->id }}">Counts</label><select id="rm{{ $rule->id }}" name="metric" class="input">@foreach (\App\Models\AchievementRule::METRICS as $k => $l)<option value="{{ $k }}" @selected($rule->metric === $k)>{{ $l }}</option>@endforeach</select></div>
                    <div><label class="text-xs font-semibold text-ink-soft" for="rt{{ $rule->id }}">At least</label><input id="rt{{ $rule->id }}" type="number" name="threshold" min="1" value="{{ $rule->threshold }}" class="input"></div>
                    <button class="btn-ghost btn-sm">Save</button>
                    <input type="hidden" name="description" value="{{ $rule->description }}">
                    <div class="flex flex-wrap gap-4 text-sm lg:col-span-5">
                        <label class="flex items-center gap-2"><input type="checkbox" name="issues_certificate" value="1" @checked($rule->issues_certificate) class="size-4 accent-curtain"> Issues a certificate</label>
                        <input type="hidden" name="is_active" value="0"><label class="flex items-center gap-2"><input type="checkbox" name="is_active" value="1" @checked($rule->is_active) class="size-4 accent-curtain"> In use</label>
                        <span class="text-ink-soft">Awarded to {{ $rule->awards_count }} {{ \Illuminate\Support\Str::plural('member', $rule->awards_count) }}</span>
                    </div>
                </form>
            @endforeach
            <form method="POST" action="{{ route('certificates.manage.rules.store') }}" class="card-pad grid gap-3 border-dashed lg:grid-cols-[1.4fr_1fr_1.2fr_6rem_auto] lg:items-end">@csrf
                <div><label class="text-xs font-semibold text-ink-soft" for="new-name">New achievement</label><input id="new-name" name="name" class="input" required maxlength="120" placeholder="Faithful reporter"></div>
                <div><label class="text-xs font-semibold text-ink-soft" for="new-kind">Kind</label><select id="new-kind" name="kind" class="input">@foreach (\App\Models\AchievementRule::KINDS as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach</select></div>
                <div><label class="text-xs font-semibold text-ink-soft" for="new-metric">Counts</label><select id="new-metric" name="metric" class="input">@foreach (\App\Models\AchievementRule::METRICS as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach</select></div>
                <div><label class="text-xs font-semibold text-ink-soft" for="new-threshold">At least</label><input id="new-threshold" type="number" name="threshold" min="1" value="5" class="input"></div>
                <button class="btn-primary btn-sm">Add rule</button>
                <label class="flex items-center gap-2 text-sm lg:col-span-5"><input type="checkbox" name="issues_certificate" value="1" class="size-4 accent-curtain"> Issues a certificate</label>
            </form>
        </div>
    </section>
</x-layouts.app>
