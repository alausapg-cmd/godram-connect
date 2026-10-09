<div class="grid grid-cols-6 gap-2 sm:grid-cols-8 lg:grid-cols-5">
    <template x-for="(item, n) in questions" :key="item.position">
        <button type="button" @click="go(n)" class="relative flex aspect-square min-h-11 items-center justify-center rounded-xl border-2 text-sm font-bold"
            :class="{ 'ring-2 ring-curtain ring-offset-2': n === i, 'border-stage bg-stage text-paper': isAnswered(item), 'border-line bg-white text-ink': !isAnswered(item) }"
            :aria-label="'Question ' + (n + 1) + (isAnswered(item) ? ', answered' : ', not answered') + (item.flagged ? ', flagged' : '') + (n === i ? ', current' : '')"
            :aria-current="n === i ? 'step' : null">
            <span x-text="n + 1"></span>
            <span x-show="item.flagged" class="absolute -right-1.5 -top-1.5 flex size-5 items-center justify-center rounded-full bg-gold text-stage"><x-icon name="flag" class="size-3" /></span>
        </button>
    </template>
</div>
<ul class="mt-4 space-y-1.5 text-xs text-ink-soft">
    <li class="flex items-center gap-2"><span class="size-4 rounded border-2 border-stage bg-stage"></span> <span>Answered (<span x-text="answeredCount"></span>)</span></li>
    <li class="flex items-center gap-2"><span class="size-4 rounded border-2 border-line bg-white"></span> <span>Not answered (<span x-text="unansweredCount"></span>)</span></li>
    <li class="flex items-center gap-2"><span class="flex size-4 items-center justify-center rounded-full bg-gold"><x-icon name="flag" class="size-2.5" /></span> <span>Flagged (<span x-text="flaggedCount"></span>)</span></li>
    <li class="flex items-center gap-2"><span class="size-4 rounded ring-2 ring-curtain ring-offset-1"></span> Current question</li>
</ul>
<button type="button" @click="confirming = true; palette = false" class="btn-primary mt-5 w-full">Finish the examination</button>
