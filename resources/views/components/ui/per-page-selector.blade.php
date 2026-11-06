@props([
    'perPage' => 20,
    'name' => 'per_page',
    'pageName' => 'page',
    'id' => 'per-page-selector',
])

<form method="GET" action="{{ url()->current() }}" {{ $attributes->class(['flex items-center gap-2']) }} data-per-page-selector>
    @foreach (request()->except([$name, $pageName]) as $queryName => $queryValue)
        @if (is_scalar($queryValue))
            <input type="hidden" name="{{ $queryName }}" value="{{ $queryValue }}">
        @endif
    @endforeach

    <label for="{{ $id }}" class="whitespace-nowrap text-sm font-semibold text-slate-600">Lihat</label>
    <select id="{{ $id }}" name="{{ $name }}" aria-label="Jumlah data per halaman"
        class="min-h-10 cursor-pointer rounded-xl border-slate-300 py-2 pl-3 pr-9 text-sm font-semibold text-slate-700 shadow-sm focus:border-sky-500 focus:ring-sky-500"
        @change="$el.form.submit()">
        @foreach ([20, 40, 60] as $option)
            <option value="{{ $option }}" @selected((int) $perPage === $option)>{{ $option }} data</option>
        @endforeach
    </select>
</form>
