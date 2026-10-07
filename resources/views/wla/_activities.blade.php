<section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
    <div>
        <p class="text-xs font-semibold uppercase text-slate-500">Section 3</p>
        <h3 class="mt-1 font-display text-lg font-bold text-slate-950">Aktivitas</h3>
    </div>

    @if ($errors->any())
        <div class="mt-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">{{ $errors->first() }}</div>
    @endif

    @if ($calculationState['has_activities_needing_review'])
        <div class="mt-4 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
            Ada aktivitas lama yang perlu direview. Aktivitas tersebut belum disertakan dalam total beban kerja dan FTE.
        </div>
    @endif

    <div class="mt-5 overflow-x-auto">
        <table class="w-full min-w-[980px] text-left text-sm">
            <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                <tr>
                    <th class="px-3 py-3">No</th>
                    <th class="px-3 py-3">Nama Aktivitas</th>
                    <th class="px-3 py-3">Frekuensi</th>
                    <th class="px-3 py-3">Periode</th>
                    <th class="px-3 py-3">Waktu per Aktivitas (Jam)</th>
                    <th class="px-3 py-3">Beban Tahunan (Jam)</th>
                    <th class="px-3 py-3">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($wla->activities as $activity)
                    @php($formId = 'activity-update-'.$activity->id)
                    @php($needsReview = $activity->period_unit === null || $activity->time_allocated_hours === null)
                    <tr>
                        <td class="px-3 py-3">{{ $loop->iteration }}</td>
                        <td class="px-3 py-3">
                            @can('update', $wla)
                                <input form="{{ $formId }}" name="activity_name" value="{{ $activity->activity_name }}"
                                    required maxlength="255" class="w-72 rounded-lg border-slate-300 text-sm">
                            @else
                                {{ $activity->activity_name }}
                            @endcan
                            @if ($needsReview)
                                <span class="mt-2 block w-fit rounded-full bg-amber-100 px-2.5 py-1 text-xs font-semibold text-amber-800">Perlu review</span>
                            @endif
                        </td>
                        <td class="px-3 py-3">
                            @can('update', $wla)
                                <input form="{{ $formId }}" type="number" name="frequency" value="{{ $activity->frequency }}"
                                    min="0.01" max="{{ \App\Models\WlaActivity::MAX_INPUT_VALUE }}" step="0.01" required class="w-28 rounded-lg border-slate-300 text-sm">
                            @else
                                {{ $activity->frequency }}
                            @endcan
                        </td>
                        <td class="px-3 py-3">
                            @can('update', $wla)
                                <select form="{{ $formId }}" name="period_unit" required class="rounded-lg border-slate-300 text-sm">
                                    <option value="">Pilih periode</option>
                                    @foreach (\App\Enums\WlaPeriodUnit::cases() as $unit)
                                        <option value="{{ $unit->value }}" @selected($activity->period_unit === $unit)>{{ $unit->value }}</option>
                                    @endforeach
                                </select>
                            @else
                                {{ $activity->period_unit?->value ?? 'Perlu review' }}
                            @endcan
                        </td>
                        <td class="px-3 py-3">
                            @can('update', $wla)
                                <input form="{{ $formId }}" type="number" name="time_allocated_hours"
                                    value="{{ $activity->time_allocated_hours }}" min="0.01" max="{{ \App\Models\WlaActivity::MAX_INPUT_VALUE }}"
                                    step="0.01" required class="w-36 rounded-lg border-slate-300 text-sm">
                            @else
                                {{ $activity->time_allocated_hours ?? 'Perlu review' }}
                            @endcan
                        </td>
                        <td class="px-3 py-3 font-semibold text-slate-700">
                            {{ $needsReview ? 'Perlu review' : $activity->annual_workload_hours }}
                        </td>
                        <td class="px-3 py-3">
                            @can('update', $wla)
                                <div class="flex items-center gap-3">
                                    <form id="{{ $formId }}" method="POST"
                                        action="{{ route('wla.activities.update', [$wla, $activity]) }}">
                                        @csrf
                                        @method('PUT')
                                        <button class="font-semibold text-sky-700 hover:text-sky-900">Simpan</button>
                                    </form>
                                    <form method="POST" action="{{ route('wla.activities.destroy', [$wla, $activity]) }}"
                                        onsubmit="return confirm('Hapus aktivitas draft ini?')">
                                        @csrf
                                        @method('DELETE')
                                        <button class="font-semibold text-red-600 hover:text-red-800">Hapus</button>
                                    </form>
                                </div>
                            @else
                                <span class="text-slate-400">Read-only</span>
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-3 py-8 text-center text-slate-500">Belum ada aktivitas.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @can('update', $wla)
        <form method="POST" action="{{ route('wla.activities.store', $wla) }}" class="mt-5 border-t border-slate-200 pt-5">
            @csrf
            <h4 class="font-semibold text-slate-800">Tambah Aktivitas</h4>
            <div class="mt-3 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <label class="text-xs font-medium text-slate-600">Nama Aktivitas
                    <input name="activity_name" required maxlength="255"
                        class="mt-1 block w-full rounded-lg border-slate-300 text-sm">
                </label>
                <label class="text-xs font-medium text-slate-600">Frekuensi
                    <input type="number" name="frequency" min="0.01" max="{{ \App\Models\WlaActivity::MAX_INPUT_VALUE }}" step="0.01" required
                        class="mt-1 block w-full rounded-lg border-slate-300 text-sm">
                </label>
                <label class="text-xs font-medium text-slate-600">Periode
                    <select name="period_unit" required class="mt-1 block w-full rounded-lg border-slate-300 text-sm">
                        <option value="">Pilih periode</option>
                        @foreach (\App\Enums\WlaPeriodUnit::cases() as $unit)
                            <option value="{{ $unit->value }}">{{ $unit->value }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="text-xs font-medium text-slate-600">Waktu per Aktivitas (Jam)
                    <input type="number" name="time_allocated_hours" min="0.01" max="{{ \App\Models\WlaActivity::MAX_INPUT_VALUE }}" step="0.01" required
                        class="mt-1 block w-full rounded-lg border-slate-300 text-sm">
                </label>
            </div>
            <button class="mt-4 rounded-lg bg-slate-950 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-700">Tambah Aktivitas</button>
        </form>
    @endcan
</section>
