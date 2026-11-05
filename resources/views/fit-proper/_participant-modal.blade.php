@php
    $isEditMode = $mode === 'edit';
    $modalParticipant = $isEditMode ? $participant : null;
    $modalIdentifier = $isEditMode ? 'fit-proper-participant-edit-'.$modalParticipant->id : 'fit-proper-participant-create';
    $modalOpenExpression = $isEditMode ? 'participantEditId === '.$modalParticipant->id : 'participantOpen';
    $modalDescription = $isEditMode
        ? 'Perbarui data peserta dan informasi asesmen pada kegiatan ini.'
        : 'Tambahkan pegawai internal atau peserta manual ke kegiatan ini.';
    $modalInitiallyOpen = $isEditMode
        ? $participantEditModalId === $modalParticipant->id
        : $participantModalOpen;
@endphp

<template x-teleport="body">
    <div
        x-cloak
        x-show="{{ $modalOpenExpression }}"
        x-data="{
            focusables() {
                return [...$el.querySelectorAll('a, button, input:not([type=hidden]), textarea, select, [tabindex]:not([tabindex=\'-1\'])')]
                    .filter(element => !element.disabled && element.offsetParent !== null);
            },
            firstFocusable() { return this.focusables()[0]; },
            lastFocusable() { return this.focusables().at(-1); },
            nextFocusable() { return this.focusables()[this.nextFocusableIndex()] || this.firstFocusable(); },
            previousFocusable() { return this.focusables()[this.previousFocusableIndex()] || this.lastFocusable(); },
            nextFocusableIndex() { return (this.focusables().indexOf(document.activeElement) + 1) % this.focusables().length; },
            previousFocusableIndex() { return Math.max(0, this.focusables().indexOf(document.activeElement)) - 1; },
        }"
        @keydown.tab.prevent="$event.shiftKey || nextFocusable()?.focus()"
        @keydown.shift.tab.prevent="previousFocusable()?.focus()"
        class="fixed inset-0 z-[100] flex items-center justify-center p-2 sm:p-5"
        role="presentation"
        @if ($isEditMode)
            data-fp-participant-edit-modal
            data-participant-id="{{ $modalParticipant->id }}"
        @else
            data-fp-participant-modal
        @endif
        data-initially-open="{{ $modalInitiallyOpen ? 'true' : 'false' }}"
        data-app-content
    >
        <div class="absolute inset-0 bg-slate-950/65 backdrop-blur-[2px]" @click="closeParticipantModal()" aria-hidden="true"></div>
        <section
            x-show="{{ $modalOpenExpression }}"
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="translate-y-3 opacity-0 sm:scale-[0.98]"
            x-transition:enter-end="translate-y-0 opacity-100 sm:scale-100"
            x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="translate-y-0 opacity-100 sm:scale-100"
            x-transition:leave-end="translate-y-3 opacity-0 sm:scale-[0.98]"
            class="fp-participant-modal-shell relative z-10 flex max-h-[90vh] w-full max-w-6xl flex-col overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-2xl shadow-slate-950/25"
            role="dialog"
            aria-modal="true"
            aria-labelledby="{{ $modalIdentifier }}-title"
            aria-describedby="{{ $modalIdentifier }}-description"
        >
            <header class="fp-participant-modal-header flex shrink-0 items-start justify-between gap-4 border-b border-slate-200 bg-white px-4 py-4 sm:px-6">
                <div class="min-w-0">
                    <p class="text-[0.6875rem] font-extrabold uppercase tracking-[0.2em] text-sky-700">Fit &amp; Proper</p>
                    <h2 id="{{ $modalIdentifier }}-title" class="mt-1 font-display text-xl font-bold text-slate-950 sm:text-2xl">{{ $isEditMode ? 'Edit Peserta' : 'Tambah Peserta' }}</h2>
                    <p id="{{ $modalIdentifier }}-description" class="mt-1 text-sm text-slate-500">{{ $modalDescription }}</p>
                </div>
                <button
                    type="button"
                    data-fp-modal-close
                    class="rounded-xl border border-transparent p-2 text-slate-400 transition hover:border-slate-200 hover:bg-slate-50 hover:text-slate-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-sky-500"
                    @click="closeParticipantModal()"
                    aria-label="Tutup form {{ $isEditMode ? 'edit' : 'tambah' }} peserta"
                >
                    <svg class="h-5 w-5" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="m5 5 10 10M15 5 5 15" stroke-linecap="round" /></svg>
                </button>
            </header>

            <div class="min-h-0 flex-1 overflow-y-auto overscroll-contain">
                @include('fit-proper._participant-form', [
                    'participant' => $modalParticipant,
                    'formClass' => '',
                    'isCreateModal' => ! $isEditMode,
                    'isEditModal' => $isEditMode,
                ])
            </div>
        </section>
    </div>
</template>
