@extends('layouts.app')
@section('page-title', 'Detail Fit & Proper')

@php
    $submittedExaminerCount = $activity->examiners->whereNotNull('submitted_at')->count();
    $examinerCount = $activity->examiners->count();
    $participantCount = $activity->participants->count();
    $progressPercentage = $examinerCount > 0
        ? min(100, round(($submittedExaminerCount / $examinerCount) * 100))
        : 0;
    $statusTone = match ($activity->status) {
        'Berjalan' => 'info',
        'Selesai' => 'success',
        default => 'warning',
    };
    $creatorName = $users->firstWhere('id', $activity->created_by)?->name
        ?? ($activity->created_by ? 'Pengguna '.$activity->created_by : '—');
    $participantModalOpen = $errors->any() && old('form_context') === 'participant-create';
    $participantEditModalId = $errors->any() && str_starts_with((string) old('form_context'), 'participant-edit-')
        ? (int) str((string) old('form_context'))->afterLast('-')->toString()
        : null;
    $participantSearchTerms = $activity->participants->map(fn ($participant): string => mb_strtolower(implode(' ', array_filter([
        $participant->name,
        $participant->sap,
        $participant->profile['department'] ?? null,
        $participant->profile['current_position'] ?? null,
        $participant->profile['target_position'] ?? null,
    ]))))->values();
    $canManagePics = $activity->status === 'Draft' && auth()->user()->can('update', $activity);
    $assignedPicIds = $activity->pics->pluck('id')->map(fn ($id): string => (string) $id)->values();
    $picUserOptions = $users->map(fn ($user): array => [
        'id' => (string) $user->id,
        'name' => $user->name,
        'email' => $user->email,
    ])->values();
    $selectedPicIds = collect(old('users', []))
        ->map(fn ($id): string => (string) $id)
        ->reject(fn (string $id): bool => $assignedPicIds->contains($id))
        ->values();
    $canManageExaminerStructure = $activity->status === 'Draft' && auth()->user()->can('update', $activity);
    $canManageExaminerLinks = $activity->status !== 'Selesai' && auth()->user()->can('update', $activity);
    $assignedExaminerUserIds = $activity->examiners->pluck('user_id')->filter()->map(fn ($id): string => (string) $id)->values();
    [$examinerBannerClasses, $examinerBannerIconClasses] = match ($activity->status) {
        'Berjalan' => ['border-sky-200 bg-sky-50 text-sky-950', 'bg-sky-100 text-sky-700'],
        'Selesai' => ['border-emerald-200 bg-emerald-50 text-emerald-950', 'bg-emerald-100 text-emerald-700'],
        default => ['border-amber-200 bg-amber-50 text-amber-950', 'bg-amber-100 text-amber-700'],
    };
    $tabs = [
        'ringkasan' => 'Ringkasan',
        'peserta' => 'Peserta',
        'pic' => 'PIC',
        'penguji' => 'Penguji',
        'rekap' => 'Rekap Penilaian',
        'hasil' => 'Hasil Akhir',
        'riwayat' => 'Riwayat',
    ];
@endphp

@section('content')
<div
    id="fit-proper-detail-{{ $activity->id }}"
    class="fp-detail space-y-4"
    data-fp-root
    x-data="{
        activeTab: 'ringkasan',
        editOpen: false,
        participantOpen: @js($participantModalOpen),
        participantEditId: @js($participantEditModalId),
        participantModalTrigger: null,
        participantSearch: '',
        participantSearchTerms: @js($participantSearchTerms),
        availableTabs: @js(array_keys($tabs)),
        matchesParticipant(searchText) {
            const term = this.participantSearch.trim().toLocaleLowerCase();
            return term === '' || searchText.toLocaleLowerCase().includes(term);
        },
        hasParticipantMatches() {
            return this.participantSearchTerms.some(searchText => this.matchesParticipant(searchText));
        },
        participantModalSelector() {
            if (this.participantOpen) return '[data-fp-participant-modal]';
            if (this.participantEditId) return `[data-fp-participant-edit-modal][data-participant-id='${this.participantEditId}']`;
            return null;
        },
        activateParticipantModal() {
            const selector = this.participantModalSelector();
            if (!selector) return;

            document.body.classList.add('overflow-y-hidden');
            this.$nextTick(() => setTimeout(() => document.querySelector(`${selector} [aria-invalid=true], ${selector} [data-fp-modal-close]`)?.focus(), 50));
        },
        openParticipantCreate(trigger) {
            this.participantModalTrigger = trigger;
            this.participantEditId = null;
            this.participantOpen = true;
            this.activateParticipantModal();
        },
        openParticipantEdit(participantId, trigger) {
            this.participantModalTrigger = trigger;
            this.participantOpen = false;
            this.participantEditId = participantId;
            this.activateParticipantModal();
        },
        closeParticipantModal() {
            const trigger = this.participantModalTrigger;
            this.participantOpen = false;
            this.participantEditId = null;
            document.body.classList.remove('overflow-y-hidden');
            this.$nextTick(() => trigger?.focus({ preventScroll: true }));
        },
        selectTab(tab) {
            if (!this.availableTabs.includes(tab)) return;
            this.activeTab = tab;
            window.history.replaceState(null, '', `#${tab}`);
        },
        syncHash() {
            const tab = window.location.hash.replace('#', '');
            if (this.availableTabs.includes(tab)) this.activeTab = tab;
        },
    }"
    x-init="syncHash(); if (participantOpen || participantEditId) { activeTab = 'peserta'; activateParticipantModal(); }"
    @hashchange.window="syncHash()"
    @keydown.escape.window="editOpen = false; closeParticipantModal()"
