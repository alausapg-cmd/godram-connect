{{-- Shared frame for every certificate design. Each design sets its own colours and layout. --}}
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<style>
    @page { margin: 0; }
    body { margin: 0; font-family: 'DejaVu Sans', sans-serif; color: #1d1915; }
    .page { position: absolute; top: 0; left: 0; width: 297mm; height: 210mm; overflow: hidden; }
    .serif { font-family: 'DejaVu Serif', serif; }
    .mono { font-family: 'DejaVu Sans Mono', monospace; }
    .center { text-align: center; }
    .sign { display: inline-block; width: 200px; margin: 0 22px; text-align: center; vertical-align: bottom; }
    .sign img { height: 48px; }
    .sign .blank { height: 48px; }
    .sign .line { border-top: 1px solid currentColor; padding-top: 4px; font-size: 11.5px; font-weight: bold; }
    .sign .role { font-size: 10px; opacity: 0.75; }
    .qr img { width: 92px; height: 92px; }
    .qr .cap { font-size: 8px; opacity: 0.8; }
    .qr .id { font-family: 'DejaVu Sans Mono', monospace; font-size: 8.5px; margin-top: 1px; }
    .specimen { position: absolute; top: 88mm; left: 0; width: 297mm; text-align: center; font-size: 90px; font-weight: bold; color: #b3261e; opacity: 0.12; letter-spacing: 20px; }
    @yield('style')
</style>
</head>
<body>
<div class="page">
    @yield('body')
    @if ($specimen ?? false)<div class="specimen">SPECIMEN</div>@endif
</div>
</body>
</html>
