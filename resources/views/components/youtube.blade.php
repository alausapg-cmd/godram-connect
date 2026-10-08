@props(['id', 'title' => 'Video', 'start' => null])
{{-- Shows only a thumbnail until tapped, so a page with videos costs almost no data until someone presses play. --}}
<div x-data="{ playing: false }" {{ $attributes->merge(['class' => 'relative aspect-video overflow-hidden rounded-2xl bg-black']) }}>
    <template x-if="playing">
        <iframe class="absolute inset-0 size-full" src="https://www.youtube-nocookie.com/embed/{{ $id }}?autoplay=1&rel=0&modestbranding=1&playsinline=1{{ $start ? '&start='.$start : '' }}"
                title="{{ $title }}" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" allowfullscreen referrerpolicy="strict-origin-when-cross-origin"></iframe>
    </template>
    <button type="button" x-show="!playing" @click="playing = true" class="group absolute inset-0 size-full" aria-label="Play {{ $title }}">
        <img src="https://i.ytimg.com/vi/{{ $id }}/hqdefault.jpg" alt="" class="size-full object-cover opacity-90 transition group-hover:opacity-100" loading="lazy">
        <span class="absolute inset-0 bg-gradient-to-t from-black/60 via-transparent"></span>
        <span class="absolute left-1/2 top-1/2 flex size-16 -translate-x-1/2 -translate-y-1/2 items-center justify-center rounded-full bg-curtain text-white shadow-lg transition group-hover:scale-105 sm:size-20">
            <svg viewBox="0 0 24 24" class="ml-1 size-7 sm:size-9" fill="currentColor" aria-hidden="true"><path d="M8 5v14l11-7z"/></svg>
        </span>
    </button>
</div>
