<x-layouts.app title="Your session expired">
    <section class="container-page mt-16 max-w-lg text-center">
        <p class="font-display text-6xl font-bold text-poster">419</p>
        <h1 class="h-page mt-2">Your session expired</h1>
        <p class="mt-3 text-ink-soft">For your safety we sign you out after a while. Go back, refresh the page and try again. Report drafts are saved as you type.</p>
        <div class="mt-6 flex justify-center gap-2">
            <a href="{{ url()->previous() }}" class="btn-ghost no-underline">Go back</a>
            <a href="{{ route('home') }}" class="btn-primary no-underline">Home</a>
        </div>
    </section>
</x-layouts.app>
