@props(['video', 'size' => 'md', 'dark' => false])
<a href="{{ route('watch.show', $video) }}" {{ $attributes->merge(['class' => 'group block no-underline']) }}>
    <div class="relative aspect-video overflow-hidden rounded-xl bg-stage-2">
        <img src="{{ $video->thumbnailUrl($size === 'lg' ? 'hqdefault' : 'mqdefault') }}" alt="" class="size-full object-cover transition duration-300 group-hover:scale-[1.03]" loading="lazy">
        <span class="absolute bottom-2 left-2 inline-flex items-center gap-1 rounded-full bg-black/70 px-2 py-0.5 text-[11px] font-semibold text-white"><x-icon name="play" class="size-3.5" />{{ $video->categoryLabel() }}</span>
    </div>
    <p @class(['mt-2 font-semibold leading-snug', 'text-ink group-hover:text-curtain' => ! $dark, 'text-paper group-hover:text-gold' => $dark])>{{ $video->title }}</p>
    @if ($video->recorded_on)<p @class(['text-xs', 'text-ink-soft' => ! $dark, 'text-paper/50' => $dark])>{{ $video->recorded_on->format('Y') }}</p>@endif
</a>
