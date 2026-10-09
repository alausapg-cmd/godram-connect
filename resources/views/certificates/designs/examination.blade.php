@extends('certificates.designs.base')
@section('style')
    .page { background: #ffffff; }
    .f1 { position: absolute; top: 9mm; left: 9mm; right: 9mm; bottom: 9mm; background: #16120f; }
    .f1b { position: absolute; top: 10.6mm; left: 10.6mm; right: 10.6mm; bottom: 10.6mm; background: #ffffff; }
    .f2 { position: absolute; top: 13.5mm; left: 13.5mm; right: 13.5mm; bottom: 13.5mm; background: #e5622a; }
    .f2b { position: absolute; top: 13.9mm; left: 13.9mm; right: 13.9mm; bottom: 13.9mm; background: #ffffff; }
    .c { position: absolute; width: 9mm; height: 9mm; background: #e5622a; }
    .content { position: absolute; top: 24mm; left: 30mm; width: 237mm; text-align: center; }
    .logo { width: 22mm; height: 22mm; }
    .org { font-size: 10px; letter-spacing: 5px; color: #5d544c; margin-top: 2mm; }
    .kicker { display: inline-block; margin-top: 6mm; padding: 1.4mm 5mm; background: #16120f; color: #f0b03c; font-size: 10px; letter-spacing: 4px; }
    h1 { margin: 4mm 0 0; font-size: 40px; color: #16120f; letter-spacing: 1px; }
    .to { font-size: 13px; color: #5d544c; margin-top: 6mm; }
    .name { font-size: 34px; font-weight: bold; margin: 2mm auto 0; display: inline-block; padding: 0 10mm 2mm; border-bottom: 0.5mm solid #e5622a; }
    .what { font-size: 14px; margin-top: 4mm; }
    .programme { font-size: 22px; font-weight: bold; color: #16120f; margin-top: 2mm; }
    .date { font-size: 12px; color: #5d544c; margin-top: 3mm; }
    .stamp { position: absolute; top: 26mm; right: 26mm; width: 36mm; height: 36mm; border-radius: 18mm; border: 1mm solid #e5622a; color: #e5622a; text-align: center; }
    .stamp .in { margin: 2mm; width: 30mm; height: 30mm; border-radius: 15mm; border: 0.3mm solid #e5622a; }
    .stamp .t1 { margin-top: 9mm; font-size: 8px; letter-spacing: 2px; }
    .stamp .t2 { font-size: 13px; font-weight: bold; letter-spacing: 1px; }
    .signs { position: absolute; bottom: 22mm; left: 24mm; color: #16120f; }
    .qrbox { position: absolute; bottom: 18mm; right: 24mm; color: #16120f; }
    .foot { position: absolute; bottom: 15mm; left: 30mm; width: 180mm; font-size: 9px; color: #5d544c; }
@endsection
@section('body')
    <div class="f1"></div><div class="f1b"></div><div class="f2"></div><div class="f2b"></div>
    <div class="c" style="top: 9mm; left: 9mm;"></div><div class="c" style="top: 9mm; right: 9mm;"></div>
    <div class="c" style="bottom: 9mm; left: 9mm;"></div><div class="c" style="bottom: 9mm; right: 9mm;"></div>
    <div class="stamp"><div class="in"><div class="t1">GODRAM</div><div class="t2 serif">CERTIFIED</div></div></div>
    <div class="content">
        <img src="{{ $logo }}" class="logo" alt="">
        <div class="org">GOFAMINT DRAMA &amp; FILM MINISTRY</div>
        <div class="kicker">EXAMINATION SUCCESS</div>
        <h1 class="serif">{{ $certificate->title }}</h1>
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
