<form method="POST" action="{{ $action }}"
    class="ui-surface space-y-5 p-5 sm:p-6">
    @csrf
    @if ($method !== 'POST')
        @method($method)
    @endif
    <div><label for="department_id" class="text-sm font-semibold text-slate-700">Department <span class="text-red-500" aria-hidden="true">*</span></label><select
            id="department_id" name="department_id" required
            class="mt-1 block w-full rounded-lg border-slate-300 text-sm focus:border-amber-400 focus:ring-amber-400">
            <option value="">Pilih Department</option>
            @foreach ($departments as $department)
                <option value="{{ $department->id }}" @selected(old('department_id', $selectedDepartmentId) == $department->id)>
                    {{ $department->name }}{{ $department->active ? '' : ' (Nonaktif)' }}
                </option>
            @endforeach
        </select>
        @error('department_id')
            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
        @enderror
    </div>
    <div><label for="code" class="text-sm font-semibold text-slate-700">Kode <span class="text-red-500" aria-hidden="true">*</span></label><input id="code"
            name="code" value="{{ old('code', $unit?->code) }}" required maxlength="50"
            class="mt-1 block w-full rounded-lg border-slate-300 text-sm focus:border-amber-400 focus:ring-amber-400">
        @error('code')
            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
        @enderror
    </div>
    <div><label for="name" class="text-sm font-semibold text-slate-700">Nama <span class="text-red-500" aria-hidden="true">*</span></label><input id="name"
            name="name" value="{{ old('name', $unit?->name) }}" required maxlength="255"
            class="mt-1 block w-full rounded-lg border-slate-300 text-sm focus:border-amber-400 focus:ring-amber-400">
        @error('name')
            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
        @enderror
    </div>
    <div><input type="hidden" name="active" value="0"><label
            class="inline-flex items-center gap-2 text-sm font-medium text-slate-700"><input type="checkbox"
                name="active" value="1" @checked((bool) old('active', $unit?->active ?? true))
                class="rounded border-slate-300 text-amber-500 focus:ring-amber-400"> Aktif</label>
        @error('active')
            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
        @enderror
    </div>
    <div class="flex flex-col-reverse gap-3 border-t border-slate-100 pt-4 sm:flex-row sm:justify-end"><a
            href="{{ route('organization.units.index') }}"
            class="ui-button ui-button-secondary w-full sm:w-auto">Batal</a><button
            class="ui-button ui-button-primary w-full sm:w-auto">Simpan</button>
    </div>
</form>
