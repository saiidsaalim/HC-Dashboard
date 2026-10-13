<?php

namespace App\Http\Requests;

use App\Models\EmployeePromotion;

class StorePromotionRequest extends PromotionRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('create', EmployeePromotion::class) ?? false;
    }
}
