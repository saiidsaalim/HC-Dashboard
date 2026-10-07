<section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
    <div class="flex flex-col justify-between gap-2 sm:flex-row sm:items-end">
        <div>
            <p class="text-xs font-semibold uppercase text-slate-500">Section 4</p>
            <h3 class="mt-1 font-display text-lg font-bold text-slate-950">Ringkasan Perhitungan WLA</h3>
        </div>
        @if (! $calculationState['calculation_available'])
            <span class="text-xs font-semibold text-amber-700">Belum dapat dihitung</span>
        @elseif ($calculationState['preview'])
            <span class="text-xs text-slate-500">Preview dari referensi master saat ini.</span>
        @else
            <span class="text-xs text-emerald-700">Snapshot kalkulasi tersimpan</span>
        @endif
    </div>

    @if (! $calculationState['calculation_available'])
        <div class="mt-5 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
            <p class="font-semibold">WLA belum dapat dihitung.</p>
            <p class="mt-1">{{ $calculationState['unavailable_reason'] }}</p>
        </div>
        <dl class="mt-5 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @foreach (['Hari Kerja Tahunan', 'Jam Kerja Tahunan', 'Jam Kerja Efektif Tahunan', 'Total Beban Tahunan', 'FTE', 'Rekomendasi Pegawai'] as $label)
                <div class="border-t border-slate-200 pt-3"><dt class="text-sm text-slate-500">{{ $label }}</dt><dd class="mt-1 font-display text-lg font-semibold text-amber-700">Belum dapat dihitung</dd></div>
            @endforeach
        </dl>
    @else
        <dl class="mt-5 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <div class="border-t border-slate-200 pt-3"><dt class="text-sm text-slate-500">Hari Kerja Tahunan</dt><dd class="mt-1 font-display text-xl font-semibold text-slate-900">{{ $calculationState['working_days'] }}</dd></div>
            <div class="border-t border-slate-200 pt-3"><dt class="text-sm text-slate-500">Jam Kerja Tahunan</dt><dd class="mt-1 font-display text-xl font-semibold text-slate-900">{{ number_format((float) $calculationState['working_hours_year'], 2, ',', '.') }}</dd></div>
            <div class="border-t border-slate-200 pt-3"><dt class="text-sm text-slate-500">Jam Kerja Efektif Tahunan</dt><dd class="mt-1 font-display text-xl font-semibold text-slate-900">{{ number_format((float) $calculationState['effective_working_hours'], 2, ',', '.') }}</dd></div>
            <div class="border-t border-slate-200 pt-3">
                <dt class="text-sm text-slate-500">{{ $calculationState['calculation_complete'] ? 'Total Beban Tahunan' : 'Subtotal Aktivitas Valid' }}</dt>
                <dd class="mt-1 font-display text-xl font-semibold text-slate-900">{{ number_format((float) $assessment->total_annual_workload_hours, 4, ',', '.') }} jam</dd>
            </div>
            <div class="border-t border-slate-200 pt-3"><dt class="text-sm text-slate-500">FTE</dt><dd class="mt-1 font-display text-xl font-semibold {{ $calculationState['calculation_complete'] ? 'text-slate-900' : 'text-amber-700' }}">{{ $calculationState['calculation_complete'] ? number_format((float) $assessment->fte, 6, ',', '.') : 'Belum lengkap' }}</dd></div>
            <div class="border-t border-slate-200 pt-3"><dt class="text-sm text-slate-500">Rekomendasi Pegawai</dt><dd class="mt-1 font-display text-xl font-semibold {{ $calculationState['calculation_complete'] ? 'text-slate-900' : 'text-amber-700' }}">{{ $calculationState['calculation_complete'] ? $assessment->recommended_employees : 'Belum lengkap' }}</dd></div>
        </dl>
        <p class="mt-4 text-sm text-slate-600">Efficiency Factor: {{ number_format((float) $assessment->efficiency_factor * 100, 2) }}%</p>
    @endif

    @if ($calculationState['calculation_available'] && ! $calculationState['calculation_complete'])
        <div class="mt-4 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
            Hasil FTE belum lengkap karena aktivitas bertanda “Perlu review” belum disertakan dalam perhitungan.
        </div>
    @endif
</section>
