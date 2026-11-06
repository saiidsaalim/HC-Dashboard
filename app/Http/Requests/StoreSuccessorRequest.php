<?php

namespace App\Http\Requests;

use App\Models\Employee;
use App\Models\Successor;
use App\Support\SuccessorOrganizationStructure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSuccessorRequest extends FormRequest
{
    protected $errorBag = 'createSuccessor';

    public function authorize(): bool
    {
        return $this->user()?->can('create', Successor::class) ?? false;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'target_position_id' => ['required', 'integer', Rule::in(app(SuccessorOrganizationStructure::class)->positionIds())],
            'name' => ['required', 'string', 'max:255'],
            'sap' => ['required', 'string', 'max:50'],
            'current_position' => ['required', 'string', 'max:255'],
            'position_history' => ['required', 'array', 'min:1', 'max:10'],
            'position_history.*.period' => ['required', 'string', 'max:100'],
            'position_history.*.position' => ['required', 'string', 'max:255'],
            'birth_date' => ['required', 'date', 'before_or_equal:today'],
            'last_education' => ['required', 'string', 'max:255'],
            'talent_box' => ['required', 'integer', 'between:1,9'],
            'succession_readiness' => ['required', 'string', Rule::in(Employee::SUCCESSION_READINESS_OPTIONS)],
            'recommendation' => ['required', 'string', 'max:2000'],
            'targeted_band' => ['required', 'string', 'max:100'],
            'performance_year' => ['required', 'integer', 'between:1900,'.(now()->year + 1)],
            'performance_score' => ['required', 'numeric', 'between:0,100'],
            'performance_category' => ['required', 'string', 'max:100'],
            'mbti_type' => ['required', 'string', 'max:20'],
            'mbti_description' => ['required', 'string', 'max:2000'],
            'top_job_family_1' => ['required', 'string', 'max:255'],
            'top_job_family_2' => ['required', 'string', 'max:255'],
            'photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ];
    }
}
