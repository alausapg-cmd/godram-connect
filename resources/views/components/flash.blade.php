@if (session('status'))
    <div class="container-page mt-4" role="status">
        <div class="flex items-start gap-3 rounded-xl border border-ok/30 bg-ok/10 px-4 py-3 text-sm text-ok">
            <x-icon name="check" class="mt-0.5 size-4 shrink-0" />
            <p>{{ session('status') }}</p>
        </div>
    </div>
@endif
@if (session('temporary_password'))
    <div class="container-page mt-4" role="alert">
        <div class="rounded-xl border border-gold bg-gold/15 px-4 py-3 text-sm text-ink">
            <p class="font-semibold">Temporary password: <span class="font-mono text-base tracking-wider">{{ session('temporary_password') }}</span></p>
            <p class="mt-1 text-ink-soft">Give this to the member privately. It is shown only once, and they will be asked to choose their own password when they sign in.</p>
        </div>
    </div>
@endif
@if (isset($errors) && $errors->any() && ! ($hideErrors ?? false))
    <div class="container-page mt-4" role="alert">
        <div class="flex items-start gap-3 rounded-xl border border-curtain/30 bg-curtain/5 px-4 py-3 text-sm text-curtain">
            <x-icon name="alert" class="mt-0.5 size-4 shrink-0" />
            <div>
                <p class="font-semibold">Please check the form.</p>
                <ul class="mt-1 list-disc pl-4">
                    @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
                </ul>
            </div>
        </div>
    </div>
@endif
