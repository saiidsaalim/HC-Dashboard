@props([
    'tone' => 'neutral',
    'dot' => true,
])

@php
    $toneClasses = match ($tone) {
        'success' => 'border-emerald-200 bg-emerald-50 text-emerald-700',
        'warning' => 'border-amber-200 bg-amber-50 text-amber-700',
        'danger' => 'border-red-200 bg-red-50 text-red-700',
        'info' => 'border-sky-200 bg-sky-50 text-sky-700',
        default => 'border-slate-200 bg-slate-100 text-slate-600',
    };
    $dotClasses = match ($tone) {
        'success' => 'bg-emerald-500',
        'warning' => 'bg-amber-500',
        'danger' => 'bg-red-500',
        'info' => 'bg-sky-500',
        default => 'bg-slate-400',
    };
@endphp

<span {{ $attributes->merge(['class' => "inline-flex items-center gap-1.5 rounded-full border px-2.5 py-1 text-xs font-bold {$toneClasses}"]) }}>
    @if ($dot)
        <span class="h-1.5 w-1.5 rounded-full {{ $dotClasses }}" aria-hidden="true"></span>
    @endif
    {{ $slot }}
</span>
