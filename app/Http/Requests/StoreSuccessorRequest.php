<?php

namespace App\Http\Requests;

use App\Models\Successor;
use App\Support\SuccessorStructure;
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
            'structure_position_key' => ['required', 'string', Rule::in(app(SuccessorStructure::class)->positionKeys())],
            'name' => ['required', 'string', 'max:255'],
            'position' => ['required', 'string', 'max:255'],
            'talent_mapping' => ['required', 'string', 'max:100'],
            'succession_readiness' => ['required', 'string', 'max:100'],
            'idp' => ['required', 'string', 'max:2000'],
            'education' => ['required', 'string', 'max:255'],
            'tenure' => ['required', 'string', 'max:100'],
            'directorate' => ['required', 'string', 'max:255'],
            'photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ];
    }
}
