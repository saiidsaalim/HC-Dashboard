@php
    $isLeadership = in_array($position['level'], ['General Manager', 'Senior Manager', 'Manager'], true);
    $cardClasses = ! $isLeadership
        ? 'relative flex h-44 w-44 flex-col overflow-hidden rounded-full border-2 border-slate-400 bg-white text-slate-800 shadow-sm'
        : 'mx-auto flex h-36 w-64 flex-col overflow-hidden rounded-lg border border-slate-400 bg-white text-slate-800 shadow-sm';
@endphp

<div class="successor-position-slot relative mx-auto w-fit">
    <button type="button"
        @click="openSuccessorDialog(@js(['key' => $position['key'], 'name' => $position['name'], 'level' => $position['level']]))"
        aria-label="Lihat successor untuk {{ $position['name'] }}"
        class="{{ $cardClasses }} transition hover:border-blue-500 hover:shadow-md focus:outline-none focus:ring-4 focus:ring-blue-200">

    @if (! $isLeadership)
        <div class="mt-6 grid grid-cols-2 border-y border-slate-300">
            <span class="flex flex-col items-center justify-center py-1.5 text-center">
                <span class="text-[10px] font-bold uppercase tracking-wide text-slate-500">Formasi</span>
                <span class="text-base font-bold text-slate-900">{{ $position['formation_count'] }}</span>
            </span>
            <span class="flex flex-col items-center justify-center border-l border-slate-300 py-1.5 text-center">
                <span class="text-[10px] font-bold uppercase tracking-wide text-slate-500">Terisi</span>
                <span class="text-base font-bold text-emerald-700">{{ $position['occupied_count'] }}</span>
            </span>
        </div>
        <div class="flex min-h-0 flex-1 items-center justify-center px-5 pb-6 pt-2">
            <p class="text-center text-sm font-semibold leading-5">{{ $position['name'] }}</p>
        </div>
    @else
        <div class="shrink-0 bg-violet-600 px-3 py-2 text-center text-sm font-bold text-white">
            {{ $position['level'] }}
        </div>
        <div class="flex min-h-0 flex-1 items-center justify-center px-3 py-2 text-center text-sm font-semibold leading-5">
            {{ $position['name'] }}
        </div>
        <div class="grid shrink-0 grid-cols-2 border-t border-slate-300 text-xs">
            <span class="px-2 py-1.5 text-center"><span class="font-medium text-slate-500">Formasi</span> <strong>{{ $position['formation_count'] }}</strong></span>
            <span class="border-l border-slate-300 px-2 py-1.5 text-center"><span class="font-medium text-slate-500">Terisi</span> <strong class="text-emerald-700">{{ $position['occupied_count'] }}</strong></span>
        </div>
    @endif

    </button>
</div>
