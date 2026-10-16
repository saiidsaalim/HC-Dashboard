@props(['active'])

@php
$classes = ($active ?? false)
            ? 'block w-full border-l-4 border-amber-400 bg-amber-50 py-2 ps-3 pe-4 text-start text-base font-semibold text-slate-900 transition duration-150 focus:border-amber-500 focus:bg-amber-100 focus:outline-none'
            : 'block w-full border-l-4 border-transparent py-2 ps-3 pe-4 text-start text-base font-medium text-slate-600 transition duration-150 hover:border-slate-300 hover:bg-slate-50 hover:text-slate-900 focus:border-slate-300 focus:bg-slate-50 focus:text-slate-900 focus:outline-none';
@endphp

<a {{ $attributes->merge(['class' => $classes]) }}>
    {{ $slot }}
</a>
