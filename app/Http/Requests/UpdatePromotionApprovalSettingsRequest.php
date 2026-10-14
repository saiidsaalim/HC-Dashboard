<?php

namespace App\Http\Requests;

use App\Enums\UserRole;
use App\Models\EmployeePromotion;
use App\Models\User;
use App\Services\Personnel\PromotionApprovalWorkflowService;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePromotionApprovalSettingsRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('manageApprovalSettings', EmployeePromotion::class) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $approvalSteps = range(1, PromotionApprovalWorkflowService::REQUIRED_STEPS);

        return [
            'approvers' => [
                'required',
                'array:'.implode(',', $approvalSteps),
                'size:'.PromotionApprovalWorkflowService::REQUIRED_STEPS,
            ],
            'approvers.*' => [
                'required',
                'integer',
                'distinct',
                Rule::exists(User::class, 'id')->where(
                    fn (Builder $query): Builder => $query->whereIn('role', [
                        UserRole::ADMIN->value,
                        UserRole::MANAGER->value,
                    ]),
                ),
            ],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'approvers.required' => 'Keempat approver wajib dipilih.',
            'approvers.array' => 'Konfigurasi approver tidak valid.',
            'approvers.size' => 'Keempat tahap approver wajib diisi.',
            'approvers.*.required' => 'Approver pada setiap tahap wajib dipilih.',
            'approvers.*.distinct' => 'Approver Tahap 1, 2, 3, dan 4 harus berbeda.',
            'approvers.*.exists' => 'Approver harus merupakan User Admin atau Manager yang tersedia.',
        ];
    }
}
