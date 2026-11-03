@props([
    'formKey',
    'description' => 'Pilih organisasi dan jabatan tujuan.',
    'showErrors' => false,
])

<section aria-labelledby="{{ $formKey }}-target-organization-heading"
    class="min-w-0 rounded-2xl border border-sky-200 bg-sky-50/50 p-4 sm:p-5">
    <div class="flex items-start gap-3">
        <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-slate-950 text-white shadow-sm">
            <x-dashboard-icon name="formasi" class="h-5 w-5" />
        </span>
        <div>
            <h4 id="{{ $formKey }}-target-organization-heading" class="font-display text-base font-bold text-slate-950">Organisasi Tujuan</h4>
            <p class="mt-1 text-xs leading-5 text-slate-500">{{ $description }}</p>
        </div>
    </div>

    <div class="mt-4 space-y-3">
        @foreach (['directorate' => 'Directorate', 'department' => 'Department', 'unit' => 'Unit', 'section' => 'Section', 'position' => 'Position'] as $field => $label)
            <div class="grid gap-1.5 sm:grid-cols-[7rem_minmax(0,1fr)] sm:items-center">
                <label for="{{ $formKey }}-new-{{ $field }}" class="text-xs font-bold text-slate-700">
                    {{ $label }}
                    @if ($field === 'section') <span class="font-normal text-slate-400">(opsional)</span> @endif
                </label>
                <div>
                    @if ($field === 'directorate')
                        <select id="{{ $formKey }}-new-directorate" name="new_directorate_id" x-model="newDirectorateId"
                            @change="newDepartmentId = ''; newUnitId = ''; newSectionId = ''; newPositionId = ''" required
                            class="block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-sky-500 focus:ring-sky-500">
                            <option value="">Pilih Directorate</option>
                            <template x-for="item in directorates" :key="item.id"><option :value="item.id" x-text="item.name"></option></template>
                        </select>
                    @elseif ($field === 'department')
                        <select id="{{ $formKey }}-new-department" name="new_department_id" x-model="newDepartmentId"
                            @change="newUnitId = ''; newSectionId = ''; newPositionId = ''" :disabled="!newDirectorateId" required
                            class="block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-sky-500 focus:ring-sky-500 disabled:cursor-not-allowed disabled:bg-slate-100">
                            <option value="" x-text="!newDirectorateId ? 'Pilih Directorate terlebih dahulu' : (availableDepartments().length ? 'Pilih Department' : 'Department tidak tersedia')"></option>
                            <template x-for="item in availableDepartments()" :key="item.id"><option :value="item.id" x-text="item.name"></option></template>
                        </select>
                    @elseif ($field === 'unit')
                        <select id="{{ $formKey }}-new-unit" name="new_unit_id" x-model="newUnitId"
                            @change="newSectionId = ''; newPositionId = ''" :disabled="!newDepartmentId" required
                            class="block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-sky-500 focus:ring-sky-500 disabled:cursor-not-allowed disabled:bg-slate-100">
                            <option value="" x-text="!newDepartmentId ? 'Pilih Department terlebih dahulu' : (availableUnits().length ? 'Pilih Unit' : 'Unit tidak tersedia')"></option>
                            <template x-for="item in availableUnits()" :key="item.id"><option :value="item.id" x-text="item.name"></option></template>
                        </select>
                    @elseif ($field === 'section')
                        <select id="{{ $formKey }}-new-section" name="new_section_id" x-model="newSectionId"
                            @change="newPositionId = ''" :disabled="!newUnitId"
                            class="block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-sky-500 focus:ring-sky-500 disabled:cursor-not-allowed disabled:bg-slate-100">
                            <option value="" x-text="!newUnitId ? 'Pilih Unit terlebih dahulu' : 'Tanpa Section'"></option>
                            <template x-for="item in availableSections()" :key="item.id"><option :value="item.id" x-text="item.name"></option></template>
                        </select>
                    @else
                        <select id="{{ $formKey }}-new-position" name="new_position_id" x-model="newPositionId" :disabled="!newUnitId" required
                            class="block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-sky-500 focus:ring-sky-500 disabled:cursor-not-allowed disabled:bg-slate-100">
                            <option value="" x-text="!newUnitId ? 'Pilih Unit terlebih dahulu' : (availablePositions().length ? 'Pilih Position' : 'Position tidak tersedia')"></option>
                            <template x-for="item in availablePositions()" :key="item.id"><option :value="item.id" x-text="item.name"></option></template>
                        </select>
                    @endif
                    @if ($showErrors)
                        @error('new_'.$field.'_id')<p class="mt-1 text-xs font-semibold text-red-600">{{ $message }}</p>@enderror
                    @endif
                </div>
            </div>
        @endforeach
    </div>
</section>
