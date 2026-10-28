@props([
    'action' => null,
    'active' => false,
    'buttonLabel' => 'Filter',
    'id' => 'filter-panel',
    'resetHref' => null,
    'title' => 'Filters',
])

@php
    $filterAction = $action ?? url()->current();
    $filterResetHref = $resetHref ?? url()->current();
@endphp

<div x-data="{ filterOpen: false }" @keydown.escape.window="filterOpen = false"
    {{ $attributes->except('id')->merge(['class' => 'flex justify-end']) }} data-filter-panel>
    <button type="button" @click="filterOpen = true" :aria-expanded="filterOpen.toString()"
        aria-controls="{{ $id }}" class="ui-button ui-button-secondary relative" data-filter-trigger>
        <svg class="h-4 w-4" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8"
            aria-hidden="true">
            <path d="M3 4h14l-5.5 6.2v4.3l-3 1.5v-5.8L3 4Z" stroke-linecap="round"
                stroke-linejoin="round" />
        </svg>
        {{ $buttonLabel }}
        @if ($active)
            <span class="h-2 w-2 rounded-full bg-sky-600" aria-label="Filter aktif"></span>
        @endif
    </button>

    <template x-teleport="body">
        <div x-cloak x-show="filterOpen" class="fixed inset-0 z-[90]" role="presentation">
            <div x-show="filterOpen" x-transition.opacity class="absolute inset-0 bg-slate-950/40 backdrop-blur-[1px]"
                @click="filterOpen = false" aria-hidden="true"></div>

            <aside id="{{ $id }}" x-show="filterOpen"
                x-transition:enter="transition ease-out duration-200"
                x-transition:enter-start="translate-x-full opacity-0"
                x-transition:enter-end="translate-x-0 opacity-100"
                x-transition:leave="transition ease-in duration-150"
                x-transition:leave-start="translate-x-0 opacity-100"
                x-transition:leave-end="translate-x-full opacity-0"
                class="absolute inset-y-0 right-0 flex w-full max-w-sm flex-col bg-slate-50 shadow-2xl"
                role="dialog" aria-modal="true" aria-labelledby="{{ $id }}-title" data-filter-drawer>
                <div class="flex items-center justify-between border-b border-slate-200 bg-white px-5 py-4">
                    <div>
                        <p class="text-xs font-bold uppercase tracking-[0.2em] text-sky-700">Filter data</p>
                        <h2 id="{{ $id }}-title" class="mt-1 font-display text-xl font-bold text-slate-950">
                            {{ $title }}
                        </h2>
                    </div>
                    <button type="button" @click="filterOpen = false" aria-label="Tutup filter"
                        class="rounded-lg p-2 text-slate-400 transition hover:bg-slate-100 hover:text-slate-700">
                        <svg class="h-5 w-5" viewBox="0 0 20 20" fill="none" stroke="currentColor"
                            stroke-width="1.8" aria-hidden="true">
                            <path d="m5 5 10 10M15 5 5 15" stroke-linecap="round" />
                        </svg>
                    </button>
                </div>

                <form method="GET" action="{{ $filterAction }}" class="flex min-h-0 flex-1 flex-col"
                    data-filter-form>
                    <div class="min-h-0 flex-1 space-y-4 overflow-y-auto px-5 py-5">
                        {{ $slot }}
                    </div>

                    <div class="space-y-3 border-t border-slate-200 bg-white px-5 py-4">
                        <button type="submit" class="ui-button ui-button-primary w-full justify-center">
                            Terapkan Filter
                        </button>
                        <a href="{{ $filterResetHref }}"
                            class="ui-button ui-button-secondary w-full justify-center" data-filter-reset>
                            <svg class="h-4 w-4" viewBox="0 0 20 20" fill="none" stroke="currentColor"
                                stroke-width="1.8" aria-hidden="true">
                                <path d="M15.8 7A6 6 0 1 0 16 12" stroke-linecap="round" />
                                <path d="M12.5 7H16V3.5" stroke-linecap="round" stroke-linejoin="round" />
                            </svg>
                            Reset Filter
                        </a>
                    </div>
                </form>
            </aside>
        </div>
    </template>
</div>
