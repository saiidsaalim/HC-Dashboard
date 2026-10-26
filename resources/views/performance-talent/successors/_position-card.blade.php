@if ($position['level'] === 'Staff')
    <div @class([
        'relative mx-auto w-fit',
        'pt-44 before:absolute before:left-1/2 before:top-0 before:h-44 before:border-l before:border-slate-400' => $position['align_with_grandchildren'] ?? false,
    ])>
        <button type="button"
            @click="openSuccessorDialog(@js(['key' => $position['key'], 'name' => $position['name'], 'level' => $position['level']]))"
            aria-label="Lihat successor untuk {{ $position['name'] }}"
            class="relative flex h-44 w-44 flex-col overflow-hidden rounded-full border-2 border-slate-400 bg-white text-slate-800 shadow-sm transition hover:border-blue-500 hover:shadow-md focus:outline-none focus:ring-4 focus:ring-blue-200">
            <div class="mt-7 grid grid-cols-3 border-y border-slate-300 text-sm font-bold">
                <span class="py-2 text-center">{{ $position['formation_count'] }}</span>
                <span class="border-l border-slate-300 py-2 text-center">F</span>
                <span aria-hidden="true" class="border-l border-slate-300 py-2">&nbsp;</span>
            </div>
            <div class="flex min-h-0 flex-1 items-center justify-center px-5 pb-7 pt-2">
                <p class="text-center text-sm font-semibold leading-5">{{ $position['name'] }}</p>
            </div>
        </button>
    </div>
@else
    @php($isGeneralManager = $position['level'] === 'General Manager')
    <button type="button"
        @click="openSuccessorDialog(@js(['key' => $position['key'], 'name' => $position['name'], 'level' => $position['level']]))"
        aria-label="Lihat successor untuk {{ $position['name'] }}"
        class="mx-auto w-56 overflow-hidden rounded-lg border border-slate-400 bg-white text-slate-800 shadow-sm transition hover:border-blue-500 hover:shadow-md focus:outline-none focus:ring-4 focus:ring-blue-200">
        <div @class([
            'grid grid-cols-[1fr_3rem] border-b border-slate-300 text-sm font-bold text-white',
            'bg-blue-600' => $isGeneralManager,
            'bg-violet-600' => ! $isGeneralManager,
        ])>
            <span class="px-3 py-2 text-center">{{ $position['level'] }}</span>
            <span class="border-l border-white/30 px-2 py-2 text-center">F</span>
        </div>
        <div class="grid min-h-16 grid-cols-[1fr_3rem] text-sm">
            <span class="flex items-center justify-center px-3 py-2 font-semibold leading-5">{{ $position['name'] }}</span>
            <span class="flex items-center justify-center border-l border-slate-300 font-bold">{{ $position['formation_count'] }}</span>
        </div>
    </button>
@endif
