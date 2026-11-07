<article class="hc-od-widget" data-dashboard-widget="{{ $widget['id'] }}" data-widget-id="{{ $widget['id'] }}" data-widget-type="{{ $widget['type'] }}">
    <div class="hc-od-widget-tools">
        <span class="hc-od-widget-grip" data-widget-drag aria-hidden="true">⠿</span>
        <span class="hc-od-widget-title">{{ $widget['title'] }}</span>
        <button type="button" data-widget-move="{{ $widget['id'] }}" aria-label="Pindahkan {{ $widget['title'] }}" title="Pindahkan widget"><x-dashboard-icon name="arrows" aria-hidden="true" /></button>
        <button type="button" data-widget-remove="{{ $widget['id'] }}" aria-label="Lepas {{ $widget['title'] }}" title="Lepas widget">×</button>
    </div>
    <div class="hc-od-widget-content">
        @switch($widget['id'])
            @case('total-employees')
                <a href="{{ route('data-pegawai') }}" class="hc-od-metric" data-dashboard-metric="employees">
                <span class="hc-od-metric-icon hc-od-tone-blue"><x-dashboard-icon name="users" aria-hidden="true" /></span>
                <span class="hc-od-metric-copy">
                <span class="hc-od-metric-label">Total Pegawai</span>
                <strong>{{ number_format($totalEmployees, 0, ',', '.') }}</strong>
                <span class="hc-od-metric-note">Pegawai terdaftar</span>
                </span>
                </a>
                @break
            @case('wla-draft')
                <a href="{{ route('wla') }}" class="hc-od-metric" data-dashboard-metric="wla">
                <span class="hc-od-metric-icon hc-od-tone-teal"><x-dashboard-icon name="formasi" aria-hidden="true" /></span>
                <span class="hc-od-metric-copy">
                <span class="hc-od-metric-label">WLA Draft</span>
                <strong>{{ number_format($wlaSummary['running'], 0, ',', '.') }}</strong>
                <span class="hc-od-metric-note">Assessment berstatus Draft</span>
                </span>
                </a>
                @break
            @case('fit-proper-participants')
                <a href="{{ route('fit-proper') }}" class="hc-od-metric" data-dashboard-metric="fit-proper">
                <span class="hc-od-metric-icon hc-od-tone-blue"><x-dashboard-icon name="clipboard" aria-hidden="true" /></span>
                <span class="hc-od-metric-copy">
                <span class="hc-od-metric-label">Peserta Fit &amp; Proper</span>
                <strong>{{ number_format($fitProperSummary['activeParticipants'], 0, ',', '.') }}</strong>
                <span class="hc-od-metric-note">Pada kegiatan Berjalan</span>
                </span>
                </a>
                @break
            @case('pending-requests')
                <div class="hc-od-metric" data-dashboard-metric="pending">
                <span class="hc-od-metric-icon hc-od-tone-amber"><x-dashboard-icon name="document" aria-hidden="true" /></span>
                <span class="hc-od-metric-copy">
                <span class="hc-od-metric-label">Pengajuan Menunggu</span>
                <strong>{{ number_format($pendingTotal, 0, ',', '.') }}</strong>
                <span class="hc-od-metric-note">Menunggu persetujuan</span>
                </span>
                </div>
                @break
            @case('career-movements')
                @php
                $maximumMovementCount = max(1, ...collect($movements)->flatMap(fn (array $movement): array => $movement['values'])->all());
                $chartStep = max(1, (int) ceil($maximumMovementCount / 4));
                $chartMaximum = $chartStep * 4;
                $chartSeriesCount = count($movements);
                $chartBarWidth = 16;
                $chartBarGap = 3;
                $chartGroupWidth = ($chartSeriesCount * $chartBarWidth) + (($chartSeriesCount - 1) * $chartBarGap);
                $hasFinalMovements = collect($movements)->sum(fn (array $movement): int => array_sum($movement['values'])) > 0;
                @endphp
                
                <section class="hc-od-panel hc-od-career" aria-labelledby="career-title">
                <div class="hc-od-panel-heading">
                <div>
                <h2 id="career-title">Pergerakan Karier</h2>
                <p>Rekaman Final berdasarkan tanggal efektif</p>
                </div>
                <span class="hc-od-period">{{ $movementPeriod }}</span>
                </div>
                <div class="hc-od-chart-wrap">
                <svg class="hc-od-chart" data-career-chart viewBox="0 0 620 188" role="img" aria-labelledby="career-chart-title career-chart-description">
                <title id="career-chart-title">Pergerakan karier enam bulan terakhir</title>
                <desc id="career-chart-description">Jumlah rekaman Final per bulan. Rincian angka tersedia pada tabel setelah grafik. Pengajuan menunggu persetujuan tidak termasuk.</desc>
                @for ($tick = 0; $tick <= 4; $tick++)
                @php
                $tickY = 150 - ($tick * 33);
                @endphp
                <line x1="35" y1="{{ $tickY }}" x2="610" y2="{{ $tickY }}" class="hc-od-chart-grid" />
                <text x="25" y="{{ $tickY + 4 }}" text-anchor="end" class="hc-od-chart-tick">{{ number_format($tick * $chartStep, 0, ',', '.') }}</text>
                @endfor
                @foreach ($movementMonths as $monthIndex => $month)
                @php
                $groupCenter = 84 + ($monthIndex * 95);
                @endphp
                @foreach ($movements as $seriesIndex => $movement)
                @php
                $movementValue = $movement['values'][$monthIndex];
                $barHeight = ($movementValue / $chartMaximum) * 132;
                $barX = $groupCenter - ($chartGroupWidth / 2) + ($seriesIndex * ($chartBarWidth + $chartBarGap));
                @endphp
                <rect x="{{ $barX }}" y="{{ 150 - $barHeight }}" width="{{ $chartBarWidth }}" height="{{ $barHeight }}" rx="3" class="hc-od-chart-bar hc-od-fill-{{ $movement['tone'] }}">
                <title>{{ $movement['label'] }}, {{ $month['fullLabel'] }}: {{ $movementValue }}</title>
                </rect>
                @if ($movementValue > 0)
                <text x="{{ $barX + ($chartBarWidth / 2) }}" y="{{ 144 - $barHeight }}" text-anchor="middle" class="hc-od-chart-value">{{ $movementValue }}</text>
                @endif
                @endforeach
                <text x="{{ $groupCenter }}" y="173" text-anchor="middle" class="hc-od-chart-month">{{ $month['label'] }}</text>
                @endforeach
                </svg>
                @unless ($hasFinalMovements)
                <p class="hc-od-chart-empty">Belum ada pergerakan Final pada periode ini.</p>
                @endunless
                </div>
                <div class="hc-od-chart-legend" aria-hidden="true">
                @foreach ($movements as $movement)
                <span><i class="hc-od-fill-{{ $movement['tone'] }}"></i>{{ $movement['label'] }}</span>
                @endforeach
                </div>
                <div class="sr-only"><table>
                <caption>Jumlah pergerakan karier berstatus Final, {{ $movementPeriod }}</caption>
                <thead><tr><th scope="col">Bulan</th>@foreach ($movements as $movement)<th scope="col">{{ $movement['label'] }}</th>@endforeach</tr></thead>
                <tbody>
                @foreach ($movementMonths as $monthIndex => $month)
                <tr><th scope="row">{{ $month['fullLabel'] }}</th>@foreach ($movements as $movement)<td>{{ $movement['values'][$monthIndex] }}</td>@endforeach</tr>
                @endforeach
                </tbody>
                </table></div>
                </section>
                @break
            @case('fit-proper-status')
                <section class="hc-od-panel hc-od-fit-proper" aria-labelledby="fit-proper-title">
                <div class="hc-od-panel-heading">
                <div>
                <h2 id="fit-proper-title">Status Fit &amp; Proper</h2>
                <p>{{ number_format($fitProperSummary['total'], 0, ',', '.') }} kegiatan sesuai akses Anda</p>
                </div>
                </div>
                <div class="hc-od-status-list">
                @foreach ($fitProperSummary['statuses'] as $status)
                <div class="hc-od-status" data-fit-proper-status="{{ $status['status'] }}">
                <div class="hc-od-status-label"><span>{{ $status['label'] }}</span><strong>{{ number_format($status['count'], 0, ',', '.') }}</strong></div>
                <div class="hc-od-progress" role="progressbar" aria-label="Kegiatan {{ $status['label'] }}" aria-valuemin="0" aria-valuemax="{{ max(1, $fitProperSummary['total']) }}" aria-valuenow="{{ $status['count'] }}" aria-valuetext="{{ $status['count'] }} dari {{ $fitProperSummary['total'] }} kegiatan">
                <span class="hc-od-fill-{{ $status['tone'] }}" style="width: {{ $fitProperSummary['total'] > 0 ? ($status['count'] / $fitProperSummary['total']) * 100 : 0 }}%"></span>
                </div>
                </div>
                @endforeach
                </div>
                @if ($fitProperSummary['total'] === 0)
                <p class="hc-od-empty-note">Belum ada kegiatan yang dapat ditampilkan.</p>
                @endif
                </section>
                @break
            @case('wla-summary')
                <section class="hc-od-panel hc-od-wla" aria-labelledby="wla-title">
                <h2 id="wla-title">Ringkasan WLA</h2>
                <div class="hc-od-wla-statuses">
                @foreach ($wlaSummary['statuses'] as $status)
                <div class="hc-od-wla-status hc-od-tone-{{ $status['tone'] }}" data-wla-status="{{ $status['status'] }}">
                <x-dashboard-icon :name="$status['status'] === 'final' ? 'clipboard' : 'document'" aria-hidden="true" />
                <span><span>{{ $status['label'] }}</span><strong>{{ number_format($status['count'], 0, ',', '.') }}</strong></span>
                </div>
                @endforeach
                </div>
                <a href="{{ route('wla') }}" class="hc-od-link">Buka WLA <span aria-hidden="true">&rarr;</span></a>
                </section>
                @break
            @case('follow-up')
                <section id="tindak-lanjut" class="hc-od-panel hc-od-follow-up" aria-labelledby="follow-up-title">
                <h2 id="follow-up-title">Perlu Ditindaklanjuti</h2>
                <div class="hc-od-follow-up-list">
                @foreach ($movements as $movement)
                <a href="{{ route($movement['route']) }}" class="hc-od-follow-up-row" data-movement-pending="{{ $movement['key'] }}">
                <x-dashboard-icon :name="$movement['icon']" class="hc-od-text-{{ $movement['tone'] }}" aria-hidden="true" />
                <span>{{ $movement['label'] }} menunggu persetujuan</span>
                <strong class="hc-od-tone-{{ $movement['tone'] }}">{{ number_format($movement['pending'], 0, ',', '.') }}</strong>
                <span class="hc-od-row-arrow" aria-hidden="true">&rarr;</span>
                </a>
                @endforeach
                </div>
                </section>
                @break
        @endswitch
    </div>
</article>
