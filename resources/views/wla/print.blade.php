<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Cetak WLA Final {{ data_get($snapshot, 'assessment_code') }}</title>
    <style>
        @page { size: A4 portrait; margin: 25mm 1mm; }
        * { box-sizing: border-box; }
        body { margin: 0; color: #111827; font-family: Arial, sans-serif; font-size: 10px; }
        .toolbar { display: flex; justify-content: flex-end; gap: 8px; margin: 12px auto; max-width: 205mm; }
        .toolbar button { border: 0; border-radius: 5px; background: #047857; color: white; cursor: pointer; padding: 9px 14px; font-weight: 700; }
        .sheet { width: 205mm; margin: 0 auto; }
        h1 { margin: 0 0 10px; text-align: center; font-size: 16px; }
        table { width: 100%; border-collapse: collapse; table-layout: fixed; }
        th, td { border: 1px solid #111827; padding: 4px; vertical-align: top; overflow-wrap: anywhere; }
        th { background: #d9eaf7; text-align: center; }
        .label { width: 22%; background: #f3f4f6; font-weight: 700; }
        .identity td { height: 22px; }
        .section-title { margin: 12px 0 4px; font-weight: 700; }
        .activity-number { width: 5%; text-align: center; }
        .activity-name { width: 43%; }
        .numeric { text-align: right; white-space: nowrap; }
        .summary { margin-top: 10px; }
        .summary .value { width: 28%; text-align: right; font-weight: 700; }
        .frozen { margin-top: 10px; text-align: center; font-size: 9px; font-weight: 700; }
        @media print {
            .no-print { display: none !important; }
            .sheet { width: 100%; }
            thead { display: table-header-group; }
            tr { break-inside: avoid; }
        }
    </style>
</head>
<body>
    <div class="toolbar no-print">
        <button type="button" onclick="window.print()">Cetak / Save as PDF</button>
    </div>

    <main class="sheet">
        <h1>ANALISIS BEBAN KERJA (WORK LOAD ANALYSIS)</h1>

        <table class="identity">
            <tbody>
                <tr><td class="label">Kode Assessment</td><td>{{ data_get($snapshot, 'assessment_code') }}</td><td class="label">Periode</td><td>{{ data_get($snapshot, 'period') }}</td></tr>
                <tr><td class="label">Departemen</td><td colspan="3">{{ data_get($snapshot, 'department.code') }} - {{ data_get($snapshot, 'department.name') }}</td></tr>
                <tr><td class="label">Unit</td><td colspan="3">{{ data_get($snapshot, 'unit.code') }} - {{ data_get($snapshot, 'unit.name') }}</td></tr>
                <tr><td class="label">Posisi</td><td colspan="3">{{ data_get($snapshot, 'position.code') }} - {{ data_get($snapshot, 'position.name') }}</td></tr>
                <tr><td class="label">Jadwal Kerja</td><td>{{ data_get($snapshot, 'schedule.code') }} - {{ data_get($snapshot, 'schedule.name') }}</td><td class="label">Kelompok / Jam Harian WLA</td><td>{{ data_get($snapshot, 'schedule.calculation_type_label') }} / {{ data_get($snapshot, 'schedule.wla_hours_per_day') }} jam</td></tr>
            </tbody>
        </table>

        <p class="section-title">Kalender dan Waktu Kerja Efektif</p>
        <table>
            <thead><tr><th>Total Hari</th><th>Cuti Tahunan</th><th>Libur Nasional</th><th>Cuti Bersama</th><th>Sabtu</th><th>Minggu</th><th>Hari Kerja</th></tr></thead>
            <tbody><tr class="numeric"><td>{{ data_get($snapshot, 'calendar.total_days') }}</td><td>{{ data_get($snapshot, 'calendar.annual_leave') }}</td><td>{{ data_get($snapshot, 'calendar.national_holiday') }}</td><td>{{ data_get($snapshot, 'calendar.common_leave') }}</td><td>{{ data_get($snapshot, 'calendar.saturday_days') }}</td><td>{{ data_get($snapshot, 'calendar.sunday_days') }}</td><td>{{ data_get($snapshot, 'working_days') }}</td></tr></tbody>
        </table>
        <table>
            <tbody>
                <tr><td class="label">Faktor Efisiensi</td><td class="numeric">{{ data_get($snapshot, 'efficiency_factor') }}</td><td class="label">Jam Kerja Tahunan</td><td class="numeric">{{ data_get($snapshot, 'annual_working_hours') }}</td><td class="label">Jam Kerja Efektif Tahunan</td><td class="numeric">{{ data_get($snapshot, 'effective_annual_working_hours') }}</td></tr>
            </tbody>
        </table>

        <p class="section-title">Aktivitas</p>
        <table>
            <thead>
                <tr><th class="activity-number">No</th><th class="activity-name">Nama Aktivitas</th><th>Frekuensi</th><th>Periode</th><th>Waktu per Aktivitas (Jam)</th><th>Beban Tahunan (Jam)</th></tr>
            </thead>
            <tbody>
                @foreach (data_get($snapshot, 'activities', []) as $activity)
                    <tr>
                        <td class="activity-number">{{ $loop->iteration }}</td>
                        <td>{{ data_get($activity, 'activity_name') }}</td>
                        <td class="numeric">{{ data_get($activity, 'frequency') }}</td>
                        <td>{{ match (data_get($activity, 'period_unit')) { 'Day' => 'Hari', 'Week' => 'Minggu', 'Month' => 'Bulan', 'Year' => 'Tahun', default => data_get($activity, 'period_unit') } }}</td>
                        <td class="numeric">{{ data_get($activity, 'time_allocated_hours') }}</td>
                        <td class="numeric">{{ data_get($activity, 'annual_workload_hours') }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <table class="summary">
            <tbody>
                <tr><td class="label">Total Beban Kerja Tahunan</td><td class="value">{{ data_get($snapshot, 'total_annual_workload') }} jam</td></tr>
                <tr><td class="label">FTE</td><td class="value">{{ data_get($snapshot, 'fte') }}</td></tr>
                <tr><td class="label">Rekomendasi Jumlah Pegawai</td><td class="value">{{ data_get($snapshot, 'recommended_employees') }}</td></tr>
                <tr><td class="label">Difinalisasi Oleh</td><td>{{ data_get($snapshot, 'finalizer.name') }} (ID {{ data_get($snapshot, 'finalizer.id') }})</td></tr>
                <tr><td class="label">Waktu Finalisasi</td><td>{{ data_get($snapshot, 'finalized_at') }}</td></tr>
            </tbody>
        </table>
        <p class="frozen">WLA FINAL - HASIL DAN KONTEKS TELAH DIBEKUKAN</p>
    </main>
</body>
</html>
