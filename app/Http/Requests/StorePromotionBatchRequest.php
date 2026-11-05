<?php

namespace App\Http\Requests;

use App\Models\EmployeePromotion;
use App\Services\Personnel\LevelTransitionService;

class StorePromotionBatchRequest extends StorePersonnelMovementBatchRequest
{
    protected function movementClass(): string
    {
        return EmployeePromotion::class;
    }

    protected function movementLabel(): string
    {
        return 'Promosi';
    }

    protected function validateLevelTransition(
        LevelTransitionService $levelTransitionService,
        string $currentLevel,
        string $newLevel,
    ): void {
        $levelTransitionService->ensurePromotion($currentLevel, $newLevel);
    }
}
