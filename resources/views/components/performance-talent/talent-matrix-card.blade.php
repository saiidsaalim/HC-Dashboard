@props([
    'box',
    'potential',
    'talents' => [],
])

@php
    $talentItems = collect($talents)
        ->map(fn ($talent): array => [
            'name' => data_get($talent, 'employee.name') ?? data_get($talent, 'name') ?? 'Nama tidak tersedia',
            'position' => data_get($talent, 'employee.position') ?? data_get($talent, 'position') ?? 'Posisi tidak tersedia',
        ])
        ->values();
    $selectionPayload = [
        'key' => $box['key'],
        'number' => $box['number'],
        'title' => $box['title'],
        'potential' => $potential,
        'performance' => $box['performance'],
        'count' => $talentItems->count(),
        'talents' => $talentItems->all(),
    ];
@endphp

<button type="button" data-talent-box data-box="{{ $box['key'] }}"
    data-display-number="{{ $box['number'] }}" data-potential="{{ $potential }}"
    data-performance="{{ $box['performance'] }}"
    x-on:click="$dispatch('talent-box-selected', @js($selectionPayload))"
    x-bind:aria-pressed="selectedBox?.key === @js($box['key'])"
    aria-label="{{ $box['title'] }}, potensi {{ $potential }}, performa {{ $box['performance'] }}, {{ $talentItems->count() }} talenta"
    class="talent-matrix-card"
    style="--matrix-background: {{ $box['background'] }}; --matrix-accent: {{ $box['accent'] }};">
    <span class="talent-matrix-card__glow" aria-hidden="true"></span>

    <span class="talent-matrix-card__top">
        <span class="talent-matrix-card__icon" aria-hidden="true">
            <img src="{{ asset('images/talent-matrix/icons/'.$box['icon']) }}" alt="" width="28" height="28">
        </span>
        <span class="talent-matrix-card__number">{{ $box['number'] }}</span>
    </span>

    <span class="talent-matrix-card__copy">
        <strong>{{ $box['title'] }}</strong>
        <span>Potensi {{ $potential }} <span aria-hidden="true">&bull;</span> Performa {{ $box['performance'] }}</span>
    </span>

    <span class="talent-matrix-card__count">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
            <path d="M16 20v-1.5A4.5 4.5 0 0 0 11.5 14h-3A4.5 4.5 0 0 0 4 18.5V20M10 10a3 3 0 1 0 0-6 3 3 0 0 0 0 6Zm7-1a2.5 2.5 0 0 1 0 5m3 6v-1a4 4 0 0 0-2.5-3.7"
                stroke-linecap="round" stroke-linejoin="round" />
        </svg>
        @if ($talentItems->isEmpty())
            Belum ada talenta
        @else
            {{ $talentItems->count() }} talenta
        @endif
    </span>

    <img class="talent-matrix-card__illustration"
        src="{{ asset('images/talent-matrix/characters/'.$box['illustration']) }}" alt="" width="320" height="220"
        loading="lazy" decoding="async">
</button>
