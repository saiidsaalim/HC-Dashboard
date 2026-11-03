<?php

namespace App\Http\Requests;

use App\Models\Employee;
use App\Services\Personnel\OrganizationHierarchyService;
use App\Services\Personnel\PersonnelMovementFormDataService;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Validator;

abstract class PersonnelActionRequest extends FormRequest
{
    /** @return array<string, array<int, ValidationRule|string>> */
    public function rules(): array
    {
        return [
            'employee_id' => ['required', 'integer', 'exists:employees,id'],
            'new_directorate_id' => ['required', 'integer', 'exists:directorates,id'],
            'new_department_id' => ['required', 'integer', 'exists:departments,id'],
            'new_unit_id' => ['required', 'integer', 'exists:units,id'],
            'new_section_id' => ['nullable', 'integer', 'exists:sections,id'],
            'new_position_id' => ['required', 'integer', 'exists:positions,id'],
            'tmt' => ['required', 'date'],
            'pg' => ['required', 'string', 'max:255'],
            'jg_lama' => ['required', 'string', 'max:255'],
            'level_baru' => [
                'required',
                'string',
                'max:255',
                Rule::in(app(PersonnelMovementFormDataService::class)->levels()->all()),
            ],
            'jg_baru' => ['required', 'string', 'max:255'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'level_lama' => 'Level Lama',
            'level_baru' => 'Level Baru',
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'level_baru.required' => 'Level Baru wajib dipilih.',
            'level_baru.in' => 'Level Baru yang dipilih tidak tersedia pada Data Pegawai.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            try {
                app(OrganizationHierarchyService::class)->validatedTarget($this->validated());
            } catch (ValidationException $exception) {
                foreach ($exception->errors() as $field => $messages) {
                    foreach ($messages as $message) {
                        $validator->errors()->add($field, $message);
                    }
                }
            }

            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $employee = Employee::query()->find($this->integer('employee_id'));
            if ($employee === null || trim((string) $employee->employee_subgroup) === '') {
                $validator->errors()->add('employee_id', 'Employee yang dipilih belum memiliki Level pada Data Pegawai.');

                return;
            }

            if (! $this->requiresOrganizationChange()) {
                return;
            }

            $oldIds = $employee === null ? [] : [
                (int) $employee->directorate_id,
                (int) $employee->department_id,
                (int) $employee->unit_id,
                $employee->section_id === null ? null : (int) $employee->section_id,
                (int) $employee->position_id,
            ];
            $newIds = [
                $this->integer('new_directorate_id'),
                $this->integer('new_department_id'),
                $this->integer('new_unit_id'),
                $this->filled('new_section_id') ? $this->integer('new_section_id') : null,
                $this->integer('new_position_id'),
            ];

            if ($oldIds === $newIds) {
                $validator->errors()->add('new_position_id', 'Mutasi harus mengubah minimal satu organisasi atau Position.');
            }
        });
    }

    protected function requiresOrganizationChange(): bool
    {
        return false;
    }
}
