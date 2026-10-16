@props([
    'eyebrow' => null,
    'title',
    'description' => null,
    'backHref' => null,
    'backLabel' => 'Kembali',
])

<div {{ $attributes->merge(['class' => 'mb-7 flex flex-col justify-between gap-4 sm:flex-row sm:items-end']) }}>
    <div class="min-w-0 max-w-3xl">
        @if ($backHref)
            <a href="{{ $backHref }}" class="ui-button ui-button-secondary mb-4 w-fit"
                aria-label="{{ $backLabel }}">
                <svg class="h-4 w-4" viewBox="0 0 20 20" fill="none" stroke="currentColor"
                    stroke-width="1.8" aria-hidden="true">
                    <path d="M16 10H4m5-5-5 5 5 5" stroke-linecap="round" stroke-linejoin="round" />
                </svg>
                {{ $backLabel }}
            </a>
        @endif

        @if ($eyebrow)
            <p class="text-xs font-bold uppercase tracking-[0.18em] text-sky-700">{{ $eyebrow }}</p>
        @endif

        <h2 class="{{ $eyebrow ? 'mt-2' : '' }} font-display text-2xl font-bold tracking-tight text-slate-950 sm:text-3xl">
            {{ $title }}
        </h2>

        @if ($description)
            <p class="mt-2 text-sm leading-6 text-slate-500">{{ $description }}</p>
        @endif
    </div>

    @isset($actions)
        <div class="ui-page-actions">
            {{ $actions }}
        </div>
    @endisset
</div>
