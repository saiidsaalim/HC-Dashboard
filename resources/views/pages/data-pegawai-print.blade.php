<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Daftar Seluruh Pegawai</title>
    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            background: #f1f5f9;
            color: #0f172a;
            font-family: Arial, sans-serif;
        }

        .report {
            max-width: 1200px;
            margin: 32px auto;
            padding: 32px;
            background: #fff;
        }

        .report-header {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 24px;
            margin-bottom: 24px;
        }

        .employee-card {
            margin-bottom: 24px;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            padding: 18px;
        }

        .employee-heading {
            display: flex;
            align-items: baseline;
            justify-content: space-between;
            gap: 16px;
            border-bottom: 1px solid #cbd5e1;
            padding-bottom: 10px;
        }

        .employee-heading h2 {
            margin: 0;
            font-size: 17px;
        }

        .field-group {
            margin-top: 14px;
        }

        .field-group h3 {
            margin: 0 0 8px;
            color: #475569;
            font-size: 10px;
            text-transform: uppercase;
        }

        .field-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 8px 14px;
            margin: 0;
        }

        .field dt {
            color: #64748b;
            font-size: 9px;
            text-transform: uppercase;
        }

        .field dd {
            margin: 3px 0 0;
            overflow-wrap: anywhere;
            font-size: 11px;
            font-weight: 600;
            white-space: pre-line;
        }

        h1 {
            margin: 0;
            font-size: 24px;
        }

        .meta {
            margin: 8px 0 0;
            color: #475569;
            font-size: 13px;
        }

        .actions {
            display: flex;
            gap: 8px;
        }

        .actions a,
        .actions button {
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            padding: 9px 12px;
            background: #fff;
            color: #0f172a;
            font-size: 13px;
            font-weight: 600;
            text-decoration: none;
            cursor: pointer;
        }

        .empty {
            padding: 24px;
            color: #64748b;
            text-align: center;
        }

        @page {
            size: landscape;
            margin: 12mm;
        }

        @media print {
            body {
                background: #fff;
            }

            .report {
                max-width: none;
                margin: 0;
                padding: 0;
            }

            .no-print {
                display: none !important;
            }

            .employee-card {
                margin-bottom: 12px;
                border-radius: 0;
                padding: 12px;
            }
        }
    </style>
</head>

<body>
    <main class="report">
        <header class="report-header">
            <div>
                <h1>Daftar Seluruh Pegawai</h1>
                <p class="meta">Total: {{ number_format($employees->count(), 0, ',', '.') }} pegawai</p>
                <p class="meta">Dicetak: {{ $printedAt->translatedFormat('d F Y, H:i') }}</p>
            </div>
            <div class="actions no-print">
                <a href="{{ route('data-pegawai') }}">Kembali</a>
                <button type="button" onclick="window.print()">Cetak / Simpan PDF</button>
            </div>
        </header>

        @forelse ($employees as $employee)
            <article class="employee-card">
                <header class="employee-heading">
                    <h2>SAP: {{ $employee->sap }}</h2>
                </header>
                @foreach ($fieldGroups as $group => $fields)
                    <section class="field-group">
                        <h3>{{ $group }}</h3>
                        <dl class="field-grid">
                            @foreach ($fields as $label => $attribute)
                                @continue($attribute === 'sap')
                                @php($value = match ($attribute) {
                                    'department' => $employee->organizationDepartmentName(),
                                    'organizational_unit' => $employee->organizationUnitName(),
                                    'position' => $employee->organizationPositionName(),
                                    default => $employee->getAttribute($attribute),
                                })
                                <div class="field">
                                    <dt>{{ $label }}</dt>
                                    <dd>{{ $value instanceof \DateTimeInterface ? $value->format('d M Y') : ($value === null || $value === '' ? '-' : $value) }}</dd>
                                </div>
                            @endforeach
                        </dl>
                    </section>
                @endforeach
            </article>
        @empty
            <p class="empty">Belum ada data pegawai.</p>
        @endforelse
    </main>
    @if (request()->boolean('autoprint'))
        <script>
            window.addEventListener('load', () => window.print());
        </script>
    @endif
</body>

</html>