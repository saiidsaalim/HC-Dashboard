<?php

namespace App\Http\Requests;

use App\Enums\CriticalPositionCriticality;
use App\Models\CriticalPosition;
use Illuminate\Database\Query\Builder;
use Illuminate\Validation\Rule;

class UpdateCriticalPositionRequest extends StoreCriticalPositionRequest
{
    protected $errorBag = 'updateCriticalPosition';

    public function authorize(): bool
    {
        $criticalPosition = $this->route('criticalPosition');

        return $criticalPosition instanceof CriticalPosition
            && ($this->user()?->can('update', $criticalPosition) ?? false);
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        $criticalPosition = $this->route('criticalPosition');
        $currentPositionId = $criticalPosition instanceof CriticalPosition
            ? $criticalPosition->position_id
            : null;

        return [
            'position_id' => [
                'required',
                'integer',
                Rule::exists('positions', 'id')->where(
                    fn (Builder $query): Builder => $query
                        ->where('active', true)
                        ->when($currentPositionId !== null, fn (Builder $query): Builder => $query->orWhere('id', $currentPositionId)),
                ),
                Rule::unique('critical_positions', 'position_id')->ignore($criticalPosition),
            ],
            'criticality' => ['required', Rule::enum(CriticalPositionCriticality::class)],
            'assessment_reason' => ['required', 'string', 'max:2000'],
            'assessed_at' => ['required', 'date'],
            'review_due_at' => ['nullable', 'date', 'after_or_equal:assessed_at'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
