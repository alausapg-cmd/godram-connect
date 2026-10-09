@php $shareUrl = route('events.show', $event); @endphp
<x-layouts.app :title="$event->title" :description="\Illuminate\Support\Str::limit($event->description, 160)"
               :image="$event->visibility === 'public' ? route('share.card', ['event', $event->slug]) : null">
    <section class="relative isolate overflow-hidden bg-stage text-paper">
        @if ($event->coverUrl())
            <img src="{{ $event->coverUrl() }}" alt="" class="absolute inset-0 -z-10 size-full object-cover">
        @endif
        <div class="hero-shade absolute inset-0 -z-10" aria-hidden="true"></div>
        <div class="poster-stripe absolute inset-x-0 bottom-0 h-1.5" aria-hidden="true"></div>
        <div class="container-page relative py-10 sm:py-14">
            <a href="{{ route('events') }}" class="text-sm font-semibold text-gold no-underline">Events</a>
            <p class="eyebrow mt-4 flex flex-wrap items-center gap-2 text-gold">{{ $event->typeLabel() }}
                @if ($event->isLive())<span class="badge bg-curtain text-white"><span class="size-1.5 animate-pulse rounded-full bg-white"></span> Live now</span>@endif
                @if ($event->visibility === 'members')<span class="badge bg-white/10 text-paper">Members only</span>@endif
            </p>
            <h1 class="mt-2 max-w-3xl font-display text-4xl font-semibold uppercase leading-tight sm:text-5xl">{{ $event->title }}</h1>
            <dl class="mt-6 grid max-w-3xl gap-4 text-sm sm:grid-cols-3">
                <div><dt class="text-paper/60">When</dt><dd class="font-semibold">{{ $event->starts_at->format('l j F Y') }}<br>{{ $event->starts_at->format('g:ia') }}@if ($event->ends_at) to {{ $event->ends_at->isSameDay($event->starts_at) ? $event->ends_at->format('g:ia') : $event->ends_at->format('j M, g:ia') }}@endif</dd></div>
                <div><dt class="text-paper/60">Where</dt><dd class="font-semibold">{{ $event->is_online ? 'Online' : $event->location }}@if ($event->address)<br><span class="font-normal text-paper/80">{{ $event->address }}</span>@endif @if ($event->stream_url && ! $event->is_online)<br><span class="font-normal text-paper/80">Also streamed on {{ $event->platformLabel() }}</span>@endif</dd></div>
                <div><dt class="text-paper/60">Organised by</dt><dd class="font-semibold">{{ $event->organiserName() }}</dd></div>
            </dl>
            <div class="mt-6 flex flex-wrap items-center gap-2">
                <a href="{{ route('events.calendar', $event) }}" class="btn-gold btn-sm no-underline"><x-icon name="calendar" class="size-4" /> Add to calendar</a>
                <a href="{{ $event->googleCalendarUrl() }}" target="_blank" rel="noopener" class="btn-sm inline-flex min-h-9 items-center rounded-full bg-white/10 px-4 text-xs font-semibold text-paper no-underline hover:bg-white/20">Google Calendar</a>
                <x-share :url="$shareUrl" :title="$event->title" :text="$event->starts_at->format('D j M').' ·'" dark class="ml-auto" />
            </div>
        </div>
    </section>

    @if ($event->isCancelled())
        <div class="container-page mt-6"><div class="rounded-2xl border border-curtain/30 bg-curtain/5 p-4 text-curtain" role="alert"><p class="font-semibold">This event has been cancelled.</p><p class="mt-1 text-sm">{{ $event->cancel_reason }}</p></div></div>
    @endif

    <section class="container-page mt-8 grid gap-8 lg:grid-cols-[1fr_340px]">
        <div class="space-y-8">
            @if ($event->stream_url && ! $event->isCancelled())
                <div>
                    @if ($event->isPast() && $event->replay_youtube_id)
                        <h2 class="h-section">Watch the replay</h2>
                        <x-youtube :id="$event->replay_youtube_id" :title="$event->title" class="mt-3" />
                    @elseif ($event->isLive() && $event->streamYoutubeId())
                        <h2 class="h-section flex items-center gap-2"><span class="size-2.5 animate-pulse rounded-full bg-curtain"></span> Live now</h2>
                        <x-youtube :id="$event->streamYoutubeId()" :title="$event->title" class="mt-3" />
                    @elseif (! $event->isPast())
                        <div class="card-pad flex flex-wrap items-center justify-between gap-4 {{ $event->isLive() ? 'border-curtain' : '' }}">
                            <div>
                                <p class="font-semibold">{{ $event->isLive() ? 'The broadcast has started' : 'This event will be streamed live' }}</p>
                                <p class="text-sm text-ink-soft">On {{ $event->platformLabel() }}{{ $event->isLive() ? '' : ', from '.$event->starts_at->format('g:ia \o\n D j M') }}.</p>
                            </div>
                            <a href="{{ $event->stream_url }}" target="_blank" rel="noopener" class="{{ $event->isLive() ? 'btn-primary' : 'btn-ghost' }} no-underline"><x-icon name="live" class="size-4" /> {{ $event->isLive() ? 'Watch live' : 'Open the stream page' }}</a>
                        </div>
                    @endif
                </div>
            @endif

            <div class="card-pad">
                <h2 class="h-section">About this event</h2>
                <div class="mt-3 space-y-3 whitespace-pre-line leading-relaxed">{{ $event->description }}</div>
            </div>

            @if ($event->people->isNotEmpty())
                <div class="card-pad">
                    <h2 class="h-section">Who is taking part</h2>
                    <ul class="mt-4 grid gap-3 sm:grid-cols-2">
                        @foreach ($event->people as $person)
                            <li class="flex items-center gap-3">
                                <span class="flex size-10 shrink-0 items-center justify-center rounded-full bg-stage font-display text-sm font-semibold text-gold">{{ collect(explode(' ', $person->name))->map(fn ($p) => mb_substr($p, 0, 1))->take(2)->implode('') }}</span>
                                <span><span class="block font-semibold">{{ $person->name }}</span><span class="text-sm text-ink-soft">{{ $person->roleLabel() }}</span></span>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @if ($event->videos->isNotEmpty())
                <div>
                    <h2 class="h-section">Videos from this event</h2>
                    <div class="mt-4 grid gap-5 sm:grid-cols-2">@foreach ($event->videos as $video)<x-video-card :video="$video" />@endforeach</div>
                </div>
            @endif
        </div>

        <aside class="space-y-6">
            @if ($event->registration_open || $registration)
                <div class="card-pad" id="register">
                    <h2 class="h-section flex items-center gap-2"><x-icon name="ticket" class="size-5 text-poster" /> Register</h2>
                    @if ($registration)
                        <p class="mt-3 rounded-xl bg-ok/10 p-3 text-sm text-ok"><span class="font-semibold">You are registered.</span><br>Reference {{ $registration->reference }}</p>
                        @unless ($event->isPast())
                            <form method="POST" action="{{ route('events.unregister', $event) }}" class="mt-3">@csrf @method('DELETE')<button class="link text-sm">I can no longer come</button></form>
                        @endunless
                    @elseif ($event->acceptsRegistrations())
                        @if ($event->placesLeft() !== null)<p class="mt-2 text-sm text-ink-soft">{{ $event->placesLeft() }} {{ \Illuminate\Support\Str::plural('place', $event->placesLeft()) }} left</p>@endif
                        @error('registration')<p class="error">{{ $message }}</p>@enderror
                        <form method="POST" action="{{ route('events.register', $event) }}" class="mt-3 space-y-3">
                            @csrf
                            @auth
                                <button class="btn-primary w-full">Reserve my place</button>
                            @else
                                <div><label for="name" class="label">Full name</label><input id="name" name="name" value="{{ old('name') }}" class="input" required autocomplete="name">@error('name')<p class="error">{{ $message }}</p>@enderror</div>
                                <div><label for="phone" class="label">Phone number</label><input id="phone" name="phone" value="{{ old('phone') }}" class="input" required inputmode="tel" autocomplete="tel">@error('phone')<p class="error">{{ $message }}</p>@enderror</div>
                                <div><label for="email" class="label">Email <span class="font-normal text-ink-soft">(optional)</span></label><input id="email" type="email" name="email" value="{{ old('email') }}" class="input" autocomplete="email"></div>
                                <button class="btn-primary w-full">Reserve my place</button>
                                <p class="text-center text-xs text-ink-soft">GODRAM member? <a href="{{ route('login') }}" class="link">Sign in</a> to register in one tap.</p>
                            @endauth
                        </form>
                        @if ($event->registration_closes_at)<p class="mt-3 text-xs text-ink-soft">Registration closes {{ $event->registration_closes_at->format('D j M, g:ia') }}.</p>@endif
                    @else
                        <p class="mt-2 text-sm text-ink-soft">Registration is closed.</p>
                    @endif
                </div>
            @endif

            @if ($canManage)
                <div class="card-pad space-y-3">
                    <h2 class="h-section">Organiser</h2>
                    <p class="text-sm text-ink-soft">{{ $registeredCount }} registered</p>
                    <div class="flex flex-wrap gap-2">
                        <a href="{{ route('events.edit', $event) }}" class="btn-ghost btn-sm no-underline"><x-icon name="edit" class="size-4" /> Edit</a>
                        <a href="{{ route('events.registrations', $event) }}" class="btn-ghost btn-sm no-underline"><x-icon name="users" class="size-4" /> Registrations</a>
                    </div>
                    @unless ($event->isCancelled() || $event->isPast())
                        <details class="text-sm">
                            <summary class="cursor-pointer font-semibold text-curtain">Cancel this event</summary>
                            <form method="POST" action="{{ route('events.cancel', $event) }}" class="mt-2 space-y-2">@csrf
                                <textarea name="cancel_reason" rows="2" class="input" placeholder="Why is it cancelled? People will see this." required></textarea>
                                @error('cancel_reason')<p class="error">{{ $message }}</p>@enderror
                                <button class="btn-primary btn-sm">Cancel the event</button>
                            </form>
                        </details>
                    @endunless
                </div>
                @unless ($event->isCancelled() || $event->isPast())
                    @php
                        $where = $event->is_online ? 'Online'.($event->platformLabel() ? ' on '.$event->platformLabel() : '') : $event->location;
                        $invite = '*'.$event->title.'*'.PHP_EOL.'🗓 '.$event->starts_at->format('l j F Y, g:ia').PHP_EOL.'📍 '.$where.PHP_EOL.PHP_EOL.\Illuminate\Support\Str::limit((string) $event->description, 200).PHP_EOL.PHP_EOL.($event->registration_open ? 'Register here: ' : 'Details: ').$shareUrl;
                    @endphp
                    <x-share-kit :url="$shareUrl" :message="$invite" filename="godram-event.png"
                        :image="$event->visibility === 'public' ? route('share.card', ['event', $event->slug]) : null" />
                @endunless
            @endif

            @if ($event->production)
                <a href="{{ route('showcase.show', $event->production) }}" class="card block overflow-hidden no-underline">
                    @if ($event->production->coverUrl())<img src="{{ $event->production->coverUrl() }}" alt="" class="aspect-[4/3] w-full object-cover" loading="lazy">@endif
                    <div class="p-4"><p class="eyebrow">The production</p><p class="mt-1 font-display text-lg font-semibold uppercase text-stage">{{ $event->production->title }}</p></div>
                </a>
            @endif

            @if ($related->isNotEmpty())
                <div>
                    <h2 class="h-section">Also coming up</h2>
                    <div class="mt-3 space-y-3">@foreach ($related as $r)<x-event-card :event="$r" />@endforeach</div>
                </div>
            @endif
        </aside>
    </section>
</x-layouts.app>
