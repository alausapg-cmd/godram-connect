<x-layouts.app title="Verify a certificate" description="Check that a GODRAM certificate is genuine.">
    <section class="container-page mt-8 max-w-2xl">
        <p class="eyebrow">GODRAM certificates</p>
        <h1 class="h-page mt-1">Verify a certificate</h1>

        @if ($number)
            @if ($certificate && $certificate->isValid())
                <div class="mt-6 overflow-hidden rounded-[var(--radius-card)] border-2 border-ok bg-white">
                    <div class="flex items-center gap-3 bg-ok px-5 py-3 text-white"><x-icon name="check-circle" class="size-6" /><p class="font-semibold">This is a genuine GODRAM certificate.</p></div>
                    <dl class="grid gap-4 p-5 sm:grid-cols-2">
                        <div><dt class="text-xs font-semibold uppercase text-ink-soft">Certificate ID</dt><dd class="font-mono font-semibold">{{ $certificate->number }}</dd></div>
                        <div><dt class="text-xs font-semibold uppercase text-ink-soft">Awarded to</dt><dd class="font-semibold">{{ $certificate->publicName() }}</dd></div>
                        <div><dt class="text-xs font-semibold uppercase text-ink-soft">Certificate</dt><dd>{{ $certificate->title }}</dd></div>
                        <div><dt class="text-xs font-semibold uppercase text-ink-soft">For</dt><dd>{{ $certificate->programme ?? $certificate->kindLabel() }}</dd></div>
                        <div><dt class="text-xs font-semibold uppercase text-ink-soft">Date</dt><dd>{{ $certificate->issued_on->format('j F Y') }}</dd></div>
                        <div><dt class="text-xs font-semibold uppercase text-ink-soft">Issued by</dt><dd>{{ $certificate->issuing_authority }}</dd></div>
                    </dl>
                    <p class="border-t border-line px-5 py-3 text-xs text-ink-soft">To protect members' privacy, only these details are shown. The printed certificate should show the same ID, name and date.</p>
                </div>
            @elseif ($certificate)
                <div class="mt-6 rounded-[var(--radius-card)] border-2 border-curtain bg-white p-5">
                    <p class="flex items-center gap-2 font-semibold text-curtain"><x-icon name="x" class="size-5" /> Certificate {{ $certificate->number }} has been revoked.</p>
                    <p class="mt-2 text-sm">It is no longer valid. If you were shown this certificate, please contact the GODRAM national office.</p>
                </div>
            @else
                <div class="mt-6 rounded-[var(--radius-card)] border-2 border-curtain bg-white p-5">
                    <p class="flex items-center gap-2 font-semibold text-curtain"><x-icon name="alert" class="size-5" /> We have no certificate with the ID {{ $number }}.</p>
                    <p class="mt-2 text-sm">Check the ID on the certificate, letter by letter. It looks like GODRAM-CERT-2026-000184.</p>
                </div>
            @endif
        @endif

        <form method="GET" action="{{ route('certificates.lookup') }}" class="card-pad mt-6">
            <label for="number" class="label">{{ $number ? 'Check another certificate' : 'Certificate ID' }}</label>
            <div class="flex gap-2"><input id="number" name="number" class="input font-mono uppercase" placeholder="GODRAM-CERT-2026-000184" required autocomplete="off"><button class="btn-primary shrink-0">Check</button></div>
            <p class="hint">You can also scan the QR code on the certificate with your phone's camera.</p>
        </form>
    </section>
</x-layouts.app>
