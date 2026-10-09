@props(['url', 'message', 'image' => null, 'filename' => 'godram.png'])
{{--
    Create, Preview, Share. WhatsApp, Instagram and TikTok let nobody post into a group or
    channel on our behalf, so GODRAM CONNECT prepares the words and the picture and the
    person sends them from their own phone. Nothing here pretends to post automatically.
--}}
<div x-data="{
        text: @js($message),
        copied: false,
        canShareFiles: false,
        init() { try { this.canShareFiles = !!(navigator.canShare && navigator.canShare({ files: [new File([''], 'x.png', { type: 'image/png' })] })) } catch (e) {} },
        // WhatsApp shows *words* in bold; the preview does the same.
        preview() {
            const safe = this.text.replace(/[&<>]/g, (c) => ({ '&': '&amp;amp;', '<': '&amp;lt;', '>': '&amp;gt;' })[c]);
            return safe.replace(/\*([^*\n]+)\*/g, '<b>$1</b>').replace(/_([^_\n]+)_/g, '<i>$1</i>');
        },
        wa() { return 'https://wa.me/?text=' + encodeURIComponent(this.text) },
        x() { return 'https://x.com/intent/post?text=' + encodeURIComponent(this.text) },
        copy() { navigator.clipboard.writeText(this.text).then(() => { this.copied = true; setTimeout(() => this.copied = false, 2000) }) },
        async withPicture() {
            try {
                const blob = await (await fetch(@js($image))).blob();
                await navigator.share({ text: this.text, files: [new File([blob], @js($filename), { type: 'image/png' })] });
            } catch (e) {}
        },
    }" {{ $attributes->merge(['class' => 'card-pad']) }}>
    <h2 class="h-section">Share on WhatsApp and social media</h2>
    <ol class="mt-3 space-y-4 text-sm">
        <li>
            <p class="font-semibold text-ink"><span class="text-poster">1.</span> Write the message</p>
            <textarea x-model="text" rows="5" class="input mt-1.5 text-sm" aria-label="Message to share"></textarea>
        </li>
        <li>
            <p class="font-semibold text-ink"><span class="text-poster">2.</span> Check how it looks</p>
            <div class="mt-1.5 rounded-xl bg-[#e7ded3] p-3">
                <div class="ml-auto max-w-[92%] overflow-hidden rounded-lg rounded-tr-none bg-[#d9fdd3] text-[13px] text-[#111] shadow-sm">
                    @if ($image)<img src="{{ $image }}" alt="The picture that goes with the message" class="aspect-[1200/630] w-full object-cover" loading="lazy">@endif
                    <p class="whitespace-pre-line break-words px-2.5 py-2" x-html="preview()"></p>
                </div>
            </div>
        </li>
        <li>
            <p class="font-semibold text-ink"><span class="text-poster">3.</span> Share it</p>
            <div class="mt-1.5 flex flex-wrap gap-2">
                <a :href="wa()" target="_blank" rel="noopener" class="btn-sm inline-flex min-h-9 items-center gap-1.5 rounded-full bg-[#1f8f4e] px-4 text-xs font-semibold text-white no-underline hover:brightness-95"><x-icon name="whatsapp" class="size-4" /> WhatsApp</a>
                @if ($image)
                    <button type="button" x-show="canShareFiles" x-cloak @click="withPicture" class="btn-dark btn-sm"><x-icon name="image" class="size-4" /> With picture</button>
                    <a href="{{ $image }}" download="{{ $filename }}" x-show="!canShareFiles" class="btn-ghost btn-sm no-underline"><x-icon name="download" class="size-4" /> Picture</a>
                @endif
                <a href="https://www.facebook.com/sharer/sharer.php?u={{ rawurlencode($url) }}" target="_blank" rel="noopener" class="btn-ghost btn-sm no-underline">Facebook</a>
                <a :href="x()" target="_blank" rel="noopener" class="btn-ghost btn-sm no-underline">X</a>
                <button type="button" @click="copy" class="btn-ghost btn-sm"><span x-show="!copied">Copy message</span><span x-cloak x-show="copied">Copied</span></button>
            </div>
            <p class="mt-2 text-xs text-ink-soft">WhatsApp opens with the message ready; you pick the group, channel or Status. For Instagram and TikTok, share the picture and paste the message as the caption.</p>
        </li>
    </ol>
</div>
