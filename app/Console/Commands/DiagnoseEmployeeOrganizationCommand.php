<?php

namespace App\Console\Commands;

use App\Models\Employee;
use App\Services\Employees\EmployeeOrganizationMapper;
use Illuminate\Console\Command;

class DiagnoseEmployeeOrganizationCommand extends Command
{
    protected $signature = 'employees:diagnose-organization {--export-mapping-template : Export a CSV template for manual mapping review}';

    protected $description = 'Diagnose how legacy Employee organization values match the Organization master, without modifying data.';

    public function handle(EmployeeOrganizationMapper $mapper): int
    {
        $employees = Employee::query()->select(['id', 'sap', 'txt_dept', 'organizational_unit', 'position'])->get();
        $counts = [
            'department' => ['exact' => 0, 'normalized' => 0, 'unmatched' => 0, 'conflict' => 0],
            'unit' => ['exact' => 0, 'normalized' => 0, 'unmatched' => 0, 'conflict' => 0],
            'position' => ['exact' => 0, 'normalized' => 0, 'unmatched' => 0, 'conflict' => 0],
        ];
        $unmatchedRecords = [];

        $this->line('========================================');
        $this->line('EMPLOYEE ORGANIZATION MAPPING DIAGNOSTIC');
        $this->line('========================================');
        $this->line('Employees: '.$employees->count());
        $this->line('');

        foreach ($employees as $employee) {
            $mapped = $mapper->resolve(
                $employee->txt_dept,
                $employee->organizational_unit,
                $employee->position,
                null,
                null,
                null,
            );

            foreach (['department', 'unit', 'position'] as $field) {
                $status = $mapped['statuses'][$field] ?? 'unmapped';
                if ($status === 'exact') {
                    $counts[$field]['exact']++;
                } elseif ($status === 'normalized') {
                    $counts[$field]['normalized']++;
                } elseif ($status === 'unmapped') {
                    $counts[$field]['unmatched']++;
                } elseif ($status === 'conflict') {
                    $counts[$field]['conflict']++;
                }
            }

            if ($mapped['statuses']['department'] === 'unmapped' || $mapped['statuses']['unit'] === 'unmapped' || $mapped['statuses']['position'] === 'unmapped') {
                $unmatchedRecords[] = [
                    'id' => $employee->id,
                    'sap' => $employee->sap,
                    'department' => $employee->txt_dept,
                    'unit' => $employee->organizational_unit,
                    'position' => $employee->position,
                    'reason' => $this->buildReason($mapped),
                ];
            }
        }

        $this->line('Departments:');
        $this->line('Exact matches: '.$counts['department']['exact']);
        $this->line('Normalized matches: '.$counts['department']['normalized']);
        $this->line('Unmatched: '.$counts['department']['unmatched']);
        $this->line('Conflicts: '.$counts['department']['conflict']);
        $this->line('');
        $this->line('Units:');
        $this->line('Exact matches: '.$counts['unit']['exact']);
        $this->line('Normalized matches: '.$counts['unit']['normalized']);
        $this->line('Unmatched: '.$counts['unit']['unmatched']);
        $this->line('Conflicts: '.$counts['unit']['conflict']);
        $this->line('');
        $this->line('Positions:');
        $this->line('Exact matches: '.$counts['position']['exact']);
        $this->line('Normalized matches: '.$counts['position']['normalized']);
        $this->line('Unmatched: '.$counts['position']['unmatched']);
        $this->line('Conflicts: '.$counts['position']['conflict']);
        $this->line('');
        $this->line('========================================');
        $this->line('UNMATCHED EMPLOYEES');
        $this->line('========================================');

        if ($unmatchedRecords === []) {
            $this->line('No unmatched employees.');
        } else {
            foreach ($unmatchedRecords as $record) {
                $this->line('Employee: '.($record['sap'] ?: 'ID '.$record['id']));
                $this->line('SAP: '.$record['sap']);
                $this->line('Legacy Department: '.($record['department'] ?? ''));
                $this->line('Legacy Unit: '.($record['unit'] ?? ''));
                $this->line('Legacy Position: '.($record['position'] ?? ''));
                $this->line('Reason: '.$record['reason']);
                $this->line('');
            }
        }

        if ($this->option('export-mapping-template')) {
            $this->exportMappingTemplate($employees);
        }

        return self::SUCCESS;
    }

    private function buildReason(array $mapping): string
    {
        $reasons = [];
        foreach (['department', 'unit', 'position'] as $field) {
            $status = $mapping['statuses'][$field] ?? 'unmapped';
            if ($status === 'unmapped') {
                $reasons[] = $field;
            }
        }

        if ($reasons === []) {
            return 'match found';
        }

        return 'unmatched field(s): '.implode(', ', $reasons);
    }

    private function exportMappingTemplate($employees): void
    {
        $path = storage_path('app/imports/employee-organization-mapping-template.csv');
        $directory = dirname($path);
        if (! is_dir($directory)) {
            mkdir($directory, 0777, true);
        }

        $handle = fopen($path, 'wb');
        fputcsv($handle, ['employee_id', 'employee_name', 'legacy_department', 'legacy_unit', 'legacy_position', 'suggested_department_id', 'suggested_unit_id', 'suggested_position_id', 'mapping_status', 'mapping_note']);

        foreach ($employees as $employee) {
            fputcsv($handle, [
                $employee->id,
                '',
                $employee->txt_dept,
                $employee->organizational_unit,
                $employee->position,
                '',
                '',
                '',
                'UNREVIEWED',
                '',
            ]);
        }

        fclose($handle);
        $this->info('Exported mapping template: '.$path);
    }
}
