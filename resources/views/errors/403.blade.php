<x-layouts.app title="This page is not for your role">
    <section class="container-page mt-16 max-w-lg text-center">
        <p class="font-display text-6xl font-bold text-poster">403</p>
        <h1 class="h-page mt-2">This page is not for your role</h1>
        <p class="mt-3 text-ink-soft">You do not have permission to see this. If you think you should, speak to your coordinator.</p>
        <div class="mt-6 flex justify-center gap-2">
            <a href="{{ url()->previous() }}" class="btn-ghost no-underline">Go back</a>
            <a href="{{ route('home') }}" class="btn-primary no-underline">Home</a>
        </div>
    </section>
</x-layouts.app>
