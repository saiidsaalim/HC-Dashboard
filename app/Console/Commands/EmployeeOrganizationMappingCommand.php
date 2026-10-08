<?php

namespace App\Console\Commands;

use App\Services\Employees\EmployeeOrganizationMapper;
use Illuminate\Console\Command;
use RuntimeException;

class EmployeeOrganizationMappingCommand extends Command
{
    protected $signature = 'employees {--export-template : Export CSV template for manual employee organization review} {--validate= : Validate a mapping CSV file} {--preview= : Preview approved mapping changes without writing} {--apply= : Apply approved mapping from a CSV file}';

    protected $description = 'Export, validate, preview, and apply Employee organization mapping review data.';

    public function handle(EmployeeOrganizationMapper $mapper): int
    {
        if ($this->option('export-template')) {
            $path = $mapper->exportTemplate();
            $this->info('Template mapping telah dibuat: '.$path);

            return self::SUCCESS;
        }

        if ($this->option('validate')) {
            $path = $this->option('validate');

            try {
                $result = $mapper->validateCsv($path);
            } catch (RuntimeException $exception) {
                $this->error($exception->getMessage());

                return self::FAILURE;
            }

            $this->line('Valid: '.$result['valid']);
            $this->line('Invalid: '.$result['invalid']);
            $this->line('Review required: '.$result['review_required']);

            return $result['invalid'] > 0 ? self::FAILURE : self::SUCCESS;
        }

        if ($this->option('preview')) {
            $path = $this->option('preview');

            try {
                $result = $mapper->previewCsv($path);
            } catch (RuntimeException $exception) {
                $this->error($exception->getMessage());

                return self::FAILURE;
            }

            $this->line('Employees to update: '.$result['employees_to_update']);
            $this->line('Department changes: '.$result['department_changes']);
            $this->line('Unit changes: '.$result['unit_changes']);
            $this->line('Position changes: '.$result['position_changes']);
            $this->line('Unmapped: '.$result['unmapped']);
            $this->line('Invalid: '.$result['invalid']);

            foreach ($result['details'] as $detail) {
                $this->line('');
                $this->line('Employee: '.$detail['employee_name']);
                $this->line('Current: department = "'.$detail['current']['department'].'", txt_biro = "'.$detail['current']['txt_biro'].'", position = "'.$detail['current']['position'].'"');
                $this->line('Will become: department_id = '.($detail['target']['department_id'] ?? 'null').', unit_id = '.($detail['target']['unit_id'] ?? 'null').', position_id = '.($detail['target']['position_id'] ?? 'null'));
            }

            return self::SUCCESS;
        }

        if ($this->option('apply')) {
            $path = $this->option('apply');

            try {
                $result = $mapper->applyCsv($path);
            } catch (RuntimeException $exception) {
                $this->error($exception->getMessage());

                return self::FAILURE;
            }

            $this->line('Total approved: '.$result['total_approved']);
            $this->line('Successfully updated: '.$result['successfully_updated']);
            $this->line('Skipped: '.$result['skipped']);
            $this->line('Failed: '.$result['failed']);
            $this->line('Department mapped: '.$result['department_mapped']);
            $this->line('Unit mapped: '.$result['unit_mapped']);
            $this->line('Position mapped: '.$result['position_mapped']);

            return self::SUCCESS;
        }

        $this->info('Gunakan --export-template, --validate=path, --preview=path, atau --apply=path.');

        return self::SUCCESS;
    }
}
