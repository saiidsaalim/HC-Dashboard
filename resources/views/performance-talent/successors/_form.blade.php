<form method="POST" :action="formAction" enctype="multipart/form-data"
    class="mt-5 rounded-xl border border-blue-200 bg-blue-50/50 p-4 dark:border-blue-900/70 dark:bg-slate-900/90 sm:p-5"
    data-successor-form>
    @csrf
    <input type="hidden" name="_method" value="PUT" :disabled="formMode !== 'edit'">
    <input type="hidden" name="successor_id" x-model="form.successor_id" :disabled="formMode !== 'edit'">
    <input type="hidden" name="target_position_id" x-model="form.target_position_id">

    <div class="flex items-start justify-between gap-3">
        <div>
            <p class="text-xs font-bold uppercase tracking-[0.16em] text-blue-700 dark:text-sky-400"
                x-text="formMode === 'edit' ? 'Edit Successor' : 'Tambah Successor'"></p>
            <h4 class="mt-1 font-display text-lg font-bold text-slate-950 dark:text-white" x-text="selectedPosition?.name"></h4>
        </div>
        <button type="button" @click="showSuccessorForm = false" aria-label="Tutup formulir successor"
            class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-slate-200 bg-white text-slate-500 transition hover:bg-slate-50 hover:text-slate-700 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-400 dark:hover:bg-slate-700 dark:hover:text-white">&times;</button>
    </div>

    @if ($errors->getBag($activeSuccessorErrorBag)->any())
        <div class="mt-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700 dark:border-red-900/70 dark:bg-red-950/40 dark:text-red-300">
            Periksa kembali data Successor yang dimasukkan.
        </div>
    @endif

    <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
        <div>
            <label for="successor-name" class="text-sm font-semibold text-slate-700 dark:text-slate-300">Name</label>
            <input id="successor-name" name="name" x-model="form.name" required maxlength="255"
                class="mt-1 w-full border-slate-300 text-sm dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100 dark:focus:border-sky-500 dark:focus:ring-sky-500">
            @if ($errors->getBag($activeSuccessorErrorBag)->has('name'))
                <p class="mt-1 text-xs text-red-600">{{ $errors->getBag($activeSuccessorErrorBag)->first('name') }}</p>
            @endif
        </div>

        <div>
            <label for="successor-position" class="text-sm font-semibold text-slate-700 dark:text-slate-300">Position</label>
            <input id="successor-position" name="position" x-model="form.position" required maxlength="255"
                class="mt-1 w-full border-slate-300 text-sm dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100 dark:focus:border-sky-500 dark:focus:ring-sky-500">
            @if ($errors->getBag($activeSuccessorErrorBag)->has('position'))
                <p class="mt-1 text-xs text-red-600">{{ $errors->getBag($activeSuccessorErrorBag)->first('position') }}</p>
            @endif
        </div>

        <div class="sm:col-span-2">
            <label for="successor-photo" class="text-sm font-semibold text-slate-700 dark:text-slate-300">Foto</label>
            <input id="successor-photo" x-ref="successorPhoto" name="photo" type="file"
                accept="image/jpeg,image/png,image/webp"
                class="mt-1 block w-full rounded-lg border border-slate-300 bg-white text-sm dark:border-slate-600 dark:bg-slate-950 dark:text-slate-300 dark:file:bg-slate-800 dark:file:text-slate-200">
            @if ($errors->getBag($activeSuccessorErrorBag)->has('photo'))
                <p class="mt-1 text-xs text-red-600">{{ $errors->getBag($activeSuccessorErrorBag)->first('photo') }}</p>
            @endif
        </div>

        <div>
            <label for="successor-talent-mapping" class="text-sm font-semibold text-slate-700 dark:text-slate-300">Talent Mapping</label>
            <input id="successor-talent-mapping" name="talent_mapping" x-model="form.talent_mapping" required maxlength="100"
                class="mt-1 w-full border-slate-300 text-sm dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100 dark:focus:border-sky-500 dark:focus:ring-sky-500">
            @if ($errors->getBag($activeSuccessorErrorBag)->has('talent_mapping'))
                <p class="mt-1 text-xs text-red-600">{{ $errors->getBag($activeSuccessorErrorBag)->first('talent_mapping') }}</p>
            @endif
        </div>

        <div>
            <label for="successor-readiness" class="text-sm font-semibold text-slate-700 dark:text-slate-300">Succession Readiness</label>
            <input id="successor-readiness" name="succession_readiness" x-model="form.succession_readiness" required maxlength="100"
                class="mt-1 w-full border-slate-300 text-sm dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100 dark:focus:border-sky-500 dark:focus:ring-sky-500">
            @if ($errors->getBag($activeSuccessorErrorBag)->has('succession_readiness'))
                <p class="mt-1 text-xs text-red-600">{{ $errors->getBag($activeSuccessorErrorBag)->first('succession_readiness') }}</p>
            @endif
        </div>

        <div>
            <label for="successor-idp" class="text-sm font-semibold text-slate-700 dark:text-slate-300">IDP (%)</label>
            <input id="successor-idp" name="idp" x-model="form.idp" type="number" min="0" max="100" required
                class="mt-1 w-full border-slate-300 text-sm dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100 dark:focus:border-sky-500 dark:focus:ring-sky-500">
            @if ($errors->getBag($activeSuccessorErrorBag)->has('idp'))
                <p class="mt-1 text-xs text-red-600">{{ $errors->getBag($activeSuccessorErrorBag)->first('idp') }}</p>
            @endif
        </div>

        <div>
            <label for="successor-education" class="text-sm font-semibold text-slate-700 dark:text-slate-300">Education</label>
            <input id="successor-education" name="education" x-model="form.education" required maxlength="255"
                class="mt-1 w-full border-slate-300 text-sm dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100 dark:focus:border-sky-500 dark:focus:ring-sky-500">
            @if ($errors->getBag($activeSuccessorErrorBag)->has('education'))
                <p class="mt-1 text-xs text-red-600">{{ $errors->getBag($activeSuccessorErrorBag)->first('education') }}</p>
            @endif
        </div>

        <div>
            <label for="successor-tenure" class="text-sm font-semibold text-slate-700 dark:text-slate-300">Tenure / Masa Kerja</label>
            <input id="successor-tenure" name="tenure" x-model="form.tenure" required maxlength="100"
                class="mt-1 w-full border-slate-300 text-sm dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100 dark:focus:border-sky-500 dark:focus:ring-sky-500">
            @if ($errors->getBag($activeSuccessorErrorBag)->has('tenure'))
                <p class="mt-1 text-xs text-red-600">{{ $errors->getBag($activeSuccessorErrorBag)->first('tenure') }}</p>
            @endif
        </div>

        <div>
            <label for="successor-directorate" class="text-sm font-semibold text-slate-700 dark:text-slate-300">Directorate</label>
            <input id="successor-directorate" name="directorate" x-model="form.directorate" required maxlength="255"
                class="mt-1 w-full border-slate-300 text-sm dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100 dark:focus:border-sky-500 dark:focus:ring-sky-500">
            @if ($errors->getBag($activeSuccessorErrorBag)->has('directorate'))
                <p class="mt-1 text-xs text-red-600">{{ $errors->getBag($activeSuccessorErrorBag)->first('directorate') }}</p>
            @endif
        </div>
    </div>

    <div class="mt-5 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
        <button type="button" @click="showSuccessorForm = false" class="ui-button ui-button-secondary">Batal</button>
        <button type="submit" class="ui-button ui-button-primary">Simpan Successor</button>
    </div>
</form>
