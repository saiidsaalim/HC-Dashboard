<?php

namespace App\Http\Requests;

use App\Models\EmployeeMutationBatch;
use Illuminate\Database\Query\Builder;
use Illuminate\Validation\Rule;

class StoreMutationBatchItemRequest extends StoreMutationRequest
{
    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        $rules = parent::rules();
        $batch = $this->route('employeeMutationBatch');

        if ($batch instanceof EmployeeMutationBatch) {
            $rules['employee_id'][] = Rule::unique('employee_mutations', 'employee_id')
                ->where(fn (Builder $query): Builder => $query->where('employee_mutation_batch_id', $batch->getKey()));
        }

        return $rules;
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            ...parent::messages(),
            'employee_id.unique' => 'Employee tersebut sudah ada di dalam Batch Mutasi ini.',
        ];
    }
}
