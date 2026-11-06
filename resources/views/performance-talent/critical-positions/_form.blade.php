@php
    $formHasErrors = $errors->getBag($errorBag)->any();
    $positionOptionData = $positionOptions->map(fn ($position) => [
        'id' => (string) $position->id,
        'name' => $position->name,
        'external_position_id' => $position->external_position_id,
        'job_level' => $position->job_level,
        'unit' => $position->unit->name,
        'department' => $position->unit->department->name,
        'directorate' => $position->unit->department->directorate?->name,
        'active' => $position->active,
        'critical_position_id' => $position->criticalPosition?->id === null ? null : (string) $position->criticalPosition->id,
        'label' => $position->name.' · '.$position->unit->department->name,
        'search' => mb_strtolower(implode(' ', array_filter([
            $position->name,
            $position->external_position_id,
            $position->job_level,
            $position->unit->name,
            $position->unit->department->name,
        ]))),
    ])->values();
@endphp

<form method="POST" action="{{ route('performance-talent.critical-positions.store') }}" x-bind:action="formAction"
    data-critical-position-form data-create-action="{{ route('performance-talent.critical-positions.store') }}"
    @submit="if (submitting) { $event.preventDefault(); return; } submitting = true"
    @pageshow.window="submitting = false"
    x-data="{
        positionId: '',
        search: '',
        dropdownOpen: false,
        loadingPreview: false,
        preview: null,
        options: @js($positionOptionData),
        previewUrlTemplate: @js(route('performance-talent.critical-positions.position-preview', ['position' => '__POSITION__'])),
        synchronizedKey: null,
        submitting: false,
        syncModal(mode, selectedPositionId, sequence) {
            const nextPositionId = String(selectedPositionId ?? '');
            const nextKey = `${sequence}:${mode}:${nextPositionId}`;
            if (nextKey === this.synchronizedKey) return;

            this.synchronizedKey = nextKey;
            this.positionId = nextPositionId;
            this.dropdownOpen = false;
            this.submitting = false;
            this.syncSearch();
            this.loadPreview();
        },
        allowedOptions() {
            const criticalPositionId = formMode === 'edit' ? String(formData.id ?? '') : '';
            const term = this.search.trim().toLocaleLowerCase('id-ID');

            return this.options.filter((option) => {
                const available = option.critical_position_id === null
                    || (formMode === 'edit' && option.critical_position_id === criticalPositionId);

                return available && (term === '' || option.search.includes(term));
            }).slice(0, 30);
        },
        selectPosition(option) {
            this.positionId = String(option.id);
            formData.position_id = this.positionId;
            this.synchronizedKey = `${formSequence}:${formMode}:${this.positionId}`;
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

            const requestedPositionId = this.positionId;
            this.loadingPreview = true;
            try {
                const response = await fetch(this.previewUrlTemplate.replace('__POSITION__', encodeURIComponent(requestedPositionId)), {
                    headers: { Accept: 'application/json' },
                });
                if (requestedPositionId === this.positionId) {
                    this.preview = response.ok ? await response.json() : null;
                }
            } finally {
                if (requestedPositionId === this.positionId) this.loadingPreview = false;
            }
        },
    }"
    x-effect="syncModal(formMode, formData.position_id, formSequence)"
    class="flex min-h-0 flex-1 flex-col">
    @csrf
    <input type="hidden" name="_method" value="PUT" x-bind:disabled="formMode !== 'edit'">
    <input type="hidden" name="critical_position_id" x-bind:value="formData.id" x-bind:disabled="formMode !== 'edit'">

    <div class="min-h-0 flex-1 overflow-y-auto overscroll-contain bg-white px-5 py-5 sm:px-10 sm:py-6">
        @if ($formHasErrors)
            <div class="mb-5 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800" role="alert" data-critical-position-errors>
                <p class="font-bold">Critical Position belum dapat disimpan.</p>
                <p class="mt-1">Periksa field yang ditandai, lalu coba kembali.</p>
            </div>
        @endif

        <div class="space-y-5">
            <div>
                <label for="critical-position-search" class="block text-[15px] font-bold text-[#0B1F3A]">Position <span class="text-red-500" aria-hidden="true">*</span></label>
                <div class="relative mt-1.5">
                    <input type="hidden" name="position_id" x-bind:value="positionId">
                    <input id="critical-position-search" data-critical-position-search type="search" x-model="search"
                        @focus="dropdownOpen = true" @input="handleSearchInput()"
                        @keydown.escape.stop="if (dropdownOpen) { dropdownOpen = false } else { closeForm() }"
                        autocomplete="off" placeholder="Cari nama atau SAP Position ID"
                        class="block w-full rounded-xl border-slate-300 bg-white py-3 pl-4 pr-11 text-base shadow-sm focus:border-[#087FC3] focus:ring-[#087FC3]">
                    <svg class="pointer-events-none absolute right-3.5 top-1/2 h-5 w-5 -translate-y-1/2 text-slate-400" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><circle cx="8.5" cy="8.5" r="5.25" /><path d="m12.5 12.5 4 4" stroke-linecap="round" /></svg>

                    <div x-cloak x-show="dropdownOpen" @click.outside="dropdownOpen = false"
                        class="absolute z-30 mt-2 max-h-64 w-full overflow-y-auto rounded-xl border border-slate-200 bg-white p-1.5 shadow-xl">
                        <template x-for="option in allowedOptions()" :key="option.id">
                            <button type="button" @click="selectPosition(option)"
                                class="block w-full rounded-lg px-3 py-2.5 text-left transition hover:bg-sky-50 focus-visible:bg-sky-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-[#087FC3]">
                                <span class="block text-sm font-bold text-slate-900" x-text="option.name"></span>
                                <span class="mt-0.5 block text-xs text-slate-500">SAP Position ID: <span x-text="option.external_position_id || '—'"></span></span>
                                <span class="mt-0.5 block text-xs text-slate-500"><span x-text="option.job_level || 'Level belum tersedia'"></span> · <span x-text="option.department"></span><span x-show="!option.active" class="font-semibold text-rose-600"> · Nonaktif</span></span>
                            </button>
                        </template>
                        <p x-show="allowedOptions().length === 0" class="px-3 py-5 text-center text-sm text-slate-500">Position tidak ditemukan atau sudah terdaftar.</p>
                    </div>
                </div>
                @error('position_id', $errorBag)<p class="mt-1.5 text-xs font-medium text-red-700">{{ $message }}</p>@enderror
            </div>

            <div x-show="!positionId && !loadingPreview" class="flex items-center gap-3 rounded-xl border border-sky-200 bg-sky-50 px-4 py-3 text-sm text-sky-900">
                <svg class="mt-0.5 h-5 w-5 shrink-0 text-[#087FC3]" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><circle cx="10" cy="10" r="7" /><path d="M10 9v4m0-7h.01" stroke-linecap="round" /></svg>
                <p>Pilih Position untuk melihat detail organisasi dan status formasi.</p>
            </div>
            <div x-cloak x-show="loadingPreview" class="rounded-xl border border-sky-200 bg-sky-50 p-4 text-sm text-sky-800">Memuat detail Position…</div>

            <section x-cloak x-show="preview && !loadingPreview" class="rounded-2xl border border-sky-200 bg-sky-50/70 p-4 sm:p-5" aria-label="Preview Position">
                <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                    <div class="min-w-0">
                        <p class="text-[0.6875rem] font-extrabold uppercase tracking-[0.16em] text-[#087FC3]">Detail Master Position</p>
                        <h3 class="mt-1 break-words font-display text-lg font-bold text-[#0B1F3A]" x-text="preview?.name"></h3>
                        <p class="mt-0.5 text-xs text-slate-500">SAP Position ID: <span x-text="preview?.external_position_id || '—'"></span></p>
                    </div>
                    <span class="w-fit rounded-full border border-sky-200 bg-white px-3 py-1 text-xs font-bold text-[#0B1F3A]" x-text="preview?.coverage"></span>
                </div>
                <dl class="mt-4 grid gap-x-5 gap-y-3 text-sm sm:grid-cols-2 lg:grid-cols-4">
                    <div><dt class="text-xs font-semibold text-slate-500">Level</dt><dd class="mt-0.5 font-semibold text-slate-800" x-text="preview?.job_level || '—'"></dd></div>
                    <div><dt class="text-xs font-semibold text-slate-500">Department</dt><dd class="mt-0.5 font-semibold text-slate-800" x-text="preview?.department || '—'"></dd></div>
                    <div><dt class="text-xs font-semibold text-slate-500">Direktorat</dt><dd class="mt-0.5 font-semibold text-slate-800" x-text="preview?.directorate || '—'"></dd></div>
                    <div><dt class="text-xs font-semibold text-slate-500">Jumlah Formasi</dt><dd class="mt-0.5 font-semibold text-slate-800" x-text="preview?.formation_count"></dd></div>
                    <div><dt class="text-xs font-semibold text-slate-500">Jumlah Incumbent</dt><dd class="mt-0.5 font-semibold text-slate-800" x-text="(preview?.incumbents || []).length"></dd></div>
                    <div><dt class="text-xs font-semibold text-slate-500">Formation Status</dt><dd class="mt-0.5 font-semibold text-slate-800" x-text="preview?.coverage"></dd></div>
                </dl>
            </section>

            <fieldset>
                <legend class="text-[15px] font-bold text-[#0B1F3A]">Criticality <span class="text-red-500" aria-hidden="true">*</span></legend>
                <div class="mt-2 grid grid-cols-1 gap-3 sm:grid-cols-3">
                    @foreach ([
                        ['value' => 'high', 'label' => 'High', 'selected' => 'border-rose-400 bg-rose-50 ring-2 ring-rose-100', 'dot' => 'border-rose-500 bg-rose-500', 'accent' => 'bg-rose-500', 'text' => 'text-rose-800'],
                        ['value' => 'medium', 'label' => 'Medium', 'selected' => 'border-amber-400 bg-amber-50 ring-2 ring-amber-100', 'dot' => 'border-amber-500 bg-amber-500', 'accent' => 'bg-amber-500', 'text' => 'text-amber-800'],
                        ['value' => 'low', 'label' => 'Low', 'selected' => 'border-emerald-400 bg-emerald-50 ring-2 ring-emerald-100', 'dot' => 'border-emerald-500 bg-emerald-500', 'accent' => 'bg-emerald-500', 'text' => 'text-emerald-800'],
                    ] as $criticalityCard)
                        <label class="block cursor-pointer">
                            <input type="radio" name="criticality" value="{{ $criticalityCard['value'] }}" x-model="formData.criticality" class="peer sr-only">
                            <span class="flex min-h-16 items-center gap-4 rounded-xl border border-slate-300 bg-white px-3 py-3 text-base font-bold text-[#0B1F3A] shadow-sm transition hover:border-slate-400 peer-focus-visible:ring-2 peer-focus-visible:ring-[#087FC3] peer-focus-visible:ring-offset-2"
                                :class="formData.criticality === '{{ $criticalityCard['value'] }}' ? '{{ $criticalityCard['selected'].' '.$criticalityCard['text'] }}' : ''">
                                <span class="h-10 w-1.5 shrink-0 rounded-full {{ $criticalityCard['accent'] }}" aria-hidden="true"></span>
                                <span class="flex h-5 w-5 items-center justify-center rounded-full border-2 border-slate-300 bg-white" :class="formData.criticality === '{{ $criticalityCard['value'] }}' ? '{{ $criticalityCard['dot'] }}' : ''">
                                    <span x-show="formData.criticality === '{{ $criticalityCard['value'] }}'" class="h-1.5 w-1.5 rounded-full bg-white"></span>
                                </span>
                                {{ $criticalityCard['label'] }}
                            </span>
                        </label>
                    @endforeach
                </div>
                @error('criticality', $errorBag)<p class="mt-1.5 text-xs font-medium text-red-700">{{ $message }}</p>@enderror
            </fieldset>

            <div>
                <label for="critical-position-reason" class="block text-[15px] font-bold text-[#0B1F3A]">Alasan Penilaian <span class="text-red-500" aria-hidden="true">*</span></label>
                <textarea id="critical-position-reason" name="assessment_reason" rows="3" maxlength="2000" x-model="formData.assessment_reason" required placeholder="Jelaskan alasan posisi ini dikategorikan sebagai critical position..." class="mt-1.5 block w-full resize-y rounded-xl border-slate-300 bg-white px-4 py-3 text-base shadow-sm focus:border-[#087FC3] focus:ring-[#087FC3]"></textarea>
                @error('assessment_reason', $errorBag)<p class="mt-1.5 text-xs font-medium text-red-700">{{ $message }}</p>@enderror
            </div>

            <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                <div>
                    <label for="critical-position-assessed-at" class="block text-[15px] font-bold text-[#0B1F3A]">Tanggal Penilaian <span class="text-red-500" aria-hidden="true">*</span></label>
                    <div class="mt-1.5">
                        <input id="critical-position-assessed-at" type="date" name="assessed_at" x-model="formData.assessed_at" required class="block w-full rounded-xl border-slate-300 bg-white px-4 py-3 text-base shadow-sm focus:border-[#087FC3] focus:ring-[#087FC3]">
                    </div>
                    @error('assessed_at', $errorBag)<p class="mt-1.5 text-xs font-medium text-red-700">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="critical-position-review-due-at" class="block text-[15px] font-bold text-[#0B1F3A]">Tanggal Review Berikutnya</label>
                    <div class="mt-1.5">
                        <input id="critical-position-review-due-at" type="date" name="review_due_at" x-model="formData.review_due_at" placeholder="dd/mm/yyyy" class="block w-full rounded-xl border-slate-300 bg-white px-4 py-3 text-base shadow-sm focus:border-[#087FC3] focus:ring-[#087FC3]">
                    </div>
                    @error('review_due_at', $errorBag)<p class="mt-1.5 text-xs font-medium text-red-700">{{ $message }}</p>@enderror
                </div>
            </div>

            <div>
                <label for="critical-position-notes" class="block text-[15px] font-bold text-[#0B1F3A]">Catatan</label>
                <textarea id="critical-position-notes" name="notes" rows="2" maxlength="5000" x-model="formData.notes" placeholder="Tambahkan catatan jika diperlukan..." class="mt-1.5 block w-full resize-y rounded-xl border-slate-300 bg-white px-4 py-3 text-base shadow-sm focus:border-[#087FC3] focus:ring-[#087FC3]"></textarea>
                @error('notes', $errorBag)<p class="mt-1.5 text-xs font-medium text-red-700">{{ $message }}</p>@enderror
            </div>
        </div>
    </div>

    <footer class="flex shrink-0 flex-col-reverse gap-3 border-t border-slate-200 bg-white px-5 py-5 sm:flex-row sm:justify-end sm:px-10">
        <button type="button" @click="closeForm()" class="inline-flex min-h-11 w-full items-center justify-center rounded-xl border border-slate-300 bg-white px-5 py-2.5 text-sm font-bold text-[#0B1F3A] shadow-sm transition hover:bg-slate-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#087FC3] focus-visible:ring-offset-2 sm:w-auto">Batal</button>
        <button type="submit" :disabled="submitting" :aria-busy="submitting.toString()" class="inline-flex min-h-11 w-full items-center justify-center gap-2 rounded-xl bg-[#0B1F3A] px-5 py-2.5 text-sm font-bold text-white shadow-sm transition hover:bg-[#102B50] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#087FC3] focus-visible:ring-offset-2 disabled:cursor-wait disabled:opacity-70 sm:w-auto sm:min-w-52">
            <svg x-cloak x-show="submitting" class="h-4 w-4 animate-spin" viewBox="0 0 20 20" fill="none" aria-hidden="true"><circle cx="10" cy="10" r="7" stroke="currentColor" stroke-opacity=".3" stroke-width="2" /><path d="M10 3a7 7 0 0 1 7 7" stroke="currentColor" stroke-width="2" stroke-linecap="round" /></svg>
            <svg x-show="!submitting" class="h-4 w-4" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M4 3h10l2 2v12H4V3Z" stroke-linejoin="round" /><path d="M7 3v5h6V3M7 17v-5h6v5" /></svg>
            <span x-text="submitting ? 'Menyimpan...' : (formMode === 'edit' ? 'Simpan Perubahan' : 'Simpan Critical Position')">Simpan Critical Position</span>
        </button>
    </footer>
</form>
