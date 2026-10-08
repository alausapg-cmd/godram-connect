<x-layouts.app :title="$unit->fullName()" description="Explore the GODRAM network from National to Assembly.">
    <section class="bg-stage text-paper">
        <div class="container-page py-10">
            <nav aria-label="Breadcrumb" class="flex flex-wrap items-center gap-1 text-sm text-paper/70">
                @foreach ($ancestors as $a)
                    <a href="{{ route('network', $a) }}" class="hover:text-white">{{ $a->name }}</a><x-icon name="chevron-right" class="size-4" />
                @endforeach
                <span class="text-gold">{{ $unit->name }}</span>
            </nav>
            <p class="eyebrow mt-4 text-gold">GODRAM Network · {{ $unit->typeLabel() }}</p>
            <h1 class="mt-1 font-display text-4xl font-bold uppercase sm:text-5xl">{{ $unit->fullName() }}</h1>
            <div class="mt-5 flex flex-wrap gap-6 text-sm">
                @if ($unit->childType())<p><span class="font-display text-2xl font-semibold text-paper">{{ $children->count() }}</span> <span class="text-paper/70">{{ str(ucfirst($unit->childType()))->plural($children->count()) }}</span></p>@endif
                @if ($memberTotal !== null)<p><span class="font-display text-2xl font-semibold text-paper">{{ number_format($memberTotal) }}</span> <span class="text-paper/70">active members</span></p>@endif
                <p><span class="font-display text-2xl font-semibold text-paper">{{ $reportCount }}</span> <span class="text-paper/70">activities reported this year</span></p>
            </div>
            @if ($canSeeMembers)
                <a href="{{ route('members.index', ['unit' => $unit->id]) }}" class="btn-gold btn-sm mt-6 no-underline"><x-icon name="users" class="size-4" />View members</a>
            @endif
        </div>
    </section>

    <section class="container-page mt-10">
        @if ($children->isNotEmpty())
            <h2 class="h-section">{{ str(ucfirst($unit->childType()))->plural() }}</h2>
            <div class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($children as $child)
                    <a href="{{ route('network', $child) }}" class="card group flex items-center justify-between gap-3 p-4 no-underline hover:border-poster">
                        <div>
                            <p class="font-display text-lg font-semibold uppercase text-stage group-hover:text-curtain">{{ $child->name }}</p>
                            <p class="text-xs text-ink-soft">
                                @if ($child->children_count){{ $child->children_count }} {{ str(ucfirst($child->childType()))->plural($child->children_count) }}@endif
                                @if ($child->member_total !== null) · {{ $child->member_total }} members @endif
                            </p>
                        </div>
                        <x-icon name="chevron-right" class="size-5 text-ink-soft" />
                    </a>
                @endforeach
            </div>
        @elseif ($unit->type !== 'assembly')
            <x-empty title="Nothing here yet">No {{ str($unit->childType())->plural() }} have been added under {{ $unit->fullName() }}.</x-empty>
        @endif

        @if ($highlights->isNotEmpty())
            <h2 class="h-section mt-12">Recent highlights</h2>
            <div class="mt-4 grid gap-4 md:grid-cols-3">
                @foreach ($highlights as $r)
                    @include('public.partials.highlight-card', ['report' => $r])
                @endforeach
            </div>
        @endif
    </section>
</x-layouts.app>
