<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

abstract class PromotionRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'employee_id' => ['required', 'integer', 'exists:employees,id'],
            'nama' => ['required', 'string', 'max:255'],
            'departemen_lama' => ['required', 'string', 'max:255'],
            'jabatan_lama' => ['required', 'string', 'max:255'],
            'departemen_baru' => ['required', 'string', 'max:255'],
            'jabatan_baru' => ['required', 'string', 'max:255'],
            'tmt' => ['required', 'date'],
            'pg' => ['required', 'string', 'max:255'],
            'band_lama' => ['required', 'string', 'max:255'],
            'jg_lama' => ['required', 'string', 'max:255'],
            'band_baru' => ['required', 'string', 'max:255'],
            'jg_baru' => ['required', 'string', 'max:255'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
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
            'band_lama' => 'BAND Saat Ini',
            'jg_lama' => 'JG Saat Ini',
            'band_baru' => 'BAND Usulan',
            'jg_baru' => 'JG Usulan',
        ];
    }
}
