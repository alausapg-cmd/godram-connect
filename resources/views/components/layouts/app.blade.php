@props(['title' => null, 'description' => null, 'image' => null, 'hideErrors' => false, 'dark' => false])
@php
    $user = auth()->user();
    $workspace = $user ? array_filter([
        ['Dashboard', 'dashboard', 'home', true],
        ['Members', 'members.index', 'users', can_do('members.view')],
        ['Reports', 'reports.index', 'report', can_do('reports.view') || can_do('reports.create')],
        ['Announcements', 'announcements.manage', 'megaphone', can_do('announcements.create') || can_do('announcements.publish')],
        ['Videos', 'videos.manage', 'play', can_do('media.submit')],
        ['Stories', can_do('stories.review') ? 'stories.manage' : 'stories.mine', 'quote', true],
        ['My learning', 'academy.mine', 'academy', (bool) $user?->member_id, 'academy.mine'],
        ...(can_do('training.manage') || ($user?->member_id && \App\Models\CourseFacilitator::where('member_id', $user->member_id)->exists())
            ? [['Training', 'academy.manage.index', 'list', true, 'academy.manage.*']]
            : [['Training', 'academy.overview', 'list', can_do('training.view'), 'academy.overview']]),
        ['Transfers', 'transfers.index', 'transfer', can_do('members.transfer.approve') || can_do('members.transfer.request')],
        ['Structure', 'admin.units.index', 'network', can_do('org.manage')],
        ['Roles', 'admin.roles.index', 'shield', can_do('roles.manage')],
        ['Audit log', 'admin.audit.index', 'history', can_do('audit.view')],
    ], fn ($i) => $i[3]) : [];
    $workspace = array_map(fn ($i) => [$i[0], $i[1], $i[2], $i[4] ?? explode('.', $i[1])[0].'*'], $workspace);
    $public = [
        ['Home', 'home'], ['About', 'about'], ['Watch', 'watch'], ['Events', 'events'], ['Stories', 'stories'],
        ['Showcase', 'showcase'], ['Academy', 'academy'], ['News', 'announcements.index'],
    ];
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ? $title.' · ' : '' }}GODRAM CONNECT</title>
    <meta name="description" content="{{ $description ?? 'The digital home of the GOFAMINT Drama & Film Ministry: connect, report, create, train, watch and celebrate.' }}">
    <meta name="theme-color" content="#16120f">
    <meta property="og:site_name" content="GODRAM CONNECT">
    <meta property="og:type" content="website">
    <meta property="og:url" content="{{ url()->current() }}">
    <meta property="og:title" content="{{ $title ?? 'GODRAM CONNECT' }}">
    <meta property="og:description" content="{{ $description ?? 'GODRAM is alive. GODRAM is creative. GODRAM is connected.' }}">
    <meta property="og:image" content="{{ $image ?? asset('images/share-default.png') }}">
    <meta name="twitter:card" content="summary_large_image">
    <link rel="manifest" href="/manifest.webmanifest">
    <link rel="icon" href="/icons/icon.svg" type="image/svg+xml">
    <link rel="apple-touch-icon" href="/icons/icon-192.png">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen pb-20 lg:pb-0 {{ $dark ? 'bg-stage' : '' }}">
<a href="#main" class="sr-only focus:not-sr-only focus:fixed focus:left-3 focus:top-3 focus:z-50 focus:rounded-lg focus:bg-white focus:px-4 focus:py-2">Skip to content</a>

@if (config('godram.demo_mode'))
    <div class="bg-gold px-4 py-1.5 text-center text-xs font-semibold text-stage">Preview: the members, reports and announcements here are sample data.</div>
@endif

