@props(['value', 'surface' => 'dark'])

<label {{ $attributes->merge([
    'class' => 'block text-sm font-semibold '.($surface === 'light' ? 'text-slate-700' : 'text-slate-200'),
]) }}>
    {{ $value ?? $slot }}
</label>
