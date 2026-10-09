@extends('certificates.designs.base')
@section('style')
    .page { background: #16120f; color: #f7f0e4; }
    .frame { position: absolute; top: 10mm; left: 10mm; right: 10mm; bottom: 10mm; border: 0.6mm solid #f0b03c; }
    .stripe { position: absolute; top: 10mm; left: 10mm; right: 10mm; height: 3mm; background: #e5622a; }
    .content { position: absolute; top: 24mm; left: 30mm; width: 237mm; text-align: center; }
    .logo { width: 15mm; height: 15mm; }
    .kicker { margin-top: 3mm; font-size: 10px; letter-spacing: 6px; color: #f0b03c; }
    h1 { margin: 4mm 0 0; font-size: 44px; color: #f0b03c; letter-spacing: 1px; }
    .to { font-size: 13px; color: #d9cdb8; margin-top: 5mm; }
    .name { font-size: 36px; font-weight: bold; color: #ffffff; margin-top: 1mm; }
    .bar { width: 40mm; height: 0.6mm; background: #f0b03c; margin: 3mm auto; }
    .what { font-size: 15px; color: #f7f0e4; }
    .programme { font-size: 20px; font-weight: bold; color: #f0b03c; margin-top: 2mm; }
    .date { font-size: 12px; color: #d9cdb8; margin-top: 3mm; }
    .panel { position: absolute; bottom: 14mm; left: 18mm; right: 18mm; height: 40mm; background: #f7f0e4; color: #16120f; }
    .signs { position: absolute; bottom: 21mm; left: 22mm; color: #16120f; }
    .qrbox { position: absolute; bottom: 15.5mm; right: 24mm; color: #16120f; }
    .qr img { width: 80px; height: 80px; }
    .foot { position: absolute; bottom: 4mm; left: 10mm; width: 277mm; text-align: center; font-size: 9px; color: #d9cdb8; }
@endsection
@section('body')
    <div class="frame"></div><div class="stripe"></div>
    <div class="content">
        <img src="{{ $logo }}" class="logo" alt="">
        <div class="kicker">SPECIAL RECOGNITION</div>
        <h1 class="serif">{{ $certificate->title }}</h1>
        <div class="to">This is to certify that</div>
        <div class="name serif">{{ $certificate->recipient_name }}</div>
        <div class="bar"></div>
        <div class="what">{{ $certificate->achievement }}</div>
        @if ($certificate->programme)<div class="programme serif">{{ $certificate->programme }}</div>@endif
        <div class="date">Awarded on {{ $certificate->issued_on->format('j F Y') }}</div>
    </div>
    <div class="panel"></div>
    <div class="signs">@include('certificates.designs._signatures')</div>
    <div class="qrbox">@include('certificates.designs._qr')</div>
    <div class="foot">{{ $certificate->issuing_authority }}</div>
@endsection
