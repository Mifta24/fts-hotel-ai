@props(['size' => '8'])

@php
    $sizeClass = match ((string) $size) {
        '4' => 'h-4 w-4',
        '6' => 'h-6 w-6',
        '8' => 'h-8 w-8',
        '9' => 'h-9 w-9',
        '10' => 'h-10 w-10',
        default => 'h-8 w-8',
    };
@endphp

<span {{ $attributes->merge(['class' => "{$sizeClass} inline-flex shrink-0 items-center justify-center rounded-lg bg-gradient-to-br from-amber-600 to-amber-400 shadow-sm"]) }}>
    <svg viewBox="0 0 24 24" class="h-2/3 w-2/3 text-white" fill="none" xmlns="http://www.w3.org/2000/svg">
        <path d="M12 3L21 20H15.5L12 13L8.5 20H3L12 3Z" fill="currentColor" />
    </svg>
</span>
