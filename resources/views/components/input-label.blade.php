@props(['value'])

<label {{ $attributes->merge(['class' => 'block text-sm font-semibold text-slate-200']) }}>
    {{ $value ?? $slot }}
</label>
