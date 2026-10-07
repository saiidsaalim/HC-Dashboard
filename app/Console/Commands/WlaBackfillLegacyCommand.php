<?php

namespace App\Console\Commands;

use App\Enums\WlaLegacyBackfillCategory;
use App\Services\Workforce\WlaLegacyBackfillService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use RuntimeException;
use Throwable;

class WlaBackfillLegacyCommand extends Command
{
    protected $signature = 'wla:backfill-legacy
        {--apply : Terapkan backfill setelah konfirmasi interaktif}
        {--assessment= : Batasi audit dan backfill ke satu assessment ID}
        {--fail-on-review : Gunakan exit code gagal jika masih ada record yang perlu ditinjau}
        {--report= : Tulis hasil audit ke file CSV yang ditentukan}';

    protected $description = 'Audit dan backfill data WLA lama secara aman dan idempotent';

    public function handle(WlaLegacyBackfillService $service): int
    {
        if (! $service->stageOneSchemaIsAvailable()) {
            $this->error('Migration WLA tahap 1 belum dijalankan.');

            return self::FAILURE;
        }

        try {
            $assessmentId = $this->assessmentId();
            $audit = $service->audit($assessmentId);
            $this->displayReport($audit, false);

            if (! $this->option('apply')) {
                $this->comment('Dry-run aktif; tidak ada perubahan database disimpan.');
                $this->writeCsvReport($audit);

                return $this->exitCodeFor($audit);
            }

            if (! $this->confirm('Terapkan backfill WLA untuk seluruh record ready?', false)) {
                $this->warn('Backfill dibatalkan; tidak ada perubahan database disimpan.');
                $this->writeCsvReport($audit);

                return self::FAILURE;
            }

            $result = $service->apply($assessmentId);
            $this->newLine();
            $this->displayReport($result, true);
            $this->writeCsvReport($result);

            return $this->exitCodeFor($result);
        } catch (RuntimeException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        } catch (Throwable $exception) {
            $this->error('Backfill WLA gagal: '.$exception->getMessage());

            return self::FAILURE;
        }
    }

    private function assessmentId(): ?int
    {
        $value = $this->option('assessment');

        if ($value === null) {
            return null;
        }

        if (! is_string($value) || ! preg_match('/^[1-9]\d*$/', $value)) {
            throw new RuntimeException('Opsi --assessment harus berupa ID positif.');
        }

        return (int) $value;
    }

    /** @param array<string, mixed> $report */
    private function displayReport(array $report, bool $applied): void
    {
        $summary = $report['summary'];
        $this->info('WLA Legacy Backfill '.($applied ? 'Apply' : 'Dry Run'));
        $this->table(['Ringkasan', 'Jumlah'], [
            ['Schedule siap', $summary['schedule_ready']],
            ['Schedule selesai', $summary['schedule_already_migrated']],
            ['Schedule perlu review/konflik', $summary['schedule_review_or_conflict']],
            ['Schedule diterapkan', $summary['schedule_applied']],
            ['Assessment siap', $summary['assessment_ready']],
            ['Assessment berhasil dibackfill', $summary['assessment_backfilled']],
            ['Assessment dilewati', $summary['assessment_skipped']],
            ['Aktivitas siap', $summary['activity_ready']],
            ['Aktivitas selesai', $summary['activity_already_migrated']],
            ['Aktivitas perlu review', $summary['activity_needs_review']],
            ['Aktivitas invalid/konflik', $summary['activity_invalid_or_conflict']],
        ]);

        if ($report['details'] === []) {
            $this->line('Tidak ada record yang memerlukan review.');

            return;
        }

        $this->table(
            ['Jenis', 'ID', 'Assessment ID', 'Kategori', 'Alasan'],
            collect($report['details'])->map(fn (array $detail): array => [
                $detail['type'],
                $detail['id'],
                $detail['assessment_id'] ?? '-',
                $detail['category'],
                $detail['reason'],
            ])->all(),
        );
    }

    /** @param array<string, mixed> $report */
    private function writeCsvReport(array $report): void
    {
        $path = $this->option('report');

        if ($path === null) {
            return;
        }

        if (! is_string($path) || trim($path) === '') {
            throw new RuntimeException('Opsi --report harus berisi path file CSV.');
        }

        $resolvedPath = $this->absoluteReportPath($path);
        File::ensureDirectoryExists(dirname($resolvedPath));
        $handle = fopen($resolvedPath, 'wb');

        if ($handle === false) {
            throw new RuntimeException('File report tidak dapat dibuat: '.$resolvedPath);
        }

        try {
            fputcsv($handle, ['record_type', 'record_id', 'assessment_id', 'category', 'reason']);

            foreach ([...$report['schedules'], ...$report['assessments'], ...$report['activities']] as $result) {
                fputcsv($handle, [
                    $result['type'],
                    $result['id'],
                    $result['assessment_id'] ?? '',
                    $result['category'] instanceof WlaLegacyBackfillCategory
                        ? $result['category']->value
                        : $result['category'],
                    $result['reason'],
                ]);
            }
        } finally {
            fclose($handle);
        }

        $this->info('Report CSV dibuat: '.$resolvedPath);
    }

    private function absoluteReportPath(string $path): string
    {
        if (preg_match('/^(?:[A-Za-z]:[\\\\\/]|[\\\\\/]{2})/', $path) === 1) {
            return $path;
        }

        return base_path($path);
    }

    /** @param array<string, mixed> $report */
    private function exitCodeFor(array $report): int
    {
        if (! $this->option('fail-on-review')) {
            return self::SUCCESS;
        }

        return $report['has_review'] ? self::FAILURE : self::SUCCESS;
    }
}
