<form method="GET" action="{{ url()->current() }}" class="flex flex-wrap items-center justify-end gap-2" data-table-toolbar>
    @foreach (request()->except(['page', 'search', 'per_page']) as $key => $value)
        @if (is_scalar($value))
            <input type="hidden" name="{{ $key }}" value="{{ $value }}">
        @endif
    @endforeach

    <label for="{{ $searchFieldId }}" class="sr-only">Cari data</label>
    <div class="relative min-w-0 flex-1 sm:w-56 sm:flex-none">
        <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
            <circle cx="8.5" cy="8.5" r="5.5" />
            <path d="m13 13 4 4" stroke-linecap="round" />
        </svg>
        <input
            id="{{ $searchFieldId }}"
            name="search"
            value="{{ $search }}"
            placeholder="Cari data..."
            class="min-h-11 w-full rounded-xl border-slate-300 py-2 pl-9 pr-3 text-sm shadow-sm focus:border-amber-400 focus:ring-amber-400"
            data-table-search
        >
    </div>

    <button type="submit" class="ui-button ui-button-secondary shrink-0" aria-label="Cari data">
        Cari
    </button>

    <label for="{{ $perPageFieldId }}" class="text-sm font-semibold text-slate-600">Tampilkan</label>
    <select
        id="{{ $perPageFieldId }}"
        name="per_page"
        class="min-h-11 rounded-xl border-slate-300 py-2 pl-3 pr-9 text-sm font-semibold shadow-sm focus:border-amber-400 focus:ring-amber-400"
        aria-label="Jumlah data per halaman"
        @change="$el.form.requestSubmit()"
    >
        @foreach ([20, 40, 60] as $option)
            <option value="{{ $option }}" @selected($perPage === $option)>{{ $option }}</option>
        @endforeach
    </select>
</form>