>
    <div data-fp-fragment="heading">
        <header class="fp-detail-hero overflow-hidden rounded-2xl border border-sky-900/60 p-5 text-white shadow-lg sm:p-6">
            <div class="relative z-10 flex flex-col gap-5 sm:flex-row sm:items-center sm:justify-between">
                <div class="flex min-w-0 items-start gap-4">
                    <span class="flex h-14 w-14 shrink-0 items-center justify-center rounded-2xl border border-sky-400/30 bg-sky-400/15 text-sky-300" aria-hidden="true">
                        <svg class="h-7 w-7" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                            <rect x="5" y="3.5" width="14" height="17" rx="2.5" />
                            <path d="M9 3.5v-1h6v1M8.5 9h7M8.5 13h7M8.5 17H12" stroke-linecap="round" />
                        </svg>
                    </span>
                    <div class="min-w-0">
                        <p class="text-xs font-bold uppercase tracking-[0.18em] text-cyan-300">Fit &amp; Proper</p>
                        <h1 class="mt-1 break-words font-display text-2xl font-bold tracking-tight sm:text-3xl">{{ $activity->name }}</h1>
                        <p class="mt-1 text-sm text-slate-300">{{ $activity->target_position ?: 'Posisi tujuan belum tersedia' }}</p>
                        @if ($activity->description)
                            <p class="mt-2 max-w-3xl whitespace-pre-line text-sm leading-5 text-slate-400">{{ $activity->description }}</p>
                        @endif
                    </div>
                </div>

                <div class="shrink-0 sm:text-right">
                    <x-ui.status-badge :tone="$statusTone" class="border-white/15 bg-slate-950/45 text-white">
                        {{ $activity->status }}
                    </x-ui.status-badge>
                    <p class="mt-3 flex items-center gap-2 text-sm font-medium text-slate-300 sm:justify-end">
                        <svg class="h-4 w-4 text-cyan-300" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true">
                            <rect x="3" y="4.5" width="14" height="12.5" rx="2" />
                            <path d="M6 2.5v4M14 2.5v4M3 8.5h14" stroke-linecap="round" />
                        </svg>
                        {{ $activity->department ?: 'Unit belum tersedia' }} · {{ $activity->scheduled_at?->format('d/m/Y') ?? '—' }}
                    </p>
                </div>
            </div>
        </header>
    </div>

    @include('fit-proper._messages')

    <nav data-fp-tabs aria-label="Bagian detail kegiatan" class="fp-detail-nav scrollbar-hidden mx-auto flex w-fit max-w-full gap-1 overflow-x-auto rounded-2xl border border-slate-200 bg-white p-2 shadow-sm">
        <a href="{{ route('fit-proper') }}" class="fp-detail-back ui-button ui-button-secondary shrink-0">
            <svg class="h-4 w-4" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                <path d="M16 10H4m5-5-5 5 5 5" stroke-linecap="round" stroke-linejoin="round" />
            </svg>
            Kembali
        </a>

        @foreach ($tabs as $id => $label)
            <a
                href="#{{ $id }}"
                class="fp-detail-tab inline-flex shrink-0 items-center gap-2 rounded-xl px-3 py-2.5 text-sm font-bold text-slate-600 transition"
                :class="activeTab === '{{ $id }}' ? 'is-active' : ''"
                :aria-selected="(activeTab === '{{ $id }}').toString()"
                role="tab"
                @click.prevent="selectTab('{{ $id }}')"
            >
                @switch($id)
                    @case('ringkasan')
                        <svg class="h-4 w-4" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M3 9 10 3l7 6v7.5a.5.5 0 0 1-.5.5h-13a.5.5 0 0 1-.5-.5V9Z" /><path d="M8 17v-5h4v5" /></svg>
                        @break
                    @case('peserta')
                        <svg class="h-4 w-4" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><circle cx="7" cy="7" r="3" /><circle cx="14" cy="8" r="2.5" /><path d="M1.5 17a5.5 5.5 0 0 1 11 0M11 14a4.5 4.5 0 0 1 7 3" stroke-linecap="round" /></svg>
                        @break
                    @case('pic')
                        <svg class="h-4 w-4" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><circle cx="10" cy="6" r="3" /><path d="M4 17a6 6 0 0 1 12 0" stroke-linecap="round" /></svg>
                        @break
                    @case('penguji')
                        <svg class="h-4 w-4" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M10 2.5 4 5v4.3c0 4 2.5 7 6 8.7 3.5-1.7 6-4.7 6-8.7V5l-6-2.5Z" /><path d="m7.5 10 1.7 1.7 3.5-4" stroke-linecap="round" stroke-linejoin="round" /></svg>
                        @break
                    @case('rekap')
                        <svg class="h-4 w-4" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M3 17V9m5 8V4m5 13v-6m4 6V2" stroke-linecap="round" /></svg>
                        @break
                    @case('hasil')
                        <svg class="h-4 w-4" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M6 3h8v5a4 4 0 0 1-8 0V3ZM6 5H3v2a3 3 0 0 0 3 3M14 5h3v2a3 3 0 0 1-3 3M10 12v3M7 17h6" stroke-linecap="round" stroke-linejoin="round" /></svg>
                        @break
                    @default
                        <svg class="h-4 w-4" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><circle cx="10" cy="10" r="7" /><path d="M10 6v4l3 2" stroke-linecap="round" /></svg>
                @endswitch
                {{ $label }}
            </a>
        @endforeach
    </nav>

    <section
        data-fp-structure="ringkasan"
        id="ringkasan"
        x-cloak
        class="fp-detail-panel rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"
        x-show="activeTab === 'ringkasan'"
        x-transition.opacity.duration.150ms
    >
        <div data-fp-fragment="summary">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <p class="text-xs font-bold uppercase tracking-[0.16em] text-sky-700">Dashboard Kegiatan</p>
                    <h2 class="mt-1 font-display text-xl font-bold text-slate-950">Ringkasan Kegiatan</h2>
                    <p class="mt-1 text-sm text-slate-500">Informasi utama dan progres penilaian kegiatan Fit &amp; Proper.</p>
                </div>
                <x-ui.status-badge :tone="$statusTone">{{ $activity->status }}</x-ui.status-badge>
            </div>

            <dl class="fp-detail-metrics mt-5 grid grid-cols-2 gap-3 lg:grid-cols-4">
                <div class="fp-detail-kpi fp-detail-kpi--blue">
                    <dt>Peserta</dt>
                    <dd>{{ $participantCount }}</dd>
                </div>
                <div class="fp-detail-kpi fp-detail-kpi--violet">
                    <dt>Penguji</dt>
                    <dd>{{ $examinerCount }}</dd>
                </div>
                <div class="fp-detail-kpi fp-detail-kpi--amber">
                    <dt>Passing Grade</dt>
                    <dd>{{ number_format($activity->standard['passing_grade'], 1, ',', '.') }}</dd>
                </div>
                <div class="fp-detail-kpi fp-detail-kpi--emerald">
                    <dt>Penilaian Terkirim</dt>
                    <dd>{{ $submittedExaminerCount }} / {{ $examinerCount }}</dd>
                </div>
            </dl>

            <div class="fp-detail-standard mt-3 flex gap-2 rounded-xl border border-sky-200 bg-sky-50 px-4 py-3 text-sm text-sky-800">
                <svg class="mt-0.5 h-4 w-4 shrink-0" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><circle cx="10" cy="10" r="7" /><path d="M10 9v5M10 6.2v.1" stroke-linecap="round" /></svg>
                <p>Standar tersimpan: KPI {{ number_format($activity->standard['weight_kpi'] * 100, 1, ',', '.') }}%, asesmen {{ number_format($activity->standard['weight_assessment'] * 100, 1, ',', '.') }}%, FP {{ number_format($activity->standard['weight_fp'] * 100, 1, ',', '.') }}%. Skala KPI {{ number_format($activity->standard['max_kpi'], 1, ',', '.') }}, asesmen {{ number_format($activity->standard['max_assessment'], 1, ',', '.') }}. Passing grade {{ number_format($activity->standard['passing_grade'], 1, ',', '.') }}.</p>
            </div>

            <div data-fp-fragment="progres" class="fp-detail-subcard mt-4 rounded-xl border border-slate-200 p-4">
                <div class="flex flex-wrap items-end justify-between gap-3">
                    <div>
                        <h3 class="font-display text-base font-bold text-slate-900">Progres Penilaian</h3>
                        <p class="mt-1 text-sm text-slate-500">{{ $submittedExaminerCount }} dari {{ $examinerCount }} penguji telah mengirim seluruh penilaian.</p>
                    </div>
                    <strong class="font-display text-2xl text-sky-700">{{ $progressPercentage }}%</strong>
                </div>
                <div class="mt-3 h-2.5 overflow-hidden rounded-full bg-slate-200" role="progressbar" aria-valuenow="{{ $progressPercentage }}" aria-valuemin="0" aria-valuemax="100" aria-label="Progres penilaian">
                    <div class="h-full rounded-full bg-sky-500 transition-all duration-300" style="width: {{ $progressPercentage }}%"></div>
                </div>
            </div>

            <div class="mt-4 flex flex-wrap gap-2">
                @if ($activity->status !== 'Selesai')
                    @can('update', $activity)
                        <form data-fp-async method="POST" action="{{ route('fit-proper.transition', $activity) }}" data-fp-confirm="Ubah status kegiatan? Kembali Draft membatalkan tautan dan status kirim seluruh penguji.">
                            @csrf
                            <input type="hidden" name="status" value="{{ $activity->status === 'Draft' ? 'Berjalan' : 'Draft' }}">
                            <button class="ui-button ui-button-primary">
                                <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="m7 5 8 5-8 5V5Z" /></svg>
                                {{ $activity->status === 'Draft' ? 'Mulai Kegiatan / Buka Penilaian' : 'Kembali ke Draft' }}
                            </button>
                        </form>
                    @endcan
                    @if ($activity->status === 'Berjalan')
                        @can('finalize', $activity)
                            <form data-fp-async method="POST" action="{{ route('fit-proper.finalize', $activity) }}" data-fp-confirm="Finalisasi mengunci seluruh hasil secara permanen. Lanjutkan?">
                                @csrf
                                <button class="ui-button border border-emerald-700 bg-emerald-700 text-white hover:bg-emerald-800">Finalisasi</button>
                            </form>
                        @endcan
                    @endif
                @endif
                @if ($activity->status === 'Draft')
                    @can('update', $activity)
                        <button type="button" class="ui-button ui-button-secondary" @click="editOpen = true">
                            <svg class="h-4 w-4" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                <path d="m13.8 3.2 3 3L7 16H4v-3L13.8 3.2Z" stroke-linecap="round" stroke-linejoin="round" />
                            </svg>
                            Edit Kegiatan
                        </button>
                    @endcan
                @endif
                @can('export', $activity)
                    <a href="{{ route('fit-proper.print', $activity) }}" class="ui-button ui-button-secondary">
                        <svg class="h-4 w-4" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M5 7V3h10v4M5 14H3V8h14v6h-2M6 12h8v5H6v-5Z" stroke-linejoin="round" /></svg>
                        Cetak Laporan
                    </a>
                    <a href="{{ route('fit-proper.excel', $activity) }}" class="ui-button ui-button-secondary">
                        <svg class="h-4 w-4" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M5 2.5h7l3 3V17.5H5v-15Z" /><path d="M12 2.5v3h3M7.5 10h5M7.5 13h5" stroke-linecap="round" /></svg>
                        Ekspor Excel
                    </a>
                @endcan
            </div>

            <div class="mt-4 grid gap-4 lg:grid-cols-2">
                <article class="fp-detail-subcard rounded-xl border border-slate-200 p-4">
                    <h3 class="flex items-center gap-2 font-display text-base font-bold text-slate-900">
                        <svg class="h-5 w-5 text-sky-600" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M4 3h12v14H4V3Z" /><path d="M7 7h6M7 10h6M7 13h4" stroke-linecap="round" /></svg>
                        Profil Kegiatan
                    </h3>
                    <dl class="mt-3 grid gap-2 text-sm sm:grid-cols-2">
                        <div><dt class="text-xs text-slate-500">Pembuat</dt><dd class="mt-0.5 font-semibold text-slate-700">{{ $creatorName }}</dd></div>
                        <div><dt class="text-xs text-slate-500">Status</dt><dd class="mt-0.5 font-semibold text-slate-700">{{ $activity->status }}</dd></div>
                        <div><dt class="text-xs text-slate-500">Unit</dt><dd class="mt-0.5 font-semibold text-slate-700">{{ $activity->department ?: '—' }}</dd></div>
                        <div><dt class="text-xs text-slate-500">Tanggal</dt><dd class="mt-0.5 font-semibold text-slate-700">{{ $activity->scheduled_at?->format('d/m/Y') ?? '—' }}</dd></div>
                        <div class="sm:col-span-2"><dt class="text-xs text-slate-500">Finalisasi</dt><dd class="mt-0.5 font-semibold text-slate-700">{{ $activity->finalized_at?->format('d/m/Y H:i') ?? 'Belum difinalisasi' }}</dd></div>
                    </dl>
                </article>

                <article class="fp-detail-subcard rounded-xl border border-slate-200 p-4">
                    <h3 class="flex items-center gap-2 font-display text-base font-bold text-slate-900">
                        <svg class="h-5 w-5 text-emerald-600" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M10 2.5 4 5v4.3c0 4 2.5 7 6 8.7 3.5-1.7 6-4.7 6-8.7V5l-6-2.5Z" /><path d="m7.5 10 1.7 1.7 3.5-4" stroke-linecap="round" stroke-linejoin="round" /></svg>
                        Status Kegiatan
                    </h3>
                    <p class="mt-3 text-sm text-slate-600">{{ $submittedExaminerCount }} dari {{ $examinerCount }} penguji telah mengirim seluruh penilaian.</p>
                    @if ($recap['complete'])
                        <p class="mt-3 rounded-xl border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-800">Seluruh data lengkap dan normalisasi tersedia. Kegiatan siap difinalisasi sesuai hak akses.</p>
                    @else
                        <p class="mt-3 rounded-xl border border-amber-200 bg-amber-50 p-3 text-sm text-amber-800">Belum siap finalisasi. Periksa kelengkapan KPI, asesmen, peserta, dan seluruh penilaian penguji.</p>
                    @endif
                    @if ($recap['zero_max'])
                        <p class="mt-2 rounded-xl border border-red-200 bg-red-50 p-3 text-sm text-red-700">Normalisasi belum tersedia: belum ada rata-rata lengkap di atas nol.</p>
                    @endif
                </article>
            </div>

            @if ($activity->status === 'Draft')
                @if ($activity->participants->isEmpty() && $activity->examiners->isEmpty())
                    @can('delete', $activity)
                        <form method="POST" action="{{ route('fit-proper.destroy', $activity) }}" class="mt-3" onsubmit="return confirm('Hapus kegiatan kosong ini?')">
                            @csrf
                            @method('DELETE')
                            <button class="ui-button border border-red-200 bg-red-50 text-red-700 hover:bg-red-100">Hapus Kegiatan Kosong</button>
                        </form>
                    @endcan
                @endif

                @can('update', $activity)
                    <template x-teleport="body">
                        <div
                            x-cloak
                            x-show="editOpen"
                            class="fixed inset-0 z-[100] flex items-center justify-center p-4 sm:p-6"
                            role="presentation"
                            data-fp-root
                            data-fp-owner="fit-proper-detail-{{ $activity->id }}"
                            data-app-content
                        >
                            <div class="absolute inset-0 bg-slate-950/60 backdrop-blur-[1px]" @click="editOpen = false" aria-hidden="true"></div>
                            <section
                                x-show="editOpen"
                                x-transition:enter="transition ease-out duration-200"
                                x-transition:enter-start="translate-y-3 opacity-0"
                                x-transition:enter-end="translate-y-0 opacity-100"
                                x-transition:leave="transition ease-in duration-150"
                                x-transition:leave-start="translate-y-0 opacity-100"
                                x-transition:leave-end="translate-y-3 opacity-0"
                                class="relative z-10 w-full max-w-3xl overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-2xl"
                                role="dialog"
                                aria-modal="true"
                                aria-labelledby="fit-proper-edit-title"
                            >
                                <header class="flex items-start justify-between gap-4 border-b border-slate-200 px-5 py-4">
                                    <div>
                                        <p class="text-xs font-bold uppercase tracking-[0.18em] text-sky-700">Fit &amp; Proper</p>
                                        <h2 id="fit-proper-edit-title" class="mt-1 font-display text-xl font-bold text-slate-950">Edit Kegiatan</h2>
                                        <p class="mt-1 text-sm text-slate-500">Perbarui informasi kegiatan tanpa mengubah proses penilaian.</p>
                                    </div>
                                    <button type="button" class="rounded-lg p-2 text-slate-400 transition hover:bg-slate-100 hover:text-slate-700" @click="editOpen = false" aria-label="Tutup form edit kegiatan">
                                        <svg class="h-5 w-5" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="m5 5 10 10M15 5 5 15" stroke-linecap="round" /></svg>
                                    </button>
                                </header>
                                <form data-fp-async method="POST" action="{{ route('fit-proper.update', $activity) }}" class="max-h-[75vh] overflow-y-auto p-5">
                                    @method('PUT')
                                    @include('fit-proper._activity-form')
                                </form>
                            </section>
                        </div>
                    </template>
                @endcan
            @endif
        </div>
    </section>

    <section
        data-fp-structure="peserta"
        id="peserta"
        x-cloak
        class="fp-detail-panel rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"
        x-show="activeTab === 'peserta'"
        x-transition.opacity.duration.150ms
    >
        <div class="flex flex-col gap-4 xl:flex-row xl:items-center xl:justify-between">
            <div>
                <h2 class="font-display text-2xl font-bold text-slate-950">Daftar Peserta</h2>
                <p class="mt-1 text-sm text-slate-500">{{ $participantCount }} peserta terdaftar pada kegiatan ini.</p>
            </div>

            @if ($participantCount > 0)
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
                    @if ($activity->status === 'Draft')
                        @can('update', $activity)
                            <button type="button" class="fp-participant-add ui-button w-full sm:w-auto" @click="openParticipantCreate($event.currentTarget)">
                                <svg class="h-5 w-5" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M10 4v12M4 10h12" stroke-linecap="round" /></svg>
                                Tambah Peserta
                            </button>
                        @endcan
                    @endif

                    <label class="relative block min-w-0 sm:w-72">
                        <span class="sr-only">Cari peserta</span>
                        <svg class="pointer-events-none absolute left-3 top-1/2 h-5 w-5 -translate-y-1/2 text-slate-400" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><circle cx="8.5" cy="8.5" r="5.25" /><path d="m12.5 12.5 4 4" stroke-linecap="round" /></svg>
                        <input
                            type="search"
                            x-model.trim="participantSearch"
                            placeholder="Cari peserta..."
                            class="w-full rounded-xl border-slate-300 py-2.5 pl-10 pr-3 text-sm shadow-sm focus:border-sky-500 focus:ring-sky-500"
                        >
                    </label>
                </div>
            @endif
        </div>

        @if ($participantCount > 0)
            <div class="fp-participant-layout mt-5 hidden rounded-xl bg-slate-100 px-4 py-3 text-xs font-extrabold uppercase tracking-wide text-slate-600 lg:grid" aria-hidden="true">
                <span>Peserta</span>
                <span>Profil &amp; Pergerakan</span>
                <span>Hasil Asesmen</span>
                <span>Tindakan</span>
            </div>

            <div class="mt-3 space-y-3" data-fp-participant-list>
                @foreach ($activity->participants as $participant)
                    @php
                        $profile = $participant->profile ?? [];
                        $participantName = $participant->name ?: 'Peserta';
                        $participantInitials = collect(preg_split('/\s+/', trim($participantName)))
                            ->filter()
                            ->take(2)
                            ->map(fn ($part) => mb_strtoupper(mb_substr($part, 0, 1)))
                            ->implode('');
                        $participantAge = $participant->age();
                        $participantKpi = $participant->kpi();
                        $participantAssessment = $participant->assessment === null ? null : (float) $participant->assessment;
                        $kpiMaximum = (float) ($activity->standard['max_kpi'] ?? 0);
                        $assessmentMaximum = (float) ($activity->standard['max_assessment'] ?? 0);
                        $kpiProgress = $participantKpi !== null && $kpiMaximum > 0 ? min(100, max(0, ($participantKpi / $kpiMaximum) * 100)) : 0;
                        $assessmentProgress = $participantAssessment !== null && $assessmentMaximum > 0 ? min(100, max(0, ($participantAssessment / $assessmentMaximum) * 100)) : 0;
                        $participantSearchText = mb_strtolower(implode(' ', array_filter([
                            $participantName,
                            $participant->sap,
                            $profile['department'] ?? null,
                            $profile['current_position'] ?? null,
                            $profile['target_position'] ?? null,
                        ])));
                    @endphp

                    <article
                        x-data="{ profileOpen: false }"
                        x-show="matchesParticipant($el.dataset.participantSearch)"
                        x-transition.opacity.duration.150ms
                        :class="{ 'is-open': profileOpen }"
                        class="fp-participant-card rounded-2xl border border-slate-200 bg-white p-4 shadow-sm"
                        data-fp-participant-card
                        data-participant-search="{{ $participantSearchText }}"
                    >
                        <div class="fp-participant-layout">
                            <div class="flex min-w-0 items-center gap-3">
                                <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-sky-100 font-display text-sm font-bold text-slate-900" aria-hidden="true">{{ $participantInitials ?: 'P' }}</span>
                                <div class="min-w-0">
                                    <h3 class="break-words font-display text-base font-bold text-slate-950">{{ $participantName }}</h3>
                                    <p class="mt-1 break-all text-sm text-slate-500">{{ $participant->sap ?: '—' }}</p>
                                    <p class="mt-0.5 text-sm text-slate-500">Umur: {{ $participantAge === null ? '—' : $participantAge.' tahun' }}</p>
                                </div>
                            </div>

                            <div class="min-w-0 border-t border-slate-200 pt-4 sm:border-0 sm:pt-0">
                                <p class="flex items-start gap-2 text-sm font-semibold text-slate-800">
                                    <svg class="mt-0.5 h-5 w-5 shrink-0 text-sky-600" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M3.5 17V7.5h4V17m0 0V3.5h5V17m0 0V9.5h4V17M2 17h16" stroke-linecap="round" stroke-linejoin="round" /></svg>
                                    <span class="break-words">{{ $profile['department'] ?? '—' }}</span>
                                </p>
                                <div class="mt-3 flex flex-wrap items-center gap-2 text-xs font-bold text-slate-700">
                                    <span class="max-w-full break-words rounded-lg bg-slate-100 px-3 py-1.5">{{ $profile['current_position'] ?? '—' }}</span>
                                    <span class="text-slate-400" aria-label="menuju">→</span>
                                    <span class="max-w-full break-words rounded-lg bg-sky-100 px-3 py-1.5 text-sky-800">{{ $profile['target_position'] ?? '—' }}</span>
                                </div>
                                <div class="mt-2 flex flex-wrap items-center gap-2 text-xs font-bold">
                                    <span class="rounded-lg bg-slate-100 px-3 py-1.5 text-slate-700">JG {{ $profile['current_jg'] ?? '—' }}</span>
                                    <span class="text-slate-400" aria-label="menuju">→</span>
                                    <span class="rounded-lg bg-sky-100 px-3 py-1.5 text-sky-800">JG {{ $profile['target_jg'] ?? '—' }}</span>
                                    <span class="rounded-lg bg-amber-100 px-3 py-1.5 text-amber-900">Gap {{ $profile['gap_jg'] ?? '—' }}</span>
                                </div>
                            </div>

                            <div class="grid min-w-0 grid-cols-2 gap-3 border-t border-slate-200 pt-4 sm:border-0 sm:pt-0">
                                <div>
                                    <p class="text-xs font-bold uppercase tracking-wide text-slate-500">KPI</p>
                                    <p class="mt-1 font-display text-2xl font-bold text-sky-700">{{ $participantKpi === null ? '—' : number_format($participantKpi, 1, ',', '.') }}</p>
                                    @if ($participantKpi !== null && $kpiMaximum > 0)
                                        <div class="mt-2 h-2 overflow-hidden rounded-full bg-slate-200" role="progressbar" aria-label="KPI {{ $participantName }}" aria-valuenow="{{ round($participantKpi, 1) }}" aria-valuemin="0" aria-valuemax="{{ $kpiMaximum }}">
                                            <span class="block h-full rounded-full bg-sky-500" style="width: {{ $kpiProgress }}%"></span>
                                        </div>
                                    @endif
                                </div>
                                <div>
                                    <p class="text-xs font-bold uppercase tracking-wide text-slate-500">Asesmen</p>
                                    <p class="mt-1 font-display text-2xl font-bold text-sky-700">{{ $participantAssessment === null ? '—' : number_format($participantAssessment, 1, ',', '.') }}</p>
                                    @if ($participantAssessment !== null && $assessmentMaximum > 0)
                                        <div class="mt-2 h-2 overflow-hidden rounded-full bg-slate-200" role="progressbar" aria-label="Asesmen {{ $participantName }}" aria-valuenow="{{ round($participantAssessment, 1) }}" aria-valuemin="0" aria-valuemax="{{ $assessmentMaximum }}">
                                            <span class="block h-full rounded-full bg-sky-500" style="width: {{ $assessmentProgress }}%"></span>
                                        </div>
                                    @endif
                                </div>
                            </div>

                            <div class="flex min-w-0 flex-wrap items-center gap-2 border-t border-slate-200 pt-4 sm:border-0 sm:pt-0 lg:justify-end">
                                <button
                                    type="button"
                                    class="fp-participant-profile-button ui-button ui-button-secondary"
                                    @click="profileOpen = !profileOpen"
                                    :aria-expanded="profileOpen.toString()"
                                    aria-controls="fp-participant-profile-{{ $participant->id }}"
                                >
                                    Profil Lengkap
                                    <svg class="h-4 w-4 transition-transform" :class="{ 'rotate-90': profileOpen }" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m7 4 6 6-6 6" stroke-linecap="round" stroke-linejoin="round" /></svg>
                                </button>

                                @if ($activity->status === 'Draft')
                                    @can('update', $activity)
                                        <button
                                            type="button"
                                            class="fp-participant-icon-button text-sky-700"
                                            aria-label="Edit peserta {{ $participantName }}"
                                            title="Edit peserta"
                                            data-fp-participant-edit-trigger
                                            @click="openParticipantEdit({{ $participant->id }}, $event.currentTarget)"
                                        >
                                            <x-dashboard-icon name="edit" class="h-5 w-5" />
                                        </button>
                                        <form method="POST" action="{{ route('fit-proper.participants.delete', [$activity, $participant]) }}" onsubmit="return confirm('Hapus peserta beserta nilainya?')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="fp-participant-icon-button text-red-600" aria-label="Hapus peserta {{ $participantName }}" title="Hapus peserta">
                                                <svg class="h-5 w-5" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M3.5 5.5h13M8 2.75h4M6 5.5l.6 11h6.8l.6-11M8.25 8.5v5.25M11.75 8.5v5.25" stroke-linecap="round" stroke-linejoin="round" /></svg>
                                            </button>
                                        </form>
                                    @endcan
                                @endif
                            </div>
                        </div>

                        <div
                            id="fp-participant-profile-{{ $participant->id }}"
                            x-cloak
                            x-show="profileOpen"
                            x-transition.opacity.duration.150ms
                            class="fp-participant-profile mt-4 rounded-xl border border-slate-200 bg-slate-50 p-4"
                        >
                            <div class="grid gap-x-6 gap-y-3 md:grid-cols-3">
                                @foreach (['education' => 'Pendidikan', 'education_background' => 'Latar pendidikan', 'experience' => 'Pengalaman', 'training' => 'Pelatihan', 'achievement' => 'Achievement', 'assessment_notes' => 'Rekomendasi asesmen'] as $key => $label)
                                    <div class="min-w-0 text-sm">
                                        <span class="font-bold text-slate-800">{{ $label }}:</span>
                                        <span class="whitespace-pre-line break-words text-slate-600">{{ filled($profile[$key] ?? null) ? $profile[$key] : '—' }}</span>
                                    </div>
                                @endforeach
                            </div>
                            @if ($participant->cv_path)
                                <a href="{{ route('fit-proper.cv', [$activity, $participant]) }}" class="mt-4 inline-flex items-center gap-2 text-sm font-bold text-sky-700 hover:text-sky-900 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-sky-500">
                                    <x-dashboard-icon name="document" class="h-4 w-4" />
                                    Lihat CV
                                </a>
                            @endif
                        </div>
                    </article>
                @endforeach
            </div>

            <div x-cloak x-show="participantSearch !== '' && !hasParticipantMatches()" class="mt-4 rounded-2xl border border-dashed border-slate-300 bg-slate-50 px-5 py-10 text-center">
                <p class="font-bold text-slate-800">Peserta tidak ditemukan</p>
                <p class="mt-1 text-sm text-slate-500">Coba gunakan nama, SAP, posisi, atau departemen lain.</p>
            </div>
        @else
            <div class="mt-5 rounded-2xl border border-dashed border-slate-300 bg-slate-50 px-5 py-10 text-center">
                <span class="mx-auto flex h-12 w-12 items-center justify-center rounded-xl bg-white text-slate-400 shadow-sm" aria-hidden="true">
                    <x-dashboard-icon name="users" class="h-6 w-6" />
                </span>
                <h3 class="mt-3 font-display text-base font-bold text-slate-800">Belum ada peserta</h3>
                <p class="mt-1 text-sm text-slate-500">Tambahkan peserta untuk memulai proses Fit &amp; Proper.</p>
                @if ($activity->status === 'Draft')
                    @can('update', $activity)
                        <button type="button" class="fp-participant-add ui-button mt-4" @click="openParticipantCreate($event.currentTarget)">
                            <svg class="h-5 w-5" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M10 4v12M4 10h12" stroke-linecap="round" /></svg>
                            Tambah Peserta
                        </button>
                    @endcan
                @endif
            </div>
        @endif

        @if ($activity->status === 'Draft')
            @can('update', $activity)
                @include('fit-proper._participant-modal', ['mode' => 'create', 'participant' => null])

                @foreach ($activity->participants as $participant)
                    @include('fit-proper._participant-modal', ['mode' => 'edit', 'participant' => $participant])
                @endforeach
            @endcan
        @endif
    </section>

    <section
        data-fp-structure="pic"
        id="pic"
        x-cloak
        class="fp-detail-panel rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"
        x-show="activeTab === 'pic'"
        x-transition.opacity.duration.150ms
    >
        <div
            data-fp-fragment="pic-panel"
            x-data="{
                search: '',
                selected: @js($selectedPicIds),
                users: @js($picUserOptions),
                assigned: @js($assignedPicIds),
                matchesUser(user) {
                    const term = this.search.trim().toLocaleLowerCase();
                    return term === '' || `${user.name} ${user.email}`.toLocaleLowerCase().includes(term);
                },
                visibleUserCount() {
                    return this.users.filter(user => this.matchesUser(user)).length;
                },
                selectedUsers() {
                    return this.users.filter(user => this.selected.includes(user.id));
                },
                removeSelection(userId) {
                    this.selected = this.selected.filter(id => id !== userId);
                },
            }"
            @fp-pic-reset.window="search = ''; selected = []"
        >
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 class="font-display text-xl font-bold text-slate-950 sm:text-2xl">Penanggung Jawab (PIC)</h2>
                    <p class="mt-1 text-sm text-slate-500">Kelola pengguna yang bertanggung jawab atas kegiatan.</p>
                </div>
                <x-ui.status-badge tone="info">{{ $activity->pics->count() }} PIC</x-ui.status-badge>
            </div>

            <div class="fp-pic-grid mt-5 grid gap-4 {{ $canManagePics ? 'lg:grid-cols-[minmax(17rem,0.58fr)_minmax(0,1fr)]' : '' }}">
                <article class="fp-pic-panel flex min-h-0 flex-col overflow-hidden rounded-2xl border border-slate-200 bg-white">
                    <header class="flex items-center gap-3 border-b border-slate-200 bg-slate-50/80 px-4 py-4">
                        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-sky-100 text-sky-700" aria-hidden="true">
                            <svg class="h-5 w-5" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M13.5 16.5v-1.25A3.25 3.25 0 0 0 10.25 12h-4A3.25 3.25 0 0 0 3 15.25v1.25M8.25 8.75a3 3 0 1 0 0-6 3 3 0 0 0 0 6Zm5-1.5a2.5 2.5 0 0 1 0 4.5m3.75 4.75v-1a3 3 0 0 0-2.25-2.9" stroke-linecap="round" stroke-linejoin="round" /></svg>
                        </span>
                        <div>
                            <h3 class="font-display text-base font-bold text-slate-950">PIC Aktif</h3>
                            <p class="mt-0.5 text-xs text-slate-500">Pengguna yang telah ditugaskan</p>
                        </div>
                    </header>

                    <div class="max-h-[26rem] flex-1 space-y-3 overflow-y-auto p-3 sm:p-4" data-fp-pic-active-list>
                        @forelse ($activity->pics as $pic)
                            @php
                                $picIdentity = filled($pic->name) ? $pic->name : $pic->email;
                                $picInitials = collect(preg_split('/\s+/', trim((string) $picIdentity)))->filter()->take(2)->map(fn ($part) => mb_strtoupper(mb_substr($part, 0, 1)))->implode('');
                            @endphp
                            <div data-fp-pic-id="{{ $pic->id }}" class="flex flex-col gap-3 rounded-xl border border-slate-200 bg-white p-3 shadow-sm sm:flex-row sm:items-center">
                                <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-sky-100 text-sm font-bold text-sky-700" aria-hidden="true">{{ $picInitials ?: 'P' }}</span>
                                <div class="min-w-0 flex-1">
                                    <strong class="block break-words text-sm text-slate-950">{{ filled($pic->name) ? $pic->name : 'Pengguna' }}</strong>
                                    <span class="mt-0.5 block break-all text-xs text-slate-500">{{ $pic->email ?: '—' }}</span>
                                </div>
                                <span class="inline-flex w-fit shrink-0 items-center gap-1.5 rounded-full border border-sky-200 bg-sky-50 px-2.5 py-1 text-xs font-bold text-sky-700">
                                    <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M10 18a8 8 0 1 0 0-16 8 8 0 0 0 0 16Zm3.7-9.8a1 1 0 0 0-1.4-1.4L9 10.1 7.7 8.8a1 1 0 0 0-1.4 1.4l2 2a1 1 0 0 0 1.4 0l4-4Z" clip-rule="evenodd" /></svg>
                                    PIC Aktif
                                </span>
                                @if ($canManagePics)
                                    <form data-fp-async data-fp-confirm="Lepaskan {{ $pic->name }} dari PIC kegiatan ini?" method="POST" action="{{ route('fit-proper.pics.delete', [$activity, $pic]) }}">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="ui-button w-full border border-red-300 bg-white px-3 py-2 text-xs font-bold text-red-700 hover:border-red-400 hover:bg-red-50 focus-visible:ring-red-400 sm:w-auto" aria-label="Lepas {{ $pic->name }} dari PIC kegiatan">
                                            <svg class="h-4 w-4" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="m7.25 12.75 5.5-5.5m-6.5-.5-1.5 1.5a3 3 0 0 0 4.25 4.25l1-1m3.75 1.75 1.5-1.5A3 3 0 0 0 11 7.5l-1 1" stroke-linecap="round" /></svg>
                                            Lepas
                                        </button>
                                    </form>
                                @endif
                            </div>
                        @empty
                            <div class="rounded-xl border border-dashed border-slate-300 bg-slate-50 px-4 py-10 text-center" data-fp-pic-empty>
                                <span class="mx-auto flex h-11 w-11 items-center justify-center rounded-xl bg-white text-slate-400 shadow-sm" aria-hidden="true"><x-dashboard-icon name="users" class="h-5 w-5" /></span>
                                <h4 class="mt-3 text-sm font-bold text-slate-800">Belum ada PIC</h4>
                                <p class="mt-1 text-xs text-slate-500">Pilih pengguna pada panel Tambahkan PIC.</p>
                            </div>
                        @endforelse
                    </div>

                    <div class="m-3 mt-auto flex items-start gap-2 rounded-xl border border-sky-100 bg-sky-50 p-3 text-xs leading-5 text-slate-600 sm:m-4">
                        <svg class="mt-0.5 h-4 w-4 shrink-0 text-sky-600" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><circle cx="10" cy="10" r="7" /><path d="M10 9v4m0-7h.01" stroke-linecap="round" /></svg>
                        <p>PIC yang sudah ditugaskan tetap tersimpan. Gunakan Lepas untuk menghapus satu penugasan.</p>
                    </div>
                </article>

                @if ($canManagePics)
                    <form data-fp-async data-fp-reset data-fp-pic-form method="POST" action="{{ route('fit-proper.pics', $activity) }}" class="fp-pic-panel flex min-h-0 flex-col overflow-hidden rounded-2xl border border-slate-200 bg-white">
                        @csrf
                        @method('PUT')
                        <header class="flex items-center gap-3 border-b border-slate-200 bg-slate-50/80 px-4 py-4">
                            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-sky-100 text-sky-700" aria-hidden="true">
                                <svg class="h-5 w-5" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="7" cy="6" r="3" /><path d="M2.5 17v-1.5A4.5 4.5 0 0 1 7 11h1m5-2v6m-3-3h6" stroke-linecap="round" /></svg>
                            </span>
                            <div>
                                <h3 class="font-display text-base font-bold text-slate-950">Tambahkan PIC</h3>
                                <p class="mt-0.5 text-xs text-slate-500">Pilih satu atau beberapa pengguna</p>
                            </div>
                        </header>

                        <fieldset data-fp-user-picker class="flex min-h-0 flex-1 flex-col p-3 sm:p-4">
                            <legend class="sr-only">Pilih satu atau beberapa pengguna sebagai PIC</legend>
                            <div class="flex flex-col gap-2 sm:flex-row">
                                <label class="relative min-w-0 flex-1">
                                    <span class="sr-only">Cari nama atau email</span>
                                    <svg class="pointer-events-none absolute left-3 top-1/2 h-5 w-5 -translate-y-1/2 text-slate-400" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><circle cx="8.5" cy="8.5" r="5.25" /><path d="m12.5 12.5 4 4" stroke-linecap="round" /></svg>
                                    <input type="search" data-fp-user-search x-model.trim="search" class="w-full rounded-xl border-slate-300 py-2.5 pl-10 pr-3 text-sm shadow-sm focus:border-sky-500 focus:ring-sky-500" placeholder="Cari nama atau email..." autocomplete="off">
                                </label>
                                <button type="button" class="ui-button ui-button-secondary w-full shrink-0 sm:w-auto" @click="selected = []" :disabled="selected.length === 0">
                                    <svg class="h-4 w-4" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M4 6h12m-8-3h4m-6 3 .5 10h7L14 6M8.5 9v4m3-4v4" stroke-linecap="round" /></svg>
                                    Bersihkan pilihan
                                </button>
                            </div>

                            <div class="mt-3 max-h-[22rem] overflow-y-auto rounded-xl border border-slate-200" data-fp-pic-candidates>
                                @foreach ($users as $user)
                                    @php
                                        $isAssignedPic = $assignedPicIds->contains((string) $user->id);
                                        $userIdentity = filled($user->name) ? $user->name : $user->email;
                                        $userInitials = collect(preg_split('/\s+/', trim((string) $userIdentity)))->filter()->take(2)->map(fn ($part) => mb_strtoupper(mb_substr($part, 0, 1)))->implode('');
                                        $userSearchText = mb_strtolower(trim($user->name.' '.$user->email));
                                    @endphp
                                    <label
                                        data-fp-user-option
                                        data-user-search="{{ $userSearchText }}"
                                        x-show="matchesUser({ id: @js((string) $user->id), name: @js($user->name), email: @js($user->email) })"
                                        :class="selected.includes(@js((string) $user->id)) ? 'border-sky-400 bg-sky-50 ring-1 ring-inset ring-sky-400' : '{{ $isAssignedPic ? 'cursor-not-allowed bg-slate-50 opacity-75' : 'cursor-pointer border-transparent hover:bg-slate-50' }}'"
                                        class="flex min-w-0 items-center gap-3 border-b p-3 transition last:border-b-0 focus-within:ring-2 focus-within:ring-inset focus-within:ring-sky-500"
                                        for="fp-pic-user-{{ $activity->id }}-{{ $user->id }}"
                                    >
                                        <input
                                            id="fp-pic-user-{{ $activity->id }}-{{ $user->id }}"
                                            type="checkbox"
                                            name="users[]"
                                            value="{{ $user->id }}"
                                            x-model="selected"
                                            @disabled($isAssignedPic)
                                            class="h-5 w-5 shrink-0 rounded border-slate-300 text-sky-600 focus:ring-sky-500"
                                        >
                                        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-sky-100 text-xs font-bold text-sky-700" aria-hidden="true">{{ $userInitials ?: 'P' }}</span>
                                        <span class="min-w-0 flex-1">
                                            <strong class="block break-words text-sm text-slate-950">{{ filled($user->name) ? $user->name : 'Pengguna' }}</strong>
                                            <span class="mt-0.5 block break-all text-xs text-slate-500">{{ $user->email ?: '—' }}</span>
                                        </span>
                                        @if ($isAssignedPic)
                                            <span class="inline-flex shrink-0 rounded-full bg-slate-200 px-2.5 py-1 text-xs font-bold text-slate-600">Sudah menjadi PIC</span>
                                        @endif
                                    </label>
                                @endforeach

                                <div x-cloak x-show="visibleUserCount() === 0" class="px-4 py-10 text-center" data-fp-pic-search-empty>
                                    <p class="text-sm font-bold text-slate-800">Pengguna tidak ditemukan</p>
                                    <p class="mt-1 text-xs text-slate-500">Coba gunakan nama atau email yang berbeda.</p>
                                </div>
                            </div>

                            <div class="mt-3 rounded-xl border border-slate-200 bg-slate-50 p-3" aria-live="polite">
                                <div class="flex flex-col gap-3 xl:flex-row xl:items-center xl:justify-between">
                                    <div class="min-w-0">
                                        <p class="text-sm font-bold text-slate-800" x-text="selected.length ? `${selected.length} pengguna dipilih` : 'Belum ada pengguna dipilih'"></p>
                                        <div class="mt-2 flex flex-wrap gap-2" x-cloak x-show="selected.length">
                                            <template x-for="user in selectedUsers()" :key="user.id">
                                                <span class="inline-flex max-w-full items-center gap-1.5 rounded-full border border-sky-200 bg-sky-50 px-2.5 py-1 text-xs font-bold text-sky-800">
                                                    <span class="truncate" x-text="user.name || user.email"></span>
                                                    <button type="button" class="rounded-full p-0.5 hover:bg-sky-100 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-sky-500" @click="removeSelection(user.id)" :aria-label="`Batalkan pilihan ${user.name || user.email}`">
                                                        <svg class="h-3 w-3" viewBox="0 0 12 12" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="m3 3 6 6m0-6L3 9" stroke-linecap="round" /></svg>
                                                    </button>
                                                </span>
                                            </template>
                                        </div>
                                    </div>
                                    <button type="submit" class="fp-participant-add ui-button w-full shrink-0 xl:w-auto" :disabled="selected.length === 0">
                                        <svg class="h-5 w-5" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M10 4v12M4 10h12" stroke-linecap="round" /></svg>
                                        <span x-text="selected.length ? `Tambahkan ${selected.length} PIC` : 'Tambahkan PIC'">Tambahkan PIC</span>
                                    </button>
                                </div>
                            </div>
                        </fieldset>
                    </form>
                @endif
            </div>
        </div>
    </section>

    <section
        data-fp-structure="penguji"
        id="penguji"
        x-cloak
        class="fp-detail-panel rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"
        x-show="activeTab === 'penguji'"
        x-transition.opacity.duration.150ms
    >
        <div data-fp-fragment="examiner-panel">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 class="font-display text-xl font-bold text-slate-950 sm:text-2xl">Penguji Kegiatan</h2>
                    <p class="mt-1 text-sm text-slate-500">Setiap penguji wajib menilai seluruh peserta.</p>
                </div>
                <x-ui.status-badge tone="info">{{ $examinerCount }} Penguji</x-ui.status-badge>
            </div>

            <div class="mt-5 flex items-start gap-4 rounded-2xl border p-4 {{ $examinerBannerClasses }}" role="status" data-fp-examiner-status>
                <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl {{ $examinerBannerIconClasses }}" aria-hidden="true">
                    <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 3 4.5 6v5.5c0 4.5 3.2 7.8 7.5 9.5 4.3-1.7 7.5-5 7.5-9.5V6L12 3Z" stroke-linejoin="round" /><path d="m9 12 2 2 4-4" stroke-linecap="round" stroke-linejoin="round" /></svg>
                </span>
                <div class="min-w-0">
                    <h3 class="font-display text-base font-bold">Status kegiatan: {{ $activity->status }}</h3>
                    @if ($activity->status === 'Draft')
                        <p class="mt-1 text-sm font-semibold">Penilaian belum dibuka. Lengkapi peserta, PIC, dan penguji, lalu buka tab Ringkasan untuk memulai kegiatan.</p>
                    @elseif ($activity->status === 'Berjalan')
                        <p class="mt-1 text-sm font-semibold">Penilaian sudah dibuka. Tautan yang valid dapat digunakan dan penilaian yang sudah dikirim akan terkunci.</p>
                    @else
                        <p class="mt-1 text-sm font-semibold">Kegiatan selesai. Seluruh penilaian terkunci dan tautan tidak dapat digunakan lagi.</p>
                    @endif
                    <p class="mt-1 text-xs opacity-75">Tanggal pelaksanaan hanya informasi jadwal; akses penilaian mengikuti status kegiatan.</p>
                </div>
            </div>

            <div class="mt-5 grid items-start gap-4 {{ $canManageExaminerStructure ? 'lg:grid-cols-[minmax(0,1fr)_minmax(19rem,0.42fr)]' : '' }}">
                <article class="fp-examiner-panel overflow-hidden rounded-2xl border border-slate-200 bg-white">
                    <header class="flex items-center gap-3 border-b border-slate-200 bg-slate-50/80 px-4 py-4 sm:px-5">
                        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-sky-100 text-sky-700" aria-hidden="true">
                            <svg class="h-5 w-5" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M13.5 16.5v-1.25A3.25 3.25 0 0 0 10.25 12h-4A3.25 3.25 0 0 0 3 15.25v1.25M8.25 8.75a3 3 0 1 0 0-6 3 3 0 0 0 0 6Zm5-1.5a2.5 2.5 0 0 1 0 4.5m3.75 4.75v-1a3 3 0 0 0-2.25-2.9" stroke-linecap="round" stroke-linejoin="round" /></svg>
                        </span>
                        <div>
                            <h3 class="font-display text-base font-bold text-slate-950">Penguji Ditugaskan</h3>
                            <p class="mt-0.5 text-xs text-slate-500">{{ $examinerCount }} penguji ditugaskan</p>
                        </div>
                    </header>

                    <div class="space-y-4 p-3 sm:p-5" data-fp-examiner-list>
                        @forelse ($activity->examiners as $examiner)
                            @php
                                $examinerName = filled($examiner->name) ? $examiner->name : 'Penguji';
                                $examinerInitials = collect(preg_split('/\s+/', trim($examinerName)))->filter()->take(2)->map(fn ($part) => mb_strtoupper(mb_substr($part, 0, 1)))->implode('');
                                $hasExaminerToken = filled($examiner->getRawOriginal('token_hash'));
                                $examinerLinkRevoked = $examiner->revoked_at !== null;
                                $examinerLinkExpired = $hasExaminerToken && $examiner->expires_at?->isPast();
                                $examinerLinkActive = $hasExaminerToken && ! $examinerLinkRevoked && $examiner->expires_at?->isFuture();
                                $examinerLinkStatus = $examinerLinkRevoked
                                    ? 'Dicabut'
                                    : ($examinerLinkExpired ? 'Kedaluwarsa' : ($examinerLinkActive ? 'Aktif' : 'Belum diterbitkan'));
                                $examinerLinkTone = $examinerLinkActive ? 'info' : ($examinerLinkExpired || $examinerLinkRevoked ? 'neutral' : 'warning');
                            @endphp
                            <article
                                data-fp-examiner-id="{{ $examiner->id }}"
                                data-link-expiry-label="{{ $examiner->expires_at ? 'Berlaku hingga '.$examiner->expires_at->format('d/m/Y H:i') : 'Masa berlaku mengikuti tautan baru' }}"
                                class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm"
                            >
                                <div class="flex flex-col gap-4 xl:flex-row xl:items-start xl:justify-between">
                                    <div class="flex min-w-0 items-start gap-3">
                                        <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-sky-100 font-display text-sm font-bold text-sky-700" aria-hidden="true">{{ $examinerInitials ?: 'P' }}</span>
                                        <div class="min-w-0">
                                            <div class="flex flex-wrap items-center gap-2">
                                                <h4 class="break-words font-display text-base font-bold text-slate-950">{{ $examinerName }}</h4>
                                                <span class="rounded-full border border-slate-200 bg-slate-50 px-2.5 py-1 text-xs font-bold text-slate-600">{{ $examiner->user_id ? 'Internal' : 'Manual' }}</span>
                                                <x-ui.status-badge :tone="$examiner->submitted_at ? 'success' : 'warning'">{{ $examiner->submitted_at ? 'Selesai' : 'Menunggu' }}</x-ui.status-badge>
                                            </div>
                                            <p class="mt-2 text-sm text-slate-600">{{ $examiner->submitted_at ? 'Sudah dikirim' : 'Belum dikirim' }}</p>
                                            <p class="mt-1 flex items-center gap-1.5 text-xs text-slate-500">
                                                <svg class="h-4 w-4 shrink-0" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><rect x="3" y="4.5" width="14" height="12" rx="2" /><path d="M6 2.5v4m8-4v4M3 8.5h14" stroke-linecap="round" /></svg>
                                                @if ($examiner->expires_at)
                                                    Tautan {{ mb_strtolower($examinerLinkStatus) }} · {{ $examiner->expires_at->format('d/m/Y H:i') }}
                                                @else
                                                    Tautan belum diterbitkan
                                                @endif
                                            </p>
                                        </div>
                                    </div>

                                    <div class="flex flex-wrap gap-2 xl:justify-end">
                                        @if ($examiner->user_id === auth()->id())
                                            <a href="{{ route('fit-proper.assess', [$activity, $examiner]) }}" class="ui-button ui-button-secondary px-3 py-2 text-xs">Penilaian saya</a>
                                        @endif
                                        @if ($canManageExaminerLinks)
                                            <form
                                                data-fp-async
                                                @if ($hasExaminerToken) data-fp-confirm="Terbitkan ulang tautan untuk {{ $examinerName }}? Tautan lama akan dibatalkan." @endif
                                                method="POST"
                                                action="{{ route('fit-proper.token', [$activity, $examiner]) }}"
                                            >
                                                @csrf
                                                <button type="submit" class="ui-button w-full border border-sky-300 bg-white px-3 py-2 text-xs font-bold text-sky-700 hover:border-sky-400 hover:bg-sky-50 sm:w-auto">
                                                    <svg class="h-4 w-4" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M15.5 8A6 6 0 1 0 16 12m-.5-8v4h-4" stroke-linecap="round" stroke-linejoin="round" /></svg>
                                                    {{ $hasExaminerToken ? 'Terbitkan ulang' : 'Terbitkan tautan' }}
                                                </button>
                                            </form>
                                            @if ($examinerLinkActive)
                                                <form data-fp-async data-fp-confirm="Cabut tautan penilaian milik {{ $examinerName }}? Penguji tidak dapat mengakses tautan tersebut setelah dicabut." method="POST" action="{{ route('fit-proper.token', [$activity, $examiner]) }}">
                                                    @csrf
                                                    <input type="hidden" name="revoke" value="1">
                                                    <button type="submit" class="ui-button w-full border border-red-300 bg-white px-3 py-2 text-xs font-bold text-red-700 hover:border-red-400 hover:bg-red-50 sm:w-auto">
                                                        <svg class="h-4 w-4" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="m7.25 12.75 5.5-5.5m-6.5-.5-1.5 1.5a3 3 0 0 0 4.25 4.25l1-1m3.75 1.75 1.5-1.5A3 3 0 0 0 11 7.5l-1 1" stroke-linecap="round" /></svg>
                                                        Cabut tautan
                                                    </button>
                                                </form>
                                            @endif
                                        @endif
                                        @if ($canManageExaminerStructure)
                                            <form data-fp-async method="POST" action="{{ route('fit-proper.examiners.delete', [$activity, $examiner]) }}" data-fp-confirm="Hapus {{ $examinerName }} dari kegiatan ini?">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="ui-button border border-red-200 bg-white p-2.5 text-red-600 hover:border-red-300 hover:bg-red-50" aria-label="Hapus penguji {{ $examinerName }}" title="Hapus penguji">
                                                    <svg class="h-4 w-4" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M3.5 5.5h13M8 2.75h4M6 5.5l.6 11h6.8l.6-11M8.25 8.5v5.25M11.75 8.5v5.25" stroke-linecap="round" stroke-linejoin="round" /></svg>
                                                </button>
                                            </form>
                                        @endif
                                    </div>
                                </div>

                                <div class="mt-4 rounded-xl border border-sky-100 bg-sky-50/70 p-3 sm:p-4" data-fp-link-slot>
                                    <div class="flex flex-wrap items-center justify-between gap-2">
                                        <h5 class="flex items-center gap-2 text-sm font-bold text-slate-900">
                                            <svg class="h-4 w-4 text-sky-700" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="m8 12 4-4m-5.5 7.5-1 .75a3 3 0 0 1-4.25-4.25l3-3a3 3 0 0 1 4.25 0m5-5 1-.75A3 3 0 0 1 18.75 7.5l-3 3a3 3 0 0 1-4.25 0" stroke-linecap="round" /></svg>
                                            Tautan Penilaian
                                        </h5>
                                        <x-ui.status-badge :tone="$examinerLinkTone">{{ $examinerLinkStatus }}</x-ui.status-badge>
                                    </div>
                                    <p class="mt-3 text-xs text-slate-600">
                                        @if ($examinerLinkActive)
                                            Tautan telah diterbitkan. URL lengkap hanya ditampilkan saat penerbitan dan tidak disimpan sebagai teks biasa.
                                        @elseif ($examinerLinkRevoked)
                                            Tautan telah dicabut. Terbitkan ulang jika akses penilaian perlu diberikan kembali.
                                        @elseif ($examinerLinkExpired)
                                            Tautan sudah kedaluwarsa. Terbitkan ulang untuk membuat tautan baru.
                                        @else
                                            Tautan belum diterbitkan untuk penguji ini.
                                        @endif
                                    </p>
                                    <p class="mt-2 text-xs text-slate-500">Tautan hanya aktif ketika kegiatan Berjalan. Terbitkan ulang hanya jika tautan hilang; tautan lama akan dibatalkan.</p>
                                </div>
                            </article>
                        @empty
                            <div class="rounded-2xl border border-dashed border-slate-300 bg-slate-50 px-5 py-12 text-center" data-fp-examiner-empty>
                                <span class="mx-auto flex h-12 w-12 items-center justify-center rounded-xl bg-white text-slate-400 shadow-sm" aria-hidden="true"><x-dashboard-icon name="users" class="h-6 w-6" /></span>
                                <h4 class="mt-3 text-sm font-bold text-slate-800">Belum ada penguji</h4>
                                <p class="mt-1 text-sm text-slate-500">Tambahkan penguji agar proses penilaian dapat disiapkan.</p>
                            </div>
                        @endforelse
                    </div>
                </article>

                @if ($canManageExaminerStructure)
                    <form
                        data-fp-async
                        data-fp-reset
                        method="POST"
                        action="{{ route('fit-proper.examiners', $activity) }}"
                        class="fp-examiner-panel overflow-hidden rounded-2xl border border-slate-200 bg-white"
                        x-data="{ selectedUser: @js((string) old('user_id', '')), manualName: @js((string) old('name', '')) }"
                    >
                        @csrf
                        <header class="flex items-center gap-3 border-b border-slate-200 bg-slate-50/80 px-4 py-4 sm:px-5">
                            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-sky-100 text-sky-700" aria-hidden="true">
                                <svg class="h-5 w-5" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="7" cy="6" r="3" /><path d="M2.5 17v-1.5A4.5 4.5 0 0 1 7 11h1m5-2v6m-3-3h6" stroke-linecap="round" /></svg>
                            </span>
                            <div>
                                <h3 class="font-display text-base font-bold text-slate-950">Tambah Penguji</h3>
                                <p class="mt-0.5 text-xs text-slate-500">Tambahkan akun internal atau penguji manual</p>
                            </div>
                        </header>

                        <div class="space-y-4 p-4 sm:p-5">
                            <label for="fp-examiner-user-{{ $activity->id }}" class="block text-sm font-bold text-slate-700">
                                Akun internal (opsional)
                                <select id="fp-examiner-user-{{ $activity->id }}" name="user_id" x-model="selectedUser" @change="if (selectedUser) manualName = ''" class="mt-1.5 w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-sky-500 focus:ring-sky-500">
                                    <option value="">Penguji manual</option>
                                    @foreach ($users as $user)
                                        <option value="{{ $user->id }}" @disabled($assignedExaminerUserIds->contains((string) $user->id))>{{ $user->name }} · {{ $user->email }}{{ $assignedExaminerUserIds->contains((string) $user->id) ? ' · Sudah menjadi penguji' : '' }}</option>
                                    @endforeach
                                </select>
                            </label>

                            <label for="fp-examiner-name-{{ $activity->id }}" class="block text-sm font-bold text-slate-700">
                                Nama penguji manual
                                <input
                                    id="fp-examiner-name-{{ $activity->id }}"
                                    name="name"
                                    x-model="manualName"
                                    :disabled="selectedUser !== ''"
                                    :required="selectedUser === ''"
                                    placeholder="Masukkan nama penguji"
                                    class="mt-1.5 w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-sky-500 focus:ring-sky-500 disabled:cursor-not-allowed disabled:bg-slate-100 disabled:text-slate-500"
                                >
                            </label>

                            <div class="flex items-start gap-2 rounded-xl border border-sky-100 bg-sky-50 p-3 text-xs leading-5 text-slate-600">
                                <svg class="mt-0.5 h-4 w-4 shrink-0 text-sky-600" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><circle cx="10" cy="10" r="7" /><path d="M10 9v4m0-7h.01" stroke-linecap="round" /></svg>
                                <p>Pilih akun internal atau isi nama penguji manual.</p>
                            </div>

                            <button type="submit" class="fp-participant-add ui-button w-full">
                                <svg class="h-5 w-5" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><circle cx="7" cy="6" r="3" /><path d="M2.5 17v-1.5A4.5 4.5 0 0 1 7 11h1m5-2v6m-3-3h6" stroke-linecap="round" /></svg>
                                Tambah Penguji
                            </button>
                        </div>
                    </form>
                @endif
            </div>
        </div>
    </section>

    <section
        data-fp-fragment="rekap"
        id="rekap"
        x-cloak
        class="fp-detail-panel rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5"
        x-show="activeTab === 'rekap'"
        x-transition.opacity.duration.150ms
    >
        @include('fit-proper._assessment-matrix')
    </section>

    <section
        data-fp-fragment="hasil"
        id="hasil"
        x-cloak
        class="fp-detail-panel fp-result-panel rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5"
        x-show="activeTab === 'hasil'"
        x-transition.opacity.duration.150ms
    >
        @include('fit-proper._recap', ['resultDashboard' => true])
    </section>

    <section
        data-fp-fragment="riwayat"
        id="riwayat"
        x-cloak
        class="fp-detail-panel rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"
        x-show="activeTab === 'riwayat'"
        x-transition.opacity.duration.150ms
    >
        <div class="flex flex-wrap items-center justify-between gap-2"><div><h2 class="font-display text-xl font-bold text-slate-950">Riwayat Kegiatan</h2><p class="mt-1 text-sm text-slate-500">Aktivitas penting yang tercatat pada kegiatan.</p></div><x-ui.status-badge tone="info">{{ $histories->count() }} Aktivitas</x-ui.status-badge></div>
        <ol class="fp-detail-timeline mt-5 space-y-3 text-sm">
            @forelse ($histories as $history)
                <li class="relative pl-7">
                    <span class="absolute left-0 top-4 h-3 w-3 rounded-full border-2 border-white bg-sky-500 ring-2 ring-sky-100" aria-hidden="true"></span>
                    <details class="fp-history rounded-xl border border-slate-200" data-history-id="{{ $history->id }}">
                        <summary class="flex cursor-pointer items-center justify-between gap-3 p-4">
                            <span class="min-w-0"><strong class="block break-words text-slate-900">{{ $history->action }}</strong><span class="mt-1 block text-xs text-slate-500"><time>{{ $history->created_at }}</time> · {{ $users->firstWhere('id', $history->user_id)?->name ?? ($history->user_id ? 'Pengguna '.$history->user_id : 'Penguji tautan / sistem') }}</span></span>
                            <span class="fp-history-chevron shrink-0 text-slate-400" aria-hidden="true"></span>
                        </summary>
                        <div class="fp-history-content border-t border-slate-200 p-4">
                            <dl class="grid gap-3 sm:grid-cols-2"><div><dt class="text-xs text-slate-500">Aktivitas</dt><dd class="mt-1 break-words text-slate-700">{{ $history->action }}</dd></div><div><dt class="text-xs text-slate-500">Waktu</dt><dd class="mt-1 text-slate-700">{{ $history->created_at }}</dd></div><div><dt class="text-xs text-slate-500">Pengguna</dt><dd class="mt-1 text-slate-700">{{ $history->user_id ?? 'Penguji tautan / sistem' }}</dd></div></dl>
                            @if ($history->context)<p class="mt-4 text-xs font-semibold text-slate-500">Rincian aktivitas</p><pre class="mt-2 whitespace-pre-wrap break-all text-xs text-slate-600">{{ $history->context }}</pre>@endif
                        </div>
                    </details>
                </li>
            @empty
                <li><x-ui.empty-state title="Belum ada riwayat kegiatan" description="Aktivitas penting akan ditampilkan di sini." /></li>
            @endforelse
        </ol>
    </section>
</div>
@endsection
