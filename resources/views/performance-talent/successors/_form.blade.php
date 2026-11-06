@php($successorErrors = $errors->getBag($activeSuccessorErrorBag))

<form method="POST" :action="formAction" enctype="multipart/form-data" data-successor-form class="min-w-0">
    @csrf
    <input type="hidden" name="_method" value="PUT" :disabled="formMode !== 'edit'">
    <input type="hidden" name="successor_id" x-model="form.successor_id" :disabled="formMode !== 'edit'">
    <input type="hidden" name="target_position_id" x-model="form.target_position_id">

    <div class="-mx-4 -mt-5 flex items-start justify-between gap-4 border-b border-slate-200 bg-white px-4 py-4 sm:-mx-6 sm:px-6">
        <div class="min-w-0">
            <p class="text-xs font-bold uppercase tracking-[0.18em] text-blue-700" x-text="formMode === 'edit' ? 'Edit Successor' : 'Tambah Successor'"></p>
            <h3 id="successor-form-title" class="mt-1 break-words font-display text-2xl font-bold text-slate-950" x-text="selectedPosition?.name"></h3>
            <p class="mt-1 text-sm text-slate-500">Lengkapi profil kandidat successor.</p>
        </div>
        <button type="button" @click="closeSuccessorForm()" aria-label="Tutup formulir successor"
            class="inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-xl border border-slate-300 bg-white text-slate-700 transition hover:border-slate-400 hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-blue-500">
            <svg class="h-5 w-5" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="m5 5 10 10M15 5 5 15" stroke-linecap="round" /></svg>
        </button>
    </div>

    @if ($successorErrors->any())
        <div class="mt-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-semibold text-red-700">
            Periksa kembali data Successor yang dimasukkan.
        </div>
    @endif

    <div class="mt-5 grid min-w-0 gap-5 xl:grid-cols-[minmax(0,2fr)_minmax(22rem,1fr)]">
        <div class="min-w-0 space-y-4">
            <section class="overflow-hidden rounded-2xl border border-blue-200 bg-white">
                <div class="flex items-center gap-3 bg-blue-50/80 px-4 py-2.5">
                    <span class="inline-flex h-8 w-8 items-center justify-center rounded-lg bg-blue-100 text-blue-700"><x-dashboard-icon name="users" class="h-5 w-5" /></span>
                    <h4 class="font-display text-lg font-bold text-slate-950">Identitas Kandidat</h4>
                </div>
                <div class="grid gap-4 p-4 md:grid-cols-[13rem_minmax(0,1fr)]">
                    <div>
                        <label class="text-sm font-bold text-slate-800">Foto Kandidat</label>
                        <label for="successor-photo" class="mt-1.5 flex min-h-36 cursor-pointer flex-col items-center justify-center rounded-xl border border-dashed border-slate-400 bg-slate-50 px-4 text-center transition hover:border-blue-500 hover:bg-blue-50/50">
                            <svg class="h-9 w-9 text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><rect x="3" y="4" width="18" height="16" rx="2" /><circle cx="9" cy="9" r="2" /><path d="m4 17 5-5 3 3 2-2 6 5" stroke-linecap="round" stroke-linejoin="round" /></svg>
                            <span class="mt-2 text-sm font-bold text-blue-700">Unggah Foto</span>
                            <span class="mt-1 text-xs text-slate-500">JPG/PNG/WEBP, maks. 2MB</span>
                        </label>
                        <input id="successor-photo" x-ref="successorPhoto" name="photo" type="file" accept="image/jpeg,image/png,image/webp"
                            class="sr-only" @change="previewSuccessorPhoto($event)">
                        @error('photo', $activeSuccessorErrorBag)<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                    </div>

                    <div class="grid content-start gap-3 sm:grid-cols-2">
                        <div>
                            <label for="successor-name" class="text-sm font-bold text-slate-800">Nama Lengkap <span class="text-red-500">*</span></label>
                            <input id="successor-name" name="name" x-model="form.name" required maxlength="255" placeholder="Masukkan nama lengkap" class="ui-input mt-1 text-sm">
                            @error('name', $activeSuccessorErrorBag)<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label for="successor-sap" class="text-sm font-bold text-slate-800">SAP / Personal Number <span class="text-red-500">*</span></label>
                            <input id="successor-sap" name="sap" x-model="form.sap" required maxlength="50" placeholder="Masukkan nomor SAP" class="ui-input mt-1 text-sm">
                            @error('sap', $activeSuccessorErrorBag)<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label for="successor-current-position" class="text-sm font-bold text-slate-800">Posisi Saat Ini <span class="text-red-500">*</span></label>
                            <input id="successor-current-position" name="current_position" x-model="form.current_position" required maxlength="255" placeholder="Masukkan posisi saat ini" class="ui-input mt-1 text-sm">
                            @error('current_position', $activeSuccessorErrorBag)<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label for="successor-birth-date" class="text-sm font-bold text-slate-800">Tanggal Lahir <span class="text-red-500">*</span></label>
                            <input id="successor-birth-date" name="birth_date" x-model="form.birth_date" type="date" required class="ui-input mt-1 text-sm">
                            @error('birth_date', $activeSuccessorErrorBag)<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                        </div>
                        <div class="sm:col-span-2">
                            <label for="successor-last-education" class="text-sm font-bold text-slate-800">Pendidikan Terakhir <span class="text-red-500">*</span></label>
                            <input id="successor-last-education" name="last_education" x-model="form.last_education" required maxlength="255" placeholder="Masukkan pendidikan terakhir" class="ui-input mt-1 text-sm">
                            @error('last_education', $activeSuccessorErrorBag)<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                        </div>
                    </div>
                </div>
            </section>

            <section class="overflow-hidden rounded-2xl border border-blue-200 bg-white">
                <div class="flex items-center gap-3 bg-blue-50/80 px-4 py-2.5">
                    <span class="inline-flex h-8 w-8 items-center justify-center rounded-lg bg-blue-100 text-blue-700"><x-dashboard-icon name="clipboard" class="h-5 w-5" /></span>
                    <h4 class="font-display text-lg font-bold text-slate-950">Riwayat Jabatan</h4>
                </div>
                <div class="p-4">
                    <p class="text-sm font-bold text-slate-800">Riwayat Posisi 3 Tahun Terakhir <span class="text-red-500">*</span></p>
                    <div class="mt-2 space-y-3">
                        <template x-for="(history, index) in form.position_history" :key="index">
                            <div class="grid gap-3 sm:grid-cols-[minmax(0,1fr)_minmax(0,1fr)_2.75rem]">
                                <div>
                                    <label class="text-xs font-semibold text-slate-600">Periode</label>
                                    <input :name="`position_history[${index}][period]`" x-model="history.period" required maxlength="100" placeholder="Contoh: 2023 - 2025" class="ui-input mt-1 text-sm">
                                </div>
                                <div>
                                    <label class="text-xs font-semibold text-slate-600">Nama Posisi</label>
                                    <input :name="`position_history[${index}][position]`" x-model="history.position" required maxlength="255" placeholder="Masukkan nama posisi" class="ui-input mt-1 text-sm">
                                </div>
                                <button type="button" @click="removePositionHistory(index)" :disabled="form.position_history.length === 1" aria-label="Hapus riwayat jabatan"
                                    class="mt-6 inline-flex h-11 w-11 items-center justify-center rounded-xl border border-slate-300 text-slate-500 transition hover:border-red-300 hover:bg-red-50 hover:text-red-600 disabled:cursor-not-allowed disabled:opacity-40">
                                    <svg class="h-5 w-5" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path d="M4 6h12M8 3h4l1 3H7l1-3Zm-2 3 1 11h6l1-11M8 9v5m4-5v5" stroke-linecap="round" stroke-linejoin="round" /></svg>
                                </button>
                            </div>
                        </template>
                    </div>
                    @if ($successorErrors->has('position_history') || $successorErrors->has('position_history.*.period') || $successorErrors->has('position_history.*.position'))
                        <p class="mt-2 text-xs text-red-600">{{ $successorErrors->first('position_history') ?: $successorErrors->first('position_history.*.period') ?: $successorErrors->first('position_history.*.position') }}</p>
                    @endif
                    <button type="button" @click="addPositionHistory()" :disabled="form.position_history.length >= 10"
                        class="mt-3 inline-flex min-h-9 items-center gap-2 rounded-lg border border-blue-500 px-3 text-sm font-bold text-blue-700 transition hover:bg-blue-50 disabled:opacity-50">
                        <span class="text-lg" aria-hidden="true">+</span> Tambah Riwayat
                    </button>
                </div>
            </section>

            <section class="overflow-hidden rounded-2xl border border-blue-200 bg-white">
                <div class="flex items-center gap-3 bg-blue-50/80 px-4 py-2.5">
                    <span class="inline-flex h-8 w-8 items-center justify-center rounded-lg bg-blue-100 text-blue-700"><x-dashboard-icon name="chart" class="h-5 w-5" /></span>
                    <h4 class="font-display text-lg font-bold text-slate-950">Talent &amp; Assessment</h4>
                </div>
                <div class="grid gap-3 p-4 md:grid-cols-3">
                    <div>
                        <label for="successor-talent-box" class="text-sm font-bold text-slate-800">Talent Box <span class="text-red-500">*</span></label>
                        <select id="successor-talent-box" name="talent_box" x-model="form.talent_box" required class="ui-input mt-1 text-sm">
                            <option value="">Pilih Talent Box (1 - 9)</option>
                            @foreach (range(1, 9) as $talentBox)<option value="{{ $talentBox }}">{{ $talentBox }}</option>@endforeach
                        </select>
                        @error('talent_box', $activeSuccessorErrorBag)<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="successor-readiness" class="text-sm font-bold text-slate-800">Succession Readiness <span class="text-red-500">*</span></label>
                        <select id="successor-readiness" name="succession_readiness" x-model="form.succession_readiness" required class="ui-input mt-1 text-sm">
                            <option value="">Pilih Succession Readiness</option>
                            @foreach (\App\Models\Employee::SUCCESSION_READINESS_OPTIONS as $readiness)<option value="{{ $readiness }}">{{ ucfirst($readiness) }}</option>@endforeach
                        </select>
                        @error('succession_readiness', $activeSuccessorErrorBag)<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="successor-targeted-band" class="text-sm font-bold text-slate-800">Targeted Band <span class="text-red-500">*</span></label>
                        <input id="successor-targeted-band" name="targeted_band" x-model="form.targeted_band" required maxlength="100" placeholder="Masukkan targeted band" class="ui-input mt-1 text-sm">
                        @error('targeted_band', $activeSuccessorErrorBag)<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <div class="md:col-span-3">
                        <label for="successor-recommendation" class="text-sm font-bold text-slate-800">Recommendation <span class="text-red-500">*</span></label>
                        <textarea id="successor-recommendation" name="recommendation" x-model="form.recommendation" required maxlength="2000" rows="2" placeholder="Masukkan rekomendasi assessment" class="ui-input mt-1 text-sm"></textarea>
                        @error('recommendation', $activeSuccessorErrorBag)<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                    </div>
                </div>
            </section>

            <section class="overflow-hidden rounded-2xl border border-blue-200 bg-white">
                <div class="flex items-center gap-3 bg-blue-50/80 px-4 py-2.5">
                    <span class="inline-flex h-8 w-8 items-center justify-center rounded-lg bg-blue-100 text-blue-700"><x-dashboard-icon name="chart" class="h-5 w-5" /></span>
                    <h4 class="font-display text-lg font-bold text-slate-950">Performance &amp; Profil</h4>
                </div>
                <div class="grid gap-3 p-4 md:grid-cols-6">
                    <div class="md:col-span-2">
                        <label for="successor-performance-year" class="text-sm font-bold text-slate-800">Tahun Kinerja <span class="text-red-500">*</span></label>
                        <input id="successor-performance-year" name="performance_year" x-model="form.performance_year" type="number" min="1900" max="{{ now()->year + 1 }}" required placeholder="{{ now()->year }}" class="ui-input mt-1 text-sm">
                        @error('performance_year', $activeSuccessorErrorBag)<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <div class="md:col-span-2">
                        <label for="successor-performance-score" class="text-sm font-bold text-slate-800">Nilai Kinerja <span class="text-red-500">*</span></label>
                        <input id="successor-performance-score" name="performance_score" x-model="form.performance_score" type="number" min="0" max="100" step="0.01" required placeholder="Masukkan nilai" class="ui-input mt-1 text-sm">
                        @error('performance_score', $activeSuccessorErrorBag)<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <div class="md:col-span-2">
                        <label for="successor-performance-category" class="text-sm font-bold text-slate-800">Kategori Kinerja <span class="text-red-500">*</span></label>
                        <input id="successor-performance-category" name="performance_category" x-model="form.performance_category" required maxlength="100" placeholder="Masukkan kategori" class="ui-input mt-1 text-sm">
                        @error('performance_category', $activeSuccessorErrorBag)<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <div class="md:col-span-2">
                        <label for="successor-mbti-type" class="text-sm font-bold text-slate-800">Tipe MBTI <span class="text-red-500">*</span></label>
                        <input id="successor-mbti-type" name="mbti_type" x-model="form.mbti_type" required maxlength="20" placeholder="Contoh: ENTJ" class="ui-input mt-1 text-sm uppercase">
                        @error('mbti_type', $activeSuccessorErrorBag)<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <div class="md:col-span-4">
                        <label for="successor-mbti-description" class="text-sm font-bold text-slate-800">Deskripsi MBTI <span class="text-red-500">*</span></label>
                        <input id="successor-mbti-description" name="mbti_description" x-model="form.mbti_description" required maxlength="2000" placeholder="Masukkan deskripsi MBTI" class="ui-input mt-1 text-sm">
                        @error('mbti_description', $activeSuccessorErrorBag)<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <div class="md:col-span-3">
                        <label for="successor-job-family-1" class="text-sm font-bold text-slate-800">Top I Job Family <span class="text-red-500">*</span></label>
                        <input id="successor-job-family-1" name="top_job_family_1" x-model="form.top_job_family_1" required maxlength="255" placeholder="Masukkan Top I Job Family" class="ui-input mt-1 text-sm">
                        @error('top_job_family_1', $activeSuccessorErrorBag)<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <div class="md:col-span-3">
                        <label for="successor-job-family-2" class="text-sm font-bold text-slate-800">Top II Job Family <span class="text-red-500">*</span></label>
                        <input id="successor-job-family-2" name="top_job_family_2" x-model="form.top_job_family_2" required maxlength="255" placeholder="Masukkan Top II Job Family" class="ui-input mt-1 text-sm">
                        @error('top_job_family_2', $activeSuccessorErrorBag)<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                    </div>
                </div>
            </section>
        </div>

        <aside class="min-w-0 xl:sticky xl:top-0 xl:self-start">
            <p class="mb-3 text-xs font-bold uppercase tracking-[0.18em] text-slate-600">Preview Kandidat</p>
            <article class="overflow-hidden rounded-2xl border border-slate-200 border-t-4 border-t-[#0B1F3A] bg-white p-4 shadow-xl">
                <div class="grid min-w-0 grid-cols-[6.5rem_minmax(0,1fr)_5.5rem] gap-3">
                    <div class="flex h-28 items-center justify-center overflow-hidden rounded-xl bg-blue-100 text-blue-700">
                        <img x-show="photoPreviewUrl" :src="photoPreviewUrl" alt="Preview foto kandidat" class="h-full w-full object-cover">
                        <svg x-show="!photoPreviewUrl" class="h-12 w-12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><circle cx="12" cy="8" r="4" /><path d="M4 21a8 8 0 0 1 16 0" stroke-linecap="round" /></svg>
                    </div>
                    <div class="min-w-0 border-l border-slate-200 pl-3">
                        <h4 class="break-words font-display text-xl font-bold text-slate-950" x-text="form.name || 'Nama Kandidat'"></h4>
                        <p class="mt-1 break-all text-sm font-semibold text-slate-500" x-text="`SAP ${form.sap || '000000'}`"></p>
                    </div>
                    <div :class="talentBoxColor(form.talent_box)" class="flex h-20 flex-col items-center justify-center rounded-2xl px-2 text-center text-white transition-colors">
                        <span class="text-xs font-bold leading-3">TALENT<br>BOX</span>
                        <strong class="mt-1 text-3xl" x-text="form.talent_box || '—'"></strong>
                    </div>
                </div>

                <div :class="successionReadinessColor(form.succession_readiness)" class="mt-3 rounded-full px-4 py-1.5 text-center text-sm font-bold text-white transition-colors" x-text="form.succession_readiness ? form.succession_readiness.replace(/^./, character => character.toUpperCase()) : 'Succession Readiness'"></div>

                <div class="mt-3 rounded-xl bg-blue-50 px-4 py-3">
                    <p class="text-[10px] font-bold uppercase tracking-wider text-blue-700">Current Position</p>
                    <p class="mt-0.5 break-words text-sm font-bold text-slate-950" x-text="form.current_position || 'Posisi Saat Ini'"></p>
                </div>

                <div class="mt-3">
                    <p class="text-xs font-bold uppercase tracking-wide text-blue-700">Position History (Last 3 Years)</p>
                    <template x-for="(history, index) in form.position_history.slice(0, 3)" :key="index">
                        <div class="mt-2 border-l-2 border-blue-500 bg-slate-50 px-3 py-2">
                            <p class="text-xs font-bold text-slate-800" x-text="history.period || 'Periode'"></p>
                            <p class="break-words text-xs text-slate-600" x-text="history.position || 'Riwayat posisi'"></p>
                        </div>
                    </template>
                </div>

                <div class="mt-3 grid grid-cols-2 gap-2">
                    <div class="rounded-xl border border-slate-200 p-3"><p class="text-[10px] font-bold uppercase text-slate-500">Birth Date</p><p class="mt-1 text-sm font-bold text-slate-900" x-text="form.birth_date || 'DD MMM YYYY'"></p></div>
                    <div class="rounded-xl border border-slate-200 p-3"><p class="text-[10px] font-bold uppercase text-slate-500">Last Education</p><p class="mt-1 break-words text-sm font-bold text-slate-900" x-text="form.last_education || 'Pendidikan Terakhir'"></p></div>
                </div>

                <div class="mt-3 rounded-xl border border-slate-200 p-3">
                    <p class="text-[10px] font-bold uppercase text-blue-700">Assessment</p>
                    <div class="mt-2 grid grid-cols-2 gap-3 text-xs"><div><span class="text-slate-500">Recommendation</span><p class="break-words font-bold text-slate-900" x-text="form.recommendation || '—'"></p></div><div><span class="text-slate-500">Targeted Band</span><p class="font-bold text-slate-900" x-text="form.targeted_band || '—'"></p></div></div>
                </div>

                <div class="mt-3 grid grid-cols-3 gap-2 rounded-xl border border-slate-200 p-3 text-center">
                    <div><p class="text-[10px] font-bold uppercase text-blue-700">Performance</p></div>
                    <div class="rounded-lg bg-blue-50 px-2 py-1 text-sm font-bold" x-text="form.performance_year || 'Tahun'"></div>
                    <div class="rounded-lg bg-blue-50 px-2 py-1 text-sm font-bold" x-text="form.performance_score || 'Nilai'"></div>
                    <div class="col-span-3 text-xs font-semibold text-slate-600" x-text="form.performance_category || 'Kategori kinerja'"></div>
                </div>

                <div class="mt-3 rounded-xl border border-slate-200 p-3">
                    <div class="grid grid-cols-[4rem_minmax(0,1fr)] gap-3"><span class="rounded-lg bg-violet-100 px-2 py-1 text-center text-sm font-bold text-violet-700" x-text="form.mbti_type || 'MBTI'"></span><div><p class="text-sm font-bold text-slate-900" x-text="form.mbti_description || 'Deskripsi MBTI'"></p></div></div>
                    <div class="mt-3 space-y-2 border-t border-slate-200 pt-3 text-xs"><p><strong>Top I Job Family</strong> <span class="float-right" x-text="form.top_job_family_1 || '—'"></span></p><p><strong>Top II Job Family</strong> <span class="float-right" x-text="form.top_job_family_2 || '—'"></span></p></div>
                </div>
            </article>
        </aside>
    </div>

    <div class="sticky -bottom-5 -mx-4 mt-5 flex flex-col-reverse gap-2 border-t border-slate-200 bg-white px-4 py-4 sm:-mx-6 sm:flex-row sm:justify-end sm:px-6">
        <button type="button" @click="closeSuccessorForm()" class="ui-button ui-button-secondary sm:min-w-28">Batal</button>
        <button type="submit" class="ui-button ui-button-primary sm:min-w-48">Simpan Successor</button>
    </div>
</form>
