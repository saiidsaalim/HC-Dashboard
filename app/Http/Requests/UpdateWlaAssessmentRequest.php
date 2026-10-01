<?php

namespace App\Http\Requests;

use App\Models\WlaAssessment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateWlaAssessmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $assessment = $this->route('wla');

        return $assessment instanceof WlaAssessment
            && ($this->user()?->can('update', $assessment) ?? false);
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
            $assessment = $this->route('wla');
            $this->merge([
                'efficiency_factor' => $assessment instanceof WlaAssessment
                    ? $assessment->efficiency_factor
                    : config('wla.default_efficiency_factor'),
            ]);
        }
    }
}
