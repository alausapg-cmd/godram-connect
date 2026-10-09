<x-layouts.app :title="$announcement->title" :description="\Illuminate\Support\Str::limit($announcement->body, 150)" :image="$announcement->status === 'published' && $announcement->isPublic() ? route('share.card', ['announcement', $announcement->id]) : null">
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
        @if ($announcement->status === 'published' && $canManage)
            <x-share-kit class="mt-8" :url="$url" filename="godram-announcement.png"
                :image="$announcement->isPublic() ? route('share.card', ['announcement', $announcement->id]) : null"
                :message="'*'.$announcement->title.'*'.PHP_EOL.PHP_EOL.\Illuminate\Support\Str::limit($announcement->body, 300).PHP_EOL.PHP_EOL.'Read more on GODRAM CONNECT: '.$url" />
        @elseif ($announcement->isPublic())
            <x-share class="mt-8 border-t border-line pt-6" :url="$url" :title="$announcement->title" />
        @endif
    </article>
</x-layouts.app>
