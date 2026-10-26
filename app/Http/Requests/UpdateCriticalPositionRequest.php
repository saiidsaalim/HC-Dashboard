<?php

namespace App\Http\Requests;

use App\Models\CriticalPosition;

class UpdateCriticalPositionRequest extends StoreCriticalPositionRequest
{
    protected $errorBag = 'updateCriticalPosition';

    public function authorize(): bool
    {
        $criticalPosition = $this->route('criticalPosition');

        return $criticalPosition instanceof CriticalPosition
            && ($this->user()?->can('update', $criticalPosition) ?? false);
    }
}
