@php
    $isEdit = $mode === 'edit';
    $formHasErrors = $errors->getBag($errorBag)->any();
    $positionOptionData = $positionOptions->map(fn ($position) => [
        'id' => (string) $position->id,
        'name' => $position->name,
        'code' => $position->code,
        'external_position_id' => $position->external_position_id,
        'job_level' => $position->job_level,
        'unit' => $position->unit->name,
        'department' => $position->unit->department->name,
        'directorate' => $position->unit->department->directorate?->name,
        'section' => $position->section?->name,
        'active' => $position->active,
        'critical_position_id' => $position->criticalPosition?->id === null ? null : (string) $position->criticalPosition->id,
        'label' => $position->name.' · '.$position->code.' · '.$position->unit->name.' / '.$position->unit->department->name,
        'search' => mb_strtolower(implode(' ', array_filter([
            $position->name,
            $position->code,
            $position->external_position_id,
            $position->unit->name,
            $position->unit->department->name,
        ]))),
    ])->values();
@endphp

<form method="POST" @if ($isEdit) x-bind:action="editAction" @else action="{{ $action }}" @endif
    x-data="{
        isEdit: @js($isEdit),
        positionId: @js($isEdit ? '' : (string) old('position_id', '')),
        search: '',
        dropdownOpen: false,
        loadingPreview: false,
        preview: null,
        options: @js($positionOptionData),
        previewUrlTemplate: @js(route('performance-talent.critical-positions.position-preview', ['position' => '__POSITION__'])),
        init() {
            if (this.isEdit) {
                this.$watch('editForm.position_id', (value) => {
                    const nextPositionId = String(value ?? '');
                    if (nextPositionId !== this.positionId) {
                        this.positionId = nextPositionId;
                        this.syncSearch();
                        this.loadPreview();
                    }
                });
                this.positionId = String(this.editForm.position_id ?? '');
            }
            this.syncSearch();
            this.loadPreview();
        },
        allowedOptions() {
            const criticalPositionId = this.isEdit ? String(this.editForm.id ?? '') : '';
            const term = this.search.trim().toLocaleLowerCase('id-ID');

            return this.options.filter((option) => {
                const available = option.critical_position_id === null || (this.isEdit && option.critical_position_id === criticalPositionId);
                return available && (term === '' || option.search.includes(term));
            }).slice(0, 30);
        },
        selectPosition(option) {
            this.positionId = String(option.id);
            if (this.isEdit) {
                this.editForm.position_id = this.positionId;
            }
            this.search = option.label;
            this.dropdownOpen = false;
            this.loadPreview();
        },
        handleSearchInput() {
            this.dropdownOpen = true;
            const selected = this.options.find((option) => String(option.id) === String(this.positionId));
            if (!selected || this.search !== selected.label) {
                this.positionId = '';
                this.preview = null;
            }
        },
        syncSearch() {
            const selected = this.options.find((option) => String(option.id) === String(this.positionId));
            this.search = selected?.label ?? '';
        },
        async loadPreview() {
            if (!this.positionId) {
                this.preview = null;
                return;
            }
            this.loadingPreview = true;
            try {
                const response = await fetch(this.previewUrlTemplate.replace('__POSITION__', encodeURIComponent(this.positionId)), {
                    headers: { 'Accept': 'application/json' },
                });
                this.preview = response.ok ? await response.json() : null;
            } finally {
                this.loadingPreview = false;
            }
        },
    }"
    class="flex min-h-0 flex-1 flex-col">
    @csrf
    @if ($isEdit)
        @method('PUT')
        <input type="hidden" name="critical_position_id" x-model="editForm.id">
    @endif

    <div class="min-h-0 flex-1 overflow-y-auto bg-slate-50 px-4 py-5 sm:px-6">
        @if ($errors->getBag($errorBag)->any())
            <div class="mb-5 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-700" role="alert">
                <p class="font-bold">Periksa kembali data berikut:</p>
                <ul class="mt-2 list-disc space-y-1 pl-5">
                    @foreach ($errors->getBag($errorBag)->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="space-y-5">
            <div class="relative">
                <label for="{{ $mode }}-position-search" class="block text-sm font-semibold text-slate-700">Position <span class="text-red-500">*</span></label>
                <input type="hidden" name="position_id" x-bind:value="positionId">
                <input id="{{ $mode }}-position-search" type="search" x-model="search" @focus="dropdownOpen = true" @input="handleSearchInput()"
                    autocomplete="off" placeholder="Cari nama, kode, atau SAP Position ID"
                    class="mt-1 block w-full rounded-lg border-slate-300 text-sm focus:border-amber-400 focus:ring-amber-400">
                <div x-cloak x-show="dropdownOpen" @click.outside="dropdownOpen = false"
                    class="absolute z-20 mt-1 max-h-64 w-full overflow-y-auto rounded-xl border border-slate-200 bg-white p-1 shadow-xl">
                    <template x-for="option in allowedOptions()" :key="option.id">
                        <button type="button" @click="selectPosition(option)" class="block w-full rounded-lg px-3 py-2 text-left hover:bg-slate-50">
                            <span class="block text-sm font-semibold text-slate-800" x-text="option.name"></span>
                            <span class="block text-xs text-slate-500">
                                <span x-text="option.code"></span> · SAP Position ID: <span x-text="option.external_position_id || '—'"></span>
                                · <span x-text="option.unit"></span> / <span x-text="option.department"></span>
                                <span x-show="!option.active" class="font-semibold text-rose-600"> · Nonaktif</span>
                            </span>
                        </button>
                    </template>
                    <p x-show="allowedOptions().length === 0" class="px-3 py-4 text-center text-sm text-slate-500">Position tidak ditemukan atau sudah terdaftar.</p>
                </div>
                @error('position_id', $errorBag)<span class="mt-1 block text-xs text-red-600">{{ $message }}</span>@enderror
            </div>

            <div x-show="loadingPreview" class="rounded-xl border border-slate-200 bg-white p-4 text-sm text-slate-500">Memuat detail Position…</div>
            <section x-cloak x-show="preview && !loadingPreview" class="rounded-xl border border-sky-200 bg-sky-50/60 p-4">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p class="text-xs font-bold uppercase tracking-wide text-sky-700">Preview Position</p>
                        <h4 class="mt-1 font-display text-lg font-bold text-slate-950" x-text="preview?.name"></h4>
                        <p class="text-xs text-slate-500">SAP Position ID: <span x-text="preview?.external_position_id || '—'"></span></p>
                    </div>
                    <span class="rounded-full bg-white px-3 py-1 text-xs font-bold text-slate-700" x-text="preview?.coverage"></span>
                </div>
                <dl class="mt-4 grid gap-3 text-sm sm:grid-cols-2">
                    <div><dt class="text-xs font-semibold text-slate-500">Level</dt><dd class="font-medium text-slate-800" x-text="preview?.job_level || '—'"></dd></div>
                    <div><dt class="text-xs font-semibold text-slate-500">Section</dt><dd class="font-medium text-slate-800" x-text="preview?.section || '—'"></dd></div>
                    <div><dt class="text-xs font-semibold text-slate-500">Unit</dt><dd class="font-medium text-slate-800" x-text="preview?.unit || '—'"></dd></div>
                    <div><dt class="text-xs font-semibold text-slate-500">Department</dt><dd class="font-medium text-slate-800" x-text="preview?.department || '—'"></dd></div>
                    <div><dt class="text-xs font-semibold text-slate-500">Direktorat</dt><dd class="font-medium text-slate-800" x-text="preview?.directorate || '—'"></dd></div>
                    <div><dt class="text-xs font-semibold text-slate-500">Formasi / Successor</dt><dd class="font-medium text-slate-800"><span x-text="preview?.formation_count"></span> formasi · <span x-text="preview?.successor_count"></span> successor</dd></div>
                </dl>
                <div class="mt-4 border-t border-sky-200 pt-3">
                    <p class="text-xs font-bold uppercase tracking-wide text-slate-500">Incumbent</p>
                    <template x-for="incumbent in (preview?.incumbents || [])" :key="`${incumbent.sap}-${incumbent.personal_number}`">
                        <div class="mt-2 rounded-lg bg-white px-3 py-2 text-sm">
                            <p class="font-semibold text-slate-800" x-text="incumbent.name"></p>
                            <p class="text-xs text-slate-500">Personal Number: <span x-text="incumbent.personal_number || '—'"></span> · SAP Pegawai: <span x-text="incumbent.sap || '—'"></span></p>
                            <p class="text-xs text-slate-500" x-text="incumbent.position || '—'"></p>
                        </div>
                    </template>
                    <p x-show="(preview?.incumbents || []).length === 0" class="mt-2 text-sm text-slate-500">Belum ada incumbent.</p>
                </div>
            </section>

            <label class="block text-sm font-semibold text-slate-700">Criticality <span class="text-red-500">*</span>
                <select name="criticality" @if ($isEdit) x-model="editForm.criticality" @endif required
                    class="mt-1 block w-full rounded-lg border-slate-300 text-sm focus:border-amber-400 focus:ring-amber-400">
                    <option value="">Pilih Criticality</option>
                    @foreach (\App\Enums\CriticalPositionCriticality::cases() as $criticalityOption)
                        <option value="{{ $criticalityOption->value }}" @if (! $isEdit) @selected($formHasErrors && old('criticality') === $criticalityOption->value) @endif>{{ $criticalityOption->label() }}</option>
                    @endforeach
                </select>
                @error('criticality', $errorBag)<span class="mt-1 block text-xs text-red-600">{{ $message }}</span>@enderror
            </label>

            <label class="block text-sm font-semibold text-slate-700">Alasan Penilaian <span class="text-red-500">*</span>
                <textarea name="assessment_reason" rows="4" maxlength="2000" @if ($isEdit) x-model="editForm.assessment_reason" @endif required
                    class="mt-1 block w-full rounded-lg border-slate-300 text-sm focus:border-amber-400 focus:ring-amber-400">@if (! $isEdit){{ $formHasErrors ? old('assessment_reason') : '' }}@endif</textarea>
                @error('assessment_reason', $errorBag)<span class="mt-1 block text-xs text-red-600">{{ $message }}</span>@enderror
            </label>

            <div class="grid gap-5 sm:grid-cols-2">
                <label class="block text-sm font-semibold text-slate-700">Tanggal Penilaian <span class="text-red-500">*</span>
                    <input type="date" name="assessed_at" @if ($isEdit) x-model="editForm.assessed_at" @else value="{{ $formHasErrors ? old('assessed_at') : now()->toDateString() }}" @endif required
                        class="mt-1 block w-full rounded-lg border-slate-300 text-sm focus:border-amber-400 focus:ring-amber-400">
                    @error('assessed_at', $errorBag)<span class="mt-1 block text-xs text-red-600">{{ $message }}</span>@enderror
                </label>
                <label class="block text-sm font-semibold text-slate-700">Tanggal Review Berikutnya
                    <input type="date" name="review_due_at" @if ($isEdit) x-model="editForm.review_due_at" @else value="{{ $formHasErrors ? old('review_due_at') : '' }}" @endif
                        class="mt-1 block w-full rounded-lg border-slate-300 text-sm focus:border-amber-400 focus:ring-amber-400">
                    @error('review_due_at', $errorBag)<span class="mt-1 block text-xs text-red-600">{{ $message }}</span>@enderror
                </label>
            </div>

            <label class="block text-sm font-semibold text-slate-700">Catatan
                <textarea name="notes" rows="3" maxlength="5000" @if ($isEdit) x-model="editForm.notes" @endif
                    class="mt-1 block w-full rounded-lg border-slate-300 text-sm focus:border-amber-400 focus:ring-amber-400">@if (! $isEdit){{ $formHasErrors ? old('notes') : '' }}@endif</textarea>
                @error('notes', $errorBag)<span class="mt-1 block text-xs text-red-600">{{ $message }}</span>@enderror
            </label>
        </div>
    </div>

    <div class="flex shrink-0 flex-col-reverse gap-3 border-t border-slate-200 bg-white px-4 py-4 sm:flex-row sm:justify-end sm:px-6">
        <button type="button" @click="closePanel()" class="ui-button ui-button-secondary w-full sm:w-auto">Batal</button>
        <button type="submit" class="ui-button ui-button-primary w-full sm:w-auto">Simpan</button>
    </div>
</form>
