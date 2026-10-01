<?php

namespace App\Console\Commands;

use App\Services\Organization\OrganizationWlaImportService;
use Illuminate\Console\Command;
use RuntimeException;

class ImportWlaOrganizationCommand extends Command
{
    protected $signature = 'organization:import-wla {path=storage/app/import/A. Form WLA.xlsx} {--dry-run : Preview without writing to database}';

    protected $description = 'Import Department, Unit, and Position master data from the WLA Excel "Tabel Posisi" sheet';

    public function handle(OrganizationWlaImportService $service): int
    {
        $path = $this->argument('path');
        $dryRun = $this->option('dry-run');

        try {
            $report = $service->import($path, $dryRun);
        } catch (RuntimeException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info('Organization Import '.($dryRun ? 'Dry Run' : 'Apply'));
        $this->line('Source rows: '.$report['source_rows']);
        $this->line('Unique departments: '.$report['unique_departments']);
        $this->line('Unique units: '.$report['unique_units']);
        $this->line('Unique positions: '.$report['unique_positions']);
        $this->line('Duplicate rows removed: '.$report['duplicate_rows_removed']);
        $this->line('Departments: new='.$report['departments']['new'].' existing='.$report['departments']['existing']);
        $this->line('Units: new='.$report['units']['new'].' existing='.$report['units']['existing']);
        $this->line('Positions: new='.$report['positions']['new'].' existing='.$report['positions']['existing']);
        $this->line('Conflicts: '.$report['conflicts']);
        $this->line('Invalid rows: '.$report['invalid_rows']);
        $this->line('Database changes: '.$report['database_changes']);

        if ($dryRun) {
            $this->comment('Dry-run aktif; tidak ada perubahan database disimpan.');

            return self::SUCCESS;
        }

        $this->info('Import master Organization selesai.');

        return self::SUCCESS;
    }
}
