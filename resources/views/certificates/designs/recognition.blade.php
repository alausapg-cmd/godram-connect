@extends('certificates.designs.base')
@section('style')
    .page { background: #fff8f0; color: #2a2420; }
    .bg { position: absolute; top: 0; left: 0; width: 297mm; height: 210mm; }
    .o1 { position: absolute; top: 10mm; left: 10mm; right: 10mm; bottom: 10mm; background: #b3261e; }
    .o2 { position: absolute; top: 10.6mm; left: 10.6mm; right: 10.6mm; bottom: 10.6mm; }
    .i1 { position: absolute; top: 13.5mm; left: 13.5mm; right: 13.5mm; bottom: 13.5mm; background: #f0b03c; }
    .i2 { position: absolute; top: 13.8mm; left: 13.8mm; right: 13.8mm; bottom: 13.8mm; }
    .clip { position: absolute; overflow: hidden; }
    .content { position: absolute; top: 21mm; left: 30mm; width: 237mm; text-align: center; }
    .logo { width: 14mm; height: 14mm; }
    .banner { display: inline-block; margin-top: 3mm; padding: 1.8mm 9mm; background: #b3261e; color: #ffffff; font-size: 10px; letter-spacing: 5px; }
    h1 { margin: 5mm 0 0; font-size: 44px; color: #b3261e; letter-spacing: 0.5px; }
    .to { font-size: 13px; color: #5d544c; margin-top: 4mm; }
    .name { font-size: 36px; font-weight: bold; color: #16120f; margin-top: 1mm; }
    .orn { margin-top: 2mm; font-size: 10px; color: #e5622a; letter-spacing: 6px; }
    .what { font-size: 15px; margin-top: 2mm; }
    .programme { font-size: 20px; font-weight: bold; color: #e5622a; margin-top: 2mm; }
    .date { font-size: 12px; color: #5d544c; margin-top: 3mm; }
    .sigL { position: absolute; bottom: 24mm; left: 24mm; color: #2a2420; }
    .sigR { position: absolute; bottom: 24mm; left: 168mm; color: #2a2420; }
    .seal { position: absolute; bottom: 25mm; left: 129mm; width: 30mm; height: 30mm; border-radius: 15mm; background: #f0b03c; text-align: center; }
    .seal .ring { margin: 1.6mm; width: 25.6mm; height: 25.6mm; border-radius: 12.8mm; border: 0.5mm solid #b3261e; }
    .seal .t1 { margin-top: 7.5mm; font-size: 7px; letter-spacing: 2px; color: #b3261e; }
    .seal .t2 { font-size: 12px; font-weight: bold; color: #b3261e; letter-spacing: 1px; }
    .qrbox { position: absolute; bottom: 19mm; right: 22mm; color: #2a2420; }
    .qr img { width: 78px; height: 78px; }
    .foot { position: absolute; bottom: 16mm; left: 30mm; width: 237mm; text-align: center; font-size: 8.5px; color: #5d544c; }
@endsection
@section('body')
    @if ($background)<img src="{{ $background }}" class="bg" alt="">@endif
    {{-- Thin red and gold rules, drawn as filled strips so every side renders the same weight. --}}
    <div class="o1"></div><div class="o2 clip">@if ($background)<img src="{{ $background }}" style="position: absolute; top: -10.6mm; left: -10.6mm; width: 297mm; height: 210mm;" alt="">@endif</div>
    <div class="i1"></div><div class="i2 clip">@if ($background)<img src="{{ $background }}" style="position: absolute; top: -13.8mm; left: -13.8mm; width: 297mm; height: 210mm;" alt="">@endif</div>
    <div class="content">
        <img src="{{ $logo }}" class="logo" alt="">
        <div><span class="banner">SPECIAL RECOGNITION</span></div>
        <h1 class="serif">{{ $certificate->title }}</h1>
        <div class="to">This is to certify that</div>
        <div class="name serif">{{ $certificate->recipient_name }}</div>
        <div class="orn">&#9670; &#9670; &#9670;</div>
        <div class="what">{{ $certificate->achievement }}</div>
        @if ($certificate->programme)<div class="programme serif">{{ $certificate->programme }}</div>@endif
        <div class="date">Awarded on {{ $certificate->issued_on->format('j F Y') }}</div>
    </div>
    @php $sigs = collect($signatures); @endphp
    <div class="sigL">@include('certificates.designs._signatures', ['signatures' => $sigs->take(1)])</div>
    <div class="seal"><div class="ring"><div class="t1">GODRAM</div><div class="t2 serif">HONOUR</div></div></div>
    <div class="sigR">@include('certificates.designs._signatures', ['signatures' => $sigs->slice(1)])</div>
    <div class="qrbox">@include('certificates.designs._qr')</div>
    <div class="foot">{{ $certificate->issuing_authority }}</div>
@endsection
