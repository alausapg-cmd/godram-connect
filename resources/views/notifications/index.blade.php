<x-layouts.app title="Notifications">
    @php $categories = config('notifications.categories'); @endphp
    <section class="container-page mt-8 max-w-3xl">
        <div class="flex flex-wrap items-end justify-between gap-3">
            <div><p class="eyebrow">My GODRAM</p><h1 class="h-page mt-1">Notifications</h1></div>
            <div class="flex gap-2">
                @if ($unread)
                    <form method="POST" action="{{ route('notifications.read-all') }}">@csrf<button class="btn-ghost btn-sm"><x-icon name="check" class="size-4" /> Mark all read</button></form>
                @endif
                <a href="{{ route('notifications.settings') }}" class="btn-dark btn-sm no-underline"><x-icon name="settings" class="size-4" /> Settings</a>
            </div>
        </div>

        <div class="card mt-6 divide-y divide-line overflow-hidden">
            @forelse ($notifications as $n)
                @php $c = $categories[$n->data['category'] ?? ''] ?? ['icon' => 'bell', 'label' => 'Notice']; @endphp
                <a href="{{ route('notifications.open', $n->id) }}" @class(['flex gap-3 p-4 no-underline hover:bg-paper/60', 'bg-gold/10' => ! $n->read_at])>
                    <span @class(['mt-0.5 flex size-10 shrink-0 items-center justify-center rounded-full', 'bg-curtain text-white' => $n->data['important'] ?? false, 'bg-stage text-gold' => ! ($n->data['important'] ?? false)])><x-icon :name="$c['icon']" class="size-5" /></span>
                    <span class="min-w-0 flex-1">
                        <span class="flex items-start justify-between gap-3">
                            <span @class(['text-ink', 'font-semibold' => ! $n->read_at])>{{ $n->data['title'] ?? 'Notice' }}</span>
                            @unless ($n->read_at)<span class="mt-1.5 size-2.5 shrink-0 rounded-full bg-curtain" title="Unread"></span>@endunless
                        </span>
                        <span class="mt-0.5 block text-sm text-ink-soft">{{ $n->data['body'] ?? '' }}</span>
                        <span class="mt-1 block text-xs text-ink-soft/80">{{ $c['label'] }} · <time datetime="{{ $n->created_at->toIso8601String() }}" title="{{ $n->created_at->format('j M Y, g:ia') }}">{{ $n->created_at->diffForHumans() }}</time></span>
                    </span>
                </a>
            @empty
                <div class="p-2"><x-empty class="border-0" title="Nothing yet">When something needs you, or there is news for your part of GODRAM, it appears here.</x-empty></div>
            @endforelse
        </div>
        <div class="mt-4">{{ $notifications->links() }}</div>
    </section>
</x-layouts.app>
