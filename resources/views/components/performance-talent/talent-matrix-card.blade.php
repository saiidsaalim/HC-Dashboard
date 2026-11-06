@props([
    'boxNumber',
    'box',
    'count' => 0,
    'previews' => collect(),
    'active' => false,
    'dimmed' => false,
    'canViewEmployees' => false,
])

@php
    $previewItems = collect($previews);
    $boxUrl = route('performance-talent.talent-pool.index', [
        ...request()->except(['box', 'page']),
        'box' => $boxNumber,
    ]).'#talent-detail';
@endphp

<article data-talent-box data-box="{{ str_pad((string) $boxNumber, 2, '0', STR_PAD_LEFT) }}"
    data-display-number="{{ $boxNumber }}" data-potential="{{ $box['potential'] }}"
    data-performance="{{ $box['performance'] }}"
    class="talent-pool-card {{ $active ? 'is-active' : '' }} {{ $dimmed ? 'is-dimmed' : '' }}"
    style="--talent-accent: {{ $box['accent'] }}; --talent-background: {{ $box['background'] }};">
    <a href="{{ $boxUrl }}" aria-label="Lihat {{ $box['label'] }}, {{ $count }} pegawai"
        @if ($active) aria-current="true" @endif
        @if ($dimmed) aria-disabled="true" tabindex="-1" @endif class="talent-pool-card__link">
        <span class="talent-pool-card__heading">
            <span class="talent-pool-card__icon" aria-hidden="true">
                <img src="{{ asset('images/talent-matrix/icons/'.$box['icon']) }}" alt="" width="24" height="24">
            </span>
            <span class="min-w-0">
                <strong>{{ $box['label'] }}</strong>
                <small>Potensi {{ $box['potential'] }} <span aria-hidden="true">&bull;</span> Performa {{ $box['performance'] }}</small>
            </span>
        </span>

        <span class="talent-pool-card__people">
            @if ($dimmed)
                <span class="talent-pool-card__empty-text">Tidak termasuk filter</span>
            @elseif ($count === 0)
                <span class="talent-pool-card__empty-text">Belum ada talent</span>
            @elseif ($canViewEmployees && $previewItems->isNotEmpty())
                <span class="talent-pool-card__avatars" aria-label="Preview pegawai">
                    @foreach ($previewItems as $employee)
                        @php
                            $displayName = trim((string) ($employee->personal_number ?: $employee->name ?: 'Nama belum tersedia'));
                            $initials = collect(preg_split('/\s+/', $displayName) ?: [])
                                ->filter()
                                ->take(2)
                                ->map(fn (string $word): string => mb_strtoupper(mb_substr($word, 0, 1)))
                                ->implode('');
                        @endphp
                        <span title="{{ $displayName }}" aria-label="{{ $displayName }}">{{ $initials ?: '—' }}</span>
                    @endforeach
                </span>
            @else
                <span class="talent-pool-card__empty-preview" aria-hidden="true">
                    <x-dashboard-icon name="users" class="h-4 w-4" />
                </span>
            @endif

            <b>{{ number_format($count, 0, ',', '.') }}</b>
            @if ($count > 3 && $canViewEmployees)
                <em>+{{ $count - 3 }}</em>
            @endif
        </span>

        <span class="talent-pool-card__action">
            {{ $dimmed ? 'Di luar filter' : 'Lihat Talent' }}
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                <path d="m9 18 6-6-6-6" stroke-linecap="round" stroke-linejoin="round" />
            </svg>
        </span>
    </a>
</article>
