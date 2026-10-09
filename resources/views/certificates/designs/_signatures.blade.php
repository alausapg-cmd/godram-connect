@foreach ($signatures as $s)<div class="sign">
    @if ($s['image'])<img src="{{ $s['image'] }}" alt="">@else<div class="blank"></div>@endif
    <div class="line">{{ $s['name'] ?? '' }}</div>
    <div class="role">{{ $s['title'] }}</div>
</div>@endforeach
