<form method="POST" :action="formAction" enctype="multipart/form-data"
    class="mt-5 rounded-xl border border-blue-200 bg-blue-50/50 p-4 sm:p-5">
    @csrf
    <input type="hidden" name="_method" value="PUT" :disabled="formMode !== 'edit'">
    <input type="hidden" name="successor_id" x-model="form.successor_id" :disabled="formMode !== 'edit'">
    <input type="hidden" name="structure_position_key" x-model="form.structure_position_key">

    <div class="flex items-start justify-between gap-3">
        <div>
            <p class="text-xs font-bold uppercase tracking-[0.16em] text-blue-700"
                x-text="formMode === 'edit' ? 'Edit Successor' : 'Tambah Successor'"></p>
            <h4 class="mt-1 font-display text-lg font-bold text-slate-950" x-text="selectedPosition?.name"></h4>
        </div>
        <button type="button" @click="showSuccessorForm = false" aria-label="Tutup formulir successor"
            class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-lg border border-slate-200 bg-white text-slate-500 hover:bg-slate-100 hover:text-slate-800">
            <svg class="h-4 w-4" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                <path d="M5 5l10 10M15 5 5 15" stroke-linecap="round" />
            </svg>
        </button>
    </div>

    @if ($errors->getBag($activeSuccessorErrorBag)->any())
        <div class="mt-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
            Periksa kembali data Successor yang dimasukkan.
        </div>
    @endif

    <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
        <div class="sm:col-span-2">
            <label for="successor-photo" class="text-sm font-semibold text-slate-700">Foto</label>
            <input id="successor-photo" x-ref="successorPhoto" name="photo" type="file" accept="image/jpeg,image/png,image/webp"
                class="mt-1 block w-full cursor-pointer rounded-lg border border-slate-300 bg-white text-sm text-slate-600 file:mr-4 file:border-0 file:border-r file:border-slate-200 file:bg-slate-50 file:px-4 file:py-2.5 file:text-sm file:font-semibold file:text-blue-700 hover:file:bg-blue-50">
            <p class="mt-1 text-xs text-slate-500" x-text="formMode === 'edit'
                ? 'Kosongkan jika tidak ingin mengganti foto. Format JPG, PNG, atau WebP, maksimal 2 MB.'
                : 'Format JPG, PNG, atau WebP, maksimal 2 MB.'"></p>
            @if ($errors->getBag($activeSuccessorErrorBag)->has('photo'))
                <p class="mt-1 text-xs text-red-600">{{ $errors->getBag($activeSuccessorErrorBag)->first('photo') }}</p>
            @endif
        </div>

        <div>
            <label for="successor-name" class="text-sm font-semibold text-slate-700">Nama</label>
            <input id="successor-name" name="name" x-model="form.name" type="text" required maxlength="255"
                class="mt-1 w-full border-slate-300 text-sm" placeholder="Nama kandidat successor">
            @if ($errors->getBag($activeSuccessorErrorBag)->has('name'))
                <p class="mt-1 text-xs text-red-600">{{ $errors->getBag($activeSuccessorErrorBag)->first('name') }}</p>
            @endif
        </div>

        <div>
            <label for="successor-position" class="text-sm font-semibold text-slate-700">Position</label>
            <input id="successor-position" name="position" x-model="form.position" type="text" required maxlength="255"
                class="mt-1 w-full border-slate-300 text-sm" placeholder="Posisi kandidat saat ini">
            @if ($errors->getBag($activeSuccessorErrorBag)->has('position'))
                <p class="mt-1 text-xs text-red-600">{{ $errors->getBag($activeSuccessorErrorBag)->first('position') }}</p>
            @endif
        </div>

        <div>
            <label for="successor-talent-mapping" class="text-sm font-semibold text-slate-700">Talent Mapping</label>
            <input id="successor-talent-mapping" name="talent_mapping" x-model="form.talent_mapping" type="text" required maxlength="100"
                class="mt-1 w-full border-slate-300 text-sm" placeholder="Contoh: High Potential">
            @if ($errors->getBag($activeSuccessorErrorBag)->has('talent_mapping'))
                <p class="mt-1 text-xs text-red-600">{{ $errors->getBag($activeSuccessorErrorBag)->first('talent_mapping') }}</p>
            @endif
        </div>

        <div>
            <label for="successor-readiness" class="text-sm font-semibold text-slate-700">Succession Readiness</label>
            <input id="successor-readiness" name="succession_readiness" x-model="form.succession_readiness" type="text" required maxlength="100"
                class="mt-1 w-full border-slate-300 text-sm" placeholder="Contoh: Ready Now">
            @if ($errors->getBag($activeSuccessorErrorBag)->has('succession_readiness'))
                <p class="mt-1 text-xs text-red-600">{{ $errors->getBag($activeSuccessorErrorBag)->first('succession_readiness') }}</p>
            @endif
        </div>

        <div class="sm:col-span-2">
            <label for="successor-idp" class="text-sm font-semibold text-slate-700">IDP</label>
            <textarea id="successor-idp" name="idp" x-model="form.idp" rows="3" required maxlength="2000"
                class="mt-1 w-full border-slate-300 text-sm" placeholder="Rencana pengembangan individu"></textarea>
            @if ($errors->getBag($activeSuccessorErrorBag)->has('idp'))
                <p class="mt-1 text-xs text-red-600">{{ $errors->getBag($activeSuccessorErrorBag)->first('idp') }}</p>
            @endif
        </div>

        <div>
            <label for="successor-education" class="text-sm font-semibold text-slate-700">Pendidikan</label>
            <input id="successor-education" name="education" x-model="form.education" type="text" required maxlength="255"
                class="mt-1 w-full border-slate-300 text-sm" placeholder="Pendidikan terakhir">
            @if ($errors->getBag($activeSuccessorErrorBag)->has('education'))
                <p class="mt-1 text-xs text-red-600">{{ $errors->getBag($activeSuccessorErrorBag)->first('education') }}</p>
            @endif
        </div>

        <div>
            <label for="successor-tenure" class="text-sm font-semibold text-slate-700">Masa Kerja</label>
            <input id="successor-tenure" name="tenure" x-model="form.tenure" type="text" required maxlength="100"
                class="mt-1 w-full border-slate-300 text-sm" placeholder="Contoh: 10 tahun">
            @if ($errors->getBag($activeSuccessorErrorBag)->has('tenure'))
                <p class="mt-1 text-xs text-red-600">{{ $errors->getBag($activeSuccessorErrorBag)->first('tenure') }}</p>
            @endif
        </div>

        <div class="sm:col-span-2">
            <label for="successor-directorate" class="text-sm font-semibold text-slate-700">Direktorat</label>
            <input id="successor-directorate" name="directorate" x-model="form.directorate" type="text" required maxlength="255"
                class="mt-1 w-full border-slate-300 text-sm" placeholder="Direktorat kandidat">
            @if ($errors->getBag($activeSuccessorErrorBag)->has('directorate'))
                <p class="mt-1 text-xs text-red-600">{{ $errors->getBag($activeSuccessorErrorBag)->first('directorate') }}</p>
            @endif
        </div>
    </div>

    <div class="mt-5 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
        <button type="button" @click="showSuccessorForm = false" class="ui-button ui-button-secondary">Batal</button>
        <button type="submit" class="ui-button ui-button-primary">
            <span x-text="formMode === 'edit' ? 'Simpan Perubahan' : 'Tambah Successor'"></span>
        </button>
    </div>
</form>
