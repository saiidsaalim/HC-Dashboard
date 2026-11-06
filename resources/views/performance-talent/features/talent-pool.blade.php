@php
    $matrixRows = [[7, 8, 9], [4, 5, 6], [1, 2, 3]];
    $activeBoxMeta = $activeBox === null ? null : $talentBoxes[$activeBox];
    $activeBoxCategory = $activeBox === null
        ? null
        : collect($talentCategories)->first(fn (array $category): bool => in_array($activeBox, $category['boxes'], true));
    $visibleBoxNumbers = in_array($activeCategory, ['all', 'managerial'], true)
        ? array_keys($talentBoxes)
        : $talentCategories[$activeCategory]['boxes'];
    $departmentOptions = $departments
        ->map(fn ($department): array => [
            'id' => (string) $department->id,
            'directorate_id' => (string) $department->directorate_id,
            'name' => $department->name,
        ])
        ->values();
    $categoryUrls = collect(['all', ...array_keys($talentCategories), 'managerial'])
        ->mapWithKeys(function (string $category): array {
            return [$category => route('performance-talent.talent-pool.index', [
                ...request()->except(['category', 'box', 'page']),
                'category' => $category,
            ])];
        });
    $chartPayload = [
        'labels' => $categoryDistribution->pluck('label')->values(),
        'series' => $categoryDistribution->pluck('count')->values(),
        'colors' => $categoryDistribution->pluck('color')->values(),
        'keys' => $categoryDistribution->keys()->values(),
        'active' => $activeCategory === 'managerial' ? 'all' : $activeCategory,
        'total' => $totalEmployeeCount,
    ];
@endphp

