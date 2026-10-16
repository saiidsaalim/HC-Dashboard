@props(['status'])

@if ($status)
    <div role="status" {{ $attributes->merge(['class' => 'rounded-xl border border-emerald-300/30 bg-emerald-400/10 px-4 py-3 text-sm font-semibold text-emerald-200']) }}>
        {{ $status }}
    </div>
@endif
