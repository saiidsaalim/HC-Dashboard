<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

abstract class PersonnelActionRequest extends FormRequest
{
    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return [
            'sap' => ['required', 'string', 'max:50'],
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
}
