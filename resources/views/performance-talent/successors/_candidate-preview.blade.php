@php($talentBoxColorClass = $talentBoxColorClasses[(int) $successorTalentBox] ?? 'bg-slate-500')
@php($successionReadinessColorClass = $successionReadinessColorClasses[mb_strtolower(trim((string) $successor->succession_readiness))] ?? 'bg-slate-500')

<article x-show="selectedPosition?.key === @js((string) $successor->target_position_id)" data-successor-candidate-preview class="w-[86%] shrink-0 snap-start overflow-hidden rounded-2xl border border-slate-200 border-t-4 border-t-[#0B1F3A] bg-white p-3 shadow-lg sm:w-[52%] lg:w-[38%] xl:w-[27.5%]">
    <div class="grid min-w-0 grid-cols-[4rem_minmax(0,1fr)_3.75rem] gap-2">
        <div class="flex h-16 w-full items-center justify-center overflow-hidden rounded-lg bg-blue-100 text-xl font-bold text-blue-700">
            @if ($successor->photo_path)
                <img src="{{ Storage::disk('public')->url($successor->photo_path) }}" alt="Foto {{ $successor->name }}" class="h-full w-full object-cover">
            @else
                <span aria-hidden="true">{{ $successorInitial ?: '?' }}</span>
            @endif
        </div>
        <div class="min-w-0 border-l border-slate-200 pl-2">
            <h5 class="break-words font-display text-base font-bold leading-tight text-slate-950">{{ $successor->name ?: 'Nama Kandidat' }}</h5>
            <p class="mt-1 break-all text-xs font-semibold text-slate-500">SAP {{ $successor->sap ?: '—' }}</p>
        </div>
        <div data-talent-box-color="{{ $talentBoxColorClass }}" class="flex h-[3.75rem] w-[3.75rem] flex-col items-center justify-center rounded-lg px-1 text-center text-white {{ $talentBoxColorClass }}">
            <span class="text-[10px] font-bold leading-[0.65rem]">TALENT<br>BOX</span>
            <strong class="text-xl">{{ $successorTalentBox ?: '—' }}</strong>
        </div>
    </div>

    <div data-succession-readiness-color="{{ $successionReadinessColorClass }}" class="mt-2 rounded-full px-3 py-1 text-center text-xs font-bold capitalize text-white {{ $successionReadinessColorClass }}">
        {{ $successor->succession_readiness ?: 'Succession Readiness' }}
    </div>

    <div class="mt-2 rounded-lg bg-blue-50 px-3 py-2">
        <p class="text-[10px] font-bold uppercase tracking-wider text-blue-700">Current Position</p>
        <p class="mt-0.5 break-words text-sm font-bold text-slate-950">{{ $successorPosition ?: '—' }}</p>
    </div>

    <div class="mt-2">
        <p class="text-[10px] font-bold uppercase tracking-wide text-blue-700">Position History (Last 3 Years)</p>
        @forelse ($successorHistory as $history)
            <div class="mt-1 grid grid-cols-[5rem_minmax(0,1fr)] gap-2 border-l-2 border-blue-500 bg-slate-50 px-2 py-1.5">
                <p class="text-[11px] font-bold text-slate-800">{{ data_get($history, 'period') ?: '—' }}</p>
                <p class="break-words text-[11px] text-slate-600">{{ data_get($history, 'position') ?: '—' }}</p>
            </div>
        @empty
            <div class="mt-1 border-l-2 border-slate-300 bg-slate-50 px-2 py-1.5 text-xs text-slate-500">—</div>
        @endforelse
    </div>

    <div class="mt-2 grid grid-cols-2 gap-2">
        <div class="rounded-lg border border-slate-200 p-2">
            <p class="text-[10px] font-bold uppercase text-slate-500">Birth Date</p>
            <p class="mt-0.5 text-xs font-bold text-slate-900">{{ $successor->birth_date?->format('d M Y') ?: '—' }}</p>
        </div>
        <div class="rounded-lg border border-slate-200 p-2">
            <p class="text-[10px] font-bold uppercase text-slate-500">Last Education</p>
            <p class="mt-0.5 break-words text-xs font-bold text-slate-900">{{ $successorEducation ?: '—' }}</p>
        </div>
    </div>

    <div class="mt-2 rounded-lg border border-slate-200 p-2">
        <p class="text-[10px] font-bold uppercase text-blue-700">Assessment</p>
        <div class="mt-1 grid grid-cols-1 gap-1.5 text-[11px] sm:grid-cols-2">
            <div><span class="text-slate-500">Recommendation</span><p class="break-words font-bold text-slate-900">{{ $successor->recommendation ?: '—' }}</p></div>
            <div><span class="text-slate-500">Targeted Band</span><p class="break-words font-bold text-slate-900">{{ $successor->targeted_band ?: '—' }}</p></div>
        </div>
    </div>

    <div class="mt-2 grid grid-cols-[auto_1fr_1fr] items-center gap-1.5 rounded-lg border border-slate-200 p-2 text-center">
        <p class="text-[9px] font-bold uppercase text-blue-700">Performance</p>
        <div class="rounded-md bg-blue-50 px-1.5 py-1 text-xs font-bold">{{ $successor->performance_year ?: '—' }}</div>
        <div class="rounded-md bg-blue-50 px-1.5 py-1 text-xs font-bold">{{ $successor->performance_score ?? '—' }}</div>
        <div class="col-span-3 break-words text-[11px] font-semibold text-slate-600">{{ $successor->performance_category ?: '—' }}</div>
    </div>

    <div class="mt-2 rounded-lg border border-slate-200 p-2">
        <div class="grid grid-cols-[4rem_minmax(0,1fr)] gap-2">
            <span class="w-fit rounded-md bg-violet-100 px-2 py-1 text-center text-xs font-bold text-violet-700">{{ $successor->mbti_type ?: 'MBTI' }}</span>
            <p class="break-words text-xs font-bold text-slate-900">{{ $successor->mbti_description ?: '—' }}</p>
        </div>
        <dl class="mt-2 space-y-1 border-t border-slate-200 pt-2 text-[11px]">
            <div class="grid grid-cols-[7rem_minmax(0,1fr)] gap-1"><dt class="font-bold">Top I Job Family</dt><dd class="break-words text-right">{{ $successor->top_job_family_1 ?: '—' }}</dd></div>
            <div class="grid grid-cols-[7rem_minmax(0,1fr)] gap-1"><dt class="font-bold">Top II Job Family</dt><dd class="break-words text-right">{{ $successor->top_job_family_2 ?: '—' }}</dd></div>
        </dl>
    </div>

    <div class="mt-2 flex flex-wrap justify-end gap-2 border-t border-slate-200 pt-2">
        @can('update', $successor)
            <button type="button" @click="openEditSuccessor(@js([
                'successor_id' => (string) $successor->id,
                'target_position_id' => (string) $successor->target_position_id,
                'name' => $successor->name,
                'sap' => $successor->sap,
                'current_position' => $successorPosition,
                'position_history' => $successor->position_history ?: [['period' => '', 'position' => '']],
                'birth_date' => $successor->birth_date?->format('Y-m-d'),
                'last_education' => $successorEducation,
                'talent_box' => $successor->talent_box,
                'succession_readiness' => $successor->succession_readiness,
                'recommendation' => $successor->recommendation,
                'targeted_band' => $successor->targeted_band,
                'performance_year' => $successor->performance_year,
                'performance_score' => $successor->performance_score,
                'performance_category' => $successor->performance_category,
                'mbti_type' => $successor->mbti_type,
                'mbti_description' => $successor->mbti_description,
                'top_job_family_1' => $successor->top_job_family_1,
                'top_job_family_2' => $successor->top_job_family_2,
                'photoUrl' => $successor->photo_path ? Storage::disk('public')->url($successor->photo_path) : null,
                'updateUrl' => route('performance-talent.successors.update', $successor),
            ]))" class="rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-bold text-slate-700 transition hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-blue-500">Edit</button>
        @endcan
        @can('delete', $successor)
            <form method="POST" action="{{ route('performance-talent.successors.destroy', $successor) }}" data-confirm-submit data-confirm-title="Hapus Successor ini?" data-confirm-text="Data kandidat {{ $successor->name }} akan dihapus permanen.">
                @csrf @method('DELETE')
                <button type="submit" class="rounded-lg border border-red-200 bg-white px-3 py-1.5 text-xs font-bold text-red-600 transition hover:bg-red-50 focus:outline-none focus:ring-2 focus:ring-red-500">Hapus</button>
            </form>
        @endcan
    </div>
</article>
