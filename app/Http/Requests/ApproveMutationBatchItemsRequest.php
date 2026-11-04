<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ApproveMutationBatchItemsRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('mutations.approve') ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'mutation_ids' => ['required', 'array', 'min:1'],
            'mutation_ids.*' => ['required', 'integer', 'distinct', 'exists:employee_mutations,id'],
        ];
    }
}
