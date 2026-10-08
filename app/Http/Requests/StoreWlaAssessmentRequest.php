<?php

namespace App\Http\Requests;

use App\Models\WlaAssessment;
use App\Rules\ClassifiedWorkSchedule;
use Illuminate\Database\Query\Builder;
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
            'department_id' => [
                'required',
                'integer',
                Rule::exists('departments', 'id')
                    ->where(fn (Builder $query): Builder => $query->where('active', true)),
            ],
            'unit_id' => [
                'required', 'integer',
                Rule::exists('units', 'id')->where(fn (Builder $query): Builder => $query
                    ->where('department_id', $this->input('department_id'))
                    ->where('active', true)),
            ],
            'position_id' => [
                'required', 'integer',
                Rule::exists('positions', 'id')->where(fn (Builder $query): Builder => $query
                    ->where('unit_id', $this->input('unit_id'))
                    ->where('active', true)),
            ],
            'work_schedule_id' => [
                'bail',
                'required',
                'integer',
                Rule::exists('work_schedules', 'id')
                    ->where(fn (Builder $query): Builder => $query->where('active', true)),
                new ClassifiedWorkSchedule,
            ],
            'work_calendar_id' => [
                'required',
                'integer',
                Rule::exists('work_calendars', 'id')
                    ->where(fn (Builder $query): Builder => $query
                        ->where('year', $this->input('period'))
                        ->where('active', true)),
            ],
            'efficiency_factor' => ['required', 'numeric', 'decimal:0,4', 'min:0.0001', 'max:1'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (! $this->has('efficiency_factor')) {
            $this->merge(['efficiency_factor' => config('wla.default_efficiency_factor')]);
        }
    }
}
