<?php

namespace App\Http\Requests;

use App\Models\EmployeeDemotion;
use App\Services\Personnel\LevelTransitionService;

class StoreDemotionRequest extends PersonnelActionRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('create', EmployeeDemotion::class) ?? false;
    }

    protected function validateLevelTransition(
        LevelTransitionService $levelTransitionService,
        string $currentLevel,
        string $newLevel,
    ): void {
        $levelTransitionService->ensureDemotion($currentLevel, $newLevel);
    }
}
