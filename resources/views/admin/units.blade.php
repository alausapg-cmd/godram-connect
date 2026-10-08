@php
$renderUnit = function ($unit) use (&$renderUnit) {
    return view('admin.partials.unit', ['unit' => $unit, 'render' => $renderUnit])->render();
};
@endphp
<x-layouts.app title="Structure">
    <section class="container-page mt-8 max-w-4xl">
        <p class="eyebrow">Administration</p>
        <h1 class="h-page">GODRAM structure</h1>
        <p class="mt-1 text-ink-soft">National, Regions, Districts and Assemblies. Members belong to an Assembly; every higher level is a view over the same members.</p>
        @if ($root)
            <div class="card mt-6 p-4">{!! $renderUnit($root) !!}</div>
        @else
            <x-empty title="No structure yet" class="mt-6">Run the installer to create the National unit.</x-empty>
        @endif
    </section>
</x-layouts.app>