<header class="bg-stage text-paper">
    <div class="container-page flex h-16 items-center justify-between gap-4">
        <a href="{{ route('home') }}" class="flex items-center gap-2.5 no-underline" aria-label="GODRAM CONNECT home">
            <span class="poster-stripe block h-8 w-1.5 rounded-full" aria-hidden="true"></span>
            <span class="font-display text-2xl font-bold uppercase leading-none tracking-wide text-paper">Godram</span>
            <span class="mt-1 font-display text-xs font-medium uppercase leading-none tracking-[0.3em] text-gold">Connect</span>
        </a>
        <nav class="hidden items-center gap-1 lg:flex" aria-label="Main">
            @foreach ($public as [$label, $route])
                <a href="{{ route($route) }}" @class(['rounded-full px-3 py-2 text-sm font-medium no-underline transition-colors', 'bg-white/10 text-white' => request()->routeIs($route.'*'), 'text-paper/75 hover:text-white' => ! request()->routeIs($route.'*')])>{{ $label }}</a>
            @endforeach
        </nav>
        <div class="flex items-center gap-2">
            <a href="{{ route('search') }}" class="rounded-full p-2 text-paper/75 hover:text-white" title="Search"><x-icon name="search" /><span class="sr-only">Search</span></a>
            @auth
                <a href="{{ route('dashboard') }}" class="hidden items-center gap-2 rounded-full bg-white/10 py-1 pl-1 pr-3 text-sm font-medium text-paper no-underline hover:bg-white/15 sm:flex">
                    @if ($user->member)<x-avatar :member="$user->member" size="size-8" />@endif
                    <span>My GODRAM</span>
                </a>
                <form method="POST" action="{{ route('logout') }}" class="hidden sm:block">@csrf
                    <button class="rounded-full p-2 text-paper/70 hover:text-white" title="Sign out"><x-icon name="logout" /><span class="sr-only">Sign out</span></button>
                </form>
            @else
                <a href="{{ route('login') }}" class="hidden rounded-full px-4 py-2 text-sm font-semibold text-paper no-underline hover:bg-white/10 sm:inline-flex">Sign in</a>
                <a href="{{ route('register') }}" class="btn-primary btn-sm no-underline">Join GODRAM</a>
            @endauth
        </div>
    </div>
    @if ($workspace)
        <nav class="border-t border-white/10 bg-stage-2" aria-label="Workspace">
            <div class="container-page flex gap-1 overflow-x-auto py-1.5 [scrollbar-width:none]">
                @foreach ($workspace as [$label, $route, $icon, $match])
                    @php $on = request()->routeIs($match) && ! request()->routeIs('announcements.index', 'announcements.show', 'academy', 'academy.show', 'academy.lesson'); @endphp
                    <a href="{{ route($route) }}" @class(['flex shrink-0 items-center gap-1.5 rounded-full px-3 py-1.5 text-sm no-underline', 'bg-gold text-stage font-semibold' => $on, 'text-paper/80 hover:text-white' => ! $on])>
                        <x-icon :name="$icon" class="size-4" />{{ $label }}
                    </a>
                @endforeach
            </div>
        </nav>
    @endif
</header>

<x-flash :hide-errors="$hideErrors" />

<main id="main">
    {{ $slot }}
</main>

<footer class="mt-16 bg-stage text-paper/70">
    <div class="container-page grid gap-8 py-10 sm:grid-cols-3">
        <div>
            <p class="font-display text-xl font-bold uppercase text-paper">Godram <span class="text-gold">Connect</span></p>
            <p class="mt-2 text-sm">The digital home of the GOFAMINT Drama &amp; Film Ministry.</p>
            <p class="mt-3 font-display text-sm uppercase tracking-wider text-gold">The Stage. The Story. The Mission.</p>
        </div>
        <div class="text-sm">
            <p class="font-semibold text-paper">Explore</p>
            <ul class="mt-2 space-y-1.5">
                <li><a class="hover:text-white" href="{{ route('about') }}">About GODRAM</a></li>
                <li><a class="hover:text-white" href="{{ route('network') }}">The GODRAM network</a></li>
                <li><a class="hover:text-white" href="{{ route('highlights') }}">Ministry highlights</a></li>
                <li><a class="hover:text-white" href="{{ route('archive') }}">From the archive</a></li>
                <li><a class="hover:text-white" href="{{ route('search') }}">Search everything</a></li>
            </ul>
        </div>
        <div class="text-sm">
            <p class="font-semibold text-paper">Follow</p>
            <ul class="mt-2 space-y-1.5">
                <li><a class="hover:text-white" href="{{ config('godram.links.youtube') }}" rel="noopener" target="_blank">GODRAM TV on YouTube</a></li>
                <li><a class="hover:text-white" href="{{ config('godram.links.facebook') }}" rel="noopener" target="_blank">GODRAM on Facebook</a></li>
            </ul>
            <button type="button" data-install class="btn-gold btn-sm mt-4 hidden">Install the app</button>
        </div>
    </div>
    <p class="border-t border-white/10 py-4 text-center text-xs">&copy; {{ date('Y') }} GOFAMINT Drama &amp; Film Ministry</p>