<div data-talent-pool-dashboard class="talent-pool-page space-y-5"
    x-data="{
        directorateId: @js((string) ($filters['directorate_id'] ?? '')),
        departmentId: @js((string) ($filters['department_id'] ?? '')),
        departments: @js($departmentOptions),
        get filteredDepartments() {
            return this.directorateId === ''
                ? this.departments
                : this.departments.filter(department => department.directorate_id === this.directorateId);
        },
        changeDirectorate() {
            if (!this.filteredDepartments.some(department => department.id === this.departmentId)) {
                this.departmentId = '';
            }
        },
    }">
    <section class="talent-pool-hero" aria-labelledby="talent-pool-title">
        <div class="talent-pool-hero__copy">
            <span>Performance &amp; Talent Management</span>
            <h1 id="talent-pool-title">Talent Pool</h1>
            <p>Pemetaan karyawan berdasarkan potensi dan performa</p>
        </div>

        <div class="talent-pool-summary" aria-label="Ringkasan Talent Pool">
            <article>
                <span class="talent-pool-summary__icon is-cyan" aria-hidden="true"><x-dashboard-icon name="users" class="h-6 w-6" /></span>
                <span><small>Total Talent Terpetakan</small><strong data-talent-summary="total">{{ number_format($summary['total'], 0, ',', '.') }}</strong></span>
            </article>
            <article>
                <span class="talent-pool-summary__icon is-amber" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="m12 3 2.8 5.7 6.2.9-4.5 4.4 1.1 6.2-5.6-3-5.6 3 1.1-6.2L3 9.6l6.2-.9L12 3Z" stroke-linejoin="round" /></svg>
                </span>
                <span><small>High Potential</small><strong data-talent-summary="high-potential">{{ number_format($summary['high_potential'], 0, ',', '.') }}</strong></span>
            </article>
            <article>
                <span class="talent-pool-summary__icon is-teal" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="m4 16 5-5 4 4 7-8M15 7h5v5" stroke-linecap="round" stroke-linejoin="round" /></svg>
                </span>
                <span><small>High Performance</small><strong data-talent-summary="high-performance">{{ number_format($summary['high_performance'], 0, ',', '.') }}</strong></span>
            </article>
        </div>
    </section>

    <form method="GET" action="{{ route('performance-talent.talent-pool.index') }}" class="talent-pool-filter"
        aria-label="Filter Talent Pool">
        <input type="hidden" name="category" value="{{ $activeCategory }}">
        @if ($activeBox !== null)
            <input type="hidden" name="box" value="{{ $activeBox }}">
        @endif

        <label>
            <span>Direktorat</span>
            <select name="directorate_id" x-model="directorateId" x-on:change="changeDirectorate()">
                <option value="">Semua Direktorat</option>
                @foreach ($directorates as $directorate)
                    <option value="{{ $directorate->id }}">{{ $directorate->name }}</option>
                @endforeach
            </select>
        </label>

        <label>
            <span>Department</span>
            <select name="department_id" x-model="departmentId">
                <option value="">Semua Department</option>
                <template x-for="department in filteredDepartments" x-bind:key="department.id">
                    <option x-bind:value="department.id" x-text="department.name"></option>
                </template>
            </select>
        </label>

        <label class="talent-pool-filter__search">
            <span>Cari pegawai</span>
            <span class="talent-pool-filter__search-field">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><circle cx="11" cy="11" r="7" /><path d="m20 20-4-4" stroke-linecap="round" /></svg>
                <input type="search" name="search" value="{{ $filters['search'] }}" maxlength="100"
                    placeholder="Cari nama, Personal Number, atau SAP">
            </span>
        </label>

        <button type="submit" class="talent-pool-filter__submit">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M4 5h16l-6 7v5l-4 2v-7L4 5Z" stroke-linejoin="round" /></svg>
            Terapkan Filter
        </button>

        @if ($hasActiveFilters)
            <a href="{{ route('performance-talent.talent-pool.index') }}" class="talent-pool-filter__reset">Reset</a>
        @endif
    </form>

    <section class="talent-pool-category-filter" aria-labelledby="talent-category-filter-title">
        <span id="talent-category-filter-title">Kategori Pemetaan</span>
        <div role="group" aria-label="Pilih kategori pemetaan">
            <a href="{{ $categoryUrls->get('all') }}" @if ($activeCategory === 'all') aria-current="true" @endif>Semua</a>
            @foreach ($talentCategories as $categoryKey => $category)
                <a href="{{ $categoryUrls->get($categoryKey) }}" @if ($activeCategory === $categoryKey) aria-current="true" @endif>
                    {{ $category['short_label'] }}
                </a>
            @endforeach
            <a href="{{ $categoryUrls->get('managerial') }}" @if ($activeCategory === 'managerial') aria-current="true" @endif>
                Managerial
            </a>
        </div>
    </section>

    @if ($errors->any())
        <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-semibold text-red-700" role="alert">{{ $errors->first() }}</div>
    @endif

    @if ($invalidMappingCount > 0)
        <div class="talent-pool-unmapped is-invalid" role="alert">
            <span aria-hidden="true">!</span>
            <p><strong>{{ number_format($invalidMappingCount, 0, ',', '.') }} pegawai memiliki Talent Mapping tidak valid.</strong> Nilai di luar rentang 1–9 tidak dimasukkan ke Talent, Non Talent, maupun Not Available.</p>
        </div>
    @endif

    <div class="talent-pool-visual-grid">
        <section class="talent-pool-matrix-panel" aria-labelledby="talent-matrix-title">
            <div class="talent-pool-matrix-panel__header">
                <div>
                    <span>9-Box Talent Matrix</span>
                    <h2 id="talent-matrix-title">Pemetaan Potensi &amp; Performa</h2>
                </div>
                <p>Posisi box tetap mengikuti sumbu Potential dan Performance.</p>
            </div>

            @if ($totalEmployeeCount === 0)
                <x-ui.empty-state class="mx-4 mb-4" title="Data Talent Pool belum tersedia."
                    :description="$hasActiveFilters ? 'Tidak ada pegawai yang sesuai dengan filter.' : 'Belum ada data pegawai dalam scope Talent Pool.'" />
            @elseif ($summary['total'] === 0)
                <div class="talent-pool-matrix-note">Belum ada pegawai yang memiliki Talent Mapping 1–9.</div>
            @endif

            <div class="talent-pool-matrix-scroll" tabindex="0" aria-label="Matriks Talent Pool dapat digeser horizontal">
                <div class="talent-pool-matrix">
                    <div class="talent-pool-axis-y" aria-label="Potential meningkat ke atas">
                        <span>Tinggi</span><span>Sedang</span><span>Rendah</span>
                        <strong>Potential</strong>
                    </div>

                    <div class="talent-pool-matrix__content">
                        <div class="talent-pool-grid" role="group" aria-label="9-Box Talent Matrix">
                            @foreach ($matrixRows as $row)
                                @foreach ($row as $boxNumber)
                                    <x-performance-talent.talent-matrix-card :box-number="$boxNumber"
                                        :box="$talentBoxes[$boxNumber]" :count="$talentCounts->get($boxNumber, 0)"
                                        :previews="$talentPreviews->get($boxNumber, collect())" :active="$activeBox === $boxNumber"
                                        :dimmed="$activeCategory !== 'all' && ! in_array($boxNumber, $visibleBoxNumbers, true)"
                                        :can-view-employees="$canViewEmployees" />
                                @endforeach
                            @endforeach
                        </div>

                        <div class="talent-pool-axis-x" aria-label="Performance meningkat ke kanan">
                            <div><span>Rendah</span><span>Sedang</span><span>Tinggi</span></div>
                            <strong>Performance</strong>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <aside class="talent-pool-distribution" aria-labelledby="talent-distribution-title">
            <header>
                <span>Ringkasan Kategori</span>
                <h2 id="talent-distribution-title">Distribusi Talent</h2>
                <p>{{ $activeCategory === 'managerial' ? 'Komposisi pemetaan pegawai managerial' : 'Komposisi pemetaan pegawai' }}</p>
            </header>

            <div data-talent-distribution-chart data-chart='@json($chartPayload)'
                class="talent-pool-distribution__chart" aria-label="Diagram distribusi Talent Pool"></div>

            <div class="talent-pool-distribution__legend">
                @foreach ($categoryDistribution as $categoryKey => $category)
                    <a href="{{ $categoryUrls->get($categoryKey) }}"
                        class="{{ $activeCategory === $categoryKey ? 'is-active' : '' }}"
                        @if ($activeCategory === $categoryKey) aria-current="true" @endif>
                        <span style="--category-color: {{ $category['color'] }}" aria-hidden="true"></span>
                        <span><strong>{{ $category['short_label'] }}</strong><small>{{ number_format($category['count'], 0, ',', '.') }} pegawai</small></span>
                        <b>{{ number_format($category['percentage'], 1, ',', '.') }}%</b>
                        @if ($activeCategory === $categoryKey)
                            <em aria-label="Kategori aktif">✓</em>
                        @endif
                    </a>
                @endforeach
            </div>
        </aside>
    </div>

    <section id="talent-detail" class="talent-pool-detail" aria-labelledby="talent-detail-title">
        <header>
            <div class="talent-pool-detail__title">
                @if ($activeCategory === 'not_available')
                    <span class="is-unmapped" aria-hidden="true"><x-dashboard-icon name="users" class="h-6 w-6" /></span>
                    <div><h2 id="talent-detail-title">Pegawai Belum Dipetakan</h2><p>Talent Mapping belum tersedia</p></div>
                @else
                    <span style="--detail-accent: {{ $activeBoxMeta['accent'] }}" aria-hidden="true">
                        <img src="{{ asset('images/talent-matrix/icons/'.$activeBoxMeta['icon']) }}" alt="" width="26" height="26">
                    </span>
                    <div>
                        <h2 id="talent-detail-title">{{ $activeBoxMeta['label'] }}</h2>
                        <p>Potensi {{ $activeBoxMeta['potential'] }} <span aria-hidden="true">&bull;</span> Performa {{ $activeBoxMeta['performance'] }}</p>
                    </div>
                @endif
            </div>
            <strong>{{ number_format($activeCategory === 'not_available' ? $unmappedCount : $talentCounts->get($activeBox, 0), 0, ',', '.') }} pegawai</strong>
        </header>

        @if (! $canViewEmployees)
            <div class="talent-pool-detail__restricted">
                <x-dashboard-icon name="users" class="h-6 w-6" />
                <div><strong>Detail pegawai dibatasi</strong><p>Anda memerlukan permission Data Pegawai untuk melihat identitas talent dan profilnya.</p></div>
            </div>
        @elseif ($activeTalents->isEmpty())
            <x-ui.empty-state
                :title="$activeCategory === 'not_available' ? 'Semua pegawai telah memiliki talent mapping.' : 'Belum ada pegawai pada kategori ini.'"
                :description="$hasActiveFilters ? 'Tidak ada pegawai yang sesuai dengan filter.' : 'Pilih kategori lain untuk melihat talent yang tersedia.'" />
        @else
            <div class="talent-pool-detail__table-wrap">
                <table class="talent-pool-detail__table">
                    <thead>
                        <tr>
                            <th>Pegawai</th>
                            <th>SAP</th>
                            <th>Jabatan</th>
                            <th>Department</th>
                            <th>Direktorat</th>
                            <th>Status</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($activeTalents as $employee)
                            @php
                                $employeeName = trim((string) ($employee->personal_number ?: $employee->name ?: 'Nama belum tersedia'));
                                $employeeInitials = collect(preg_split('/\s+/', $employeeName) ?: [])->filter()->take(2)
                                    ->map(fn (string $word): string => mb_strtoupper(mb_substr($word, 0, 1)))->implode('');
                                $positionName = $employee->organizationPosition?->name ?: $employee->position ?: '—';
                                $departmentName = $employee->organizationDepartment?->name ?: $employee->txt_dept ?: $employee->department ?: '—';
                                $directorateName = $employee->organizationDirectorate?->name ?: $employee->txt_dir ?: $employee->directorate ?: '—';
                                $statusLabel = $activeCategory === 'not_available' ? 'Belum Dipetakan' : ($activeBoxCategory['label'] ?? '—');
                            @endphp
                            <tr>
                                <td data-label="Pegawai">
                                    <span class="talent-pool-employee">
                                        <span aria-hidden="true">{{ $employeeInitials ?: '—' }}</span>
                                        <span><strong>{{ $employeeName }}</strong><small>{{ $employee->name ?: '—' }}</small></span>
                                    </span>
                                </td>
                                <td data-label="SAP"><strong>{{ $employee->sap ?: '—' }}</strong></td>
                                <td data-label="Jabatan">{{ $positionName }}</td>
                                <td data-label="Department">{{ $departmentName }}</td>
                                <td data-label="Direktorat">{{ $directorateName }}</td>
                                <td data-label="Status"><span class="talent-pool-badge {{ $activeCategory === 'not_available' ? 'is-unmapped' : 'is-potential' }}">{{ $statusLabel }}</span></td>
                                <td data-label="Aksi"><a href="{{ route('performance-talent.data-pegawai.show', $employee) }}">Lihat Profil <span aria-hidden="true">&rarr;</span></a></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if ($activeTalents->hasPages())
                <div class="talent-pool-detail__pagination">{{ $activeTalents->onEachSide(1)->links() }}</div>
            @endif
        @endif
    </section>
</div>
