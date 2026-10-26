<?php

namespace App\Http\Requests;

use App\Enums\UserRole;
use App\Models\User;
use App\Services\Authorization\WorkspaceRegistry;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $actor = $this->user();
        $target = $this->route('user');

        if (! $actor instanceof User || ! $target instanceof User || ! $actor->hasPermission('users.update')) {
            return false;
        }

        if ($target->isSuperAdmin() && ! $actor->isSuperAdmin()) {
            return false;
        }

        return ! $this->workspaceAccessSubmitted() || $actor->isSuperAdmin();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var User $user */
        $user = $this->route('user');
        $usesEmployeeIdentity = $this->filled('employee_id')
            || (! $this->exists('employee_id') && $user->employee_id !== null);

        return [
            '_form' => ['nullable', 'string', 'in:edit-user-'.$user->getKey()],
            'name' => [Rule::requiredIf(! $usesEmployeeIdentity), 'nullable', 'string', 'max:255'],
            'email' => [Rule::requiredIf(! $usesEmployeeIdentity), 'nullable', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user)],
            'password' => ['nullable', 'string', 'min:8'],
            'employee_id' => [
                Rule::prohibitedIf($user->roleEnum() !== UserRole::STAFF),
                'nullable',
                'integer',
                Rule::exists('employees', 'id'),
                Rule::unique('users', 'employee_id')->ignore($user),
            ],
            'workspace_access_submitted' => ['sometimes', 'accepted'],
            'workspace_access' => ['array'],
            'workspace_access.*' => [
                'string',
                Rule::in(array_keys(app(WorkspaceRegistry::class)->assignable())),
            ],
        ];
    }

    public function workspaceAccessSubmitted(): bool
    {
        return $this->exists('workspace_access_submitted') || $this->exists('workspace_access');
    }

    protected function prepareForValidation(): void
    {
        if (! $this->exists('workspace_access_submitted')) {
            return;
        }

        $workspaceAccess = $this->input('workspace_access', []);

        if (is_array($workspaceAccess)) {
            $this->merge([
                'workspace_access' => collect($workspaceAccess)->uniqueStrict()->values()->all(),
            ]);
        }
    }
}
