<?php

namespace App\Http\Requests;

use App\Enums\WlaFrequencyUnit;
use App\Enums\WlaTimeUnit;
use App\Enums\WlaVolumeUnit;
use App\Models\WlaAssessment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateWlaActivityRequest extends FormRequest
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
            'frequency' => ['required', 'numeric', 'decimal:0,2', 'min:0', 'max:99999999.99'],
            'frequency_unit' => ['required', Rule::enum(WlaFrequencyUnit::class)],
            'volume' => ['required', 'numeric', 'decimal:0,2', 'min:0', 'max:9999999999.99'],
            'volume_unit' => ['required', Rule::enum(WlaVolumeUnit::class)],
            'time_allocated' => ['required', 'numeric', 'decimal:0,2', 'min:0.01', 'max:99999999.99'],
            'time_unit' => ['required', Rule::enum(WlaTimeUnit::class)],
            'sort_order' => ['sometimes', 'required', 'integer', 'min:0'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
