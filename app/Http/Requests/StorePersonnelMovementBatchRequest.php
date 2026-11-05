<?php

namespace App\Http\Requests;

use App\Models\Employee;
use App\Models\EmployeeDemotion;
use App\Models\EmployeePromotion;
use App\Services\Personnel\LevelTransitionService;
use App\Services\Personnel\OrganizationHierarchyService;
use App\Services\Personnel\PersonnelMovementFormDataService;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Validator;

abstract class StorePersonnelMovementBatchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', $this->movementClass()) ?? false;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'default_tmt' => ['required', 'date'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.employee_id' => ['required', 'integer', 'distinct', 'exists:employees,id'],
            'items.*.new_directorate_id' => ['required', 'integer', 'exists:directorates,id'],
            'items.*.new_department_id' => ['required', 'integer', 'exists:departments,id'],
            'items.*.new_unit_id' => ['required', 'integer', 'exists:units,id'],
            'items.*.new_section_id' => ['nullable', 'integer', 'exists:sections,id'],
            'items.*.new_position_id' => ['required', 'integer', 'exists:positions,id'],
            'items.*.tmt' => ['required', 'date'],
            'items.*.pg' => ['required', 'string', 'max:255'],
            'items.*.jg_lama' => ['required', 'string', 'max:255'],
            'items.*.level_baru' => [
                'required',
                'string',
                'max:255',
                Rule::in(app(PersonnelMovementFormDataService::class)->levels()->all()),
            ],
            'items.*.jg_baru' => ['required', 'string', 'max:255'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'name.required' => 'Nama batch wajib diisi.',
            'default_tmt.required' => 'TMT default wajib diisi.',
            'items.required' => 'Pilih minimal satu pegawai untuk Batch '.$this->movementLabel().'.',
            'items.min' => 'Batch '.$this->movementLabel().' harus berisi minimal satu pegawai.',
            'items.*.employee_id.distinct' => 'Pegawai yang sama tidak boleh dipilih lebih dari satu kali.',
            'items.*.level_baru.in' => 'Level Baru harus menggunakan urutan level resmi yang tersedia.',
            'items.*.required' => ':attribute wajib diisi untuk setiap pegawai.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            /** @var array<int, array<string, mixed>> $items */
            $items = $this->input('items', []);
            $employees = Employee::query()
                ->whereIn('id', collect($items)->pluck('employee_id')->filter())
                ->get()
                ->keyBy('id');

            foreach ($items as $index => $item) {
                $employee = $employees->get((int) $item['employee_id']);
                if (! $employee instanceof Employee || trim((string) $employee->employee_subgroup) === '') {
                    $validator->errors()->add("items.{$index}.employee_id", 'Employee belum memiliki Level pada Data Pegawai.');

                    continue;
                }

                try {
                    app(OrganizationHierarchyService::class)->validatedTarget($item);
                    $this->validateLevelTransition(
                        app(LevelTransitionService::class),
                        (string) $employee->employee_subgroup,
                        (string) $item['level_baru'],
                    );
                } catch (ValidationException $exception) {
                    foreach ($exception->errors() as $field => $messages) {
                        foreach ($messages as $message) {
                            $validator->errors()->add("items.{$index}.{$field}", $message);
                        }
                    }
                }
            }
        });
    }

    /** @return class-string<EmployeePromotion|EmployeeDemotion> */
    abstract protected function movementClass(): string;

    abstract protected function movementLabel(): string;

    abstract protected function validateLevelTransition(
        LevelTransitionService $levelTransitionService,
        string $currentLevel,
        string $newLevel,
    ): void;
}
