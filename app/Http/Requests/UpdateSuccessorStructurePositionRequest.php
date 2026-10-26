<?php

namespace App\Http\Requests;

use App\Models\Successor;
use App\Models\SuccessorStructurePosition;
use Illuminate\Validation\Rule;

class UpdateSuccessorStructurePositionRequest extends StoreSuccessorStructurePositionRequest
{
    protected $errorBag = 'updateStructurePosition';

    public function authorize(): bool
    {
        return $this->structurePosition() instanceof SuccessorStructurePosition
            && ($this->user()?->can('updateStructure', Successor::class) ?? false);
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        $position = $this->structurePosition();

        return [
            ...parent::rules(),
            'department_id' => ['required', 'integer', Rule::in([$position?->department_id])],
            'structure_position_id' => ['required', 'integer', Rule::in([$position?->getKey()])],
        ];
    }
}
