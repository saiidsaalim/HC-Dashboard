<?php

namespace App\Http\Requests;

use App\Models\WlaAssessment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreWlaAssessmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', WlaAssessment::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'period' => ['required', 'integer', 'min:1900', 'max:2100'],
            'department_id' => ['required', 'integer', 'exists:departments,id'],
            'unit_id' => [
                'required', 'integer',
                Rule::exists('units', 'id')->where(fn ($query) => $query->where('department_id', $this->input('department_id'))),
            ],
            'position_id' => [
                'required', 'integer',
                Rule::exists('positions', 'id')->where(fn ($query) => $query->where('unit_id', $this->input('unit_id'))),
            ],
            'work_schedule_id' => ['required', 'integer', 'exists:work_schedules,id'],
            'work_calendar_id' => ['required', 'integer', 'exists:work_calendars,id'],
            'efficiency_factor' => ['required', 'numeric', 'decimal:0,4', 'min:0', 'max:1'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (! $this->has('efficiency_factor')) {
            $this->merge(['efficiency_factor' => config('wla.default_efficiency_factor')]);
        }
    }
}
