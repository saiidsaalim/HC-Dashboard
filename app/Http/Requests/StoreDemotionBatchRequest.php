<?php

namespace App\Http\Requests;

use App\Models\EmployeeDemotion;
use App\Services\Personnel\LevelTransitionService;

class StoreDemotionBatchRequest extends StorePersonnelMovementBatchRequest
{
    protected function movementClass(): string
    {
        return EmployeeDemotion::class;
    }

    protected function movementLabel(): string
    {
        return 'Demosi';
    }

    protected function validateLevelTransition(
        LevelTransitionService $levelTransitionService,
        string $currentLevel,
        string $newLevel,
    ): void {
        $levelTransitionService->ensureDemotion($currentLevel, $newLevel);
    }
}
