@props(['page', 'eyebrow' => null, 'title' => null, 'lead' => null, 'size' => 'md', 'centred' => false])
@php
    // A page header with a slow image carousel behind it. Pictures come from
    // config/heroes.php; photos fill the frame, posters and papers stand to the right.
    $known = collect(config('archive'))->mapWithKeys(fn ($a) => ['archive/'.$a['file'] => $a])
        ->merge(collect(config('gallery'))->mapWithKeys(fn ($g) => ['gallery/'.$g['file'] => $g + ['kind' => 'photo']]));
    $slides = collect(config("heroes.$page", []))->map(function ($path) use ($known) {
        $item = $known[$path] ?? [];
        $label = $item['title'] ?? '';
        if (! empty($item['year']) && ! str_contains($label, (string) $item['year'])) $label .= ', '.$item['year'];

        return ['src' => asset('images/'.$path), 'photo' => ($item['kind'] ?? 'photo') === 'photo', 'label' => $label, 'from' => str_starts_with($path, 'archive/') ? 'From the archive' : 'GODRAM in action'];
    })->values();
    $height = ['lg' => 'min-h-[34rem] sm:min-h-[40rem]', 'md' => 'min-h-[26rem] sm:min-h-[30rem]', 'sm' => 'min-h-[20rem] sm:min-h-[22rem]', 'full' => 'min-h-[calc(100vh-4rem)]'][$size];
@endphp
<section {{ $attributes->merge(['class' => "hero relative isolate flex overflow-hidden bg-stage text-paper $height"]) }}
    x-data="carousel({{ $slides->count() }})" @mouseenter="hold(matchMedia('(hover: hover)').matches)" @mouseleave="hold(false)" @focusin="hold($event.target.matches(':focus-visible'))" @focusout="hold(false)"
    @touchstart.passive="touchStart($event)" @touchend="touchEnd($event)" aria-roledescription="carousel">
    <div class="absolute inset-0 -z-10" aria-hidden="true">
        @foreach ($slides as $n => $slide)
            @php $src = $n === 0 ? "src=\"{$slide['src']}\"" : ''; $bind = $n === 0 ? '' : "x-bind:src=\"seen.includes($n) ? '{$slide['src']}' : null\""; @endphp
            <div class="hero-slide {{ $n === 0 ? 'is-active' : '' }}" :class="{ 'is-active': i === {{ $n }} }">
                @if ($slide['photo'])
                    <img {!! $src !!} {!! $bind !!} alt="" class="hero-photo" @if ($n === 0) fetchpriority="high" @endif>
                @else
                    <img {!! $src !!} {!! $bind !!} alt="" class="hero-wash">
                    <img {!! $src !!} {!! $bind !!} alt="" class="hero-poster">
                @endif
            </div>
        @endforeach
    </div>
    <div class="hero-shade absolute inset-0 -z-10" aria-hidden="true"></div>

    <div class="container-page relative w-full self-center pt-12 pb-24 sm:pt-16">
        <div @class(['mx-auto w-full max-w-md' => $centred, 'max-w-3xl lg:max-w-[58%]' => ! $centred])>
            @if ($eyebrow)<p class="hero-eyebrow">{{ $eyebrow }}</p>@endif
            @if (isset($heading))
                {{ $heading }}
            @elseif ($title)
                <h1 class="hero-title mt-3 text-4xl sm:text-6xl">{{ $title }}</h1>
            @endif
            @if ($lead)<p class="mt-4 max-w-2xl text-lg text-paper/90 [text-shadow:0_1px_8px_rgb(0_0_0/0.5)]">{{ $lead }}</p>@endif
            {{ $slot }}
        </div>
    </div>

    @if ($slides->count() > 1)
        <div class="container-page absolute inset-x-0 bottom-5 z-10 flex items-center justify-between gap-4">
            <div class="flex items-center gap-1.5">
                @foreach ($slides as $n => $slide)
                    <button type="button" class="group flex h-6 items-center" @click="choose({{ $n }})" aria-label="Show picture {{ $n + 1 }} of {{ $slides->count() }}" :aria-current="i === {{ $n }}">
                        <span class="block h-1.5 rounded-full transition-all duration-500" :class="i === {{ $n }} ? 'w-7 bg-gold' : 'w-2.5 bg-paper/45 group-hover:bg-paper/80'"></span>
                    </button>
                @endforeach
                <button type="button" class="ml-2 rounded-full p-1.5 text-paper/75 hover:text-white" @click="toggle()" :aria-label="playing ? 'Pause the pictures' : 'Play the pictures'">
                    <span x-show="playing"><x-icon name="pause" class="size-3.5" /></span><span x-show="!playing" x-cloak><x-icon name="resume" class="size-3.5" /></span>
                </button>
            </div>
            <div class="flex min-w-0 items-center gap-3">
                <p class="hidden truncate text-xs text-paper/70 sm:block" aria-live="polite">
                    @foreach ($slides as $n => $slide)<span x-show="i === {{ $n }}" @if ($n) x-cloak @endif>{{ $slide['from'] }} · {{ $slide['label'] }}</span>@endforeach
                </p>
                <button type="button" class="hidden rounded-full border border-paper/25 p-2 text-paper/85 hover:bg-white/10 sm:block" @click="choose(i - 1)" aria-label="Previous picture"><x-icon name="chevron-left" class="size-4" /></button>
                <button type="button" class="hidden rounded-full border border-paper/25 p-2 text-paper/85 hover:bg-white/10 sm:block" @click="choose(i + 1)" aria-label="Next picture"><x-icon name="chevron-right" class="size-4" /></button>
            </div>
        </div>
    @endif
    <div class="poster-stripe absolute inset-x-0 bottom-0 h-1.5" aria-hidden="true"></div>
</section>
