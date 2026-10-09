@php $list = \App\Services\YouTubeChannel::uploadsPlaylistId(); @endphp
{{-- Plays the latest GODRAM TV uploads straight from the channel. Loads nothing from YouTube until tapped. --}}
@if ($list)
    <div x-data="{ playing: false }" {{ $attributes->merge(['class' => 'relative aspect-video overflow-hidden rounded-2xl bg-black']) }}>
        <template x-if="playing">
            <iframe class="absolute inset-0 size-full" src="https://www.youtube-nocookie.com/embed/videoseries?list={{ $list }}&autoplay=1&rel=0&playsinline=1" title="Latest from GODRAM TV" allow="autoplay; encrypted-media; picture-in-picture" allowfullscreen></iframe>
        </template>
        <button type="button" x-show="!playing" @click="playing = true" class="group absolute inset-0 flex flex-col items-center justify-center gap-4 bg-[radial-gradient(circle_at_30%_20%,var(--color-curtain),transparent_55%),radial-gradient(circle_at_80%_90%,var(--color-poster),transparent_50%)] text-paper">
            <span class="flex size-20 items-center justify-center rounded-full bg-paper text-curtain shadow-xl transition group-hover:scale-105"><svg viewBox="0 0 24 24" class="ml-1 size-9" fill="currentColor"><path d="M8 5v14l11-7z"/></svg></span>
            <span class="font-display text-2xl font-semibold uppercase sm:text-3xl">Latest from GODRAM TV</span>
            <span class="text-sm text-paper/70">Plays the newest uploads from the channel</span>
        </button>
    </div>
@endif
