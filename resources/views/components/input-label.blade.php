@props(['value', 'surface' => 'light'])

<label {{ $attributes->merge([
    'class' => 'block text-sm font-semibold leading-5 '.($surface === 'light' ? 'text-slate-700' : 'text-slate-200'),
]) }}>
    {{ $value ?? $slot }}
</label>
