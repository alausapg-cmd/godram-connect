@props(['url', 'title', 'text' => null, 'dark' => false])
@php
    $message = trim(($text ? $text.' ' : '').$title.' '.$url);
    $links = [
        ['WhatsApp', 'https://wa.me/?text='.rawurlencode($message), 'M20.5 3.5A11.8 11.8 0 0 0 1.9 17.7L.5 23.5l6-1.6A11.8 11.8 0 0 0 23.8 12a11.7 11.7 0 0 0-3.3-8.5ZM12 21.7a9.8 9.8 0 0 1-5-1.4l-.4-.2-3.6.9 1-3.5-.2-.4a9.8 9.8 0 1 1 8.2 4.6Zm5.4-7.3c-.3-.2-1.8-.9-2-1s-.5-.2-.7.1-.8 1-1 1.2-.4.2-.7.1a8 8 0 0 1-4-3.5c-.3-.5.3-.5.9-1.6.1-.2 0-.4 0-.5l-.9-2.2c-.2-.6-.5-.5-.7-.5h-.6a1.1 1.1 0 0 0-.8.4 3.4 3.4 0 0 0-1 2.5 5.9 5.9 0 0 0 1.2 3.1 13.5 13.5 0 0 0 5.2 4.6c1.9.8 2.7.9 3.6.7a3.1 3.1 0 0 0 2-1.4 2.5 2.5 0 0 0 .2-1.4c-.1-.1-.3-.2-.6-.3Z'],
        ['Facebook', 'https://www.facebook.com/sharer/sharer.php?u='.rawurlencode($url), 'M24 12a12 12 0 1 0-13.9 11.9v-8.4H7.1V12h3V9.4c0-3 1.8-4.7 4.6-4.7 1.3 0 2.7.2 2.7.2v3h-1.5c-1.5 0-2 .9-2 1.9V12h3.4l-.5 3.5h-2.9v8.4A12 12 0 0 0 24 12Z'],
        ['X', 'https://x.com/intent/post?text='.rawurlencode(trim(($text ? $text.' ' : '').$title)).'&url='.rawurlencode($url), 'M18.2 2.3h3.4l-7.4 8.4 8.7 11.5h-6.8l-5.3-7-6.1 7H1.3l7.9-9L.9 2.3h7l4.8 6.4 5.5-6.4Zm-1.2 17.9h1.9L7 4.2H5Z'],
    ];
@endphp
<div x-data="{ copied: false, canShare: !!navigator.share }" {{ $attributes->merge(['class' => 'flex flex-wrap items-center gap-2']) }}>
    <button type="button" x-show="canShare" x-cloak
            @click="navigator.share({ title: @js($title), text: @js($text ?? $title), url: @js($url) }).catch(() => {})"
            class="{{ $dark ? 'btn-gold' : 'btn-dark' }} btn-sm"><x-icon name="share" class="size-4" /> Share</button>
    @foreach ($links as [$label, $href, $path])
        <a href="{{ $href }}" target="_blank" rel="noopener" title="Share on {{ $label }}"
           class="inline-flex size-9 items-center justify-center rounded-full no-underline {{ $dark ? 'bg-white/10 text-paper hover:bg-white/20' : 'border border-line bg-white text-ink hover:border-ink-soft' }}">
            <svg viewBox="0 0 24 24" class="size-4" fill="currentColor" aria-hidden="true"><path d="{{ $path }}"/></svg><span class="sr-only">Share on {{ $label }}</span>
        </a>
    @endforeach
    <button type="button" @click="navigator.clipboard.writeText(@js($url)).then(() => { copied = true; setTimeout(() => copied = false, 2000) })"
            class="inline-flex size-9 items-center justify-center rounded-full {{ $dark ? 'bg-white/10 text-paper hover:bg-white/20' : 'border border-line bg-white text-ink hover:border-ink-soft' }}" title="Copy link">
        <x-icon name="link" class="size-4" x-show="!copied" /><x-icon name="check" class="size-4 text-ok" x-show="copied" x-cloak /><span class="sr-only">Copy link</span>
    </button>
</div>
