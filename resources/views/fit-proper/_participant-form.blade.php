@php
    $participant = $participant ?? null;
    $formClass = $formClass ?? '';
    $isCreateModal = $participant === null && ($isCreateModal ?? false);
    $isEditModal = $participant !== null && ($isEditModal ?? false);
    $participantFormContext = $isCreateModal
        ? 'participant-create'
        : ($isEditModal ? 'participant-edit-'.$participant->id : null);
    $usesOldInput = $participantFormContext === null || old('form_context') === $participantFormContext;
    $formMode = $participant ? 'edit' : 'create';
    $formInstance = $participant ? 'edit-'.$participant->id : 'create';
    $formFieldPrefix = 'fit-proper-participant-'.$formInstance;
    $isModalForm = $isCreateModal || $isEditModal;
    $submitLabel = $participant ? 'Simpan Perubahan' : 'Simpan Peserta';
    $selectedEmployeeId = (string) ($usesOldInput ? old('employee_id', $participant?->employee_id ?? '') : ($participant?->employee_id ?? ''));
    $selectedEmployeeLabel = $selectedEmployeeId !== ''
        ? trim(($usesOldInput ? old('sap', $participant?->sap) : $participant?->sap).' · '.($usesOldInput ? old('name', $participant?->name) : $participant?->name), ' ·')
        : '';
@endphp

<form
    enctype="multipart/form-data"
    method="POST"
    action="{{ $participant ? route('fit-proper.participants.update', [$activity, $participant]) : route('fit-proper.participants.store', $activity) }}"
    class="fp-participant-form {{ $formClass }}"
    data-fp-participant-form="{{ $formMode }}"
    @submit="if (submitting) { $event.preventDefault(); return; } submitting = true"
    @pageshow.window="submitting = false"
    x-data="{
        query: '',
        results: [],
        selected: @js($selectedEmployeeId),
        selectedLabel: @js($selectedEmployeeLabel),
        searching: false,
        searchError: '',
        hasSearched: false,
        searchSequence: 0,
        cvFileName: '',
        submitting: false,
        setField(name, value) {
            const field = this.$root.elements.namedItem(name);

            if (!(field instanceof HTMLElement)) return;

            field.value = value ?? '';
            field.dispatchEvent(new Event('input', { bubbles: true }));
            field.dispatchEvent(new Event('change', { bubbles: true }));
        },
        selectEmployee(employee) {
            this.selected = String(employee.id);
            this.selectedLabel = employee.sap + ' · ' + employee.name;
            this.results = [];
            this.query = '';
            this.hasSearched = false;

            for (const [field, value] of Object.entries({
                name: employee.name,
                sap: employee.sap,
                birth_date: employee.birth_date,
                department: employee.department,
                current_position: employee.current_position,
                education: employee.education,
            })) this.setField(field, value);
        },
        clearEmployee() {
            this.selected = '';
            this.selectedLabel = '';

            for (const field of ['name', 'sap', 'birth_date', 'department', 'current_position', 'education']) this.setField(field, '');
        },
        async search() {
            const searchTerm = this.query.trim();
            const sequence = ++this.searchSequence;

            if (searchTerm.length < 2) {
                this.results = [];
                this.searchError = '';
                this.hasSearched = false;
                this.searching = false;
                return;
            }

            this.searching = true;
            this.searchError = '';

            try {
                const response = await fetch(
                    @js(route('fit-proper.employees')).concat('?activity={{ $activity->id }}&q=', encodeURIComponent(searchTerm)),
                    { headers: { Accept: 'application/json' } },
                );

                if (!response.ok) throw new Error('employee-search-failed');
                if (sequence !== this.searchSequence) return;

                this.results = await response.json();
                this.hasSearched = true;
            } catch (error) {
                if (sequence !== this.searchSequence) return;
                this.results = [];
                this.hasSearched = true;
                this.searchError = 'Pencarian SAP belum berhasil. Silakan coba lagi.';
            } finally {
                if (sequence === this.searchSequence) this.searching = false;
            }
        },
    }"
