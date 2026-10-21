@props([
    'href',
    'label',
    'icon',
    'active' => false,
    'outlined' => false,
])

<a href="{{ $href }}" title="{{ $label }}"
    @if ($active) aria-current="page" @endif
    :class="{ 'justify-center': !sidebarExpanded && !sidebarOpen }"
    {{ $attributes->class([
        'app-nav-link flex min-w-0 items-center gap-3 rounded-xl px-3 py-3 text-[15px] font-medium leading-5',
        'border border-slate-800 text-slate-300 hover:border-amber-400/50 hover:bg-slate-900 hover:text-white' => $outlined,
        'bg-amber-400 text-slate-950 shadow-lg shadow-amber-400/10' => $active,
        'text-slate-400 hover:bg-slate-900 hover:text-white' => ! $active && ! $outlined,
    ]) }}>
    <x-dashboard-icon :name="$icon" class="h-[22px] w-[22px]" />
    <span x-show="sidebarExpanded || sidebarOpen"
        x-transition:enter="transition-opacity delay-200 duration-100"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="transition-opacity duration-75"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        class="min-w-0 flex-1 break-words pr-3">{{ $label }}</span>
</a>
