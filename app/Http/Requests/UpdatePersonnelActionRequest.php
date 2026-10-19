<?php

namespace App\Http\Requests;

class UpdatePersonnelActionRequest extends PersonnelActionRequest
{
    public function authorize(): bool
    {
        $permission = match ($this->route()?->getName()) {
            'mutasi.update' => 'mutations.update',
            'demosi.update' => 'demotions.update',
            default => null,
        };

        return $permission !== null && ($this->user()?->hasPermission($permission) ?? false);
    }
}
