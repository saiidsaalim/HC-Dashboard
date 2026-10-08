<div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900">
    <p class="font-semibold">Hasil WLA Final sudah dibekukan.</p>
    <p class="mt-1">Tampilan ini menggunakan snapshot final dan tidak berubah ketika master diperbarui.</p>
</div>

@if (! is_array($snapshot))
    <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
        Snapshot final tidak tersedia. Data master saat ini tidak digunakan sebagai pengganti.
    </div>
@else
    <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
        <h3 class="font-display text-lg font-bold text-slate-950">Konteks Final</h3>
        <dl class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div><dt class="text-xs uppercase text-slate-500">Department</dt><dd class="mt-1 font-medium text-slate-800">{{ data_get($snapshot, 'department.code') }} · {{ data_get($snapshot, 'department.name') }}</dd></div>
            <div><dt class="text-xs uppercase text-slate-500">Unit</dt><dd class="mt-1 font-medium text-slate-800">{{ data_get($snapshot, 'unit.code') }} · {{ data_get($snapshot, 'unit.name') }}</dd></div>
            <div><dt class="text-xs uppercase text-slate-500">Position</dt><dd class="mt-1 font-medium text-slate-800">{{ data_get($snapshot, 'position.code') }} · {{ data_get($snapshot, 'position.name') }}</dd></div>
            <div><dt class="text-xs uppercase text-slate-500">Jadwal Kerja</dt><dd class="mt-1 font-medium text-slate-800">{{ data_get($snapshot, 'schedule.code') }} · {{ data_get($snapshot, 'schedule.name') }} · {{ data_get($snapshot, 'schedule.calculation_type_label') }}</dd></div>
            <div><dt class="text-xs uppercase text-slate-500">Jam Harian WLA</dt><dd class="mt-1 font-medium text-slate-800">{{ data_get($snapshot, 'schedule.wla_hours_per_day') }} jam</dd></div>
            <div><dt class="text-xs uppercase text-slate-500">Kalender Kerja</dt><dd class="mt-1 font-medium text-slate-800">{{ data_get($snapshot, 'calendar.year') }} · {{ data_get($snapshot, 'calendar.total_days') }} hari</dd></div>
            <div><dt class="text-xs uppercase text-slate-500">Pengurangan Kalender</dt><dd class="mt-1 font-medium text-slate-800">Cuti {{ data_get($snapshot, 'calendar.annual_leave') }}, libur nasional {{ data_get($snapshot, 'calendar.national_holiday') }}, cuti bersama {{ data_get($snapshot, 'calendar.common_leave') }}, Sabtu {{ data_get($snapshot, 'calendar.saturday_days') }}, Minggu {{ data_get($snapshot, 'calendar.sunday_days') }}</dd></div>
            <div><dt class="text-xs uppercase text-slate-500">Efficiency Factor</dt><dd class="mt-1 font-medium text-slate-800">{{ data_get($snapshot, 'efficiency_factor') }}</dd></div>
            <div><dt class="text-xs uppercase text-slate-500">Difinalisasi Oleh</dt><dd class="mt-1 font-medium text-slate-800">{{ data_get($snapshot, 'finalizer.name') }} (ID {{ data_get($snapshot, 'finalizer.id') }})</dd></div>
            <div><dt class="text-xs uppercase text-slate-500">Waktu Finalisasi</dt><dd class="mt-1 font-medium text-slate-800">{{ data_get($snapshot, 'finalized_at') }}</dd></div>
        </dl>
    </section>

    <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
        <h3 class="font-display text-lg font-bold text-slate-950">Aktivitas Final</h3>
        <div class="mt-5 overflow-x-auto">
            <table class="w-full min-w-[900px] text-left text-sm">
                <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                    <tr><th class="px-3 py-3">No</th><th class="px-3 py-3">Nama Aktivitas</th><th class="px-3 py-3">Frekuensi</th><th class="px-3 py-3">Periode</th><th class="px-3 py-3">Waktu (Jam)</th><th class="px-3 py-3">Beban Tahunan</th><th class="px-3 py-3">Catatan</th></tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach (data_get($snapshot, 'activities', []) as $activity)
                        <tr>
                            <td class="px-3 py-3">{{ $loop->iteration }}</td>
                            <td class="px-3 py-3">{{ data_get($activity, 'activity_name') }}</td>
                            <td class="px-3 py-3">{{ data_get($activity, 'frequency') }}</td>
                            <td class="px-3 py-3">{{ data_get($activity, 'period_unit') }}</td>
                            <td class="px-3 py-3">{{ data_get($activity, 'time_allocated_hours') }}</td>
                            <td class="px-3 py-3">{{ data_get($activity, 'annual_workload_hours') }}</td>
                            <td class="px-3 py-3">{{ data_get($activity, 'notes') ?: '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>

    <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
        <h3 class="font-display text-lg font-bold text-slate-950">Ringkasan Final</h3>
        <dl class="mt-5 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <div class="border-t border-slate-200 pt-3"><dt class="text-sm text-slate-500">Hari Kerja Tahunan</dt><dd class="mt-1 font-display text-xl font-semibold text-slate-900">{{ data_get($snapshot, 'working_days') }}</dd></div>
            <div class="border-t border-slate-200 pt-3"><dt class="text-sm text-slate-500">Jam Kerja Tahunan</dt><dd class="mt-1 font-display text-xl font-semibold text-slate-900">{{ data_get($snapshot, 'annual_working_hours') }}</dd></div>
            <div class="border-t border-slate-200 pt-3"><dt class="text-sm text-slate-500">Jam Kerja Efektif Tahunan</dt><dd class="mt-1 font-display text-xl font-semibold text-slate-900">{{ data_get($snapshot, 'effective_annual_working_hours') }}</dd></div>
            <div class="border-t border-slate-200 pt-3"><dt class="text-sm text-slate-500">Total Beban Tahunan</dt><dd class="mt-1 font-display text-xl font-semibold text-slate-900">{{ data_get($snapshot, 'total_annual_workload') }} jam</dd></div>
            <div class="border-t border-slate-200 pt-3"><dt class="text-sm text-slate-500">FTE</dt><dd class="mt-1 font-display text-xl font-semibold text-slate-900">{{ data_get($snapshot, 'fte') }}</dd></div>
            <div class="border-t border-slate-200 pt-3"><dt class="text-sm text-slate-500">Rekomendasi Pegawai</dt><dd class="mt-1 font-display text-xl font-semibold text-slate-900">{{ data_get($snapshot, 'recommended_employees') }}</dd></div>
        </dl>
    </section>
@endif
