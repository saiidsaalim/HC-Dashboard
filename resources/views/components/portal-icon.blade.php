@props(['name'])

<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor"
    stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"
    {{ $attributes }}>
    @switch($name)
        @case('users')
            <path d="M16 21v-2a4 4 0 0 0-4-4H7a4 4 0 0 0-4 4v2" />
            <circle cx="9.5" cy="7" r="4" />
            <path d="M20 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75" />
            @break
        @case('chart')
            <path d="M3 3v18h18" />
            <path d="M8 15v3m5-8v8m5-13v13" />
            @break
        @case('clipboard')
            <rect x="5" y="4" width="14" height="17" rx="2" />
            <path d="M9 4.5V3h6v1.5M9 10h6m-6 4h6m-6 4h3" />
            @break
        @case('arrows')
            <path d="M7 7h13l-3-3m3 3-3 3M17 17H4l3 3m-3-3 3-3" />
            @break
        @case('promotion')
            <path d="M4 17 10 11l4 4 6-8" />
            <path d="M14 7h6v6M4 21h16" />
            @break
        @case('demosi')
            <path d="M4 7 10 13l4-4 6 8" />
            <path d="M14 17h6v-6M4 3v18" />
            @break
        @case('formasi')
            <rect x="3" y="3" width="7" height="7" rx="1" />
            <rect x="14" y="3" width="7" height="7" rx="1" />
            <rect x="3" y="14" width="7" height="7" rx="1" />
            <rect x="14" y="14" width="7" height="7" rx="1" />
            @break
        @case('definitif')
            <path d="M12 22s8-4 8-11V5l-8-3-8 3v6c0 7 8 11 8 11Z" />
            <path d="m9 12 2 2 4-4" />
            @break
        @case('document')
            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" />
            <path d="M14 2v6h6M8 13h8m-8 4h8" />
            @break
        @case('org-chart')
            <rect x="9" y="3" width="6" height="5" rx="1" />
            <rect x="2.5" y="16" width="6" height="5" rx="1" />
            <rect x="15.5" y="16" width="6" height="5" rx="1" />
            <path d="M12 8v4m-6.5 4v-4h13v4" />
            @break
        @case('target')
            <circle cx="12" cy="12" r="9" />
            <circle cx="12" cy="12" r="5.5" />
            <circle cx="12" cy="12" r="2" />
            <path d="m13.5 10.5 6-6m-3 0h3v3" />
            @break
        @case('search')
            <circle cx="10.8" cy="10.8" r="6.8" />
            <path d="m16 16 4.5 4.5" />
            @break
        @case('arrow')
            <path d="M5 12h14m-6-6 6 6-6 6" />
            @break
    @endswitch
</svg>