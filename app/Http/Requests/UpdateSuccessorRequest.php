<?php

namespace App\Http\Requests;

use App\Models\Successor;
use Illuminate\Validation\Rule;

class UpdateSuccessorRequest extends StoreSuccessorRequest
{
    protected $errorBag = 'updateSuccessor';

    public function authorize(): bool
    {
        $successor = $this->route('successor');

        return $successor instanceof Successor
            && ($this->user()?->can('update', $successor) ?? false);
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        $successor = $this->route('successor');

        return [
            ...parent::rules(),
            'employee_id' => [
                'required',
                'integer',
                'exists:employees,id',
                Rule::unique('successors')
                    ->where('target_position_id', $this->input('target_position_id'))
                    ->ignore($successor),
            ],
            'successor_id' => ['required', 'integer', Rule::in([$successor?->getKey()])],
        ];
    }
}
