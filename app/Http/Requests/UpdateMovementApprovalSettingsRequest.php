<?php

namespace App\Http\Requests;

use App\Enums\UserRole;
use App\Models\EmployeeDemotion;
use App\Models\EmployeeMutation;
use App\Models\User;
use App\Services\Personnel\MovementApprovalWorkflowService;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateMovementApprovalSettingsRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $movementClass = $this->routeIs('mutasi.approval-settings.update')
            ? EmployeeMutation::class
            : EmployeeDemotion::class;

        return $this->user()?->can('manageApprovalSettings', $movementClass) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $approvalSteps = range(1, MovementApprovalWorkflowService::REQUIRED_STEPS);

        return [
            'approvers' => [
                'required',
                'array:'.implode(',', $approvalSteps),
                'size:'.MovementApprovalWorkflowService::REQUIRED_STEPS,
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
            'approvers.required' => 'Ketiga approver wajib dipilih.',
            'approvers.array' => 'Konfigurasi approver tidak valid.',
            'approvers.size' => 'Ketiga tahap approver wajib diisi.',
            'approvers.*.required' => 'Approver pada setiap tahap wajib dipilih.',
            'approvers.*.distinct' => 'Approver Tahap 1, 2, dan 3 harus berbeda.',
            'approvers.*.exists' => 'Approver harus merupakan User Admin atau Manager yang tersedia.',
        ];
    }
}
