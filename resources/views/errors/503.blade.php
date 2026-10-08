<x-layouts.app title="GODRAM CONNECT is being updated">
    <section class="container-page mt-16 max-w-lg text-center">
        <p class="font-display text-6xl font-bold text-poster">503</p>
        <h1 class="h-page mt-2">GODRAM CONNECT is being updated</h1>
        <p class="mt-3 text-ink-soft">We will be back in a few minutes.</p>
        <div class="mt-6 flex justify-center gap-2">
            <a href="{{ url()->previous() }}" class="btn-ghost no-underline">Go back</a>
            <a href="{{ route('home') }}" class="btn-primary no-underline">Home</a>
        </div>
    </section>
</x-layouts.app>
