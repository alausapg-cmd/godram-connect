<x-layouts.app title="We could not find that page">
    <section class="container-page mt-16 max-w-lg text-center">
        <p class="font-display text-6xl font-bold text-poster">404</p>
        <h1 class="h-page mt-2">We could not find that page</h1>
        <p class="mt-3 text-ink-soft">It may have moved, or the link may be incomplete.</p>
        <div class="mt-6 flex justify-center gap-2">
            <a href="{{ url()->previous() }}" class="btn-ghost no-underline">Go back</a>
            <a href="{{ route('home') }}" class="btn-primary no-underline">Home</a>
        </div>
    </section>
</x-layouts.app>
