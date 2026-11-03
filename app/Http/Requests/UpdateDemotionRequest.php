<?php

namespace App\Http\Requests;

class UpdateDemotionRequest extends PersonnelActionRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('employeeDemotion')) ?? false;
    }
}
