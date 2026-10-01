<?php

namespace App\Http\Requests;

use App\Enums\UserRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEmployeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->roleEnum() === UserRole::SUPER_ADMIN;
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return [
            'sap' => ['required', 'string', 'max:50', 'unique:employees,sap'],
            'department_id' => ['nullable', 'integer', 'exists:departments,id', 'required_with:unit_id'],
            'unit_id' => [
                'nullable', 'integer', 'required_with:position_id',
                Rule::exists('units', 'id')->where('department_id', $this->input('department_id')),
            ],
            'position_id' => [
                'nullable', 'integer',
                Rule::exists('positions', 'id')->where('unit_id', $this->input('unit_id')),
            ],
            'id_number' => ['nullable', 'string', 'max:100'],
            'agkn' => ['nullable', 'string', 'max:100'],
            'position_id_source' => ['nullable', 'string', 'max:100'],
            'personal_number' => ['nullable', 'string', 'max:100'],
            'position' => ['nullable', 'string', 'max:255'],
            'employee_subgroup' => ['nullable', 'string', 'max:100'],
            'cost_ctr' => ['nullable', 'string', 'max:100'],
            'txt_dir' => ['nullable', 'string', 'max:255'],
            'txt_dept' => ['nullable', 'string', 'max:255'],
            'txt_biro' => ['nullable', 'string', 'max:255'],
            'txt_sect' => ['nullable', 'string', 'max:255'],
            'gender_key' => ['nullable', 'string', 'max:20'],
            'abrevation_position' => ['nullable', 'string', 'max:100'],
            'abrevation_organization' => ['nullable', 'string', 'max:100'],
            'organizational_unit' => ['nullable', 'string', 'max:255'],
            'cost_center' => ['nullable', 'string', 'max:100'],
            'masa_kontrak' => ['nullable', 'date'],
            'birth_date' => ['nullable', 'date'],
            'tempat_lahir' => ['nullable', 'string', 'max:255'],
            'personnel_area' => ['nullable', 'string', 'max:100'],
            'email' => ['nullable', 'email', 'max:255'],
            'religious' => ['nullable', 'string', 'max:100'],
            'usia' => ['nullable', 'integer', 'min:0', 'max:2147483647'],
            'pendidikan' => ['nullable', 'string', 'max:255'],
            'hiring' => ['nullable', 'date'],
            'organilk' => ['nullable', 'date'],
            'alamat' => ['nullable', 'string'],
        ];
    }
}
