@props(['align' => 'right', 'width' => '48', 'contentClasses' => 'border border-slate-200 bg-white p-2'])

@php
$alignmentClasses = match ($align) {
    'left' => 'ltr:origin-top-left rtl:origin-top-right start-0',
    'top' => 'origin-top',
    default => 'ltr:origin-top-right rtl:origin-top-left end-0',
};

$width = match ($width) {
    '48' => 'w-48',
    '64' => 'w-64',
    default => $width,
};
@endphp

<div class="relative" x-data="{ open: false }" @click.outside="open = false" @close.stop="open = false"
    @keydown.escape.stop.prevent="open = false; $nextTick(() => $refs.trigger.querySelector('button, a')?.focus())">
    <div x-ref="trigger" @click="open = ! open">
        {{ $trigger }}
    </div>

    <div x-cloak x-show="open" x-ref="menu" role="menu"
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0 -translate-y-1 scale-95"
            x-transition:enter-end="opacity-100 scale-100"
            x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="opacity-100 scale-100"
            x-transition:leave-end="opacity-0 -translate-y-1 scale-95"
            class="absolute z-50 mt-2 max-w-[calc(100vw-2rem)] {{ $width }} overflow-hidden rounded-xl shadow-xl shadow-slate-950/10 {{ $alignmentClasses }}"
            @click="open = false">
        <div class="rounded-xl {{ $contentClasses }}">
            {{ $content }}
        </div>
    </div>
</div>
