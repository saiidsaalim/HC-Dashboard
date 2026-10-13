@php
    $isCurrentForm = old('_form') === $formKey;
    $initialEmployeeId = $isCurrentForm ? old('employee_id') : $promotion?->employee?->id;
    $value = fn (string $field, mixed $fallback = '') => $isCurrentForm
        ? old($field, $fallback)
        : ($promotion?->{$field} ?? $fallback);
    $initialData = [
        'employeeId' => $initialEmployeeId ? (int) $initialEmployeeId : '',
        'sap' => $promotion?->sap ?? '',
        'nama' => $value('nama'),
        'departemen_lama' => $value('departemen_lama'),
        'jabatan_lama' => $value('jabatan_lama'),
        'departemen_baru' => $value('departemen_baru'),
        'jabatan_baru' => $value('jabatan_baru'),
        'tmt' => $value('tmt', $promotion?->tmt?->format('Y-m-d')),
        'pg' => $value('pg'),
        'band_lama' => $value('band_lama'),
        'jg_lama' => $value('jg_lama'),
        'band_baru' => $value('band_baru'),
        'jg_baru' => $value('jg_baru'),
    ];
@endphp

<form method="POST" action="{{ $action }}"
    x-data="{
        employees: @js($employeeOptions),
        employeeId: @js($initialData['employeeId']),
        sap: @js($initialData['sap']),
        nama: @js($initialData['nama']),
        departemen_lama: @js($initialData['departemen_lama']),
        jabatan_lama: @js($initialData['jabatan_lama']),
        departemen_baru: @js($initialData['departemen_baru']),
        jabatan_baru: @js($initialData['jabatan_baru']),
        tmt: @js($initialData['tmt']),
        pg: @js($initialData['pg']),
        band_lama: @js($initialData['band_lama']),
        jg_lama: @js($initialData['jg_lama']),
        band_baru: @js($initialData['band_baru']),
        jg_baru: @js($initialData['jg_baru']),
        selectEmployee() {
            const employee = this.employees.find((item) => Number(item.id) === Number(this.employeeId));
            if (!employee) return;
            this.sap = employee.sap;
            this.nama = employee.name;
            this.departemen_lama = employee.department;
            this.jabatan_lama = employee.position;
        },
    }"
    class="mt-5 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
    @csrf
    @if ($method !== 'POST')
        @method($method)
    @endif
    <input type="hidden" name="_form" value="{{ $formKey }}">

    <div class="sm:col-span-2 xl:col-span-3">
        <label for="{{ $formKey }}-employee" class="text-sm font-semibold text-slate-700">Employee</label>
        <select id="{{ $formKey }}-employee" name="employee_id" x-model="employeeId" @change="selectEmployee()" required
            class="mt-1 block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-amber-400 focus:ring-amber-400">
            <option value="">Pilih Employee existing</option>
            @foreach ($employeeOptions as $employeeOption)
                <option value="{{ $employeeOption['id'] }}">{{ $employeeOption['label'] }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label for="{{ $formKey }}-sap" class="text-sm font-semibold text-slate-700">SAP</label>
        <input id="{{ $formKey }}-sap" x-model="sap" readonly
            class="mt-1 block w-full rounded-xl border-slate-200 bg-slate-100 text-sm text-slate-600 shadow-sm">
    </div>

    @if ($promotion && !$promotion->employee)
        <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800 sm:col-span-2 xl:col-span-4">
            Data Employee asal tidak lagi tersedia. Informasi Promosi tetap menggunakan snapshot tersimpan.
        </div>
    @endif

    @foreach ([
        'nama' => 'Nama',
        'departemen_lama' => 'Department Lama',
        'jabatan_lama' => 'Jabatan Saat Ini',
        'departemen_baru' => 'Department Baru',
        'jabatan_baru' => 'Jabatan Usulan',
    ] as $field => $label)
        <div>
            <label for="{{ $formKey }}-{{ $field }}" class="text-sm font-semibold text-slate-700">{{ $label }}</label>
            <input id="{{ $formKey }}-{{ $field }}" name="{{ $field }}" x-model="{{ $field }}" required
                class="mt-1 block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-amber-400 focus:ring-amber-400">
        </div>
    @endforeach
    <div>
        <label for="{{ $formKey }}-tmt" class="text-sm font-semibold text-slate-700">TMT</label>
        <input id="{{ $formKey }}-tmt" name="tmt" type="date" x-model="tmt" required
            class="mt-1 block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-amber-400 focus:ring-amber-400">
    </div>

    <details open class="rounded-xl border border-slate-200 bg-slate-50 p-4 sm:col-span-2 xl:col-span-4">
        <summary class="cursor-pointer text-sm font-semibold text-slate-700">Detail PG, BAND, dan JG</summary>
        <div class="mt-4 grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
            @foreach (['pg' => 'PG', 'band_lama' => 'BAND Saat Ini', 'jg_lama' => 'JG Saat Ini', 'band_baru' => 'BAND Usulan', 'jg_baru' => 'JG Usulan'] as $field => $label)
                <div>
                    <label for="{{ $formKey }}-{{ $field }}" class="text-sm font-semibold text-slate-700">{{ $label }}</label>
                    <input id="{{ $formKey }}-{{ $field }}" name="{{ $field }}" x-model="{{ $field }}" required
                        class="mt-1 block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-amber-400 focus:ring-amber-400">
                </div>
            @endforeach
        </div>
    </details>

    <p class="text-xs text-slate-500 sm:col-span-2 xl:col-span-4">
        Koreksi pada form ini hanya berlaku untuk dokumen Promosi dan tidak mengubah Data Pegawai.
    </p>
    <div class="flex justify-end sm:col-span-2 xl:col-span-4">
        <button type="submit"
            class="w-full rounded-xl bg-slate-950 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-slate-700 sm:w-auto">
            {{ $submitLabel }}
        </button>
    </div>
</form>