</footer>

{{-- Mobile bottom navigation --}}
<nav x-data="{ more: false }" class="fixed inset-x-0 bottom-0 z-40 border-t border-white/10 bg-stage pb-[env(safe-area-inset-bottom)] lg:hidden" aria-label="Mobile">
    <div class="grid grid-cols-5">
        @foreach ([['Home', 'home', 'home'], ['Watch', 'watch', 'play'], ['Academy', 'academy', 'academy'], ['Events', 'events', 'calendar']] as [$label, $route, $icon])
            <a href="{{ route($route) }}" @class(['flex flex-col items-center gap-0.5 py-2 text-[11px] font-medium no-underline', 'text-gold' => request()->routeIs($route), 'text-paper/70' => ! request()->routeIs($route)])>
                <x-icon :name="$icon" class="size-6" />{{ $label }}
            </a>
        @endforeach
        <button type="button" @click="more = true" class="flex flex-col items-center gap-0.5 py-2 text-[11px] font-medium text-paper/70">
            <x-icon :name="$user ? 'user' : 'menu'" class="size-6" />{{ $user ? 'Me' : 'More' }}
        </button>
    </div>
    <div x-cloak x-show="more" x-transition.opacity class="fixed inset-0 z-50 bg-black/50" @click="more = false"></div>
    <div x-cloak x-show="more" x-transition class="fixed inset-x-0 bottom-0 z-50 max-h-[80vh] overflow-y-auto rounded-t-3xl bg-paper p-5 pb-[calc(1.25rem+env(safe-area-inset-bottom))]" @keydown.escape.window="more = false">
        <div class="mx-auto mb-4 h-1.5 w-12 rounded-full bg-line"></div>
        @auth
            <a href="{{ route('dashboard') }}" class="mb-3 flex items-center gap-3 rounded-2xl bg-stage p-3 text-paper no-underline">
                @if ($user->member)<x-avatar :member="$user->member" />@endif
                <span><span class="block font-semibold">{{ $user->name }}</span><span class="block text-xs text-paper/70">{{ $user->primaryTitle() }}</span></span>
            </a>
            <div class="grid grid-cols-2 gap-2">
                @foreach ($workspace as [$label, $route, $icon, $match])
                    <a href="{{ route($route) }}" class="card flex items-center gap-2 p-3 text-sm font-medium no-underline"><x-icon :name="$icon" class="size-5 text-poster" />{{ $label }}</a>
                @endforeach
            </div>
        @endauth
        <div class="mt-4 grid grid-cols-2 gap-2">
            @foreach ([['Stories', 'stories'], ['Showcase', 'showcase'], ['News', 'announcements.index'], ['About GODRAM', 'about'], ['Network', 'network'], ['Archive', 'archive'], ['Highlights', 'highlights'], ['Search', 'search']] as [$label, $route])
                <a href="{{ route($route) }}" class="rounded-xl border border-line bg-white p-3 text-sm font-medium no-underline">{{ $label }}</a>
            @endforeach
        </div>
        <div class="mt-4">
            @auth
                <form method="POST" action="{{ route('logout') }}">@csrf<button class="btn-ghost w-full">Sign out</button></form>
            @else
                <div class="grid grid-cols-2 gap-2">
                    <a href="{{ route('login') }}" class="btn-ghost no-underline">Sign in</a>
                    <a href="{{ route('register') }}" class="btn-primary no-underline">Join GODRAM</a>
                </div>
            @endauth
            <button type="button" data-install class="btn-gold mt-2 hidden w-full">Install the app</button>
        </div>
    </div>
</nav>
</body>
</html>
