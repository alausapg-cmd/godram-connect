<x-layouts.app title="Audit log">
    <section class="container-page mt-8">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="eyebrow">Administration</p>
                <h1 class="h-page">Audit log</h1>
                <p class="mt-1 text-sm text-ink-soft">Every sign-in, appointment, approval, transfer and change. Entries are chained, so any alteration is detectable.</p>
            </div>
            <a href="{{ route('admin.audit.index', ['verify' => 1]) }}" class="btn-ghost btn-sm no-underline"><x-icon name="shield" class="size-4" />Check integrity</a>
        </div>
        @if ($verified)
            @if ($broken === null)
                <p class="mt-4 rounded-xl bg-ok/10 px-4 py-3 text-sm font-semibold text-ok">The audit log is intact. No entry has been changed or removed.</p>
            @else
                <p class="mt-4 rounded-xl bg-curtain/10 px-4 py-3 text-sm font-semibold text-curtain">Warning: the chain breaks at entry #{{ $broken }}. An entry may have been altered or deleted.</p>
            @endif
        @endif
        <form method="GET" class="mt-4 flex flex-wrap gap-2">
            <input name="q" value="{{ request('q') }}" placeholder="Search" class="input max-w-xs">
            <select name="action" class="input w-auto">
                <option value="">All actions</option>
                @foreach (['auth' => 'Sign-ins', 'member' => 'Members', 'report' => 'Reports', 'announcement' => 'Announcements', 'role' => 'Roles', 'org' => 'Structure'] as $k => $label)<option value="{{ $k }}" @selected(request('action') === $k)>{{ $label }}</option>@endforeach
            </select>
            <button class="btn-dark">Filter</button>
        </form>
        <div class="card mt-4 overflow-x-auto">
            <table class="table">
                <thead><tr><th>#</th><th>When</th><th>Who</th><th>What</th><th>Action</th></tr></thead>
                <tbody>
                @foreach ($entries as $e)
                    <tr>
                        <td class="font-mono text-xs text-ink-soft">{{ $e->id }}</td>
                        <td class="whitespace-nowrap text-ink-soft">{{ $e->created_at->format('j M Y, H:i') }}</td>
                        <td>{{ $e->user?->name ?? 'System' }}</td>
                        <td>{{ $e->summary }}</td>
                        <td><span class="badge-neutral font-mono">{{ $e->action }}</span></td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        <div class="mt-4">{{ $entries->links() }}</div>
    </section>
</x-layouts.app>
