<?php

namespace App\Http\Requests;

use App\Models\EmployeeDemotionBatch;
use Illuminate\Database\Query\Builder;
use Illuminate\Validation\Rule;

class StoreDemotionBatchItemRequest extends StoreDemotionRequest
{
    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        $rules = parent::rules();
        $batch = $this->route('employeeDemotionBatch');

        if ($batch instanceof EmployeeDemotionBatch) {
            $rules['employee_id'][] = Rule::unique('employee_demotions', 'employee_id')
                ->where(fn (Builder $query): Builder => $query->where('employee_demotion_batch_id', $batch->getKey()));
        }

        return $rules;
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            ...parent::messages(),
            'employee_id.unique' => 'Employee tersebut sudah ada di dalam Batch Demosi ini.',
        ];
    }
}
