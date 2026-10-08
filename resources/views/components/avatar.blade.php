@props(['member', 'size' => 'size-10'])
<span {{ $attributes->merge(['class' => "$size inline-flex shrink-0 items-center justify-center rounded-full bg-stage font-display text-sm font-semibold text-gold"]) }} aria-hidden="true">{{ $member->initials }}</span>
