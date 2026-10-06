@props(['unit'])

<button
    class="portal-unit-card"
    type="button"
    data-unit-select="{{ $unit['id'] }}"
    aria-controls="portal-workspace-{{ $unit['id'] }}"
    aria-pressed="false"
>
    <span class="portal-unit-card__name">{{ $unit['name'] }}</span>
    <span class="portal-unit-card__action" aria-hidden="true">
        <svg viewBox="0 0 20 20" fill="none">
            <path d="m7.5 4.5 5.5 5.5-5.5 5.5" />
        </svg>
    </span>
</button>
