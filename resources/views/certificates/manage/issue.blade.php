<x-layouts.app title="Special recognition">
    <section class="container-page mt-8 max-w-2xl">
        <a href="{{ route('certificates.manage.index') }}" class="text-sm font-semibold text-curtain">Certificates</a>
        <h1 class="h-page mt-2">Special recognition</h1>
        <p class="mt-1 text-sm text-ink-soft">For service, leadership or excellence that no automatic rule covers. The reason is kept with the certificate and in the audit log.</p>
        <form method="POST" action="{{ route('certificates.manage.store') }}" class="card-pad mt-6 space-y-5">@csrf
            <div><label for="member_no" class="label">Member ID</label><input id="member_no" name="member_no" value="{{ old('member_no') }}" class="input font-mono uppercase" required placeholder="GDM-000123">@error('member_no')<p class="error">{{ $message }}</p>@enderror</div>
            <div><label for="title" class="label">Certificate title</label><input id="title" name="title" value="{{ old('title', 'Certificate of Recognition') }}" class="input" required maxlength="120"></div>
            <div><label for="achievement" class="label">Wording</label><input id="achievement" name="achievement" value="{{ old('achievement', 'is recognised for outstanding service to the drama ministry') }}" class="input" required maxlength="300"><p class="hint">Reads after the name: "This is to certify that [name] …"</p></div>
            <div><label for="programme" class="label">Programme or occasion <span class="font-normal text-ink-soft">(optional)</span></label><input id="programme" name="programme" value="{{ old('programme') }}" class="input" maxlength="150" placeholder="GODRAM National Convention 2026"></div>
            <div><label for="reason" class="label">Why it is being issued</label><textarea id="reason" name="reason" rows="3" class="input py-2" required maxlength="500">{{ old('reason') }}</textarea>@error('reason')<p class="error">{{ $message }}</p>@enderror</div>
            <button class="btn-primary">Issue certificate</button>
        </form>
    </section>
</x-layouts.app>
