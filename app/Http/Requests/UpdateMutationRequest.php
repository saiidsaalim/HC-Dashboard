<?php

namespace App\Http\Requests;

class UpdateMutationRequest extends PersonnelActionRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('employeeMutation')) ?? false;
    }

    protected function requiresOrganizationChange(): bool
    {
        return true;
    }
}
