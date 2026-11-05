@if ($resultDashboard ?? false)
@php
    $resultRows = collect($recap['rows'] ?? []);
    $assessedParticipantCount = $resultRows
        ->filter(fn (array $row): bool => ($row['submitted_count'] ?? 0) > 0)
        ->count();
    $highestTotal = $resultRows
        ->pluck('total')
        ->filter(fn ($value): bool => $value !== null)
        ->max();
    $passedParticipantCount = $resultRows
        ->filter(fn (array $row): bool => ($row['passed'] ?? null) === true)
        ->count();
    $topParticipants = $resultRows
        ->filter(fn (array $row): bool => ($row['rank_total'] ?? null) !== null)
        ->sortBy('rank_total')
        ->take(3);
    $examinerCount = $activity->examiners->count();
    $submittedExaminerCount = $activity->examiners->whereNotNull('submitted_at')->count();
    $isFinalResult = $activity->status === 'Selesai';
    $formatResultNumber = fn ($value): string => $value === null
        ? '—'
        : number_format((float) $value, 1, ',', '.');
    $participantInitials = function (string $name): string {
        $parts = preg_split('/\s+/u', trim($name)) ?: [];
        $initials = collect($parts)
            ->filter()
            ->take(2)
            ->map(fn (string $part): string => mb_strtoupper(mb_substr($part, 0, 1)))
            ->implode('');

        return $initials !== '' ? $initials : 'P';
    };
@endphp

