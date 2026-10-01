<section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
    <div class="flex flex-col justify-between gap-2 sm:flex-row sm:items-end">
        <div>
            <p class="text-xs font-semibold uppercase text-slate-500">Section 4</p>
            <h3 class="mt-1 font-display text-lg font-bold text-slate-950">Foundation Preview</h3>
        </div>
        @if (($foundationPreview['preview'] ?? false) === true)
            <span class="text-xs text-slate-500">Preview from current master references; not saved as a snapshot.</span>
        @else
            <span class="text-xs text-emerald-700">Saved foundation snapshot</span>
        @endif
    </div>
    <dl class="mt-5 grid gap-4 sm:grid-cols-3">
        <div class="border-t border-slate-200 pt-3"><dt class="text-sm text-slate-500">Working Days</dt><dd class="mt-1 font-display text-xl font-semibold text-slate-900">{{ $foundationPreview['working_days'] }}</dd></div>
        <div class="border-t border-slate-200 pt-3"><dt class="text-sm text-slate-500">Working Hours / Year</dt><dd class="mt-1 font-display text-xl font-semibold text-slate-900">{{ $foundationPreview['working_hours_year'] }}</dd></div>
        <div class="border-t border-slate-200 pt-3"><dt class="text-sm text-slate-500">Effective Working Hours</dt><dd class="mt-1 font-display text-xl font-semibold text-slate-900">{{ $foundationPreview['effective_working_hours'] }}</dd></div>
    </dl>
    @if (isset($assessment))
        <p class="mt-4 text-sm text-slate-600">Efficiency Factor: {{ number_format((float) $assessment->efficiency_factor * 100, 2) }}%</p>
    @endif
</section>