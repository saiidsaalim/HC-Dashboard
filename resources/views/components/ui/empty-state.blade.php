@props([
    'colspan' => null,
    'title' => 'Belum ada data',
    'description' => null,
])

@if ($colspan)
    <td colspan="{{ $colspan }}" {{ $attributes->merge(['class' => 'px-5 py-12 text-center']) }}>
        <span class="mx-auto flex h-11 w-11 items-center justify-center rounded-xl bg-slate-100 text-slate-400" aria-hidden="true">
            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                <path stroke-linecap="round" stroke-linejoin="round" d="M4 6.75A2.75 2.75 0 0 1 6.75 4h10.5A2.75 2.75 0 0 1 20 6.75v10.5A2.75 2.75 0 0 1 17.25 20H6.75A2.75 2.75 0 0 1 4 17.25V6.75Z" />
                <path stroke-linecap="round" stroke-linejoin="round" d="M8 9h8M8 13h5" />
            </svg>
        </span>
        <span class="mt-3 block text-sm font-bold text-slate-700">{{ $title }}</span>
        @if ($description)
            <span class="mt-1 block text-sm text-slate-500">{{ $description }}</span>
        @endif
        {{ $slot }}
    </td>
@else
    <div {{ $attributes->merge(['class' => 'rounded-2xl border border-dashed border-slate-300 bg-slate-50 px-5 py-12 text-center']) }}>
        <span class="mx-auto flex h-11 w-11 items-center justify-center rounded-xl bg-white text-slate-400 shadow-sm" aria-hidden="true">
            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                <path stroke-linecap="round" stroke-linejoin="round" d="M4 6.75A2.75 2.75 0 0 1 6.75 4h10.5A2.75 2.75 0 0 1 20 6.75v10.5A2.75 2.75 0 0 1 17.25 20H6.75A2.75 2.75 0 0 1 4 17.25V6.75Z" />
                <path stroke-linecap="round" stroke-linejoin="round" d="M8 9h8M8 13h5" />
            </svg>
        </span>
        <p class="mt-3 text-sm font-bold text-slate-700">{{ $title }}</p>
        @if ($description)
            <p class="mt-1 text-sm text-slate-500">{{ $description }}</p>
        @endif
        {{ $slot }}
    </div>
@endif