<div class="fp-result-dashboard">
    <header class="fp-result-header">
        <div>
            <p class="fp-result-eyebrow"><span aria-hidden="true"></span>Fit &amp; Proper</p>
            <h2 class="font-display text-2xl font-bold tracking-tight text-slate-950 sm:text-3xl">Hasil Akhir Fit &amp; Proper</h2>
            <p class="mt-1 text-sm text-slate-600 sm:text-base">Ringkasan peringkat, kontribusi nilai, dan status kelulusan peserta.</p>
        </div>
        <span class="fp-result-status {{ $isFinalResult ? 'is-final' : 'is-temporary' }}">
            <span aria-hidden="true"></span>
            {{ $isFinalResult ? 'Final' : 'Sementara' }}
        </span>
    </header>

    <details class="fp-result-method">
        <summary>
            <span class="fp-result-method-copy">
                <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true">
                    <circle cx="10" cy="10" r="7.5" />
                    <path d="M10 9v5M10 6.2v.1" stroke-linecap="round" />
                </svg>
                <span>{{ $recap['normalization'] }}</span>
            </span>
            <span class="fp-result-method-action">
                <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path d="M7 5H5.5A1.5 1.5 0 0 0 4 6.5v8A1.5 1.5 0 0 0 5.5 16h8a1.5 1.5 0 0 0 1.5-1.5V13M11 4h5v5M9 11l7-7" stroke-linecap="round" stroke-linejoin="round" /></svg>
                Lihat metode perhitungan
            </span>
        </summary>
        <div class="fp-result-method-details">
            <p>Nilai FP normal diperoleh dari rata-rata FP peserta dibagi rata-rata FP tertinggi dalam kegiatan. Kontribusi KPI, asesmen, dan FP kemudian mengikuti bobot standar kegiatan yang tersimpan.</p>
            <p>Angka pada layar dibulatkan satu desimal untuk tampilan. Ranking dan kelulusan tetap memakai nilai internal dengan presisi {{ $recap['precision'] }} desimal.</p>
        </div>
    </details>

    <dl class="fp-result-summary-grid">
        <div class="fp-result-summary-card is-cyan">
            <span class="fp-result-summary-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="9" cy="8" r="3" /><circle cx="17" cy="9" r="2.5" /><path d="M3 20v-1.5a6 6 0 0 1 12 0V20M14 15a5 5 0 0 1 7 4.5V20" stroke-linecap="round" /></svg>
            </span>
            <div><dt>Peserta Dinilai</dt><dd>{{ $assessedParticipantCount }}</dd><p>Total peserta yang telah dinilai.</p></div>
        </div>
        <div class="fp-result-summary-card is-blue">
            <span class="fp-result-summary-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="5" y="4" width="14" height="17" rx="2.5" /><path d="M9 4V2.5h6V4M8.5 12l2.2 2.2 4.8-5" stroke-linecap="round" stroke-linejoin="round" /></svg>
            </span>
            <div><dt>Penguji Selesai</dt><dd>{{ $submittedExaminerCount }} / {{ $examinerCount }}</dd><p>Seluruh penguji yang telah mengirimkan penilaian.</p></div>
        </div>
        <div class="fp-result-summary-card is-amber">
            <span class="fp-result-summary-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M7 4h10v5a5 5 0 0 1-10 0V4ZM7 6H3.5v2A4 4 0 0 0 7 12M17 6h3.5v2a4 4 0 0 1-3.5 4M12 14v4M8.5 21h7" stroke-linecap="round" stroke-linejoin="round" /></svg>
            </span>
            <div><dt>Nilai Tertinggi</dt><dd>{{ $formatResultNumber($highestTotal) }}</dd><p>Total nilai tertinggi dari peserta.</p></div>
        </div>
        <div class="fp-result-summary-card is-emerald">
            <span class="fp-result-summary-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="9" /><path d="m8 12 2.7 2.7L16.5 9" stroke-linecap="round" stroke-linejoin="round" /></svg>
            </span>
            <div><dt>Peserta Lulus</dt><dd>{{ $passedParticipantCount }}</dd><p>Total peserta dengan status lulus.</p></div>
        </div>
    </dl>

    <section class="fp-result-section" aria-labelledby="fp-result-ranking-title">
        <div class="fp-result-section-heading">
            <span aria-hidden="true"></span>
            <div>
                <h3 id="fp-result-ranking-title">Peringkat Peserta</h3>
                <p>Urutan berdasarkan total hasil penilaian.</p>
            </div>
        </div>

        @if ($topParticipants->isNotEmpty())
            <div class="fp-result-ranking-grid">
                @foreach ($topParticipants as $participant)
                    @php
                        $rank = (int) $participant['rank_total'];
                        $rankTone = match ($rank) {
                            1 => 'is-gold',
                            2 => 'is-silver',
                            default => 'is-bronze',
                        };
                    @endphp
                    <article class="fp-result-ranking-card {{ $rankTone }}">
                        <span class="fp-result-medal" aria-label="Peringkat {{ $rank }}">
                            <svg viewBox="0 0 44 48" fill="none" aria-hidden="true"><path d="m12 28-2 16 12-7 12 7-2-16" /><circle cx="22" cy="18" r="15" /><circle cx="22" cy="18" r="10" /></svg>
                            <strong>{{ $rank }}</strong>
                        </span>
                        <span class="fp-result-avatar" aria-hidden="true">{{ $participantInitials($participant['name']) }}</span>
                        <div class="fp-result-ranking-person">
                            <h4>{{ $participant['name'] }}</h4>
                            <p>{{ $participant['submitted_count'] }} / {{ count($participant['details']) }} penguji</p>
                        </div>
                        <div class="fp-result-ranking-score">
                            <strong>{{ $formatResultNumber($participant['total']) }}</strong>
                            <span class="{{ $participant['passed'] ? 'is-passed' : 'is-not-passed' }}">{{ $participant['passed'] ? 'Lulus' : 'Tidak lulus' }}</span>
                        </div>
                    </article>
                @endforeach
            </div>
        @else
            <div class="fp-result-ranking-empty">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path d="M7 4h10v5a5 5 0 0 1-10 0V4ZM7 6H4v2a4 4 0 0 0 3 4M17 6h3v2a4 4 0 0 1-3 4M12 14v4M8.5 21h7" stroke-linecap="round" /></svg>
                <div><strong>Peringkat belum tersedia</strong><p>Ranking dan kelulusan ditampilkan setelah seluruh data peserta dan penilaian penguji lengkap.</p></div>
            </div>
        @endif
    </section>

    <section class="fp-result-section" aria-labelledby="fp-result-details-title">
        <div class="fp-result-section-heading">
            <span aria-hidden="true"></span>
            <div><h3 id="fp-result-details-title">Rincian Perhitungan</h3></div>
        </div>

        <div class="fp-result-table-wrap" tabindex="0" role="region" aria-label="Rincian perhitungan hasil Fit &amp; Proper">
            <table class="fp-recap-table">
                <thead>
                    <tr>
                        @foreach (['Peserta', 'Kirim Penguji', 'Rata FP', 'FP Normal', 'KPI × Bobot', 'Asesmen × Bobot', 'FP × Bobot', 'Total', 'Rank FP', 'Rank Total', 'Kelulusan'] as $label)
                            <th scope="col">{{ $label }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @forelse ($resultRows as $row)
                        @php
                            $isFirstRank = ($row['rank_total'] ?? null) === 1;
                            $totalProgress = $row['total'] === null ? 0 : min(100, max(0, (float) $row['total']));
                        @endphp
                        <tr class="{{ $isFirstRank ? 'is-top-result' : '' }}">
                            <th scope="row">{{ $row['name'] }}</th>
                            <td>
                                <span class="fp-result-submission {{ $row['submitted_count'] === count($row['details']) && count($row['details']) > 0 ? 'is-complete' : '' }}">
                                    <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><circle cx="10" cy="10" r="7" /><path d="m7 10 2 2 4-4" stroke-linecap="round" stroke-linejoin="round" /></svg>
                                    {{ $row['submitted_count'] }} / {{ count($row['details']) }}
                                </span>
                            </td>
                            @foreach (['raw', 'fp', 'contribution_kpi', 'contribution_assessment', 'contribution_fp'] as $key)
                                <td>{{ $formatResultNumber($row[$key]) }}</td>
                            @endforeach
                            <td>
                                <strong class="fp-result-total">{{ $formatResultNumber($row['total']) }}</strong>
                                @if ($row['total'] !== null)
                                    <span class="fp-result-progress" role="progressbar" aria-label="Total nilai {{ $row['name'] }}" aria-valuenow="{{ $row['total'] }}" aria-valuemin="0" aria-valuemax="100"><span style="width: {{ $totalProgress }}%"></span></span>
                                @endif
                            </td>
                            <td><span class="fp-result-rank">{{ $row['rank_fp'] ?? '—' }}</span></td>
                            <td><span class="fp-result-rank">{{ $row['rank_total'] ?? '—' }}</span></td>
                            <td>
                                @if ($row['passed'] === null)
                                    <span class="fp-result-outcome is-pending">Belum lengkap</span>
                                @elseif ($row['passed'])
                                    <span class="fp-result-outcome is-passed">Lulus</span>
                                @else
                                    <span class="fp-result-outcome is-not-passed">Tidak lulus</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="11" class="fp-result-table-empty">Belum ada hasil penilaian. Hasil akan tersedia setelah peserta dan penilaian ditambahkan.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <p class="fp-result-precision-note">
            <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><circle cx="10" cy="10" r="7.5" /><path d="M10 9v5M10 6.2v.1" stroke-linecap="round" /></svg>
            Angka ditampilkan dengan satu desimal; perbandingan ranking dan kelulusan memakai {{ $recap['precision'] }} desimal. Ranking dan kelulusan hanya tersedia setelah seluruh data lengkap.
        </p>
    </section>

    <aside class="fp-result-disclaimer">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="m12 3 8 3v5.8c0 4.5-3.1 7.8-8 9.2-4.9-1.4-8-4.7-8-9.2V6l8-3Z" /><path d="m9 12 2 2 4-4" stroke-linecap="round" stroke-linejoin="round" /></svg>
        <p><strong>Hasil ini merupakan referensi.</strong> Tidak membuat atau menyetujui promosi dan tidak mengubah jabatan pegawai.</p>
    </aside>
</div>
@else
<p class="my-3 text-sm text-slate-600">{{ $recap['normalization'] }} Angka ditampilkan dengan satu desimal; perbandingan ranking dan kelulusan memakai 6 desimal. Ranking dan kelulusan hanya tersedia setelah seluruh data lengkap.</p>
<div class="overflow-x-auto"><table class="fp-recap-table w-full text-left text-sm"><thead><tr class="border-b text-slate-500">@foreach(['Peserta','Kirim penguji','Rata FP','FP normal','KPI × bobot','Asesmen × bobot','FP × bobot','Total','Rank FP','Rank total','Kelulusan'] as $label)<th class="p-3">{{ $label }}</th>@endforeach</tr></thead><tbody class="divide-y">
@forelse($recap['rows'] as $r)<tr><td class="p-3 font-semibold">{{ $r['name'] }}</td><td class="p-3">{{ $r['submitted_count'] }} / {{ count($r['details']) }}</td>@foreach(['raw','fp','contribution_kpi','contribution_assessment','contribution_fp','total'] as $key)<td class="p-3">{{ $r[$key] === null ? '—' : number_format($r[$key], 1, ',', '.') }}</td>@endforeach<td class="p-3">{{ $r['rank_fp'] ?? '—' }}</td><td class="p-3">{{ $r['rank_total'] ?? '—' }}</td><td class="p-3">{{ $r['passed'] === null ? 'Belum lengkap' : ($r['passed'] ? 'Lulus' : 'Tidak lulus') }}</td></tr>@empty<tr><x-ui.empty-state colspan="11" title="Belum ada hasil penilaian." description="Hasil akan tersedia setelah peserta dan penilaian ditambahkan." /></tr>@endforelse
</tbody></table></div>
@endif
