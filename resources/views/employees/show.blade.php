@extends('layouts.app')

@section('page-title', 'Profil Pegawai')

@section('content')
    <div class="mb-8 flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
        <div>
            <a href="{{ route('data-pegawai') }}"
                class="mb-4 inline-block text-sm font-semibold text-slate-500 transition hover:text-slate-900">
                Kembali ke Data Pegawai
            </a>
            <h2 class="mt-1 font-display text-3xl font-bold tracking-tight text-slate-950">SAP: {{ $employee->sap }}</h2>
            <p class="mt-1 text-sm text-slate-500">Personal Number: {{ $employee->personal_number ?: '-' }}</p>
        </div>
    </div>

    <div class="space-y-6">
        <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 px-5 py-4 sm:px-6">
                <h3 class="font-display text-lg font-bold text-slate-950">Informasi Organisasi</h3>
            </div>
            <dl class="grid gap-px bg-white sm:grid-cols-2 xl:grid-cols-4">
                @foreach ([
                    'Position ID' => $employee->position_id_source,
                    'Position' => $employee->position,
                    'Employee Subgroup' => $employee->employee_subgroup,
                    'Cost Ctr' => $employee->cost_ctr,
                    'TXT_DIR' => $employee->txt_dir,
                    'TXT_DEPT' => $employee->txt_dept,
                    'TXT_BIRO' => $employee->txt_biro,
                    'TXT_SECT' => $employee->txt_sect,
                    'Personnel Area' => $employee->personnel_area,
                    'abrevation position' => $employee->abrevation_position,
                    'abrevation organization' => $employee->abrevation_organization,
                    'Organizational Unit' => $employee->organizational_unit,
                    'Cost Center' => $employee->cost_center,
                    'Masa Kontrak' => $employee->masa_kontrak?->format('d M Y'),
                    'Organik' => $employee->organilk?->format('d M Y'),
                    'Hiring' => $employee->hiring?->format('d M Y'),
                ] as $label => $value)
                    <div class="bg-white px-5 py-4 sm:px-6">
                        <dt class="text-xs font-medium uppercase tracking-wide text-slate-400">{{ $label }}</dt>
                        <dd class="mt-1 break-words text-sm font-semibold text-slate-700">{{ $value ?: '-' }}</dd>
                    </div>
                @endforeach
            </dl>
        </section>

        <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 px-5 py-4 sm:px-6">
                <h3 class="font-display text-lg font-bold text-slate-950">Data Pribadi</h3>
            </div>
            <dl class="grid gap-px bg-slate-100 sm:grid-cols-2 xl:grid-cols-4">
                @foreach ([
                    'ID Number' => $employee->id_number,
                    'AGKN' => $employee->agkn,
                    'Birth date' => $employee->birth_date?->format('d M Y'),
                    'Gender Key' => $employee->gender_key,
                    'Religious' => $employee->religious,
                    'Usia' => $employee->usia,
                    'Tempat Lahir' => $employee->tempat_lahir,
                    'Pendidikan' => $employee->pendidikan,
                ] as $label => $value)
                    <div class="bg-white px-5 py-4 sm:px-6">
                        <dt class="text-xs font-medium uppercase tracking-wide text-slate-400">{{ $label }}</dt>
                        <dd class="mt-1 break-words text-sm font-semibold text-slate-700">{{ $value ?: '-' }}</dd>
                    </div>
                @endforeach
            </dl>
        </section>

        <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 px-5 py-4 sm:px-6">
                <h3 class="font-display text-lg font-bold text-slate-950">Kontak dan Alamat</h3>
            </div>
            <dl class="grid gap-px bg-slate-100 sm:grid-cols-2">
                <div class="bg-white px-5 py-4 sm:px-6">
                    <dt class="text-xs font-medium uppercase tracking-wide text-slate-400">E-mail</dt>
                    <dd class="mt-1 break-words text-sm font-semibold text-slate-700">
                        @if ($employee->email)
                            <a href="mailto:{{ $employee->email }}" class="text-sky-700 hover:text-sky-900">{{ $employee->email }}</a>
                        @else
                            -
                        @endif
                    </dd>
                </div>
                <div class="bg-white px-5 py-4 sm:col-span-2 sm:px-6">
                    <dt class="text-xs font-medium uppercase tracking-wide text-slate-400">Alamat</dt>
                    <dd class="mt-1 whitespace-pre-line break-words text-sm font-semibold text-slate-700">{{ $employee->alamat ?: '-' }}</dd>
                </div>
            </dl>
        </section>
    </div>
@endsection