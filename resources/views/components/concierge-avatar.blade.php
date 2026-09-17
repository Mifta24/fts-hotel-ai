@props(['size' => '9'])

@php
    $sizeClass = match ((string) $size) {
        '7' => 'h-7 w-7',
        '8' => 'h-8 w-8',
        '9' => 'h-9 w-9',
        '10' => 'h-10 w-10',
        '12' => 'h-12 w-12',
        '14' => 'h-14 w-14',
        '16' => 'h-16 w-16',
        default => 'h-9 w-9',
    };
@endphp

{{-- A flat-illustration bot mascot, not a photo — the AI Concierge should
     always read as AI, never as a specific real person. --}}
<span {{ $attributes->merge(['class' => "{$sizeClass} inline-flex shrink-0 items-center justify-center rounded-full bg-gradient-to-br from-amber-600 to-amber-400 shadow-sm"]) }}>
    <svg viewBox="0 0 24 24" class="h-2/3 w-2/3" xmlns="http://www.w3.org/2000/svg">
        <rect x="9" y="2" width="6" height="3" rx="1.5" fill="#DC2626" />
        <rect x="4" y="5" width="16" height="14" rx="6" fill="#FFFFFF" />
        <rect x="8" y="11" width="2.4" height="3.2" rx="1.2" fill="#1E293B" />
        <rect x="13.6" y="11" width="2.4" height="3.2" rx="1.2" fill="#1E293B" />
        <rect x="9.4" y="16.2" width="5.2" height="1.6" rx="0.8" fill="#1E293B" />
    </svg>
</span>
