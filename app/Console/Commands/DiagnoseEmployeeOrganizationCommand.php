<?php

namespace App\Console\Commands;

use App\Services\Employees\EmployeeOrganizationMapper;
use App\Services\Employees\EmployeeOrganizationSyncService;
use Illuminate\Console\Command;

class DiagnoseEmployeeOrganizationCommand extends Command
{
    protected $signature = 'employees:diagnose-organization {--export-mapping-template : Export a CSV template for manual mapping review}';

    protected $description = 'Diagnose legacy Employee organization values without modifying data.';

    public function handle(EmployeeOrganizationSyncService $service, EmployeeOrganizationMapper $mapper): int
    {
        $result = $service->audit();
        $summary = $result['summary'];

        $this->line('========================================');
        $this->line('EMPLOYEE ORGANIZATION MAPPING DIAGNOSTIC');
        $this->line('========================================');
        $this->line('Employees: '.$summary['employees']);
        $this->line('Departments: '.$summary['unique_departments']);
        $this->line('Units: '.$summary['unique_units']);
        $this->line('Positions: '.$summary['unique_positions']);
        $this->line('Exact matches: '.$summary['exact_matches']);
        $this->line('Normalized matches: '.$summary['normalized_matches']);
        $this->line('New masters: '.($summary['departments_to_create'] + $summary['units_to_create'] + $summary['positions_to_create']));
        $this->line('Conflicts: '.$summary['conflicts']);
        $this->line('Review required: '.$summary['review_employees']);
        $this->line('========================================');
        $this->line('REVIEW EMPLOYEES');
        $this->line('========================================');

        $reviewRows = array_values(array_filter(
            $result['rows'],
            fn (array $row): bool => in_array($row['status'], ['needs_review', 'conflict', 'invalid'], true),
        ));
        if ($reviewRows === []) {
            $this->line('No employees require review.');
        } else {
            $this->table(
                ['Employee ID', 'Status', 'Reason'],
                array_map(fn (array $row): array => [$row['employee_id'], $row['status'], $row['reason']], $reviewRows),
            );
        }

        if ($this->option('export-mapping-template')) {
            $this->info('Exported mapping template: '.$mapper->exportTemplate());
        }

        return self::SUCCESS;
    }
}
