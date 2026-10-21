<?php

namespace App\Console\Commands;

use App\Services\Authorization\PermissionSynchronizer;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Throwable;

#[Signature('permissions:sync {--dry-run : Tampilkan perubahan tanpa menulis ke database} {--apply-defaults : Samakan hak akses seluruh role dengan baseline kode}')]
#[Description('Sinkronkan definisi permission dan baseline hak akses role.')]
class PermissionsSyncCommand extends Command
{
    public function handle(PermissionSynchronizer $synchronizer): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $applyDefaults = (bool) $this->option('apply-defaults');

        if ($applyDefaults && ! $dryRun) {
            $this->warn('PERINGATAN: konfigurasi hak akses role lokal akan disamakan dengan baseline yang tersimpan dalam kode.');

            if (! $this->input->isInteractive()) {
                $this->error('Mode --apply-defaults ditolak pada proses non-interaktif. Jalankan secara interaktif dan konfirmasi perubahan.');

                return self::FAILURE;
            }

            if (! $this->confirm('Terapkan baseline hak akses role?', false)) {
                $this->info('Sinkronisasi dibatalkan tanpa perubahan.');

                return self::SUCCESS;
            }
        }

        try {
            $result = $synchronizer->synchronize($dryRun, $applyDefaults);
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        if ($dryRun) {
            $this->comment('DRY RUN - tidak ada perubahan database yang dilakukan.');
        }

        $this->line('Permission dibuat      : '.$result['created']);
        $this->line('Permission diperbarui  : '.$result['updated']);
        $this->line('Permission tetap       : '.$result['unchanged']);
        $this->line('Relasi ditambahkan     : '.$result['relations_added']);
        $this->line('Relasi dilepas         : '.$result['relations_removed']);
        $this->line('Permission stale       : '.$result['stale']);

        $this->displayItems('Akan dibuat', $result['created_keys']);
        $this->displayItems('Akan diperbarui', $result['updated_keys']);
        $this->displayRelations('Relasi akan ditambahkan', $result['relation_additions']);
        $this->displayRelations('Relasi akan dilepas', $result['relation_removals']);
        $this->displayItems('Permission stale (tidak dihapus)', $result['stale_keys']);

        $this->info($dryRun ? 'Audit sinkronisasi selesai.' : 'Sinkronisasi permission selesai.');

        return self::SUCCESS;
    }

    /** @param array<int, string> $items */
    private function displayItems(string $heading, array $items): void
    {
        if ($items === []) {
            return;
        }

        $this->newLine();
        $this->line($heading.':');

        foreach ($items as $item) {
            $this->line('  - '.$item);
        }
    }

    /** @param array<int, array{role: string, key: string}> $relations */
    private function displayRelations(string $heading, array $relations): void
    {
        if ($relations === []) {
            return;
        }

        $this->newLine();
        $this->line($heading.':');

        foreach ($relations as $relation) {
            $this->line("  - {$relation['role']}: {$relation['key']}");
        }
    }
}
