<?php

namespace App\Console\Commands;

use App\Models\EmployeeDemotion;
use App\Models\EmployeeMutation;
use App\Models\EmployeePromotion;
use App\Services\Personnel\EmployeeMovementFinalizationService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

#[Signature('personnel:apply-due-movements')]
#[Description('Apply approved personnel movements whose effective date has arrived')]
class ApplyDueEmployeeMovements extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(EmployeeMovementFinalizationService $finalizationService): int
    {
        $applied = 0;
        $skipped = 0;
        $failed = 0;

        foreach ($this->movementDefinitions() as $movementClass => $statuses) {
            /** @var Builder<Model> $query */
            $query = $movementClass::query()
                ->whereIn('verification_status', $statuses);

            $query->chunkById(100, function ($movements) use ($finalizationService, &$applied, &$skipped, &$failed): void {
                foreach ($movements as $movement) {
                    try {
                        $wasApplied = DB::transaction(function () use ($movement, $finalizationService): bool {
                            $lockedMovement = $movement->newQuery()
                                ->lockForUpdate()
                                ->find($movement->getKey());

                            return $lockedMovement !== null
                                && $finalizationService->applyIfDue($lockedMovement);
                        }, attempts: 3);

                        $wasApplied ? $applied++ : $skipped++;
                    } catch (Throwable $exception) {
                        $failed++;
                        Log::error('Gagal menerapkan personnel movement.', [
                            'movement_id' => $movement->getKey(),
                            'movement_type' => class_basename($movement),
                            'employee_id' => $movement->employee_id,
                            'message' => $this->safeMessage($exception),
                        ]);
                    }
                }
            });
        }

        $this->line("Berhasil diterapkan: {$applied}");
        $this->line("Dilewati: {$skipped}");
        $this->line("Gagal: {$failed}");

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }

    /** @return array<class-string<Model>, array<int, string>> */
    private function movementDefinitions(): array
    {
        return [
            EmployeeMutation::class => ['Pending', 'Approved', 'Final'],
            EmployeePromotion::class => ['Waiting Approval', 'Approved', 'Final'],
            EmployeeDemotion::class => ['Pending', 'Approved', 'Final'],
        ];
    }

    private function safeMessage(Throwable $exception): string
    {
        if ($exception instanceof ValidationException) {
            return collect($exception->errors())->flatten()->first()
                ?? 'Validasi penerapan movement gagal.';
        }

        return 'Penerapan movement gagal karena kesalahan internal.';
    }
}
