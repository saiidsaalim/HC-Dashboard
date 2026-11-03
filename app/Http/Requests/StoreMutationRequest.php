<?php

namespace App\Http\Requests;

use App\Models\EmployeeMutation;

class StoreMutationRequest extends PersonnelActionRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('create', EmployeeMutation::class) ?? false;
    }

    protected function requiresOrganizationChange(): bool
    {
        return true;
    }
}
