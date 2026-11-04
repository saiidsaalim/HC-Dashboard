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

        foreach ([EmployeeMutation::class, EmployeePromotion::class, EmployeeDemotion::class] as $movementClass) {
            /** @var Builder<Model> $query */
            $query = $movementClass::query()
                ->where('verification_status', 'Approved')
                ->whereNotNull('finalized_at')
                ->whereNull('employee_applied_at')
                ->whereDate('tmt', '<=', today());

            $query->chunkById(100, function ($movements) use ($finalizationService, &$applied): void {
                foreach ($movements as $movement) {
                    DB::transaction(function () use ($movement, $finalizationService, &$applied): void {
                        $lockedMovement = $movement->newQuery()->lockForUpdate()->findOrFail($movement->getKey());

                        if ($finalizationService->applyIfDue($lockedMovement)) {
                            $applied++;
                        }
                    }, attempts: 3);
                }
            });
        }

        $this->info("{$applied} perubahan pegawai diterapkan.");

        return self::SUCCESS;
    }
}
