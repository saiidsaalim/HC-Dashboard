@php
    $firstExaminerId = $recapMatrix['examiners'][0]['id'] ?? null;
    $scoreTone = static fn (?float $value): string => match (true) {
        $value === null => 'empty',
        $value < 2.7 => 'danger',
        $value <= 3 => 'warning',
        default => 'success',
    };
    $guidanceTone = static fn (?string $guidance): string => match ($guidance) {
        'Tidak Disarankan' => 'danger',
        'Disarankan Dengan Pertimbangan' => 'warning',
        'Disarankan' => 'success',
        default => 'empty',
    };
@endphp

<div
    class="fp-assessment-comparison"
    x-data="{
        selectedExaminer: String(@js($firstExaminerId ?? '')),
        scrollStart: true,
        scrollEnd: true,
        activePanel() {
            return Array.from(this.$root.querySelectorAll('[data-examiner-matrix]'))
                .find(panel => panel.dataset.examinerMatrix === this.selectedExaminer);
        },
        updateScroll(viewport = null) {
            viewport ??= this.activePanel()?.querySelector('[data-fp-matrix-viewport]');
            if (!viewport) {
                this.scrollStart = true;
                this.scrollEnd = true;
                return;
            }
            this.scrollStart = viewport.scrollLeft <= 2;
            this.scrollEnd = viewport.scrollLeft + viewport.clientWidth >= viewport.scrollWidth - 2;
        },
        selectExaminer(examinerId) {
            this.selectedExaminer = String(examinerId);
            this.scrollStart = true;
            this.scrollEnd = true;
            this.$nextTick(() => {
                const viewport = this.activePanel()?.querySelector('[data-fp-matrix-viewport]');
                if (viewport) viewport.scrollLeft = 0;
                this.updateScroll(viewport);
            });
        },
        moveParticipants(event, direction) {
            const viewport = event.currentTarget.closest('[data-examiner-matrix]')?.querySelector('[data-fp-matrix-viewport]');
            viewport?.scrollBy({ left: direction * viewport.clientWidth * 0.85, behavior: 'smooth' });
        },
    }"
    x-init="$nextTick(() => updateScroll())"
    @resize.window.debounce.150ms="updateScroll()"
