@php
    $approvedCount = $mutation->approvals->where('status', 'Approved')->unique('manager_id')->count();
    $currentApprovalStep = $mutation->verification_status === 'Pending' ? min($approvedCount + 1, 3) : null;
    $approvalsByStep = $mutation->approvals->whereNotNull('step_order')->keyBy('step_order');
    $legacyApprovals = $mutation->approvals->whereNull('step_order')->values();
@endphp

<x-personnel-movement-detail-summary
    :movement="$mutation"
    movement-label="Mutasi"
    :status-label="$mutation->verification_status"
>
    <section class="h-full rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="flex items-center gap-2 text-slate-800">
                <span class="flex h-6 w-6 items-center justify-center rounded-full border-2 border-sky-700 text-xs font-bold text-sky-700">✓</span>
                <h4 class="font-display font-bold">Persetujuan {{ $approvedCount }} dari 3</h4>
            </div>
            <div class="flex items-center" aria-label="Progres persetujuan {{ $approvedCount }} dari 3">
                @foreach (range(1, 3) as $step)
                    <span class="flex h-6 w-6 items-center justify-center rounded-full border-2 text-xs font-bold {{ $step <= $approvedCount ? 'border-emerald-500 bg-emerald-500 text-white' : ($step === $currentApprovalStep ? 'border-sky-500 bg-sky-50 text-sky-700' : 'border-slate-300 bg-white text-slate-400') }}">{{ $step <= $approvedCount ? '✓' : $step }}</span>
                    @if ($step < 3)
                        <span class="h-0.5 w-7 {{ $step <= $approvedCount ? 'bg-emerald-500' : 'bg-slate-200' }} sm:w-12"></span>
                    @endif
                @endforeach
            </div>
        </div>

        <div class="mt-4 grid gap-3 sm:grid-cols-3">
            @foreach (range(1, 3) as $step)
                @php
                    $decision = $approvalsByStep->get($step) ?? $legacyApprovals->get($step - 1);
                    $isApproved = $decision?->status === 'Approved';
                    $isCurrent = ! $isApproved && $step === $currentApprovalStep;
                    $approverName = $decision?->approver_name_snapshot ?? $decision?->manager?->name ?? 'Snapshot approver tidak tersedia';
                @endphp
                <article class="rounded-xl border p-3 {{ $isApproved ? 'border-emerald-200 bg-emerald-50' : ($isCurrent ? 'border-sky-200 bg-sky-50' : 'border-slate-200 bg-slate-50') }}">
                    <div class="flex items-start gap-2.5">
                        <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full text-sm font-bold {{ $isApproved ? 'bg-emerald-500 text-white' : ($isCurrent ? 'bg-sky-100 text-sky-700' : 'bg-slate-200 text-slate-500') }}">{{ $isApproved ? '✓' : $step }}</span>
                        <div class="min-w-0">
                            <p class="text-xs font-bold text-slate-800">Penyetuju {{ $step }}</p>
                            <p class="mt-0.5 truncate text-xs text-slate-600" title="{{ $approverName }}">{{ $approverName }}</p>
                            <p class="mt-1 text-xs font-semibold {{ $isApproved ? 'text-emerald-700' : ($isCurrent ? 'text-sky-700' : 'text-slate-500') }}">
                                {{ $isApproved ? 'Disetujui' : ($isCurrent ? 'Menunggu keputusan' : 'Menunggu giliran') }}
                            </p>
                            @if ($isApproved)
                                <p class="mt-1 text-[11px] text-slate-500">{{ $decision->approved_at?->format('d M Y H:i') ?: 'Waktu tidak tersedia' }}</p>
                            @endif
                            @if ($decision?->note)
                                <p class="mt-1 break-words text-[11px] text-slate-500">{{ $decision->note }}</p>
                            @endif
                        </div>
                    </div>
                </article>
            @endforeach
        </div>
    </section>
</x-personnel-movement-detail-summary>
