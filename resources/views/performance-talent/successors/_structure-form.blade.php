<form x-ref="structureFormElement" method="POST" :action="structureFormAction"
    class="mt-5 rounded-xl border border-blue-200 bg-blue-50/50 p-4 sm:p-5">
    @csrf
    <input type="hidden" name="_method" value="PUT" :disabled="structureFormMode !== 'edit'">
    <input type="hidden" name="structure_position_id" x-model="structureForm.structure_position_id"
        :disabled="structureFormMode !== 'edit'">
    <input type="hidden" name="department_id" x-model="structureForm.department_id">

    <div class="flex items-start justify-between gap-3">
        <div>
            <p class="text-xs font-bold uppercase tracking-[0.16em] text-blue-700"
                x-text="structureFormMode === 'edit' ? 'Edit Posisi' : 'Tambah Posisi'"></p>
            <h4 class="mt-1 font-display text-lg font-bold text-slate-950" x-text="selectedDepartment"></h4>
        </div>
        <button type="button" @click="showStructureForm = false" aria-label="Tutup formulir struktur"
            class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-lg border border-slate-200 bg-white text-slate-500 hover:bg-slate-100 hover:text-slate-800">
            <svg class="h-4 w-4" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                <path d="M5 5l10 10M15 5 5 15" stroke-linecap="round" />
            </svg>
        </button>
    </div>

    @if ($errors->getBag($activeStructureErrorBag)->any())
        <div class="mt-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
            Periksa kembali data posisi struktur yang dimasukkan.
        </div>
    @endif

    <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
        <div class="sm:col-span-2">
            <label for="structure-parent" class="text-sm font-semibold text-slate-700">Posisi Atasan</label>
            <select id="structure-parent" name="parent_id" x-model="structureForm.parent_id"
                class="mt-1 w-full border-slate-300 text-sm">
                <option value="">Tanpa atasan (posisi paling atas)</option>
                <template x-for="position in structureParentOptions" :key="position.id">
                    <option :value="String(position.id)" x-text="`${position.level} — ${position.name}`"></option>
                </template>
            </select>
            <p class="mt-1 text-xs text-slate-500">Satu department hanya dapat memiliki satu posisi paling atas.</p>
            @if ($errors->getBag($activeStructureErrorBag)->has('parent_id'))
                <p class="mt-1 text-xs text-red-600">{{ $errors->getBag($activeStructureErrorBag)->first('parent_id') }}</p>
            @endif
        </div>

        <div>
            <label for="structure-level" class="text-sm font-semibold text-slate-700">Level</label>
            <input id="structure-level" name="level" x-model="structureForm.level" type="text" list="structure-level-options"
                required maxlength="100" class="mt-1 w-full border-slate-300 text-sm" placeholder="Contoh: Senior Manager">
            <datalist id="structure-level-options">
                <option value="General Manager"></option>
                <option value="Senior Manager"></option>
                <option value="Manager"></option>
                <option value="Staff"></option>
            </datalist>
            @if ($errors->getBag($activeStructureErrorBag)->has('level'))
                <p class="mt-1 text-xs text-red-600">{{ $errors->getBag($activeStructureErrorBag)->first('level') }}</p>
            @endif
        </div>

        <div>
            <label for="structure-name" class="text-sm font-semibold text-slate-700">Nama Posisi</label>
            <input id="structure-name" name="name" x-model="structureForm.name" type="text" required maxlength="255"
                class="mt-1 w-full border-slate-300 text-sm" placeholder="Nama posisi dalam struktur">
            @if ($errors->getBag($activeStructureErrorBag)->has('name'))
                <p class="mt-1 text-xs text-red-600">{{ $errors->getBag($activeStructureErrorBag)->first('name') }}</p>
            @endif
        </div>

        <div>
            <label for="structure-formation-count" class="text-sm font-semibold text-slate-700">Jumlah Formasi</label>
            <input id="structure-formation-count" name="formation_count" x-model="structureForm.formation_count"
                type="number" required min="1" max="999" class="mt-1 w-full border-slate-300 text-sm">
            @if ($errors->getBag($activeStructureErrorBag)->has('formation_count'))
                <p class="mt-1 text-xs text-red-600">{{ $errors->getBag($activeStructureErrorBag)->first('formation_count') }}</p>
            @endif
        </div>

        <div>
            <label for="structure-sort-order" class="text-sm font-semibold text-slate-700">Urutan</label>
            <input id="structure-sort-order" name="sort_order" x-model="structureForm.sort_order"
                type="number" required min="0" max="999" class="mt-1 w-full border-slate-300 text-sm">
            <p class="mt-1 text-xs text-slate-500">Angka lebih kecil ditampilkan lebih dahulu.</p>
            @if ($errors->getBag($activeStructureErrorBag)->has('sort_order'))
                <p class="mt-1 text-xs text-red-600">{{ $errors->getBag($activeStructureErrorBag)->first('sort_order') }}</p>
            @endif
        </div>

        <label class="flex items-start gap-3 rounded-lg border border-slate-200 bg-white px-3 py-3 sm:col-span-2">
            <input type="hidden" name="align_with_grandchildren" value="0">
            <input name="align_with_grandchildren" x-model="structureForm.align_with_grandchildren" type="checkbox" value="1"
                class="mt-0.5 rounded border-slate-300 text-blue-600 focus:ring-blue-500">
            <span>
                <span class="block text-sm font-semibold text-slate-700">Sejajarkan dengan posisi dua tingkat di bawah</span>
                <span class="mt-0.5 block text-xs leading-5 text-slate-500">Gunakan untuk posisi Staff yang langsung berada di bawah posisi paling atas.</span>
            </span>
        </label>
    </div>

    <div class="mt-5 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
        <button type="button" @click="showStructureForm = false" class="ui-button ui-button-secondary">Batal</button>
        <button type="submit" class="ui-button ui-button-primary">
            <span x-text="structureFormMode === 'edit' ? 'Simpan Perubahan' : 'Tambah Posisi'"></span>
        </button>
    </div>
</form>
