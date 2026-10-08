<?php

namespace App\Http\Requests;

use App\Models\WlaAssessment;
use App\Rules\ClassifiedWorkSchedule;
use Illuminate\Database\Query\Builder;
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
        $assessment = $this->route('wla');
        $assessment = $assessment instanceof WlaAssessment ? $assessment : null;

        return [
            'period' => ['required', 'integer', 'min:1900', 'max:2100'],
            'department_id' => [
                'required',
                'integer',
                Rule::exists('departments', 'id')
                    ->where(fn (Builder $query): Builder => $this->activeOrCurrent(
                        $query,
                        $assessment?->department_id,
                    )),
            ],
            'unit_id' => [
                'required', 'integer',
                Rule::exists('units', 'id')->where(fn (Builder $query): Builder => $this->activeOrCurrent(
                    $query->where('department_id', $this->input('department_id')),
                    $assessment?->unit_id,
                )),
            ],
            'position_id' => [
                'required', 'integer',
                Rule::exists('positions', 'id')->where(fn (Builder $query): Builder => $this->activeOrCurrent(
                    $query->where('unit_id', $this->input('unit_id')),
                    $assessment?->position_id,
                )),
            ],
            'work_schedule_id' => [
                'bail',
                'required',
                'integer',
                Rule::exists('work_schedules', 'id')
                    ->where(fn (Builder $query): Builder => $this->activeOrCurrent(
                        $query,
                        $assessment?->work_schedule_id,
                    )),
                new ClassifiedWorkSchedule,
            ],
            'work_calendar_id' => [
                'required',
                'integer',
                Rule::exists('work_calendars', 'id')
                    ->where(fn (Builder $query): Builder => $this->activeOrCurrent(
                        $query->where('year', $this->input('period')),
                        $assessment?->work_calendar_id,
                    )),
            ],
            'efficiency_factor' => ['required', 'numeric', 'decimal:0,4', 'min:0.0001', 'max:1'],
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

    private function activeOrCurrent(Builder $query, ?int $currentId): Builder
    {
        return $query->where(function (Builder $query) use ($currentId): void {
            $query->where('active', true);

            if ($currentId !== null) {
                $query->orWhere('id', $currentId);
            }
        });
    }
}
