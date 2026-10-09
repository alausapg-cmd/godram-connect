<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<style>
    @page { margin: 0; }
    body { margin: 0; font-family: 'DejaVu Sans', sans-serif; color: #1d1915; }
    .sheet { position: absolute; top: 0; left: 0; right: 0; bottom: 0; background: #fbf6ec; }
    .frame { position: absolute; top: 22px; left: 22px; right: 22px; bottom: 22px; border: 3px solid #16120f; }
    .inner { position: absolute; top: 32px; left: 32px; right: 32px; bottom: 32px; border: 1px solid #f0b03c; }
    .stripe { position: absolute; top: 22px; bottom: 22px; left: 22px; width: 18px; background: #b3261e; }
    .stripe2 { position: absolute; top: 22px; bottom: 22px; left: 40px; width: 6px; background: #e5622a; }
    .content { position: absolute; top: 78px; left: 110px; right: 80px; text-align: center; }
    .brand { font-size: 13px; letter-spacing: 4px; text-transform: uppercase; color: #5d544c; }
    .logo { width: 64px; height: 64px; }
    h1 { font-family: 'DejaVu Serif', serif; font-size: 42px; margin: 22px 0 6px; color: #16120f; letter-spacing: 1px; }
    .presented { font-size: 14px; color: #5d544c; margin-top: 26px; }
    .name { font-family: 'DejaVu Serif', serif; font-size: 34px; font-weight: bold; margin: 8px auto 4px; padding-bottom: 6px; border-bottom: 1px solid #f0b03c; display: inline-block; min-width: 55%; }
    .achievement { font-size: 15px; margin-top: 10px; }
    .programme { font-family: 'DejaVu Serif', serif; font-size: 22px; font-weight: bold; color: #b3261e; margin-top: 6px; }
    .date { font-size: 13px; color: #5d544c; margin-top: 10px; }
    .signs { position: absolute; bottom: 70px; left: 110px; right: 230px; }
    .sign { display: inline-block; width: 44%; margin-right: 4%; text-align: center; vertical-align: bottom; }
    .sign img { height: 50px; }
    .sign .blank { height: 50px; }
    .sign .line { border-top: 1px solid #16120f; padding-top: 4px; font-size: 12px; font-weight: bold; }
    .sign .role { font-size: 11px; color: #5d544c; }
    .qr { position: absolute; bottom: 58px; right: 70px; width: 130px; text-align: center; font-size: 9px; color: #5d544c; }
    .qr img { width: 110px; height: 110px; }
    .id { font-family: 'DejaVu Sans Mono', monospace; font-size: 10px; color: #16120f; margin-top: 2px; }
    .authority { position: absolute; bottom: 40px; left: 110px; right: 230px; font-size: 10px; color: #5d544c; text-align: center; }
</style>
</head>
<body>
<div class="sheet">
    <div class="frame"></div><div class="inner"></div><div class="stripe"></div><div class="stripe2"></div>
    <div class="content">
        <img src="{{ $logo }}" class="logo" alt="">
        <div class="brand">GOFAMINT Drama &amp; Film Ministry · GODRAM</div>
        <h1>{{ $certificate->title }}</h1>
        <div class="presented">This is to certify that</div>
        <div class="name">{{ $certificate->recipient_name }}</div>
        <div class="achievement">{{ $certificate->achievement }}</div>
        @if ($certificate->programme)<div class="programme">{{ $certificate->programme }}</div>@endif
        <div class="date">Awarded on {{ $certificate->issued_on->format('j F Y') }}</div>
    </div>
    <div class="signs">
        @foreach ($signatures as $s)
            <div class="sign">
                @if ($s['image'])<img src="{{ $s['image'] }}" alt="">@else<div class="blank"></div>@endif
                <div class="line">{{ $s['name'] ?? '' }}</div>
                <div class="role">{{ $s['title'] }}</div>
            </div>
        @endforeach
    </div>
    <div class="authority">{{ $certificate->issuing_authority }}</div>
    <div class="qr">
        <img src="{{ $qr }}" alt="">
        <div>Scan to verify</div>
        <div class="id">{{ $certificate->number }}</div>
    </div>
</div>
</body>
</html>
