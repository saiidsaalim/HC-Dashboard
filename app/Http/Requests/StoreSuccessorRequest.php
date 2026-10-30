<?php

namespace App\Http\Requests;

use App\Models\Successor;
use App\Support\SuccessorOrganizationStructure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSuccessorRequest extends FormRequest
{
    protected $errorBag = 'createSuccessor';

    public function authorize(): bool
    {
        return $this->user()?->can('create', Successor::class) ?? false;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'target_position_id' => ['required', 'integer', Rule::in(app(SuccessorOrganizationStructure::class)->positionIds())],
            'employee_id' => [
                'required',
                'integer',
                'exists:employees,id',
                Rule::unique('successors')->where('target_position_id', $this->input('target_position_id')),
            ],
            'talent_mapping' => ['required', 'string', 'max:100'],
            'succession_readiness' => ['required', 'string', 'max:100'],
            'idp' => ['required', 'string', 'max:2000'],
            'photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ];
    }
}
