<?php

namespace App\Http\Requests;

use App\Services\Personnel\LevelTransitionService;

class UpdateDemotionRequest extends PersonnelActionRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('employeeDemotion')) ?? false;
    }

    protected function validateLevelTransition(
        LevelTransitionService $levelTransitionService,
        string $currentLevel,
        string $newLevel,
    ): void {
        $levelTransitionService->ensureDemotion($currentLevel, $newLevel);
    }
}
