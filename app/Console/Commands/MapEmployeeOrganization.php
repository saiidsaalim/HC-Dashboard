<?php

namespace App\Console\Commands;

use App\Models\Employee;
use App\Services\Employees\EmployeeOrganizationMapper;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

#[Signature('employees:map-organization {--dry-run : Report mappings without modifying employees} {--apply : Backfill unambiguous foreign keys} {--chunk=500 : Employees per chunk}')]
#[Description('Report or safely backfill Employee organization foreign keys')]
class MapEmployeeOrganization extends Command
{
    public function handle(EmployeeOrganizationMapper $mapper): int
    {
        if ($this->option('dry-run') && $this->option('apply')) {
            $this->error('Pilih salah satu: --dry-run atau --apply.');

            return self::FAILURE;
        }

        $chunkSize = filter_var($this->option('chunk'), FILTER_VALIDATE_INT);
        if ($chunkSize === false || $chunkSize < 1) {
            $this->error('Nilai --chunk harus berupa integer positif.');

            return self::FAILURE;
        }

        $applying = (bool) $this->option('apply');
        $report = [
            'total' => Employee::query()->count(),
            'mapped' => ['department' => 0, 'unit' => 0, 'position' => 0],
            'unmapped' => ['department' => 0, 'unit' => 0, 'position' => 0],
            'empty' => ['department' => 0, 'unit' => 0, 'position' => 0],
            'conflicts' => 0,
            'updated' => 0,
        ];

        Employee::query()
            ->select([
                'id', 'sap', 'txt_dept', 'organizational_unit', 'position',
                'department_id', 'unit_id', 'position_id',
            ])
            ->chunkById($chunkSize, function (Collection $employees) use ($mapper, $applying, &$report): void {
                $plans = [];

                foreach ($employees as $employee) {
                    $mapping = $mapper->resolve(
                        $employee->txt_dept,
                        $employee->organizational_unit,
                        $employee->position,
                        $employee->department_id,
                        $employee->unit_id,
                        $employee->position_id,
                    );

                    foreach ($mapping['statuses'] as $field => $status) {
                        if ($this->isMapped($status)) {
                            $report['mapped'][$field]++;
                        } elseif ($status === 'unmapped') {
                            $report['unmapped'][$field]++;
                        }

                        $legacyValue = match ($field) {
                            'department' => $employee->txt_dept,
                            'unit' => $employee->organizational_unit,
                            'position' => $employee->position,
                        };

                        if (trim((string) $legacyValue) === '') {
                            $report['empty'][$field]++;
                        }
                    }

                    $unmappedFields = array_keys(array_filter(
                        $mapping['statuses'],
                        fn (string $status): bool => $status === 'unmapped',
                    ));
                    if ($mapping['conflicts'] !== [] || $unmappedFields !== []) {
                        $this->warn(sprintf(
                            'Employee %s (%s): conflicts=[%s], unmapped=[%s].',
                            $employee->id,
                            $employee->sap,
                            implode(',', $mapping['conflicts']),
                            implode(',', $unmappedFields),
                        ));
                    }

                    if ($mapping['conflicts'] !== []) {
                        $report['conflicts']++;

                        continue;
                    }

                    $plans[] = [
                        'employee_id' => $employee->id,
                        'current' => [
                            'department_id' => $employee->department_id,
                            'unit_id' => $employee->unit_id,
                            'position_id' => $employee->position_id,
                        ],
                        'mapped' => $mapping['ids'],
                    ];
                }

                if (! $applying || $plans === []) {
                    return;
                }

                DB::transaction(function () use ($plans, &$report): void {
                    foreach ($plans as $plan) {
                        $changes = [];

                        foreach ($plan['mapped'] as $field => $mappedId) {
                            if ($plan['current'][$field] === null && $mappedId !== null) {
                                $changes[$field] = $mappedId;
                            }
                        }

                        if ($changes === []) {
                            continue;
                        }

                        $query = Employee::query()->whereKey($plan['employee_id']);
                        foreach ($plan['current'] as $field => $currentId) {
                            if ($currentId === null) {
                                $query->whereNull($field);
                            } else {
                                $query->where($field, $currentId);
                            }
                        }

                        $report['updated'] += $query->update($changes);
                    }
                });
            });

        $this->line('Total Employees: '.$report['total']);
        foreach (['department' => 'Department', 'unit' => 'Unit', 'position' => 'Position'] as $field => $label) {
            $this->line("Mapped {$label}: ".$report['mapped'][$field]);
            $this->line("Unmapped {$label}: ".$report['unmapped'][$field]);
            $this->line("Empty {$label} text: ".$report['empty'][$field]);
        }
        $this->line('Conflicts: '.$report['conflicts']);
        $this->line('Mode: '.($applying ? 'apply' : 'dry-run'));
        $this->line('Employee rows updated: '.$report['updated']);

        if (! $applying) {
            $this->comment('Tidak ada data Employee yang diubah. Gunakan --apply hanya setelah meninjau laporan.');
        }

        return self::SUCCESS;
    }

    private function isMapped(string $status): bool
    {
        return in_array($status, ['exact', 'normalized', 'existing'], true)
            || str_starts_with($status, 'inferred_');
    }
}
