@extends('layouts.app')

@section('page-title', 'Profil Pegawai')

@section('content')
    @php
        $employeeName = trim((string) ($employee->personal_number ?: $employee->name));
        $employeeInitials = collect(preg_split('/\s+/', $employeeName) ?: [])
            ->filter()
            ->take(2)
            ->map(fn (string $namePart): string => mb_strtoupper(mb_substr($namePart, 0, 1)))
            ->implode('');
        $positionName = $employee->position ?: $employee->organizationPosition?->name;
        $departmentName = $employee->organizationDepartment?->name ?: $employee->txt_dept;
        $unitName = $employee->unit?->name ?: $employee->txt_biro;
        $criticalityValue = trim((string) $employee->criticality);
        $formationStatusValue = trim((string) $employee->formation_status);
        $successionReadinessValue = trim((string) $employee->succession_readiness);
        $idpIsNumeric = $employee->idp_percentage !== null && is_numeric($employee->idp_percentage);
        $idpProgress = $idpIsNumeric ? max(0, min(100, (float) $employee->idp_percentage)) : 0;
        $idpDisplay = $idpIsNumeric ? number_format((float) $employee->idp_percentage, 2, ',', '.').'%' : '—';
        $criticalityClasses = match (strtolower($criticalityValue)) {
            'high' => 'border-rose-200 bg-rose-50 text-rose-700',
            'medium' => 'border-amber-200 bg-amber-50 text-amber-700',
            'low' => 'border-emerald-200 bg-emerald-50 text-emerald-700',
            default => 'border-slate-200 bg-slate-50 text-slate-700',
        };
        $formationStatusClasses = match (strtolower($formationStatusValue)) {
            'covered' => 'border-emerald-200 bg-emerald-50 text-emerald-700',
            'vacant' => 'border-rose-200 bg-rose-50 text-rose-700',
            default => 'border-slate-200 bg-slate-50 text-slate-700',
        };
        $organizationFields = [
            ['label' => 'Position ID', 'value' => $employee->position_id_source, 'icon' => 'tag'],
            ['label' => 'Position', 'value' => $employee->position, 'icon' => 'briefcase'],
            ['label' => 'Employee Subgroup', 'value' => $employee->employee_subgroup, 'icon' => 'users'],
            ['label' => 'Cost CTR', 'value' => $employee->cost_ctr, 'icon' => 'database'],
            ['label' => 'TXT_DIR', 'value' => $employee->txt_dir, 'icon' => 'building'],
            ['label' => 'TXT_DEPT', 'value' => $employee->txt_dept, 'icon' => 'building'],
            ['label' => 'TXT_BIRO', 'value' => $employee->txt_biro, 'icon' => 'hierarchy'],
            ['label' => 'TXT_SECT', 'value' => $employee->txt_sect, 'icon' => 'layers'],
            ['label' => 'Personnel Area', 'value' => $employee->personnel_area, 'icon' => 'pin'],
            ['label' => 'Abbreviation Position', 'value' => $employee->abrevation_position, 'icon' => 'id'],
            ['label' => 'Abbreviation Organization', 'value' => $employee->abrevation_organization, 'icon' => 'hierarchy'],
            ['label' => 'Organizational Unit', 'value' => $employee->organizational_unit, 'icon' => 'hierarchy'],
            ['label' => 'Cost Center', 'value' => $employee->cost_center, 'icon' => 'database'],
            ['label' => 'Masa Kontrak', 'value' => $employee->masa_kontrak?->format('d M Y'), 'icon' => 'calendar'],
            ['label' => 'Organik', 'value' => $employee->organilk?->format('d M Y'), 'icon' => 'leaf'],
            ['label' => 'Hiring', 'value' => $employee->hiring?->format('d M Y'), 'icon' => 'calendar'],
        ];
        $personalFields = [
            ['label' => 'ID Number', 'value' => $employee->id_number, 'icon' => 'id'],
            ['label' => 'AGKN', 'value' => $employee->agkn, 'icon' => 'grid'],
            ['label' => 'Birth Date', 'value' => $employee->birth_date?->format('d M Y'), 'icon' => 'calendar'],
            ['label' => 'Gender Key', 'value' => $employee->gender_key, 'icon' => 'gender'],
            ['label' => 'Religious', 'value' => $employee->religious, 'icon' => 'moon'],
            ['label' => 'Usia', 'value' => $employee->usia, 'icon' => 'users'],
            ['label' => 'Tempat Lahir', 'value' => $employee->tempat_lahir, 'icon' => 'pin'],
            ['label' => 'Pendidikan', 'value' => $employee->pendidikan, 'icon' => 'education'],
        ];
    @endphp

    <div class="space-y-5 sm:space-y-6">
        <a href="{{ route($employeeIndexRoute) }}"
            class="inline-flex min-h-9 w-fit items-center gap-2 rounded-lg border border-[#0B1F3A] bg-white px-3.5 py-2 text-sm font-bold text-[#0B1F3A] shadow-sm transition hover:bg-[#0B1F3A] hover:text-white focus:outline-none focus:ring-2 focus:ring-sky-400 focus:ring-offset-2"
            aria-label="Kembali ke Data Pegawai">
            <svg class="h-4 w-4 shrink-0" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                <path d="M16 10H4m5-5-5 5 5 5" stroke-linecap="round" stroke-linejoin="round" />
            </svg>
            <span>Kembali ke Data Pegawai</span>
        </a>

        <section class="relative isolate overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="pointer-events-none absolute inset-y-0 right-0 hidden w-[28rem] overflow-hidden lg:block" aria-hidden="true">
                <div class="absolute -right-64 -top-[29rem] h-[43rem] w-[43rem] rounded-full bg-[#0B1F3A]"></div>
                <div class="absolute -right-52 -top-[30rem] h-[40rem] w-[40rem] rounded-full border-[1.35rem] border-[#087FC3]"></div>
                <div class="absolute -right-40 -top-[31rem] h-[37rem] w-[37rem] rounded-full border-[1.35rem] border-sky-100"></div>
            </div>

            <div class="relative flex min-h-[9.5rem] min-w-0 flex-col gap-5 px-6 py-5 sm:flex-row sm:items-center lg:pr-[28rem]">
                <div class="flex h-20 w-20 shrink-0 items-center justify-center rounded-full bg-sky-100 font-display text-2xl font-bold text-[#0B1F3A] shadow-md sm:h-24 sm:w-24 sm:text-3xl">
                    @if ($employeeInitials !== '')
                        <span aria-hidden="true">{{ $employeeInitials }}</span>
                    @else
                        <svg class="h-10 w-10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true">
                            <path d="M20 21a8 8 0 0 0-16 0" stroke-linecap="round" />
                            <circle cx="12" cy="7" r="4" />
                        </svg>
                    @endif
                </div>

                <div class="hidden h-24 w-px shrink-0 bg-slate-200 sm:block" aria-hidden="true"></div>

                <div class="min-w-0 flex-1">
                    <p class="text-xs font-bold uppercase tracking-[0.2em] text-sky-700">Profil Pegawai</p>
                    <h2 class="mt-2 break-words font-display text-2xl font-bold tracking-tight text-slate-950 sm:text-3xl">
                        {{ $employeeName !== '' ? $employeeName : 'Nama belum tersedia' }}
                    </h2>

                    <div class="mt-3 flex flex-wrap items-center gap-x-4 gap-y-2 text-sm text-slate-700">
                        <span class="inline-flex max-w-full items-center gap-2 rounded-full border border-sky-200 bg-sky-50 px-3 py-1.5 font-bold text-sky-800">
                            <span class="text-[10px] uppercase tracking-wider text-sky-600">SAP</span>
                            <span class="break-all">{{ filled($employee->sap) ? $employee->sap : '—' }}</span>
                        </span>
                        <span class="hidden h-6 w-px bg-slate-200 sm:block" aria-hidden="true"></span>
                        <span class="inline-flex min-w-0 items-center gap-1.5 break-words">
                            <svg class="h-4 w-4 shrink-0 text-sky-600" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true">
                                <path d="M3 17h14M5 17V6l5-3 5 3v11M8 8h4M8 11h4M8 14h4" stroke-linecap="round" stroke-linejoin="round" />
                            </svg>
                            {{ filled($positionName) ? $positionName : '—' }}
                        </span>
                        <span class="hidden h-6 w-px bg-slate-200 sm:block" aria-hidden="true"></span>
                        <span class="inline-flex min-w-0 items-center gap-1.5 break-words">
                            <svg class="h-4 w-4 shrink-0 text-sky-600" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true">
                                <path d="M4 16V5.5L10 3l6 2.5V16M2.5 16h15M7 7h.01M10 7h.01M13 7h.01M7 10h.01M10 10h.01M13 10h.01" stroke-linecap="round" stroke-linejoin="round" />
                            </svg>
                            {{ filled($departmentName) ? $departmentName : (filled($unitName) ? $unitName : '—') }}
                        </span>
                        <span class="hidden h-6 w-px bg-slate-200 sm:block" aria-hidden="true"></span>
                        <span class="inline-flex min-w-0 items-center gap-1.5 break-words font-semibold text-slate-700">
                            <svg class="h-4 w-4 shrink-0 text-sky-600" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true">
                                <path d="M5 4h10v12H5zM8 7h4M8 10h4M8 13h2" stroke-linecap="round" stroke-linejoin="round" />
                            </svg>
                            {{ filled($employee->employee_subgroup) ? $employee->employee_subgroup : '—' }}
                        </span>
                    </div>
                </div>
            </div>
        </section>

        @if ($showPerformanceTalentFields)
            <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white px-4 py-4 shadow-sm sm:px-5">
                <div class="mb-3">
                    <div class="flex items-start gap-3">
                        <span class="inline-flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-sky-100 text-[#0B1F3A]">
                            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                <path d="M4 19V9m6 10V5m6 14v-7m4 7H2" stroke-linecap="round" stroke-linejoin="round" />
                            </svg>
                        </span>
                        <div class="min-w-0">
                            <h3 class="font-display text-lg font-bold text-slate-950">Performance &amp; Talent</h3>
                            <p class="mt-1 text-sm leading-6 text-slate-500">Informasi succession planning dan rencana pengembangan pegawai.</p>
                        </div>
                    </div>
                </div>

                <dl class="grid items-stretch gap-3 sm:auto-rows-fr sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5">
                    <div class="flex min-h-24 min-w-0 items-center gap-3 rounded-xl border px-4 py-3 {{ $criticalityClasses }}">
                        <span class="inline-flex h-14 w-14 shrink-0 items-center justify-center rounded-xl bg-rose-100 text-rose-600">
                            <svg class="h-7 w-7" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                <path d="M10 3 2.8 16h14.4L10 3Z" stroke-linejoin="round" /><path d="M10 7.5v3.8m0 2.2h.01" stroke-linecap="round" />
                            </svg>
                        </span>
                        <div class="min-w-0">
                            <dt class="text-[11px] font-bold uppercase tracking-[0.08em] text-slate-500">Criticality</dt>
                            <dd class="mt-1 break-words text-xl font-bold text-slate-950">{{ $criticalityValue !== '' ? ucfirst($criticalityValue) : '—' }}</dd>
                        </div>
                    </div>

                    <div class="flex min-h-24 min-w-0 items-center gap-3 rounded-xl border px-4 py-3 {{ $formationStatusClasses }}">
                        <span class="inline-flex h-14 w-14 shrink-0 items-center justify-center rounded-xl bg-emerald-100 text-emerald-600">
                            <svg class="h-7 w-7" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                <circle cx="10" cy="10" r="7" /><path d="m6.8 10 2.1 2.1 4.5-4.5" stroke-linecap="round" stroke-linejoin="round" />
                            </svg>
                        </span>
                        <div class="min-w-0">
                            <dt class="text-[11px] font-bold uppercase tracking-[0.08em] text-slate-500">Status Formasi</dt>
                            <dd class="mt-1 break-words text-xl font-bold text-slate-950">{{ $formationStatusValue !== '' ? ucfirst($formationStatusValue) : '—' }}</dd>
                        </div>
                    </div>

                    <div class="flex min-h-24 min-w-0 items-center gap-3 rounded-xl border border-sky-200 bg-sky-50 px-4 py-3">
                        <span class="inline-flex h-14 w-14 shrink-0 items-center justify-center rounded-xl bg-sky-100 text-sky-700">
                            <svg class="h-7 w-7" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                <rect x="3" y="3" width="5" height="5" rx="1" /><rect x="12" y="3" width="5" height="5" rx="1" /><rect x="3" y="12" width="5" height="5" rx="1" /><rect x="12" y="12" width="5" height="5" rx="1" />
                            </svg>
                        </span>
                        <div class="min-w-0">
                            <dt class="text-[11px] font-bold uppercase tracking-[0.08em] text-slate-500">Talent Mapping</dt>
                            <dd class="mt-1 break-words text-xl font-bold text-sky-800">{{ $employee->talent_mapping ?? '—' }}</dd>
                        </div>
                    </div>

                    <div class="flex min-h-24 min-w-0 items-center gap-3 rounded-xl border border-violet-200 bg-violet-50 px-4 py-3">
                        <span class="inline-flex h-14 w-14 shrink-0 items-center justify-center rounded-xl bg-violet-100 text-violet-700">
                            <svg class="h-7 w-7" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                <path d="M4 16V8m6 8V4m6 12v-5" stroke-linecap="round" /><path d="m3 5 4-2 4 2 5-3" stroke-linecap="round" stroke-linejoin="round" />
                            </svg>
                        </span>
                        <div class="min-w-0">
                            <dt class="text-[11px] font-bold uppercase tracking-[0.08em] text-slate-500">Succession Readiness</dt>
                            <dd class="mt-1 break-words text-lg font-bold text-violet-700">{{ $successionReadinessValue !== '' ? ucfirst($successionReadinessValue) : '—' }}</dd>
                        </div>
                    </div>

                    <div class="flex min-h-24 min-w-0 items-center gap-3 rounded-xl border border-blue-200 bg-blue-50 px-4 py-3">
                        <span class="inline-flex h-14 w-14 shrink-0 items-center justify-center rounded-xl bg-blue-100 text-blue-700">
                            <svg class="h-7 w-7" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                <circle cx="6" cy="6" r="2" /><circle cx="14" cy="14" r="2" /><path d="m5 15 10-10" stroke-linecap="round" />
                            </svg>
                        </span>
                        <div class="min-w-0 flex-1">
                            <dt class="text-[11px] font-bold uppercase tracking-[0.08em] text-slate-500">IDP (%)</dt>
                            <dd class="mt-1 text-xl font-bold text-slate-950">{{ $idpDisplay }}</dd>
                            <div class="mt-2 h-2 overflow-hidden rounded-full bg-blue-100" role="progressbar" aria-label="Progres IDP"
                                aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $idpProgress }}">
                                <div class="h-full rounded-full bg-[#087FC3] transition-[width] duration-300" style="width: {{ $idpProgress }}%"></div>
                            </div>
                        </div>
                    </div>
                </dl>
            </section>
        @endif

        <div class="grid min-w-0 items-stretch gap-4 xl:grid-cols-5">
            <section class="flex h-full min-w-0 flex-col overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm xl:col-span-3">
                <div class="px-5 pt-3 sm:px-6">
                    <div class="flex items-center gap-3">
                        <span class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-[#0B1F3A] text-white shadow-sm">
                            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                <path d="M4 21V7l8-4 8 4v14M2 21h20M8 9h.01M12 9h.01M16 9h.01M8 13h.01M12 13h.01M16 13h.01M10 21v-4h4v4" stroke-linecap="round" stroke-linejoin="round" />
                            </svg>
                        </span>
                        <h3 class="font-display text-xl font-bold text-slate-950">Informasi Organisasi</h3>
                    </div>
                    <div class="mt-2 border-b border-slate-200"></div>
                </div>

                <dl class="grid min-w-0 flex-1 sm:auto-rows-fr sm:grid-cols-2">
                    @foreach ($organizationFields as $field)
                        <div class="flex min-h-[3.15rem] min-w-0 items-center gap-3 border-b border-slate-100 px-5 py-2 last:border-b-0 sm:px-6 sm:[&:nth-child(odd)]:border-r sm:[&:nth-last-child(-n+2)]:border-b-0">
                            <span class="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-slate-100 text-slate-600">
                                <svg class="h-4 w-4" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true">
                                    @switch($field['icon'])
                                        @case('tag')
                                            <path d="M3 10.5 10.5 3H16v5.5L8.5 16 3 10.5Z" stroke-linejoin="round" /><circle cx="13.2" cy="5.8" r=".8" />
                                            @break
                                        @case('briefcase')
                                            <path d="M3 7h14v9H3zM7 7V4h6v3M3 10h14" stroke-linecap="round" stroke-linejoin="round" />
                                            @break
                                        @case('users')
                                            <circle cx="8" cy="7" r="2.5" /><path d="M3.5 16a4.5 4.5 0 0 1 9 0M13 6.5a2.2 2.2 0 0 1 0 4.2M14 12a4 4 0 0 1 3 4" stroke-linecap="round" />
                                            @break
                                        @case('database')
                                            <ellipse cx="10" cy="5" rx="6" ry="2.5" /><path d="M4 5v5c0 1.4 2.7 2.5 6 2.5s6-1.1 6-2.5V5M4 10v5c0 1.4 2.7 2.5 6 2.5s6-1.1 6-2.5v-5" />
                                            @break
                                        @case('building')
                                            <path d="M4 17V5l6-2 6 2v12M2.5 17h15M7 7h.01M10 7h.01M13 7h.01M7 10h.01M10 10h.01M13 10h.01M8 17v-4h4v4" stroke-linecap="round" stroke-linejoin="round" />
                                            @break
                                        @case('layers')
                                            <path d="m3 7 7-4 7 4-7 4-7-4Zm0 3.5 7 4 7-4M3 14l7 4 7-4" stroke-linecap="round" stroke-linejoin="round" />
                                            @break
                                        @case('pin')
                                            <path d="M16 8c0 4.5-6 9-6 9S4 12.5 4 8a6 6 0 1 1 12 0Z" /><circle cx="10" cy="8" r="2" />
                                            @break
                                        @case('id')
                                            <rect x="3" y="5" width="14" height="10" rx="2" /><circle cx="7" cy="9" r="1.5" /><path d="M5 13a2.2 2.2 0 0 1 4 0M11 8h4m-4 3h4" stroke-linecap="round" />
                                            @break
                                        @case('calendar')
                                            <rect x="3" y="4.5" width="14" height="13" rx="2" /><path d="M6 2.5v4m8-4v4M3 8h14" stroke-linecap="round" />
                                            @break
                                        @case('leaf')
                                            <path d="M16.5 3.5C10 3.5 5 6.5 5 11c0 2.2 1.8 4 4 4 4.5 0 7.5-5 7.5-11.5ZM4 17c2.5-4.5 5.5-7 9-9" stroke-linecap="round" stroke-linejoin="round" />
                                            @break
                                        @default
                                            <path d="M4 16V5.5L10 3l6 2.5V16M2.5 16h15" stroke-linecap="round" stroke-linejoin="round" />
                                    @endswitch
                                </svg>
                            </span>
                            <div class="min-w-0">
                                <dt class="text-[10px] font-bold uppercase tracking-[0.08em] text-slate-500">{{ $field['label'] }}</dt>
                                <dd class="break-words text-sm font-semibold leading-5 text-slate-900">{{ filled($field['value']) ? $field['value'] : '—' }}</dd>
                            </div>
                        </div>
                    @endforeach
                </dl>
            </section>

            <div class="grid min-w-0 gap-4 xl:col-span-2 xl:h-full xl:grid-rows-[1.2fr_0.8fr]">
                <section class="flex min-w-0 flex-col overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                    <div class="px-5 pt-3 sm:px-6">
                        <div class="flex items-center gap-3">
                            <span class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-[#0B1F3A] text-white shadow-sm">
                                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                    <circle cx="12" cy="8" r="4" /><path d="M4.5 21a7.5 7.5 0 0 1 15 0" stroke-linecap="round" />
                                </svg>
                            </span>
                            <h3 class="font-display text-xl font-bold text-slate-950">Data Pribadi</h3>
                        </div>
                        <div class="mt-2 border-b border-slate-200"></div>
                    </div>

                    <dl class="grid min-w-0 flex-1 sm:auto-rows-fr sm:grid-cols-2 xl:grid-cols-2">
                        @foreach ($personalFields as $field)
                            <div class="flex min-h-[3.15rem] min-w-0 items-center gap-3 border-b border-slate-100 px-5 py-2 last:border-b-0 sm:[&:nth-child(odd)]:border-r sm:[&:nth-last-child(-n+2)]:border-b-0 sm:px-6">
                                <span class="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-slate-100 text-slate-600">
                                    <svg class="h-4 w-4" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true">
                                        @switch($field['icon'])
                                            @case('id')
                                                <rect x="3" y="5" width="14" height="10" rx="2" /><circle cx="7" cy="9" r="1.5" /><path d="M5 13a2.2 2.2 0 0 1 4 0M11 8h4m-4 3h4" stroke-linecap="round" />
                                                @break
                                            @case('grid')
                                                <rect x="3" y="3" width="5" height="5" rx=".5" /><rect x="12" y="3" width="5" height="5" rx=".5" /><rect x="3" y="12" width="5" height="5" rx=".5" /><rect x="12" y="12" width="5" height="5" rx=".5" />
                                                @break
                                            @case('calendar')
                                                <rect x="3" y="4.5" width="14" height="13" rx="2" /><path d="M6 2.5v4m8-4v4M3 8h14" stroke-linecap="round" />
                                                @break
                                            @case('gender')
                                                <circle cx="8" cy="11" r="4" /><path d="m11 8 5-5m-4 0h4v4" stroke-linecap="round" stroke-linejoin="round" />
                                                @break
                                            @case('moon')
                                                <path d="M16 13.5A7 7 0 0 1 6.5 4 7 7 0 1 0 16 13.5Z" stroke-linejoin="round" />
                                                @break
                                            @case('users')
                                                <circle cx="8" cy="7" r="2.5" /><path d="M3.5 16a4.5 4.5 0 0 1 9 0M13 6.5a2.2 2.2 0 0 1 0 4.2M14 12a4 4 0 0 1 3 4" stroke-linecap="round" />
                                                @break
                                            @case('pin')
                                                <path d="M16 8c0 4.5-6 9-6 9S4 12.5 4 8a6 6 0 1 1 12 0Z" /><circle cx="10" cy="8" r="2" />
                                                @break
                                            @case('education')
                                                <path d="m2.5 7 7.5-4 7.5 4-7.5 4-7.5-4Z" stroke-linejoin="round" /><path d="M5 9.5V14c3 2 7 2 10 0V9.5M17.5 7v5" stroke-linecap="round" />
                                                @break
                                        @endswitch
                                    </svg>
                                </span>
                                <div class="min-w-0">
                                    <dt class="text-[10px] font-bold uppercase tracking-[0.08em] text-slate-500">{{ $field['label'] }}</dt>
                                    <dd class="break-words text-sm font-semibold leading-5 text-slate-900">{{ filled($field['value']) ? $field['value'] : '—' }}</dd>
                                </div>
                            </div>
                        @endforeach
                    </dl>
                </section>

                <section class="flex min-w-0 flex-col overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                    <div class="px-5 pt-3 sm:px-6">
                        <div class="flex items-center gap-3">
                            <span class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-[#0B1F3A] text-white shadow-sm">
                                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                    <path d="M4 5h16v14H4z" stroke-linejoin="round" /><path d="m4 7 8 6 8-6" stroke-linecap="round" stroke-linejoin="round" />
                                </svg>
                            </span>
                            <h3 class="font-display text-xl font-bold text-slate-950">Kontak dan Alamat</h3>
                        </div>
                        <div class="mt-2 border-b border-slate-200"></div>
                    </div>

                    <dl class="grid flex-1 grid-rows-2 divide-y divide-slate-100">
                        <div class="flex min-w-0 items-center gap-3 px-5 py-3 sm:px-6">
                            <span class="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-slate-100 text-slate-600">
                                <svg class="h-4 w-4" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true">
                                    <rect x="2.5" y="4" width="15" height="12" rx="2" /><path d="m3 6 7 5 7-5" stroke-linecap="round" stroke-linejoin="round" />
                                </svg>
                            </span>
                            <div class="min-w-0">
                                <dt class="text-[10px] font-bold uppercase tracking-[0.08em] text-slate-500">E-mail</dt>
                                <dd class="min-w-0 break-words text-sm font-semibold leading-5">
                                    @if (filled($employee->email))
                                        <a href="mailto:{{ $employee->email }}" class="break-all text-sky-700 transition hover:text-sky-900 hover:underline focus:rounded focus:outline-none focus:ring-2 focus:ring-sky-400">
                                            {{ $employee->email }}
                                        </a>
                                    @else
                                        <span class="text-slate-800">—</span>
                                    @endif
                                </dd>
                            </div>
                        </div>
                        <div class="flex min-w-0 items-center gap-3 px-5 py-3 sm:px-6">
                            <span class="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-slate-100 text-slate-600">
                                <svg class="h-4 w-4" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true">
                                    <path d="M16 8c0 4.5-6 9-6 9S4 12.5 4 8a6 6 0 1 1 12 0Z" /><circle cx="10" cy="8" r="2" />
                                </svg>
                            </span>
                            <div class="min-w-0">
                                <dt class="text-[10px] font-bold uppercase tracking-[0.08em] text-slate-500">Alamat</dt>
                                <dd class="whitespace-pre-line break-words text-sm font-semibold leading-5 text-slate-900">{{ filled($employee->alamat) ? $employee->alamat : '—' }}</dd>
                            </div>
                        </div>
                    </dl>
                </section>
            </div>
        </div>
    </div>
@endsection
