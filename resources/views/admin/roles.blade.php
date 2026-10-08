<x-layouts.app title="Roles">
    <section class="container-page mt-8 max-w-5xl">
        <p class="eyebrow">Administration</p>
        <h1 class="h-page">Roles and appointments</h1>
        <p class="mt-1 text-ink-soft">Each role is held at a place in the structure. Permissions follow from the role and stay inside that place.</p>

        <form method="POST" action="{{ route('admin.roles.store') }}" class="card-pad mt-6 grid gap-3 sm:grid-cols-4 sm:items-end">
            @csrf
            <div><label for="member_no" class="label">Member ID</label><input id="member_no" name="member_no" value="{{ old('member_no') }}" class="input font-mono" placeholder="GDM-000123" required></div>
            <div><label for="role_id" class="label">Role</label>
                <select id="role_id" name="role_id" class="input" required>@foreach ($roles as $r)<option value="{{ $r->id }}" @selected(old('role_id') == $r->id)>{{ $r->name }}</option>@endforeach</select></div>
            <div><label for="org_unit_id" class="label">At</label>
                <select id="org_unit_id" name="org_unit_id" class="input" required>@foreach ($units as $u)<option value="{{ $u->id }}" @selected(old('org_unit_id') == $u->id)>{{ str_repeat('· ', $u->depth) }}{{ $u->name }}</option>@endforeach</select></div>
            <button class="btn-primary">Appoint</button>
        </form>

        <div class="card mt-6 overflow-x-auto">
            <table class="table">
                <thead><tr><th>Member</th><th>Role</th><th>Since</th><th></th></tr></thead>
                <tbody>
                @foreach ($assignments as $a)
                    <tr>
                        <td><a href="{{ route('members.show', $a->member) }}" class="font-semibold text-ink">{{ $a->member->full_name }}</a><span class="block font-mono text-xs text-ink-soft">{{ $a->member->member_no }}</span></td>
                        <td>{{ $a->title() }}</td>
                        <td class="text-ink-soft">{{ $a->starts_on->format('M Y') }}</td>
                        <td class="text-right">@if ($a->member_id !== auth()->user()->member_id)<form method="POST" action="{{ route('admin.roles.end', $a) }}" onsubmit="return confirm('End this appointment?')">@csrf<button class="text-sm font-semibold text-ink-soft hover:text-curtain">End</button></form>@endif</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        <div class="mt-4">{{ $assignments->links() }}</div>
    </section>
</x-layouts.app>