>
    @csrf
    @if ($participant)
        @method('PUT')
    @endif
    @if ($participantFormContext)
        <input type="hidden" name="form_context" value="{{ $participantFormContext }}">
    @endif

    <nav class="fp-participant-form-nav sticky top-0 z-20 overflow-x-auto border-b border-slate-200 bg-white px-4 py-3 sm:px-6" aria-label="Bagian form peserta">
        <ol class="grid min-w-[43rem] grid-cols-4 gap-2">
            @foreach ([['sourceSection', 'Sumber Peserta'], ['identitySection', 'Identitas & Posisi'], ['profileSection', 'Profil Pendukung'], ['assessmentSection', 'Asesmen']] as $index => [$sectionReference, $sectionLabel])
                <li>
                    <button type="button" class="flex w-full items-center gap-2 rounded-xl px-3 py-2 text-left text-xs font-bold {{ $index === 0 ? 'bg-sky-50 text-sky-800' : 'text-slate-500 hover:bg-slate-50 hover:text-slate-800' }} focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-sky-500" @click="$refs.{{ $sectionReference }}.scrollIntoView({ behavior: 'smooth', block: 'start' })">
                        <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full {{ $index === 0 ? 'bg-sky-600 text-white' : 'bg-slate-200 text-slate-600' }}">{{ $index + 1 }}</span>
                        <span>{{ $sectionLabel }}</span>
                    </button>
                </li>
            @endforeach
        </ol>
    </nav>

    <div class="space-y-5 p-4 sm:p-6">
        @if ($errors->any() && $usesOldInput)
            <div class="rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800" role="alert" data-fp-participant-errors>
                <p class="font-bold">Peserta belum dapat disimpan.</p>
                <p class="mt-1">Periksa field yang ditandai, lalu coba kembali.</p>
            </div>
        @endif

        <section x-ref="sourceSection" class="scroll-mt-20 rounded-2xl border border-sky-200 bg-sky-50/70 p-4 sm:p-5">
            <div class="flex flex-wrap items-center gap-2">
                <span class="flex h-8 w-8 items-center justify-center rounded-xl bg-sky-100 text-sky-700" aria-hidden="true">
                    <svg class="h-4 w-4" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="8.5" cy="8.5" r="5.25" /><path d="m12.5 12.5 4 4" stroke-linecap="round" /></svg>
                </span>
                <h3 class="font-display text-base font-bold text-slate-950">Sumber Peserta</h3>
                <span class="rounded-full border border-sky-200 bg-white px-2 py-0.5 text-[0.6875rem] font-bold text-sky-700">Opsional</span>
            </div>
            <p id="{{ $formFieldPrefix }}-source-help" class="mt-2 max-w-4xl text-xs leading-5 text-slate-600">Cari berdasarkan SAP. Setelah pegawai dipilih, nama dari Personal Number serta data profil yang tersedia akan diisi otomatis. Kosongkan pilihan untuk peserta manual.</p>
            <input type="hidden" name="employee_id" :value="selected">
            <div class="relative mt-4">
                <label for="{{ $formFieldPrefix }}-employee-search" class="block text-xs font-bold text-slate-700">Cari SAP</label>
                <input
                    id="{{ $formFieldPrefix }}-employee-search"
                    x-model.trim="query"
                    @input.debounce.300ms="search()"
                    @keydown.escape="if (query || results.length) { $event.stopPropagation(); results = []; query = ''; }"
                    placeholder="Ketik minimal dua karakter SAP"
                    class="mt-1.5 w-full rounded-xl border-slate-300 bg-white text-sm shadow-sm focus:border-sky-500 focus:ring-sky-500"
                    autocomplete="off"
                    inputmode="search"
                    aria-describedby="{{ $formFieldPrefix }}-source-help {{ $formFieldPrefix }}-search-status"
                >
                <span x-cloak x-show="searching" class="pointer-events-none absolute bottom-3 right-3 h-4 w-4 animate-spin rounded-full border-2 border-sky-500 border-r-transparent" aria-hidden="true"></span>
            </div>
            <div id="{{ $formFieldPrefix }}-search-status" class="mt-2 text-xs" aria-live="polite">
                <p x-cloak x-show="searching" class="text-slate-500">Mencari pegawai…</p>
                <p x-cloak x-show="searchError" x-text="searchError" class="font-medium text-red-700"></p>
                <p x-cloak x-show="hasSearched && !searching && !searchError && results.length === 0" class="text-slate-500">SAP tidak ditemukan. Anda tetap dapat mengisi peserta secara manual.</p>
                @if ($errors->has('employee_id') && $usesOldInput)
                    <p class="font-medium text-red-700" data-fp-field-error>{{ $errors->first('employee_id') }}</p>
                @endif
            </div>
            <ul class="mt-2 max-h-48 divide-y divide-slate-200 overflow-y-auto rounded-xl border border-slate-200 bg-white shadow-lg" x-cloak x-show="results.length" aria-label="Hasil pencarian SAP">
                <template x-for="employee in results" :key="employee.id">
                    <li><button type="button" class="w-full p-3 text-left text-sm transition hover:bg-sky-50 focus-visible:bg-sky-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-sky-500" @click="selectEmployee(employee)"><span class="font-bold text-slate-900" x-text="employee.sap"></span><span class="ml-2 text-slate-600" x-text="employee.name"></span></button></li>
                </template>
            </ul>
            <div class="mt-3 flex flex-wrap items-center gap-2 rounded-xl border border-sky-200 bg-white p-3 text-sm text-slate-700" x-cloak x-show="selected">
                <span>Pegawai dipilih: <strong x-text="selectedLabel"></strong></span>
                <button type="button" @click="clearEmployee()" class="font-bold text-red-700 underline underline-offset-2 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-red-500">Gunakan manual</button>
            </div>
        </section>

        <section x-ref="identitySection" class="scroll-mt-20 rounded-2xl border border-slate-200 bg-white p-4 sm:p-5">
            <h3 class="font-display text-base font-bold text-slate-950">Identitas &amp; Posisi</h3>
            <p class="mt-1 text-xs text-slate-500">Data utama peserta dan tujuan pergerakan jabatan.</p>
            <div class="mt-4 grid gap-4 md:grid-cols-2">
                @foreach (['name' => 'Nama', 'sap' => 'SAP', 'birth_date' => 'Tanggal lahir', 'department' => 'Departemen / unit'] as $key => $label)
                    @include('fit-proper._participant-field')
                @endforeach
            </div>
            <div class="mt-5 rounded-xl border border-slate-200 bg-slate-50/80 p-4">
                <h4 class="flex items-center gap-2 text-sm font-bold text-slate-800">
                    Pergerakan Posisi
                    <svg class="h-4 w-4 text-sky-600" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M3 10h14m-4-4 4 4-4 4" stroke-linecap="round" stroke-linejoin="round" /></svg>
                </h4>
                <div class="mt-3 grid gap-4 md:grid-cols-2">
                    @foreach (['current_position' => 'Posisi saat ini', 'target_position' => 'Posisi tujuan', 'current_jg' => 'JG saat ini', 'target_jg' => 'JG tujuan'] as $key => $label)
                        @include('fit-proper._participant-field')
                    @endforeach
                </div>
            </div>
        </section>

        <section x-ref="profileSection" class="scroll-mt-20 rounded-2xl border border-slate-200 bg-white p-4 sm:p-5">
            <h3 class="font-display text-base font-bold text-slate-950">Profil Pendukung</h3>
            <p class="mt-1 text-xs text-slate-500">Riwayat pendidikan, pengalaman, dan pencapaian peserta.</p>
            <div class="mt-4 grid gap-4 md:grid-cols-2">
                @foreach (['education' => 'Pendidikan', 'education_background' => 'Latar belakang pendidikan', 'experience' => 'Pengalaman', 'training' => 'Pelatihan', 'achievement' => 'Achievement'] as $key => $label)
                    @include('fit-proper._participant-field')
                @endforeach
            </div>
        </section>

        <section x-ref="assessmentSection" class="scroll-mt-20 rounded-2xl border border-slate-200 bg-white p-4 sm:p-5">
            <h3 class="font-display text-base font-bold text-slate-950">Asesmen</h3>
            <p class="mt-1 text-xs text-slate-500">Nilai kinerja, asesmen, rekomendasi, dan dokumen pendukung.</p>
            <div class="mt-4 grid gap-4 md:grid-cols-2">
                @foreach (['kpi_year_1' => 'KPI tahun pertama', 'kpi_year_2' => 'KPI tahun kedua', 'assessment' => 'Nilai asesmen', 'assessment_notes' => 'Narasi / rekomendasi asesmen'] as $key => $label)
                    @include('fit-proper._participant-field')
                @endforeach

                <div class="min-w-0 md:col-span-2">
                    <span class="block text-xs font-bold text-slate-700">CV opsional (PDF, maksimal 5 MB)</span>
                    <div class="mt-1.5 flex min-w-0 flex-col gap-3 rounded-xl border border-dashed border-slate-300 bg-slate-50 p-4 sm:flex-row sm:items-center">
                        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-white text-sky-700 shadow-sm" aria-hidden="true">
                            <svg class="h-5 w-5" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M10 13V3m0 0L6.5 6.5M10 3l3.5 3.5M4 12.5V16h12v-3.5" stroke-linecap="round" stroke-linejoin="round" /></svg>
                        </span>
                        <div class="min-w-0 flex-1">
                            <label for="{{ $formFieldPrefix }}-cv" class="ui-button ui-button-secondary w-fit cursor-pointer px-3 py-2 text-xs">Pilih file</label>
                            <input id="{{ $formFieldPrefix }}-cv" type="file" name="cv" accept="application/pdf" class="sr-only" @change="cvFileName = $event.target.files[0]?.name || ''">
                            <p class="mt-2 truncate text-xs text-slate-500" x-text="cvFileName || 'Belum ada file dipilih'">Belum ada file dipilih</p>
                            <p class="mt-1 text-[0.6875rem] text-slate-400">Hanya PDF, ukuran maksimal 5 MB.</p>
                        </div>
                    </div>
                    @if ($errors->has('cv') && $usesOldInput)
                        <p class="mt-1.5 text-xs font-medium text-red-700" data-fp-field-error>{{ $errors->first('cv') }}</p>
                    @endif

                    @if ($participant)
                        <div class="mt-3 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-slate-200 bg-white p-3 text-xs">
                            <div>
                                <p class="font-bold text-slate-800">{{ $participant->cv_path ? 'CV aktif tersedia' : 'Belum ada CV' }}</p>
                                <p class="mt-1 text-slate-500">Pilih file baru hanya jika ingin mengganti CV aktif.</p>
                            </div>
                            @if ($participant->cv_path)
                                <div class="flex flex-wrap items-center gap-3">
                                    <a href="{{ route('fit-proper.cv', [$activity, $participant]) }}" target="_blank" rel="noopener" class="font-bold text-sky-700 underline underline-offset-2">Lihat CV</a>
                                    <label for="{{ $formFieldPrefix }}-remove-cv" class="flex items-center gap-2 text-slate-600"><input id="{{ $formFieldPrefix }}-remove-cv" type="checkbox" name="remove_cv" value="1" class="rounded border-slate-300 text-sky-600 focus:ring-sky-500"> Hapus CV aktif</label>
                                </div>
                            @endif
                        </div>
                    @endif
                </div>
            </div>

            <div class="mt-5 flex items-start gap-3 rounded-xl border border-amber-200 bg-amber-50 p-4 text-xs leading-5 text-amber-950">
                <svg class="mt-0.5 h-4 w-4 shrink-0 text-amber-600" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><circle cx="10" cy="10" r="7" /><path d="M10 9v4m0-7h.01" stroke-linecap="round" /></svg>
                <p>Umur, gap JG (saat ini - tujuan), dan rata-rata dua KPI dihitung oleh server. Kosong berarti belum lengkap; nilai nol tetap sah. CV baru menggantikan CV aktif.</p>
            </div>
            @if ($participant)
                <p class="mt-3 text-[0.6875rem] text-slate-400">Identitas internal: {{ $participant->internal_identity }}</p>
            @endif
        </section>
    </div>

    <footer class="fp-participant-modal-footer {{ $isModalForm ? 'sticky bottom-0 z-20' : '' }} flex flex-col-reverse gap-3 border-t border-slate-200 bg-white/95 px-4 py-4 backdrop-blur sm:flex-row sm:justify-end sm:px-6">
        @if ($isModalForm)
            <button type="button" class="ui-button ui-button-secondary w-full sm:w-auto" @click="closeParticipantModal()">Batal</button>
        @endif
        <button type="submit" class="fp-participant-add ui-button w-full sm:w-auto sm:min-w-40" :disabled="submitting" :aria-busy="submitting.toString()">
            <svg x-cloak x-show="submitting" class="h-4 w-4 animate-spin" viewBox="0 0 20 20" fill="none" aria-hidden="true"><circle cx="10" cy="10" r="7" stroke="currentColor" stroke-opacity=".3" stroke-width="2" /><path d="M10 3a7 7 0 0 1 7 7" stroke="currentColor" stroke-width="2" stroke-linecap="round" /></svg>
            <span x-text="submitting ? 'Menyimpan…' : @js($submitLabel)">{{ $submitLabel }}</span>
        </button>
    </footer>
</form>
