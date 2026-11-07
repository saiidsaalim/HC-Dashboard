<nav class="formation-tabs mb-6 flex flex-wrap gap-2 rounded-2xl border border-slate-200 bg-white p-1.5" aria-label="Navigasi Formasi">
    @can('formation.view')
        <a href="{{ route('formasi') }}"
            @if (request()->routeIs('formasi', 'formasi.requirement.*')) aria-current="page" @endif
            class="formation-tab inline-flex min-h-11 items-center justify-center gap-2 rounded-xl px-4 py-2.5 text-sm font-semibold {{ request()->routeIs('formasi', 'formasi.requirement.*') ? 'is-active bg-slate-950 text-white' : 'text-slate-500 hover:bg-slate-50 hover:text-slate-900' }}">
            <x-dashboard-icon name="chart" class="formation-tab-icon h-4 w-4" aria-hidden="true" />
            <span>Analisis Formasi</span>
        </a>
        <a href="{{ route('formasi.structure') }}"
            @if (request()->routeIs('formasi.structure')) aria-current="page" @endif
            class="formation-tab inline-flex min-h-11 items-center justify-center gap-2 rounded-xl px-4 py-2.5 text-sm font-semibold {{ request()->routeIs('formasi.structure') ? 'is-active bg-slate-950 text-white' : 'text-slate-500 hover:bg-slate-50 hover:text-slate-900' }}">
            <x-dashboard-icon name="formasi" class="formation-tab-icon h-4 w-4" aria-hidden="true" />
            <span>Struktur Organisasi</span>
        </a>
        @can('organization.view')
            <a href="{{ route('formasi.master') }}"
                @if (request()->routeIs('formasi.master', 'organization.*')) aria-current="page" @endif
                class="formation-tab inline-flex min-h-11 items-center justify-center gap-2 rounded-xl px-4 py-2.5 text-sm font-semibold {{ request()->routeIs('formasi.master', 'organization.*') ? 'is-active bg-slate-950 text-white' : 'text-slate-500 hover:bg-slate-50 hover:text-slate-900' }}">
                <x-dashboard-icon name="clipboard" class="formation-tab-icon h-4 w-4" aria-hidden="true" />
                <span>Master Organisasi</span>
            </a>
        @endcan
    @endcan
</nav>
