<x-layouts.app title="Highlights">
    <section class="container-page mt-10">
        <p class="eyebrow">GODRAM in action</p>
        <h1 class="h-page mt-1">Ministry highlights</h1>
        <p class="mt-2 max-w-2xl text-ink-soft">Performances, outreaches and productions reported by Assemblies, Districts and Regions, approved and shared for everyone to see.</p>
        @if ($reports->isEmpty())
            <x-empty title="No highlights yet" class="mt-8">Approved reports chosen for publication will appear here.</x-empty>
        @else
            <div class="mt-8 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($reports as $report)
                    @include('public.partials.highlight-card', ['report' => $report])
                @endforeach
            </div>
            <div class="mt-8">{{ $reports->links() }}</div>
        @endif
    </section>
</x-layouts.app>
