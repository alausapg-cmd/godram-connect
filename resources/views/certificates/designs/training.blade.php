@extends('certificates.designs.base')
@section('style')
    .page { background: #fbf6ec; }
    .band { position: absolute; top: 0; left: 0; width: 297mm; height: 30mm; background: #b3261e; }
    .band2 { position: absolute; top: 30mm; left: 0; width: 297mm; height: 2.2mm; background: #f0b03c; }
    .logo { position: absolute; top: 3mm; left: 14mm; width: 24mm; height: 24mm; }
    .academy { position: absolute; top: 9.5mm; left: 42mm; color: #fff; }
    .academy .a1 { font-size: 20px; font-weight: bold; letter-spacing: 3px; }
    .academy .a2 { font-size: 10px; letter-spacing: 3px; opacity: 0.85; }
    .kicker { position: absolute; top: 12mm; right: 16mm; color: #fff; font-size: 10px; letter-spacing: 4px; }
    .content { position: absolute; top: 44mm; left: 20mm; width: 190mm; }
    h1 { margin: 0; font-size: 44px; color: #16120f; }
    .rule { width: 28mm; height: 1.4mm; background: #b3261e; margin: 5mm 0; }
    .to { font-size: 13px; color: #5d544c; }
    .name { font-size: 34px; font-weight: bold; margin: 2mm 0 4mm; }
    .what { font-size: 14px; }
    .programme { font-size: 25px; font-weight: bold; color: #b3261e; margin-top: 2mm; }
    .date { font-size: 12px; color: #5d544c; margin-top: 4mm; }
    .seal { position: absolute; top: 52mm; right: 24mm; width: 52mm; height: 52mm; border-radius: 26mm; background: #b3261e; border: 2.2mm solid #f0b03c; color: #fff; text-align: center; }
    .seal .s1 { margin-top: 12mm; font-size: 10px; letter-spacing: 3px; }
    .seal .s2 { font-size: 22px; font-weight: bold; letter-spacing: 1px; margin-top: 1mm; }
    .seal .s3 { font-size: 9px; letter-spacing: 2px; margin-top: 1mm; }
    .tail1 { position: absolute; top: 98mm; right: 36mm; width: 10mm; height: 22mm; background: #8f1d17; }
    .tail2 { position: absolute; top: 98mm; right: 48mm; width: 10mm; height: 18mm; background: #b3261e; }
    .signs { position: absolute; bottom: 20mm; left: 14mm; color: #16120f; }
    .qrbox { position: absolute; bottom: 14mm; right: 20mm; color: #16120f; }
    .foot { position: absolute; bottom: 7mm; left: 20mm; font-size: 9px; color: #5d544c; }
@endsection
@section('body')
    <div class="band"></div><div class="band2"></div>
    <img src="{{ $logo }}" class="logo" alt="">
    <div class="academy"><div class="a1">GODRAM VIRTUAL ACADEMY</div><div class="a2">GOFAMINT DRAMA &amp; FILM MINISTRY</div></div>
    <div class="kicker">TRAINING COMPLETION</div>
    <div class="tail2"></div><div class="tail1"></div>
    <div class="seal"><div class="s1">GODRAM</div><div class="s2 serif">TRAINED</div><div class="s3">{{ $certificate->issued_on->format('Y') }}</div></div>
    <div class="content">
        <h1 class="serif">{{ $certificate->title }}</h1>
        <div class="rule"></div>
        <div class="to">This is to certify that</div>
        <div class="name serif">{{ $certificate->recipient_name }}</div>
        <div class="what">{{ $certificate->achievement }}</div>
        @if ($certificate->programme)<div class="programme serif">{{ $certificate->programme }}</div>@endif
        <div class="date">Awarded on {{ $certificate->issued_on->format('j F Y') }}</div>
    </div>
    <div class="signs">@include('certificates.designs._signatures')</div>
    <div class="qrbox">@include('certificates.designs._qr')</div>
    <div class="foot">{{ $certificate->issuing_authority }}</div>
@endsection
