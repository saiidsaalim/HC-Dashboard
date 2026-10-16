@props(['active'])

@php
$classes = ($active ?? false)
            ? 'inline-flex items-center border-b-2 border-amber-400 px-1 pt-1 text-sm font-semibold leading-5 text-slate-950 transition duration-150 focus:border-amber-500 focus:outline-none'
            : 'inline-flex items-center border-b-2 border-transparent px-1 pt-1 text-sm font-medium leading-5 text-slate-500 transition duration-150 hover:border-slate-300 hover:text-slate-800 focus:border-slate-300 focus:text-slate-800 focus:outline-none';
@endphp

<a {{ $attributes->merge(['class' => $classes]) }}>
    {{ $slot }}
</a>
