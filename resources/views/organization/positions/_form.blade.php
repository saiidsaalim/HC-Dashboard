<form method="POST" action="{{ $action }}"
    x-data="{
        directorateId: @js((string) old('directorate_id', $selectedDirectorateId)),
        departmentId: @js((string) old('department_id', $selectedDepartmentId)),
        unitId: @js((string) old('unit_id', $selectedUnitId)),
        sectionId: @js((string) old('section_id', $selectedSectionId)),
        departments: @js($departments->map(fn ($department) => [
            'id' => $department->id,
            'directorate_id' => $department->directorate_id,
            'label' => $department->name.($department->active ? '' : ' (Nonaktif)'),
        ])->values()),
        units: @js($units->map(fn ($unit) => [
            'id' => $unit->id,
            'department_id' => $unit->department_id,
            'label' => $unit->name.($unit->active ? '' : ' (Nonaktif)'),
        ])->values()),
        sections: @js($sections->map(fn ($section) => [
            'id' => $section->id,
            'unit_id' => $section->unit_id,
            'label' => $section->name.($section->active ? '' : ' (Nonaktif)'),
        ])->values()),
    }"
    class="ui-surface space-y-5 p-5 sm:p-6">
    @csrf
    @if ($method !== 'POST')
        @method($method)
    @endif

    <div>
        <label for="directorate_id" class="text-sm font-semibold text-slate-700">Directorate</label>
        <select id="directorate_id" name="directorate_id" x-model="directorateId" x-on:change="departmentId = ''; unitId = ''; sectionId = ''"
            class="mt-1 block w-full rounded-lg border-slate-300 text-sm focus:border-amber-400 focus:ring-amber-400">
            <option value="">Tanpa Directorate (data lama)</option>
            @foreach ($directorates as $directorate)
                <option value="{{ $directorate->id }}">
                    {{ $directorate->name }}{{ $directorate->active ? '' : ' (Nonaktif)' }}
                </option>
            @endforeach
        </select>
        @error('directorate_id')
            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
        @enderror
    </div>

    <div>
        <label for="department_id" class="text-sm font-semibold text-slate-700">Department <span class="text-red-500" aria-hidden="true">*</span></label>
        <select id="department_id" name="department_id" x-model="departmentId" x-on:change="unitId = ''; sectionId = ''" required
            class="mt-1 block w-full rounded-lg border-slate-300 text-sm focus:border-amber-400 focus:ring-amber-400">
            <option value="">Pilih Department</option>
            <template x-for="department in departments.filter(item => String(item.directorate_id ?? '') === String(directorateId))" :key="department.id">
                <option :value="String(department.id)" x-text="department.label"></option>
            </template>
        </select>
        @error('department_id')
            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
        @enderror
    </div>

    <div>
        <label for="unit_id" class="text-sm font-semibold text-slate-700">Unit <span class="text-red-500" aria-hidden="true">*</span></label>
        <select id="unit_id" name="unit_id" x-model="unitId" x-on:change="sectionId = ''" :disabled="!departmentId" required
            class="mt-1 block w-full rounded-lg border-slate-300 text-sm focus:border-amber-400 focus:ring-amber-400 disabled:cursor-not-allowed disabled:bg-slate-100 disabled:text-slate-400">
            <option value="" x-text="departmentId ? 'Pilih Unit' : 'Pilih Department terlebih dahulu'"></option>
            <template x-for="unit in units.filter(item => String(item.department_id) === String(departmentId))" :key="unit.id">
                <option :value="String(unit.id)" x-text="unit.label"></option>
            </template>
        </select>
        @error('unit_id')
            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
        @enderror
    </div>

    <div>
        <label for="section_id" class="text-sm font-semibold text-slate-700">Section</label>
        <select id="section_id" name="section_id" x-model="sectionId" :disabled="!unitId"
            class="mt-1 block w-full rounded-lg border-slate-300 text-sm focus:border-amber-400 focus:ring-amber-400 disabled:bg-slate-100">
            <option value="" x-text="unitId ? 'Tanpa Section (data lama)' : 'Pilih Unit terlebih dahulu'"></option>
            <template x-for="section in sections.filter(item => String(item.unit_id) === String(unitId))" :key="section.id">
                <option :value="String(section.id)" x-text="section.label"></option>
            </template>
        </select>
        @error('section_id')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
    </div>

    <div>
        <label for="code" class="text-sm font-semibold text-slate-700">Kode <span class="text-red-500" aria-hidden="true">*</span></label>
        <input id="code" name="code" value="{{ old('code', $position?->code) }}" required maxlength="50"
            class="mt-1 block w-full rounded-lg border-slate-300 text-sm focus:border-amber-400 focus:ring-amber-400">
        @error('code')
            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
        @enderror
    </div>

    <div>
        <label for="name" class="text-sm font-semibold text-slate-700">Nama <span class="text-red-500" aria-hidden="true">*</span></label>
        <input id="name" name="name" value="{{ old('name', $position?->name) }}" required maxlength="255"
            class="mt-1 block w-full rounded-lg border-slate-300 text-sm focus:border-amber-400 focus:ring-amber-400">
        @error('name')
            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
        @enderror
    </div>

    <div>
        <input type="hidden" name="active" value="0">
        <label class="inline-flex items-center gap-2 text-sm font-medium text-slate-700">
            <input type="checkbox" name="active" value="1" @checked((bool) old('active', $position?->active ?? true))
                class="rounded border-slate-300 text-amber-500 focus:ring-amber-400">
            Aktif
        </label>
        @error('active')
            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
        @enderror
    </div>

    <div class="flex flex-col-reverse gap-3 border-t border-slate-100 pt-4 sm:flex-row sm:justify-end">
        <a href="{{ route('organization.positions.index') }}"
            class="ui-button ui-button-secondary w-full sm:w-auto">Batal</a>
        <button class="ui-button ui-button-primary w-full sm:w-auto">Simpan</button>
    </div>
</form>
