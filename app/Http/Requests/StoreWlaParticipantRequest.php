<?php

namespace App\Http\Requests;

use App\Enums\UserRole;
use App\Models\WlaAssessment;
use App\Models\WlaParticipant;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreWlaParticipantRequest extends FormRequest
{
    public function authorize(): bool
    {
        $assessment = $this->route('wla');

        return $assessment instanceof WlaAssessment
            && ($this->user()?->can('manageParticipants', [WlaParticipant::class, $assessment]) ?? false);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'employee_id' => [
                'required',
                'integer',
                Rule::exists('employees', 'id')->where(fn (Builder $query): Builder => $query
                    ->where('department_id', $this->assessment()->department_id)
                    ->where('unit_id', $this->assessment()->unit_id)
                    ->where('position_id', $this->assessment()->position_id)),
                Rule::exists('users', 'employee_id')->where('role', UserRole::STAFF->value),
                Rule::unique('wla_participants', 'employee_id')
                    ->where('wla_assessment_id', $this->assessment()->getKey()),
            ],
        ];
    }

    private function assessment(): WlaAssessment
    {
        $assessment = $this->route('wla');
        abort_unless($assessment instanceof WlaAssessment, 404);

        return $assessment;
    }
}
