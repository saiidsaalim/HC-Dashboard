<?php

namespace App\Http\Requests;

use App\Enums\CriticalPositionCriticality;
use App\Enums\CriticalPositionStatus;
use App\Models\CriticalPosition;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCriticalPositionRequest extends FormRequest
{
    protected $errorBag = 'createCriticalPosition';

    public function authorize(): bool
    {
        return $this->user()?->can('create', CriticalPosition::class) ?? false;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'position' => ['required', 'string', 'max:255'],
            'level' => ['required', 'string', 'max:100'],
            'department' => ['required', 'string', 'max:255'],
            'directorate' => ['required', 'string', 'max:255'],
            'criticality' => ['required', Rule::enum(CriticalPositionCriticality::class)],
            'incumbent' => ['nullable', 'string', 'max:100'],
            'sap' => ['nullable', 'string', 'max:50'],
            'status' => ['required', Rule::enum(CriticalPositionStatus::class)],
        ];
    }
}
