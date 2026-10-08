<?php

namespace App\Console\Commands;

use App\Services\Employees\EmployeeOrganizationSyncService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use RuntimeException;

#[Signature('employees:map-organization
    {--dry-run : Report mappings without modifying data}
    {--apply : Create missing masters and map safe Employee rows}
    {--confirm= : Non-interactive confirmation token}
    {--report= : Optional CSV report path}
    {--chunk=500 : Employees read per database chunk}')]
#[Description('Audit or safely synchronize Employee organization masters and foreign keys')]
class MapEmployeeOrganization extends Command
{
    public function handle(EmployeeOrganizationSyncService $service): int
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
        if ($applying && ! $this->confirmed()) {
            $this->warn('Sinkronisasi dibatalkan. Tidak ada data yang diubah.');

            return self::FAILURE;
        }

        try {
            $result = $applying ? $service->apply($chunkSize) : $service->audit($chunkSize);
            if (is_string($this->option('report')) && trim($this->option('report')) !== '') {
                $this->writeReport($result, $this->option('report'));
            }
        } catch (RuntimeException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->display($result, $applying);

        return self::SUCCESS;
    }

    private function confirmed(): bool
    {
        if ($this->option('confirm') === EmployeeOrganizationSyncService::Confirmation) {
            return true;
        }

        if ($this->input->isInteractive()) {
            return $this->confirm('Terapkan sinkronisasi organisasi pegawai yang aman?', false);
        }

        if ($this->option('confirm') !== EmployeeOrganizationSyncService::Confirmation) {
            $this->error('Mode non-interaktif memerlukan --confirm='.EmployeeOrganizationSyncService::Confirmation.'.');

            return false;
        }

        return true;
    }

    /** @param array<string, mixed> $result */
    private function display(array $result, bool $applying): void
    {
        $summary = $result['summary'];
        $this->line('Mode: '.($applying ? 'apply' : 'dry-run'));
        $this->line('Total Employees: '.$summary['employees']);
        $this->line('Unique Department (txt_dept): '.$summary['unique_departments']);
        $this->line('Unique Unit (txt_dept + txt_biro): '.$summary['unique_units']);
        $this->line('Unique Position (txt_dept + txt_biro + position): '.$summary['unique_positions']);
        $this->line('Exact master matches: '.$summary['exact_matches']);
        $this->line('Normalized master matches: '.$summary['normalized_matches']);
        $this->line('New Departments: '.$summary['departments_to_create']);
        $this->line('New Units: '.$summary['units_to_create']);
        $this->line('New Positions: '.$summary['positions_to_create']);
        $this->line('Empty txt_dept: '.$summary['empty_department']);
        $this->line('Empty txt_biro: '.$summary['empty_unit']);
        $this->line('Empty position: '.$summary['empty_position']);
        $this->line('Conflicts: '.$summary['conflicts']);
        $this->line('Mappable Employees: '.$summary['mappable_employees']);
        $this->line('Employees Requiring Review: '.$summary['review_employees']);
        $this->line('Foreign Keys To Update: '.$summary['foreign_keys_to_update']);

        foreach ($summary['statuses'] as $status => $count) {
            $this->line($status.': '.$count);
        }

        $reviewRows = array_values(array_filter(
            $result['rows'],
            fn (array $row): bool => in_array($row['status'], ['needs_review', 'conflict', 'invalid'], true),
        ));
        if ($reviewRows !== []) {
            $this->table(
                ['Employee ID', 'Status', 'Reason'],
                array_map(fn (array $row): array => [$row['employee_id'], $row['status'], $row['reason']], $reviewRows),
            );
        }

        if ($applying) {
            $this->line('Employee rows updated: '.$result['applied']['employees_updated']);
            $this->line('Foreign keys updated: '.$result['applied']['foreign_keys_updated']);
            $this->line('Departments created: '.$result['applied']['departments_created']);
            $this->line('Units created: '.$result['applied']['units_created']);
            $this->line('Positions created: '.$result['applied']['positions_created']);
        } else {
            $this->comment('Dry-run selesai. Database tidak diubah.');
        }
    }

    /** @param array<string, mixed> $result */
    private function writeReport(array $result, string $path): void
    {
        $directory = dirname($path);
        if (! is_dir($directory) && ! mkdir($directory, 0777, true) && ! is_dir($directory)) {
            throw new RuntimeException('Direktori report tidak dapat dibuat.');
        }

        $handle = fopen($path, 'wb');
        if ($handle === false) {
            throw new RuntimeException('File report tidak dapat dibuat.');
        }

        fputcsv($handle, [
            'employee_id', 'status', 'reason', 'current_department_id', 'current_unit_id',
            'current_position_id', 'target_department_id', 'target_unit_id', 'target_position_id',
            'foreign_keys_to_update',
        ]);
        foreach ($result['rows'] as $row) {
            fputcsv($handle, [
                $row['employee_id'],
                $row['status'],
                $row['reason'],
                $row['current']['department_id'],
                $row['current']['unit_id'],
                $row['current']['position_id'],
                $row['target']['department_id'],
                $row['target']['unit_id'],
                $row['target']['position_id'],
                $row['foreign_keys_to_update'],
            ]);
        }
        fclose($handle);
        $this->info('CSV report dibuat: '.$path);
    }
}
