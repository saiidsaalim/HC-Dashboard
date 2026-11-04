@props(['employee'])

<p {{ $attributes->merge(['class' => 'mt-1 break-all text-sm font-medium text-slate-500']) }} data-position-id-source>
    Position ID {{ $employee?->position_id_source ?: 'Tidak tersedia' }}
</p>
