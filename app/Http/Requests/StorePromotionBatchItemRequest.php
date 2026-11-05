<?php

namespace App\Http\Requests;

use App\Models\EmployeePromotionBatch;
use Illuminate\Database\Query\Builder;
use Illuminate\Validation\Rule;

class StorePromotionBatchItemRequest extends StorePromotionRequest
{
    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        $rules = parent::rules();
        $batch = $this->route('employeePromotionBatch');

        if ($batch instanceof EmployeePromotionBatch) {
            $rules['employee_id'][] = Rule::unique('employee_promotions', 'employee_id')
                ->where(fn (Builder $query): Builder => $query->where('employee_promotion_batch_id', $batch->getKey()));
        }

        return $rules;
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            ...parent::messages(),
            'employee_id.unique' => 'Employee tersebut sudah ada di dalam Batch Promosi ini.',
        ];
    }
}
