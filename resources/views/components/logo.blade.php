{{-- The official GODRAM emblem. Use this component wherever the logo appears. --}}
<img src="{{ asset(config('godram.logo.svg')) }}" {{ $attributes->merge(['class' => 'shrink-0', 'alt' => 'GODRAM']) }} width="512" height="512">
