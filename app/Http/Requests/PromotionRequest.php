<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;

abstract class PromotionRequest extends PersonnelActionRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return parent::rules();
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            ...parent::messages(),
            'employee_id.required' => 'Employee wajib dipilih.',
            'employee_id.exists' => 'Employee yang dipilih tidak tersedia.',
            '*.required' => ':attribute wajib diisi.',
            '*.max' => ':attribute maksimal :max karakter.',
            'tmt.date' => 'TMT harus berupa tanggal yang valid.',
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'nama' => 'Nama',
            'departemen_lama' => 'Department Lama',
            'jabatan_lama' => 'Jabatan Saat Ini',
            'departemen_baru' => 'Department Baru',
            'jabatan_baru' => 'Jabatan Usulan',
            'tmt' => 'TMT',
            'pg' => 'PG',
            'level_lama' => 'Level Lama',
            'jg_lama' => 'JG Saat Ini',
            'level_baru' => 'Level Baru',
            'jg_baru' => 'JG Usulan',
        ];
    }
}
