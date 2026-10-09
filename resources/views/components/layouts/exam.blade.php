@props(['title'])
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex">
    <title>{{ $title }} · GODRAM CONNECT</title>
    <meta name="theme-color" content="#16120f">
    <link rel="icon" href="/icons/icon-192.png" type="image/png">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-paper">
{{ $slot }}
</body>
</html>