>
    <header class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <p class="text-xs font-extrabold uppercase tracking-[0.14em] text-sky-600">Fit &amp; Proper</p>
            <h2 class="mt-1 font-display text-2xl font-bold text-slate-950">Rekap Penilaian per Penguji</h2>
            <p class="mt-1 text-sm text-slate-600">Bandingkan hasil penilaian seluruh peserta dari satu penguji.</p>
        </div>
        <span class="inline-flex items-center gap-2 rounded-full border border-sky-200 bg-sky-50 px-3 py-1.5 text-sm font-bold text-sky-700">
            <svg class="h-4 w-4" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><circle cx="7" cy="6" r="2.5" /><circle cx="14" cy="7" r="2" /><path d="M2.5 16v-1A4.5 4.5 0 0 1 7 10.5 4.5 4.5 0 0 1 11.5 15v1M12 11.5c2.8 0 4.5 1.5 4.5 3.5v1" stroke-linecap="round" /></svg>
            {{ $activity->participants->count() }} Peserta
        </span>
    </header>

    @if (empty($recapMatrix['examiners']))
        <div class="mt-6">
            <x-ui.empty-state title="Belum ada penguji untuk dibandingkan." description="Tambahkan penguji agar rekap penilaian dapat ditampilkan." />
        </div>
    @elseif ($activity->participants->isEmpty())
        <div class="mt-6">
            <x-ui.empty-state title="Belum ada peserta pada kegiatan ini." description="Tambahkan peserta agar matriks penilaian dapat ditampilkan." />
        </div>
    @else
        <div class="fp-matrix-toolbar mt-5 flex flex-wrap items-end gap-3 rounded-xl border border-slate-200 bg-slate-50 p-3">
            <label class="min-w-0 flex-1 sm:max-w-md">
                <span class="text-xs font-bold text-slate-700">Pilih Penguji</span>
                <select
                    class="mt-1 w-full rounded-xl border-slate-300 bg-white text-sm font-semibold text-slate-800 shadow-sm focus:border-sky-500 focus:ring-sky-500"
                    :value="selectedExaminer"
                    @change="selectExaminer($event.target.value)"
                >
                    @foreach ($recapMatrix['examiners'] as $examiner)
                        <option value="{{ $examiner['id'] }}">{{ $examiner['name'] }}</option>
                    @endforeach
                </select>
            </label>

            @foreach ($recapMatrix['examiners'] as $examiner)
                <span
                    x-cloak
                    x-show="selectedExaminer === String({{ $examiner['id'] }})"
                    class="inline-flex min-h-11 items-center gap-2 rounded-xl border border-emerald-200 bg-emerald-50 px-3 text-sm font-bold text-emerald-700"
                    aria-live="polite"
                >
                    <svg class="h-4 w-4" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><circle cx="10" cy="10" r="7" /><path d="m7 10 2 2 4-4" stroke-linecap="round" stroke-linejoin="round" /></svg>
                    {{ $examiner['completed_count'] }} dari {{ $examiner['participant_count'] }} peserta dinilai
                </span>
            @endforeach

            @can('export', $activity)
                <a href="{{ route('fit-proper.excel', $activity) }}" class="ui-button ui-button-secondary ml-auto min-h-11">
                    <svg class="h-4 w-4" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M10 3v9m0 0 3-3m-3 3L7 9M4 14v2h12v-2" stroke-linecap="round" stroke-linejoin="round" /></svg>
                    Unduh Rekap
                </a>
            @endcan
        </div>

        <div class="mt-3 flex items-start gap-2 rounded-xl border border-sky-100 bg-sky-50 px-3 py-2.5 text-xs leading-5 text-slate-600">
            <svg class="mt-0.5 h-4 w-4 shrink-0 text-sky-600" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><circle cx="10" cy="10" r="7" /><path d="M10 9v4m0-7h.01" stroke-linecap="round" /></svg>
            <p>Panduan mentah: &lt; 2,7 Tidak Disarankan; 2,7–3,0 Disarankan Dengan Pertimbangan; &gt; 3,0 Disarankan. Panduan ini terpisah dari kelulusan total.</p>
        </div>

        @foreach ($recapMatrix['examiners'] as $examiner)
            @php
                $hasParticipantOverflow = $examiner['participant_count'] > 3;
            @endphp
            <div
                data-examiner-matrix="{{ $examiner['id'] }}"
                x-cloak
                x-show="selectedExaminer === String({{ $examiner['id'] }})"
                x-effect="if (selectedExaminer === String({{ $examiner['id'] }})) $nextTick(() => updateScroll())"
                class="mt-4"
            >
                @if ($hasParticipantOverflow)
                    <div class="mb-2 flex items-center justify-end gap-2 text-xs font-semibold text-slate-600">
                        <svg class="h-4 w-4" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M3 10h14M6 7l-3 3 3 3m8-6 3 3-3 3" stroke-linecap="round" stroke-linejoin="round" /></svg>
                        Geser untuk melihat peserta lainnya
                    </div>
                @endif

                <div
                    class="fp-assessment-matrix {{ $hasParticipantOverflow ? 'is-scrollable' : '' }}"
                    style="--fp-participant-count: {{ $examiner['participant_count'] }}; --fp-canvas-min-width: {{ $examiner['participant_count'] * 17 }}rem; --fp-canvas-desktop-width: {{ number_format(($examiner['participant_count'] / 3) * 100, 4, '.', '') }}%; --fp-canvas-tablet-width: {{ number_format(($examiner['participant_count'] / 2) * 100, 4, '.', '') }}%; --fp-canvas-mobile-width: {{ $examiner['participant_count'] * 100 }}%"
                    role="table"
                    aria-label="Rekap penilaian oleh {{ $examiner['name'] }}"
                    data-participant-count="{{ $examiner['participant_count'] }}"
                    data-scrollable="{{ $hasParticipantOverflow ? 'true' : 'false' }}"
                >
                    <div class="fp-matrix-labels" role="rowgroup">
                        <div class="fp-matrix-row fp-matrix-row--participant-header flex items-center px-4 text-xs font-extrabold uppercase tracking-wide text-slate-800" role="columnheader">Komponen Penilaian</div>

                        @foreach ($recapMatrix['groups'] as $group)
                            <div class="fp-matrix-row fp-matrix-row--group px-4" role="rowheader">
                                <span>{{ $group['label'] }}</span>
                            </div>
                            @foreach ($group['components'] as $component)
                                <div class="fp-matrix-row fp-matrix-row--score px-4" role="rowheader">{{ $component['label'] }}</div>
                            @endforeach
                            @if ($group['key'] === 'core')
                                <div class="fp-matrix-row fp-matrix-row--subtotal px-4" role="rowheader">Rata-rata Kompetensi Inti</div>
                            @endif
                        @endforeach

                        <div class="fp-matrix-row fp-matrix-row--group px-4" role="rowheader"><span>Ringkasan Hasil</span></div>
                        <div class="fp-matrix-row fp-matrix-row--score px-4 font-semibold" role="rowheader">Kompetensi Inti</div>
                        <div class="fp-matrix-row fp-matrix-row--score px-4 font-semibold" role="rowheader">Kelayakan</div>
                        <div class="fp-matrix-row fp-matrix-row--average px-4" role="rowheader">Rata-rata Akhir</div>
                        <div class="fp-matrix-row fp-matrix-row--guidance px-4" role="rowheader">Panduan / Catatan</div>
                        <div class="fp-matrix-row fp-matrix-row--notes px-4" role="rowheader">Catatan Penguji</div>
                    </div>

                    <div class="fp-matrix-participant-region">
                        <div class="fp-matrix-edge fp-matrix-edge--left" x-cloak x-show="!scrollStart" aria-hidden="true"></div>
                        <div class="fp-matrix-edge fp-matrix-edge--right" x-cloak x-show="!scrollEnd" aria-hidden="true"></div>

                        @if ($hasParticipantOverflow)
                            <button type="button" class="fp-matrix-scroll-button fp-matrix-scroll-button--left" x-cloak x-show="!scrollStart" @click="moveParticipants($event, -1)" aria-label="Lihat peserta sebelumnya">
                                <svg class="h-5 w-5" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m12 5-5 5 5 5" stroke-linecap="round" stroke-linejoin="round" /></svg>
                            </button>
                            <button type="button" class="fp-matrix-scroll-button fp-matrix-scroll-button--right" x-cloak x-show="!scrollEnd" @click="moveParticipants($event, 1)" aria-label="Lihat peserta berikutnya">
                                <svg class="h-5 w-5" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m8 5 5 5-5 5" stroke-linecap="round" stroke-linejoin="round" /></svg>
                            </button>
                        @endif

                        <div
                            class="fp-matrix-viewport"
                            data-fp-matrix-viewport
                            tabindex="0"
                            aria-label="Daftar nilai peserta dapat digeser horizontal."
                            @scroll.passive="updateScroll($event.currentTarget)"
                        >
                            <div class="fp-matrix-canvas" role="rowgroup">
                                @foreach ($examiner['participants'] as $participant)
                                    <div class="fp-matrix-participant" role="presentation">
                                        <div class="fp-matrix-row fp-matrix-row--participant-header fp-matrix-participant-header px-3" role="columnheader">
                                            <span class="fp-matrix-avatar" aria-hidden="true">{{ $participant['initials'] }}</span>
                                            <span class="min-w-0">
                                                <strong class="block truncate text-sm text-slate-950" title="{{ $participant['name'] }}">{{ $participant['name'] }}</strong>
                                                <span class="mt-0.5 block truncate text-xs text-slate-500">{{ $participant['sap'] ?: 'Identitas tidak tersedia' }}</span>
                                                <span class="fp-matrix-status {{ $participant['submitted'] ? 'is-complete' : 'is-incomplete' }}">{{ $participant['submitted'] ? 'Lengkap' : 'Belum Lengkap' }}</span>
                                            </span>
                                        </div>

                                        @foreach ($recapMatrix['groups'] as $group)
                                            <div class="fp-matrix-row fp-matrix-row--group" role="presentation" aria-hidden="true"></div>
                                            @foreach ($group['components'] as $component)
                                                @php
                                                    $indicatorValue = $participant['submitted'] && array_key_exists($component['key'], $participant['indicators']) && is_numeric($participant['indicators'][$component['key']])
                                                        ? (float) $participant['indicators'][$component['key']]
                                                        : null;
                                                    $indicatorTone = $scoreTone($indicatorValue);
                                                @endphp
                                                <div class="fp-matrix-row fp-matrix-row--score fp-matrix-score is-{{ $indicatorTone }} px-3" role="cell" aria-label="{{ $component['label'] }}: {{ $indicatorValue === null ? 'belum dinilai' : number_format($indicatorValue, 1, ',', '.') }}">
                                                    <strong>{{ $indicatorValue === null ? '—' : number_format($indicatorValue, 1, ',', '.') }}</strong>
                                                    <span class="fp-matrix-segments" aria-hidden="true">
                                                        @foreach (range(1, 5) as $segment)
                                                            <i class="{{ $indicatorValue !== null && $indicatorValue >= $segment ? 'is-active' : '' }}"></i>
                                                        @endforeach
                                                    </span>
                                                </div>
                                            @endforeach
                                            @if ($group['key'] === 'core')
                                                @php
                                                    $coreSubtotal = $participant['submitted'] && isset($participant['calculation']['core']) ? (float) $participant['calculation']['core'] : null;
                                                    $coreSubtotalTone = $scoreTone($coreSubtotal);
                                                @endphp
                                                <div class="fp-matrix-row fp-matrix-row--subtotal fp-matrix-score is-{{ $coreSubtotalTone }} px-3" role="cell">
                                                    <strong>{{ $coreSubtotal === null ? '—' : number_format($coreSubtotal, 1, ',', '.') }}</strong>
                                                </div>
                                            @endif
                                        @endforeach

                                        <div class="fp-matrix-row fp-matrix-row--group" role="presentation" aria-hidden="true"></div>
                                        @foreach (['core', 'eligibility'] as $summaryKey)
                                            @php
                                                $summaryValue = $participant['submitted'] && isset($participant['calculation'][$summaryKey]) ? (float) $participant['calculation'][$summaryKey] : null;
                                                $summaryTone = $scoreTone($summaryValue);
                                            @endphp
                                            <div class="fp-matrix-row fp-matrix-row--score fp-matrix-score is-{{ $summaryTone }} px-3" role="cell">
                                                <strong>{{ $summaryValue === null ? '—' : number_format($summaryValue, 1, ',', '.') }}</strong>
                                                <span class="fp-matrix-segments" aria-hidden="true">
                                                    @foreach (range(1, 5) as $segment)
                                                        <i class="{{ $summaryValue !== null && $summaryValue >= $segment ? 'is-active' : '' }}"></i>
                                                    @endforeach
                                                </span>
                                            </div>
                                        @endforeach

                                        @php
                                            $average = $participant['submitted'] && isset($participant['calculation']['average']) ? (float) $participant['calculation']['average'] : null;
                                            $averageTone = $scoreTone($average);
                                            $guidance = $participant['submitted'] ? ($participant['calculation']['guidance'] ?? null) : null;
                                            $guidanceStatusTone = $guidanceTone($guidance);
                                        @endphp
                                        <div class="fp-matrix-row fp-matrix-row--average fp-matrix-score is-{{ $averageTone }} px-3" role="cell">
                                            <strong>{{ $average === null ? '—' : number_format($average, 1, ',', '.') }}</strong>
                                        </div>
                                        <div class="fp-matrix-row fp-matrix-row--guidance flex-col items-start justify-center gap-1 px-3" role="cell">
                                            <span class="fp-matrix-guidance is-{{ $guidanceStatusTone }}">{{ $guidance ?? 'Belum Lengkap' }}</span>
                                            @if ($participant['submitted'] && filled($participant['recommendation']))
                                                <span class="line-clamp-2 text-xs text-slate-600" title="{{ $participant['recommendation'] }}">{{ $participant['recommendation'] }}</span>
                                            @endif
                                        </div>
                                        <div class="fp-matrix-row fp-matrix-row--notes block px-3 py-2" role="cell">
                                            <strong class="block text-xs text-slate-800">Catatan Penguji</strong>
                                            <p class="mt-1 line-clamp-3 text-xs leading-4 text-slate-600" title="{{ $participant['submitted'] ? $participant['notes'] : '' }}">
                                                {{ $participant['submitted'] && filled($participant['notes']) ? $participant['notes'] : 'Belum ada catatan' }}
                                            </p>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
    @endif
</div>
