<x-layouts.app title="My certificates">
    <section class="container-page mt-8">
        <p class="eyebrow">GODRAM Virtual Academy</p>
        <div class="mt-1 flex flex-wrap items-end justify-between gap-3">
            <h1 class="h-page">My certificates and achievements</h1>
            <a href="{{ route('exams.index') }}" class="btn-ghost no-underline">My examinations</a>
        </div>

        <h2 class="h-section mt-8">Certificates</h2>
        @if ($certificates->isEmpty())
            <x-empty title="No certificates yet" class="mt-3">Certificates are earned by passing a certification examination, or by reaching an achievement such as years of service. Opening lessons alone does not earn one.</x-empty>
        @else
            <div class="mt-3 grid gap-4 md:grid-cols-2">
                @foreach ($certificates as $c)
                    <article @class(['overflow-hidden rounded-[var(--radius-card)] border', 'border-gold/60 bg-stage text-paper' => $c->isValid(), 'border-line bg-white opacity-70' => ! $c->isValid()])>
                        <div class="poster-stripe h-1.5"></div>
                        <div class="p-5">
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <p @class(['text-xs font-semibold uppercase tracking-[0.16em]', 'text-gold' => $c->isValid()])>{{ $c->kindLabel() }}</p>
                                    <h3 class="mt-1 font-display text-xl font-semibold uppercase">{{ $c->title }}</h3>
                                    @if ($c->programme)<p @class(['text-sm', 'text-paper/75' => $c->isValid()])>{{ $c->programme }}</p>@endif
                                </div>
                                <x-icon name="award" @class(['size-10 shrink-0', 'text-gold' => $c->isValid()]) />
                            </div>
                            <p @class(['mt-3 text-xs', 'text-paper/60' => $c->isValid()])>{{ $c->number }} · issued {{ $c->issued_on->format('j F Y') }}</p>
                            @if ($c->isValid())
                                <div class="mt-4 flex flex-wrap gap-2" x-data="{ copied: false }">
                                    <a href="{{ route('certificates.download', $c) }}" class="btn-gold btn-sm no-underline"><x-icon name="download" class="size-4" /> Download</a>
                                    <a href="{{ $c->verifyUrl() }}" class="btn-sm inline-flex items-center gap-1 rounded-full border border-white/25 px-4 text-paper no-underline" target="_blank"><x-icon name="shield" class="size-4" /> Verification page</a>
                                    <button type="button" class="btn-sm inline-flex items-center gap-1 rounded-full border border-white/25 px-4 text-paper"
                                        @click="navigator.share ? navigator.share({ title: @js($c->title), text: @js('My GODRAM certificate: '.$c->title.($c->programme ? ', '.$c->programme : '')), url: @js($c->verifyUrl()) }).catch(() => {}) : (navigator.clipboard.writeText(@js($c->verifyUrl())), copied = true)">
                                        <x-icon name="share" class="size-4" /> <span x-text="copied ? 'Link copied' : 'Share'"></span></button>
                                </div>
                            @else
                                <p class="mt-3 text-sm font-semibold text-curtain">Revoked: {{ $c->revoked_reason }}</p>
                            @endif
                        </div>
                    </article>
                @endforeach
            </div>
        @endif

        <div class="mt-10 grid gap-8 lg:grid-cols-[1fr_20rem]">
            <div>
                <h2 class="h-section">Achievement timeline</h2>
                @if ($timeline->isEmpty())
                    <p class="mt-3 text-sm text-ink-soft">Your milestones will appear here: trainings completed, examinations passed, achievements and certificates.</p>
                @else
                    <ol class="relative mt-4 space-y-5 border-l-2 border-line pl-6">
                        @foreach ($timeline as $item)
                            <li class="relative">
                                <span @class(['absolute -left-[33px] top-0.5 flex size-4 items-center justify-center rounded-full ring-4 ring-paper', 'bg-gold' => in_array($item->kind, ['achievement', 'certificate']), 'bg-curtain' => $item->kind === 'exam', 'bg-poster' => $item->kind === 'training', 'bg-stage' => $item->kind === 'joined'])></span>
                                <p class="text-xs font-semibold uppercase tracking-wide text-ink-soft">{{ $item->date->format('j F Y') }}</p>
                                <p class="font-semibold">{{ $item->title }}</p>
                                @if ($item->text)<p class="text-sm text-ink-soft">{{ $item->text }}</p>@endif
                                @if ($item->certificate)<a href="{{ route('certificates.download', $item->certificate) }}" class="link text-sm">Certificate {{ $item->certificate->number }}</a>@endif
                            </li>
                        @endforeach
                    </ol>
                @endif
            </div>
            <aside>
                @if ($next->isNotEmpty())
                    <div class="card-pad">
                        <h2 class="h-section">Coming up</h2>
                        <ul class="mt-3 space-y-4">
                            @foreach ($next as $n)
                                <li>
                                    <p class="text-sm font-semibold">{{ $n->rule->name }}</p>
                                    <div class="mt-1 flex items-center gap-2 text-xs text-ink-soft">
                                        <div class="h-2 flex-1 rounded-full bg-paper-2"><div class="h-full rounded-full bg-gold" style="width: {{ min(100, round($n->value / max(1, $n->rule->threshold) * 100)) }}%"></div></div>
                                        <span>{{ $n->value }} / {{ $n->rule->threshold }}</span>
                                    </div>
                                    <p class="mt-0.5 text-xs text-ink-soft">{{ $n->rule->metricLabel() }}</p>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif
                <p class="mt-4 text-sm text-ink-soft">Anyone can check a GODRAM certificate at <a href="{{ route('certificates.lookup') }}" class="link">{{ str_replace(['https://', 'http://'], '', route('certificates.lookup')) }}</a>.</p>
            </aside>
        </div>
    </section>
</x-layouts.app>
