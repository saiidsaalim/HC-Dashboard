<section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
    <div>
        <p class="text-xs font-semibold uppercase text-slate-500">Section 3</p>
        <h3 class="mt-1 font-display text-lg font-bold text-slate-950">Activities</h3>
    </div>

    @if ($errors->any())
        <div class="mt-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">{{ $errors->first() }}</div>
    @endif

    <div class="mt-5 overflow-x-auto">
        <table class="w-full min-w-[1260px] text-left text-sm">
            <thead class="bg-slate-50 text-xs uppercase text-slate-500"><tr><th class="px-3 py-3">No</th><th class="px-3 py-3">Activity</th><th class="px-3 py-3">Frequency</th><th class="px-3 py-3">Frequency Unit</th><th class="px-3 py-3">Volume</th><th class="px-3 py-3">Volume Unit</th><th class="px-3 py-3">Time Allocated</th><th class="px-3 py-3">Time Unit</th><th class="px-3 py-3">Action</th></tr></thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($wla->activities as $activity)
                    @php($formId = 'activity-update-'.$activity->id)
                    <tr>
                        <td class="px-3 py-3"><input form="{{ $formId }}" type="number" name="sort_order" value="{{ $activity->sort_order }}" min="0" required class="w-16 rounded-lg border-slate-300 text-sm"></td>
                        <td class="px-3 py-3"><input form="{{ $formId }}" name="activity_name" value="{{ $activity->activity_name }}" required maxlength="255" class="w-64 rounded-lg border-slate-300 text-sm"></td>
                        <td class="px-3 py-3"><input form="{{ $formId }}" type="number" name="frequency" value="{{ $activity->frequency }}" min="0" step="0.01" required class="w-24 rounded-lg border-slate-300 text-sm"></td>
                        <td class="px-3 py-3"><select form="{{ $formId }}" name="frequency_unit" required class="rounded-lg border-slate-300 text-sm">@foreach (\App\Enums\WlaFrequencyUnit::cases() as $unit)<option value="{{ $unit->value }}" @selected($activity->frequency_unit === $unit)>{{ $unit->value }}</option>@endforeach</select></td>
                        <td class="px-3 py-3"><input form="{{ $formId }}" type="number" name="volume" value="{{ $activity->volume }}" min="0" step="0.01" required class="w-24 rounded-lg border-slate-300 text-sm"></td>
                        <td class="px-3 py-3"><select form="{{ $formId }}" name="volume_unit" required class="rounded-lg border-slate-300 text-sm">@foreach (\App\Enums\WlaVolumeUnit::cases() as $unit)<option value="{{ $unit->value }}" @selected($activity->volume_unit === $unit)>{{ $unit->value }}</option>@endforeach</select></td>
                        <td class="px-3 py-3"><input form="{{ $formId }}" type="number" name="time_allocated" value="{{ $activity->time_allocated }}" min="0.01" step="0.01" required class="w-28 rounded-lg border-slate-300 text-sm"></td>
                        <td class="px-3 py-3"><select form="{{ $formId }}" name="time_unit" required class="rounded-lg border-slate-300 text-sm"><option value="Hour" selected>Hour</option></select></td>
                        <td class="px-3 py-3"><div class="flex items-center gap-3"><form id="{{ $formId }}" method="POST" action="{{ route('wla.activities.update', [$wla, $activity]) }}">@csrf @method('PUT')<button class="font-semibold text-sky-700 hover:text-sky-900">Save</button></form><form method="POST" action="{{ route('wla.activities.destroy', [$wla, $activity]) }}" onsubmit="return confirm('Delete this draft activity?')">@csrf @method('DELETE')<button class="font-semibold text-red-600 hover:text-red-800">Delete</button></form></div></td>
                    </tr>
                @empty
                    <tr><td colspan="9" class="px-3 py-8 text-center text-slate-500">No activity rows yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @can('update', $wla)
        <form method="POST" action="{{ route('wla.activities.store', $wla) }}" class="mt-5 border-t border-slate-200 pt-5">
            @csrf
            <h4 class="font-semibold text-slate-800">Add Activity</h4>
            <div class="mt-3 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <label class="text-xs font-medium text-slate-600">Activity<input name="activity_name" required maxlength="255" class="mt-1 block w-full rounded-lg border-slate-300 text-sm"></label>
                <label class="text-xs font-medium text-slate-600">Frequency<input type="number" name="frequency" min="0" step="0.01" required class="mt-1 block w-full rounded-lg border-slate-300 text-sm"></label>
                <label class="text-xs font-medium text-slate-600">Frequency Unit<select name="frequency_unit" required class="mt-1 block w-full rounded-lg border-slate-300 text-sm"><option value="">Select unit</option>@foreach (\App\Enums\WlaFrequencyUnit::cases() as $unit)<option value="{{ $unit->value }}">{{ $unit->value }}</option>@endforeach</select></label>
                <label class="text-xs font-medium text-slate-600">Volume<input type="number" name="volume" min="0" step="0.01" required class="mt-1 block w-full rounded-lg border-slate-300 text-sm"></label>
                <label class="text-xs font-medium text-slate-600">Volume Unit<select name="volume_unit" required class="mt-1 block w-full rounded-lg border-slate-300 text-sm"><option value="">Select unit</option>@foreach (\App\Enums\WlaVolumeUnit::cases() as $unit)<option value="{{ $unit->value }}">{{ $unit->value }}</option>@endforeach</select></label>
                <label class="text-xs font-medium text-slate-600">Time Allocated<input type="number" name="time_allocated" min="0.01" step="0.01" required class="mt-1 block w-full rounded-lg border-slate-300 text-sm"></label>
                <label class="text-xs font-medium text-slate-600">Time Unit<select name="time_unit" required class="mt-1 block w-full rounded-lg border-slate-300 text-sm"><option value="Hour">Hour</option></select></label>
                <label class="text-xs font-medium text-slate-600">Sort Order<input type="number" name="sort_order" min="0" value="{{ $wla->activities->isEmpty() ? 0 : $wla->activities->max('sort_order') + 1 }}" class="mt-1 block w-full rounded-lg border-slate-300 text-sm"></label>
                <label class="text-xs font-medium text-slate-600 sm:col-span-2 lg:col-span-4">Notes<textarea name="notes" rows="2" class="mt-1 block w-full rounded-lg border-slate-300 text-sm"></textarea></label>
            </div>
            <button class="mt-4 rounded-lg bg-slate-950 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-700">Add Row</button>
        </form>
    @endcan
</section>