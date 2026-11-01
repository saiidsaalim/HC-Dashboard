@php
    $talentMatrixData = collect($talentMatrix ?? []);
    $matrixRows = [
        [
            'potential' => 'Tinggi',
            'boxes' => [
                ['key' => '07', 'number' => '01', 'title' => 'Diam-Diam Jago', 'performance' => 'Rendah', 'background' => '#DFF8EB', 'accent' => '#27C696', 'icon' => 'bulb.svg', 'illustration' => '01_diam_diam_jago.png'],
                ['key' => '08', 'number' => '02', 'title' => 'Lagi Ngebut', 'performance' => 'Sedang', 'background' => '#E1F0FF', 'accent' => '#378CED', 'icon' => 'trending-up.svg', 'illustration' => '02_lagi_ngebut.png'],
                ['key' => '09', 'number' => '03', 'title' => 'Rising Star', 'performance' => 'Tinggi', 'background' => '#FFF2BA', 'accent' => '#F3AE13', 'icon' => 'star.svg', 'illustration' => '03_rising_star.png'],
            ],
        ],
        [
            'potential' => 'Sedang',
            'boxes' => [
                ['key' => '04', 'number' => '04', 'title' => 'Butuh Arah', 'performance' => 'Rendah', 'background' => '#FFE6EA', 'accent' => '#F47C92', 'icon' => 'signpost.svg', 'illustration' => '04_butuh_arah.png'],
                ['key' => '05', 'number' => '05', 'title' => 'Tim Andalan', 'performance' => 'Sedang', 'background' => '#EEE3FF', 'accent' => '#9765E8', 'icon' => 'users.svg', 'illustration' => '05_tim_andalan.png'],
                ['key' => '06', 'number' => '06', 'title' => 'Gaspol', 'performance' => 'Tinggi', 'background' => '#E5F2FF', 'accent' => '#2F91EC', 'icon' => 'rocket.svg', 'illustration' => '06_gaspol.png'],
            ],
        ],
        [
            'potential' => 'Rendah',
            'boxes' => [
                ['key' => '01', 'number' => '07', 'title' => 'Perlu Booster', 'performance' => 'Rendah', 'background' => '#FFEBD8', 'accent' => '#F49B50', 'icon' => 'battery-low.svg', 'illustration' => '07_perlu_booster.png'],
                ['key' => '02', 'number' => '08', 'title' => 'Aman Terkendali', 'performance' => 'Sedang', 'background' => '#DDF9EE', 'accent' => '#22BE99', 'icon' => 'shield-check.svg', 'illustration' => '08_aman_terkendali.png'],
                ['key' => '03', 'number' => '09', 'title' => 'Jagonya Eksekusi', 'performance' => 'Tinggi', 'background' => '#F0E5FF', 'accent' => '#9454E7', 'icon' => 'clipboard-check.svg', 'illustration' => '09_jagonya_eksekusi.png'],
            ],
        ],
    ];
    $totalTalentCount = $talentMatrixData->sum(fn ($talents): int => collect($talents)->count());
@endphp

<section x-data="{ selectedBox: null }" x-on:talent-box-selected.window="selectedBox = $event.detail"
    class="talent-matrix-panel" aria-labelledby="talent-matrix-title">
    <header class="talent-matrix-header">
        <div>
            <span class="talent-matrix-header__eyebrow">9-Box Talent Matrix</span>
            <h3 id="talent-matrix-title" class="font-display">Talent Matrix <span>3&times;3</span></h3>
            <p>Pemetaan talenta yang simple, ramah, dan mudah dipahami tanpa mengubah klasifikasi penilaian.</p>
        </div>
        <div class="talent-matrix-header__summary" aria-label="Ringkasan jumlah talent">
            <strong>{{ $totalTalentCount }}</strong>
            <span>Total talent terpetakan</span>
        </div>
    </header>

    <p class="talent-matrix-scroll-hint">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
            <path d="M8 7 3 12l5 5m8-10 5 5-5 5M3 12h18" stroke-linecap="round" stroke-linejoin="round" />
        </svg>
        Geser horizontal untuk melihat seluruh matriks.
    </p>

    <div class="talent-matrix-scroll" tabindex="0" aria-label="Area Talent Matrix dapat digeser horizontal">
        <div class="talent-matrix-layout">
            <div class="talent-matrix-axis-y" aria-label="Potential meningkat ke atas">
                <span>Tinggi</span>
                <span>Sedang</span>
                <span>Rendah</span>
                <strong>Potential <span aria-hidden="true">&uarr;</span></strong>
            </div>

            <div>
                <div role="group" aria-label="9-Box Talent Matrix" class="talent-matrix-grid">
                    @foreach ($matrixRows as $row)
                        <div class="contents">
                            @foreach ($row['boxes'] as $box)
                                <x-performance-talent.talent-matrix-card :box="$box" :potential="$row['potential']"
                                    :talents="$talentMatrixData->get($box['key'], [])" />
                            @endforeach
                        </div>
                    @endforeach
                </div>

                <div class="talent-matrix-axis-x" aria-label="Performance meningkat ke kanan">
                    <div>
                        <span>Rendah</span>
                        <span>Sedang</span>
                        <span>Tinggi</span>
                    </div>
                    <strong>Performance <span aria-hidden="true">&rarr;</span></strong>
                </div>
            </div>
        </div>
    </div>

    <div class="talent-matrix-detail" aria-live="polite">
        <template x-if="selectedBox === null">
            <div class="talent-matrix-detail__placeholder">
                <span aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                        <path d="M8 10h8m-8 4h5m8-2a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" stroke-linecap="round" stroke-linejoin="round" />
                    </svg>
                </span>
                <div>
                    <strong>Pilih salah satu kotak</strong>
                    <p>Detail kombinasi Potential dan Performance akan tampil di sini.</p>
                </div>
            </div>
        </template>

        <template x-if="selectedBox !== null">
            <div>
                <div class="talent-matrix-detail__header">
                    <div>
                        <span x-text="selectedBox.number"></span>
                        <h4 class="font-display" x-text="selectedBox.title"></h4>
                        <p>Potensi <strong x-text="selectedBox.potential"></strong> <span aria-hidden="true">&bull;</span> Performa <strong x-text="selectedBox.performance"></strong></p>
                    </div>
                    <strong class="talent-matrix-detail__count"><span x-text="selectedBox.count"></span> talent</strong>
                </div>

                <template x-if="selectedBox.count === 0">
                    <p class="talent-matrix-detail__empty">Belum ada data talent pada kombinasi ini.</p>
                </template>

                <template x-if="selectedBox.count > 0">
                    <ul class="talent-matrix-detail__list">
                        <template x-for="talent in selectedBox.talents" x-bind:key="talent.name + talent.position">
                            <li>
                                <span x-text="talent.name.slice(0, 1).toUpperCase()"></span>
                                <div>
                                    <strong x-text="talent.name"></strong>
                                    <p x-text="talent.position"></p>
                                </div>
                            </li>
                        </template>
                    </ul>
                </template>
            </div>
        </template>
    </div>
</section>
