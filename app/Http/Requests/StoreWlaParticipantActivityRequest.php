<?php

namespace App\Http\Requests;

use App\Enums\WlaPeriodUnit;
use App\Models\WlaActivity;
use App\Models\WlaParticipant;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreWlaParticipantActivityRequest extends FormRequest
{
    public function authorize(): bool
    {
        $participant = $this->route('participant');

        return $participant instanceof WlaParticipant
            && ($this->user()?->can('update', $participant) ?? false);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'activity_name' => ['required', 'string', 'max:255'],
            'frequency' => ['required', 'numeric', 'decimal:0,2', 'min:0.01', 'max:'.WlaActivity::MAX_INPUT_VALUE],
            'period_unit' => ['required', Rule::enum(WlaPeriodUnit::class)],
            'time_allocated_hours' => ['required', 'numeric', 'decimal:0,2', 'min:0.01', 'max:'.WlaActivity::MAX_INPUT_VALUE],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
