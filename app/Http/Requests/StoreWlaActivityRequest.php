<?php

namespace App\Http\Requests;

use App\Enums\WlaPeriodUnit;
use App\Models\WlaActivity;
use App\Models\WlaAssessment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreWlaActivityRequest extends FormRequest
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
            'activity_name' => ['required', 'string', 'max:255'],
            'frequency' => ['required', 'numeric', 'decimal:0,2', 'min:0.01', 'max:'.WlaActivity::MAX_INPUT_VALUE],
            'period_unit' => ['required', Rule::enum(WlaPeriodUnit::class)],
            'time_allocated_hours' => ['required', 'numeric', 'decimal:0,2', 'min:0.01', 'max:'.WlaActivity::MAX_INPUT_VALUE],
            'sort_order' => ['sometimes', 'required', 'integer', 'min:0', 'max:'.WlaActivity::MAX_SORT_ORDER],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
