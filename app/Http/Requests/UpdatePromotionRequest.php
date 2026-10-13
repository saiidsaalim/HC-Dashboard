<?php

namespace App\Http\Requests;

class UpdatePromotionRequest extends PromotionRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('employeePromotion')) ?? false;
    }
}
