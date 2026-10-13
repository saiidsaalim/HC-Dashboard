<?php

namespace App\Http\Requests;

use App\Models\EmployeePromotion;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ImportPromotionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('import', EmployeePromotion::class) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'mimes:xlsx,csv,txt', 'max:5120'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'file.required' => 'File Promosi wajib dipilih.',
            'file.mimes' => 'File Promosi harus berformat XLSX, CSV, atau TXT.',
            'file.max' => 'Ukuran file Promosi maksimal 5 MB.',
        ];
    }
}
