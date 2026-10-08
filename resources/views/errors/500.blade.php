<x-layouts.app title="Something went wrong on our side">
    <section class="container-page mt-16 max-w-lg text-center">
        <p class="font-display text-6xl font-bold text-poster">500</p>
        <h1 class="h-page mt-2">Something went wrong on our side</h1>
        <p class="mt-3 text-ink-soft">We have been notified. If you were submitting a report, your saved draft is still available.</p>
        <div class="mt-6 flex justify-center gap-2">
            <a href="{{ url()->previous() }}" class="btn-ghost no-underline">Go back</a>
            <a href="{{ route('home') }}" class="btn-primary no-underline">Home</a>
        </div>
    </section>
</x-layouts.app>
