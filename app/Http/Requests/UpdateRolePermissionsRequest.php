<?php

namespace App\Http\Requests;

use App\Enums\UserRole;
use App\Services\Authorization\PermissionRegistry;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateRolePermissionsRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->roleEnum() === UserRole::SUPER_ADMIN
            && ($this->user()?->hasPermission('users.manage-access') ?? false);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $rules = [
            '_form' => ['nullable', 'string', 'in:role-access'],
            'permissions' => ['array'],
        ];

        foreach (UserRole::cases() as $role) {
            $rules["permissions.{$role->name}"] = ['sometimes', 'array'];
            $rules["permissions.{$role->name}.*"] = [
                'string',
                'distinct',
                Rule::in(array_keys(PermissionRegistry::permissions())),
                Rule::exists('permissions', 'key'),
            ];
        }

        return $rules;
    }

    /** @return array<int, callable(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $submittedMatrix = $this->input('permissions', []);

            if (! is_array($submittedMatrix)) {
                return;
            }

            $validRoles = array_column(UserRole::cases(), 'name');

            foreach (array_diff(array_keys($submittedMatrix), $validRoles) as $invalidRole) {
                $validator->errors()->add('permissions.'.$invalidRole, 'Role hak akses tidak valid.');
            }
        }];
    }

    protected function prepareForValidation(): void
    {
        if (! $this->has('permissions')) {
            $this->merge(['permissions' => []]);
        }
    }
}
