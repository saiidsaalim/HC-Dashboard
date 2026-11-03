<?php

namespace App\Http\Requests;

use App\Models\EmployeeDemotion;

class StoreDemotionRequest extends PersonnelActionRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('create', EmployeeDemotion::class) ?? false;
    }
}
