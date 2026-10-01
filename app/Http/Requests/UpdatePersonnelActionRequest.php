<?php

namespace App\Http\Requests;

use App\Enums\UserRole;

class UpdatePersonnelActionRequest extends PersonnelActionRequest
{
    public function authorize(): bool
    {
        return $this->user()?->roleEnum() === UserRole::SUPER_ADMIN;
    }
}
