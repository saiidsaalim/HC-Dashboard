@props(['workspace'])

<button
    class="portal-workspace"
    type="button"
    data-open-login
    data-workspace-name="{{ $workspace['name'] }}"
    style="--workspace-color: {{ $workspace['color'] }}"
>
    <span class="portal-workspace__icon" aria-hidden="true">
        <svg viewBox="0 0 32 32" fill="none">
            @switch($workspace['icon'])
                @case('career')
                    <path d="M8 25V12.5h16V25M12 12.5V9a4 4 0 0 1 8 0v3.5M5 25h22M12 17h8M12 21h5" />
                    @break
                @case('performance')
                    <path d="M7 24V17M13 24V10M19 24v-8M25 24V6M5 26h22M10 13l4-4 4 3 7-7" />
                    @break
                @case('learning')
                    <path d="M5 10.5 16 5l11 5.5L16 16 5 10.5ZM8 12v7c4.5 4 11.5 4 16 0v-7M27 11v8" />
                    @break
                @default
                    <path d="M5 25V11l11-6 11 6v14M10 25v-8h12v8M3 25h26M13 11h.01M19 11h.01" />
            @endswitch
        </svg>
    </span>
    <span class="portal-workspace__name">{{ $workspace['name'] }}</span>
</button>
