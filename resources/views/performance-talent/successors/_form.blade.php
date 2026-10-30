<form method="POST" :action="formAction" enctype="multipart/form-data"
    class="mt-5 rounded-xl border border-blue-200 bg-blue-50/50 p-4 sm:p-5">
    @csrf
    <input type="hidden" name="_method" value="PUT" :disabled="formMode !== 'edit'">
    <input type="hidden" name="successor_id" x-model="form.successor_id" :disabled="formMode !== 'edit'">
    <input type="hidden" name="target_position_id" x-model="form.target_position_id">

    <div class="flex items-start justify-between gap-3">
        <div>
            <p class="text-xs font-bold uppercase tracking-[0.16em] text-blue-700"
                x-text="formMode === 'edit' ? 'Edit Successor' : 'Tambah Successor'"></p>
            <h4 class="mt-1 font-display text-lg font-bold text-slate-950" x-text="selectedPosition?.name"></h4>
        </div>
        <button type="button" @click="showSuccessorForm = false" aria-label="Tutup formulir successor"
            class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-slate-200 bg-white text-slate-500">×</button>
    </div>

    @if ($errors->getBag($activeSuccessorErrorBag)->any())
        <div class="mt-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
            Periksa kembali data Successor yang dimasukkan.
        </div>
    @endif

    <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
        <div class="sm:col-span-2">
            <label for="successor-employee" class="text-sm font-semibold text-slate-700">Pegawai</label>
            <select id="successor-employee" name="employee_id" x-model="form.employee_id" required
                class="mt-1 w-full border-slate-300 text-sm">
                <option value="">Pilih pegawai dari Data Pegawai</option>
                @foreach ($employees as $employee)
                    <option value="{{ $employee->id }}">
                        {{ $employee->name }} — {{ $employee->organizationPositionName() ?? 'Tanpa posisi' }} ({{ $employee->sap }})
                    </option>
                @endforeach
            </select>
            @if ($errors->getBag($activeSuccessorErrorBag)->has('employee_id'))
                <p class="mt-1 text-xs text-red-600">{{ $errors->getBag($activeSuccessorErrorBag)->first('employee_id') }}</p>
            @endif
        </div>

        <div class="sm:col-span-2">
            <label for="successor-photo" class="text-sm font-semibold text-slate-700">Foto</label>
            <input id="successor-photo" x-ref="successorPhoto" name="photo" type="file"
                accept="image/jpeg,image/png,image/webp"
                class="mt-1 block w-full rounded-lg border border-slate-300 bg-white text-sm">
            @if ($errors->getBag($activeSuccessorErrorBag)->has('photo'))
                <p class="mt-1 text-xs text-red-600">{{ $errors->getBag($activeSuccessorErrorBag)->first('photo') }}</p>
            @endif
        </div>

        <div>
            <label for="successor-talent-mapping" class="text-sm font-semibold text-slate-700">Talent Mapping</label>
            <input id="successor-talent-mapping" name="talent_mapping" x-model="form.talent_mapping" required maxlength="100"
                class="mt-1 w-full border-slate-300 text-sm">
            @if ($errors->getBag($activeSuccessorErrorBag)->has('talent_mapping'))
                <p class="mt-1 text-xs text-red-600">{{ $errors->getBag($activeSuccessorErrorBag)->first('talent_mapping') }}</p>
            @endif
        </div>

        <div>
            <label for="successor-readiness" class="text-sm font-semibold text-slate-700">Succession Readiness</label>
            <input id="successor-readiness" name="succession_readiness" x-model="form.succession_readiness" required maxlength="100"
                class="mt-1 w-full border-slate-300 text-sm">
            @if ($errors->getBag($activeSuccessorErrorBag)->has('succession_readiness'))
                <p class="mt-1 text-xs text-red-600">{{ $errors->getBag($activeSuccessorErrorBag)->first('succession_readiness') }}</p>
            @endif
        </div>

        <div class="sm:col-span-2">
            <label for="successor-idp" class="text-sm font-semibold text-slate-700">IDP</label>
            <textarea id="successor-idp" name="idp" x-model="form.idp" rows="3" required maxlength="2000"
                class="mt-1 w-full border-slate-300 text-sm"></textarea>
            @if ($errors->getBag($activeSuccessorErrorBag)->has('idp'))
                <p class="mt-1 text-xs text-red-600">{{ $errors->getBag($activeSuccessorErrorBag)->first('idp') }}</p>
            @endif
        </div>
    </div>

    <div class="mt-5 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
        <button type="button" @click="showSuccessorForm = false" class="ui-button ui-button-secondary">Batal</button>
        <button type="submit" class="ui-button ui-button-primary">Simpan Successor</button>
    </div>
</form>
