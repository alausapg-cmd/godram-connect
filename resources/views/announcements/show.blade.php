<x-layouts.app :title="$announcement->title" :description="\Illuminate\Support\Str::limit($announcement->body, 150)">
    <article class="container-page mt-10 max-w-3xl">
        <a href="{{ route('announcements.index') }}" class="text-sm font-semibold text-curtain">All news</a>
        @if ($announcement->status !== 'published')
            <p class="mt-4 rounded-xl bg-gold/20 px-4 py-2 text-sm">Status: {{ $announcement->statusLabel() }}. Only you and approvers can see this.</p>
        @endif
        <p class="mt-4 text-sm text-ink-soft">{{ $announcement->published_at?->format('l j F Y') }} · For {{ $announcement->audienceLabel() }}</p>
        <h1 class="h-page mt-1">{{ $announcement->title }}</h1>
        @if ($announcement->image_path)<img src="{{ route('announcements.image', $announcement) }}" alt="" class="mt-6 w-full rounded-2xl">@endif
        <div class="mt-6 whitespace-pre-line text-lg leading-relaxed">{{ $announcement->body }}</div>
        @if ($announcement->link_url)
            <a href="{{ $announcement->link_url }}" class="btn-primary mt-6 no-underline" target="_blank" rel="noopener">{{ $announcement->cta_label ?? 'Learn more' }} <x-icon name="arrow-right" class="size-4" /></a>
        @endif
        @php $url = route('announcements.show', $announcement); @endphp
        @if ($announcement->isPublic())
            <div class="mt-8 flex flex-wrap gap-2 border-t border-line pt-6">
                <a class="btn-ghost btn-sm no-underline" href="https://wa.me/?text={{ urlencode($announcement->title.' - '.$url) }}" target="_blank" rel="noopener">Share on WhatsApp</a>
                <a class="btn-ghost btn-sm no-underline" href="https://www.facebook.com/sharer/sharer.php?u={{ urlencode($url) }}" target="_blank" rel="noopener">Share on Facebook</a>
            </div>
        @endif
    </article>
</x-layouts.app>
