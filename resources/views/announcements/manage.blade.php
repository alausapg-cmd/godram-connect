<x-layouts.app title="Manage announcements">
    <section class="container-page mt-8 max-w-4xl">
        <div class="flex items-end justify-between gap-3">
            <div>
                <p class="eyebrow">Communicate</p>
                <h1 class="h-page">Announcements</h1>
            </div>
            @if (can_do('announcements.create'))<a href="{{ route('announcements.create') }}" class="btn-primary btn-sm no-underline"><x-icon name="plus" class="size-4" />New announcement</a>@endif
        </div>

        @if ($queue->isNotEmpty())
            <h2 class="h-section mt-8">Waiting for your approval</h2>
            @foreach ($queue as $a)
                <div class="card mt-3 p-5" x-data="{ returning: false }">
                    <p class="text-xs text-ink-soft">From {{ $a->author?->name }} · for {{ $a->audienceLabel() }}</p>
                    <h3 class="mt-1 font-display text-xl font-semibold uppercase text-stage">{{ $a->title }}</h3>
                    <p class="mt-2 whitespace-pre-line text-sm text-ink-soft">{{ $a->body }}</p>
                    <form method="POST" action="{{ route('announcements.decide', $a) }}" class="mt-4 space-y-2">
                        @csrf
                        <input x-show="returning" x-cloak name="reason" class="input" placeholder="What should change?">
                        <div class="flex gap-2">
                            <button name="decision" value="approve" class="btn-primary btn-sm" x-show="!returning">Approve and publish</button>
                            <button type="button" class="btn-ghost btn-sm" x-show="!returning" @click="returning = true">Return</button>
                            <button name="decision" value="reject" class="btn-primary btn-sm" x-show="returning" x-cloak>Send back</button>
                        </div>
                    </form>
                </div>
            @endforeach
        @endif

        <h2 class="h-section mt-8">My announcements</h2>
        @if ($mine->isEmpty())
            <x-empty title="Nothing yet" class="mt-3">Announcements you write for your area will be listed here.</x-empty>
        @else
            <div class="card mt-3 overflow-x-auto">
                <table class="table">
                    <thead><tr><th>Title</th><th>Audience</th><th>Status</th><th></th></tr></thead>
                    <tbody>
                    @foreach ($mine as $a)
                        <tr>
                            <td><a href="{{ route('announcements.show', $a) }}" class="font-semibold text-ink">{{ $a->title }}</a><span class="block text-xs text-ink-soft">{{ $a->created_at->format('j M Y') }}</span></td>
                            <td class="text-ink-soft">{{ $a->audienceLabel() }}</td>
                            <td><x-status-badge :status="$a->status" :label="$a->statusLabel()" />@if($a->reject_reason)<span class="mt-1 block text-xs text-curtain">{{ $a->reject_reason }}</span>@endif</td>
                            <td class="whitespace-nowrap text-right">
                                @if (in_array($a->status, ['draft', 'rejected', 'submitted']))<a href="{{ route('announcements.edit', $a) }}" class="link text-sm">Edit</a>@endif
                                @if ($a->status === 'published')<form method="POST" action="{{ route('announcements.archive', $a) }}" class="inline">@csrf<button class="text-sm font-semibold text-ink-soft hover:text-curtain">Archive</button></form>@endif
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>
</x-layouts.app>
