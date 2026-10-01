@php
    $assessment = $assessment ?? null;
    $efficiencyFactor = old('efficiency_factor', $assessment?->efficiency_factor ?? config('wla.default_efficiency_factor'));
@endphp

<form method="POST" action="{{ $action }}"
    x-data="{
        departmentId: @js((string) old('department_id', $assessment?->department_id ?? '')),
        unitId: @js((string) old('unit_id', $assessment?->unit_id ?? '')),
        positionId: @js((string) old('position_id', $assessment?->position_id ?? '')),
        units: @js($units->map(fn ($unit) => ['id' => $unit->id, 'department_id' => $unit->department_id, 'label' => $unit->code.' · '.$unit->name])->values()),
        positions: @js($positions->map(fn ($position) => ['id' => $position->id, 'unit_id' => $position->unit_id, 'label' => $position->code.' · '.$position->name])->values()),
        resetDepartment() { this.unitId = ''; this.positionId = ''; },
        resetUnit() { this.positionId = ''; },
    }"
    class="space-y-6">
    @csrf
    @if ($method !== 'POST')
        @method($method)
    @endif
    @if ($departments->isEmpty() || $units->isEmpty() || $positions->isEmpty() || $workSchedules->isEmpty() || $workCalendars->isEmpty())
        <div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
            WLA draft requires Organization, Work Schedule, and Work Calendar master records. No default production calendar is created from the Excel template.
            @can('create', \App\Models\WorkCalendar::class)
                <a href="{{ route('work-calendars.create') }}" class="ml-1 font-semibold underline">Add Work Calendar</a>
            @endcan
        </div>
    @endif

    <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
        <h3 class="font-display text-lg font-bold text-slate-950">1. Organization</h3>
        <div class="mt-5 grid gap-4 sm:grid-cols-2">
            <label class="block text-sm font-medium text-slate-700">Period / Year
                <input type="number" name="period" value="{{ old('period', $assessment?->period) }}" required min="1900" max="2100" class="mt-1 block w-full rounded-lg border-slate-300 text-sm focus:border-amber-400 focus:ring-amber-400">
                @error('period')<span class="mt-1 block text-xs text-red-600">{{ $message }}</span>@enderror
            </label>
            <label class="block text-sm font-medium text-slate-700">Department
                <select name="department_id" x-model="departmentId" @change="resetDepartment()" required class="mt-1 block w-full rounded-lg border-slate-300 text-sm focus:border-amber-400 focus:ring-amber-400">
                    <option value="">Select Department</option>
                    @foreach ($departments as $department)
                        <option value="{{ $department->id }}">{{ $department->code }} · {{ $department->name }}{{ $department->active ? '' : ' (Inactive)' }}</option>
                    @endforeach
                </select>
                @error('department_id')<span class="mt-1 block text-xs text-red-600">{{ $message }}</span>@enderror
            </label>
            <label class="block text-sm font-medium text-slate-700">Unit
                <select name="unit_id" x-model="unitId" @change="resetUnit()" :disabled="!departmentId" required class="mt-1 block w-full rounded-lg border-slate-300 text-sm focus:border-amber-400 focus:ring-amber-400 disabled:bg-slate-100">
                    <option value="">Select Unit</option>
                    <template x-for="unit in units.filter(item => String(item.department_id) === String(departmentId))" :key="unit.id">
                        <option :value="String(unit.id)" x-text="unit.label"></option>
                    </template>
                </select>
                @error('unit_id')<span class="mt-1 block text-xs text-red-600">{{ $message }}</span>@enderror
            </label>
            <label class="block text-sm font-medium text-slate-700">Position
                <select name="position_id" x-model="positionId" :disabled="!unitId" required class="mt-1 block w-full rounded-lg border-slate-300 text-sm focus:border-amber-400 focus:ring-amber-400 disabled:bg-slate-100">
                    <option value="">Select Position</option>
                    <template x-for="position in positions.filter(item => String(item.unit_id) === String(unitId))" :key="position.id">
                        <option :value="String(position.id)" x-text="position.label"></option>
                    </template>
                </select>
                @error('position_id')<span class="mt-1 block text-xs text-red-600">{{ $message }}</span>@enderror
            </label>
        </div>
    </section>

    <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
        <h3 class="font-display text-lg font-bold text-slate-950">2. Work Parameters</h3>
        <div class="mt-5 grid gap-4 sm:grid-cols-2">
            <label class="block text-sm font-medium text-slate-700">Work Schedule
                <select name="work_schedule_id" required class="mt-1 block w-full rounded-lg border-slate-300 text-sm focus:border-amber-400 focus:ring-amber-400">
                    <option value="">Select Work Schedule</option>
                    @foreach ($workSchedules as $schedule)
                        <option value="{{ $schedule->id }}" @selected(old('work_schedule_id', $assessment?->work_schedule_id) == $schedule->id)>
                            {{ $schedule->name }} · {{ $schedule->working_hours_per_day }} hours/day × {{ $schedule->working_days_per_week }} days/week = {{ $schedule->working_hours_per_week }} hours/week{{ $schedule->active ? '' : ' (Inactive)' }}
                        </option>
                    @endforeach
                </select>
                @error('work_schedule_id')<span class="mt-1 block text-xs text-red-600">{{ $message }}</span>@enderror
            </label>
            <label class="block text-sm font-medium text-slate-700">Work Calendar
                <select name="work_calendar_id" required class="mt-1 block w-full rounded-lg border-slate-300 text-sm focus:border-amber-400 focus:ring-amber-400">
                    <option value="">Select Work Calendar</option>
                    @foreach ($workCalendars as $calendar)
                        <option value="{{ $calendar->id }}" @selected(old('work_calendar_id', $assessment?->work_calendar_id) == $calendar->id)>
                            {{ $calendar->year }} · {{ $calendar->total_days }} days · annual leave {{ $calendar->annual_leave }} · national holiday {{ $calendar->national_holiday }} · common leave {{ $calendar->common_leave }}{{ $calendar->active ? '' : ' (Inactive)' }}
                        </option>
                    @endforeach
                </select>
                @error('work_calendar_id')<span class="mt-1 block text-xs text-red-600">{{ $message }}</span>@enderror
            </label>
            <div x-data="{ factor: @js((string) $efficiencyFactor) }" class="block text-sm font-medium text-slate-700">
                <label for="efficiency-factor-percent">Efficiency Factor</label>
                <div class="mt-1 flex items-center gap-2">
                    <input id="efficiency-factor-percent" type="number" min="0" max="100" step="0.01" required :value="(Number(factor) * 100).toFixed(2)" @input="factor = (Number($event.target.value) / 100).toFixed(4)" class="block w-full rounded-lg border-slate-300 text-sm focus:border-amber-400 focus:ring-amber-400">
                    <span class="text-sm text-slate-500">%</span>
                </div>
                <input type="hidden" name="efficiency_factor" :value="factor" value="{{ $efficiencyFactor }}">
                @error('efficiency_factor')<span class="mt-1 block text-xs text-red-600">{{ $message }}</span>@enderror
            </div>
        </div>
    </section>

    <div class="flex items-center justify-end gap-3">
        <a href="{{ route('wla') }}" class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Cancel</a>
        <button @disabled($departments->isEmpty() || $units->isEmpty() || $positions->isEmpty() || $workSchedules->isEmpty() || $workCalendars->isEmpty()) class="rounded-lg bg-slate-950 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-700 disabled:cursor-not-allowed disabled:opacity-50">Save Draft</button>
    </div>
</form>