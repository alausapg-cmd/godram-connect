@props(['status', 'label'])
@php
$class = match ($status) {
    'approved', 'published', 'active' => 'badge-ok',
    'submitted', 'under_review', 'pending' => 'badge-info',
    'rejected', 'archived', 'inactive' => 'badge-bad',
    'draft', 'temporarily_unavailable' => 'badge-warn',
    default => 'badge-neutral',
};
@endphp
<span class="{{ $class }}">{{ $label }}</span>
