@extends('certificates.designs.base')
@section('style')
    .page { background: #fdf6e3; }
    .b1 { position: absolute; top: 7mm; left: 7mm; right: 7mm; bottom: 7mm; background: #f0b03c; }
    .b2 { position: absolute; top: 11mm; left: 11mm; right: 11mm; bottom: 11mm; background: #fdf6e3; }
    .b3 { position: absolute; top: 14mm; left: 14mm; right: 14mm; bottom: 14mm; border: 0.4mm solid #a86411; }
    .corner { position: absolute; width: 16mm; height: 16mm; border-radius: 8mm; background: #f0b03c; border: 0.8mm solid #a86411; }
    .content { position: absolute; top: 22mm; left: 30mm; width: 237mm; text-align: center; }
    .medal { width: 30mm; height: 30mm; margin: 0 auto; border-radius: 15mm; background: #f0b03c; border: 1.4mm solid #a86411; color: #16120f; }
    .medal .star { font-size: 40px; padding-top: 5.5mm; text-align: center; }
    .kicker { margin-top: 3mm; font-size: 10px; letter-spacing: 5px; color: #a86411; }
    h1 { margin: 2mm 0 0; font-size: 42px; color: #16120f; }
    .to { font-size: 13px; color: #5d544c; margin-top: 4mm; }
    .name { font-size: 36px; font-weight: bold; color: #16120f; margin-top: 1mm; }
    .what { font-size: 15px; margin-top: 3mm; font-style: italic; }
    .programme { display: inline-block; margin-top: 3mm; padding: 1.4mm 6mm; border: 0.4mm solid #a86411; border-radius: 6mm; font-size: 13px; letter-spacing: 2px; color: #a86411; }
    .date { font-size: 12px; color: #5d544c; margin-top: 3mm; }
    .signs { position: absolute; bottom: 22mm; left: 26mm; color: #16120f; }
    .qrbox { position: absolute; bottom: 19mm; right: 26mm; color: #16120f; }
    .foot { position: absolute; bottom: 16mm; left: 30mm; width: 180mm; font-size: 9px; color: #5d544c; }
@endsection
@section('body')
    <div class="b1"></div><div class="b2"></div><div class="b3"></div>
    <div class="corner" style="top: 5mm; left: 5mm;"></div><div class="corner" style="top: 5mm; right: 5mm;"></div>
    <div class="corner" style="bottom: 5mm; left: 5mm;"></div><div class="corner" style="bottom: 5mm; right: 5mm;"></div>
    <div class="content">
        <div class="medal"><div class="star">&#9733;</div></div>
        <div class="kicker">GODRAM ACHIEVEMENT AWARD</div>
        <h1 class="serif">{{ $certificate->title }}</h1>
        <div class="to">This is proudly presented to</div>
        <div class="name serif">{{ $certificate->recipient_name }}</div>
        <div class="what serif">{{ $certificate->achievement }}</div>
        @if ($certificate->programme)<div class="programme">{{ mb_strtoupper($certificate->programme) }}</div>@endif
        <div class="date">Awarded on {{ $certificate->issued_on->format('j F Y') }}</div>
    </div>
    <div class="signs">@include('certificates.designs._signatures')</div>
    <div class="qrbox">@include('certificates.designs._qr')</div>
    <div class="foot">{{ $certificate->issuing_authority }}</div>
@endsection
