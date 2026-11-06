<?php

namespace App\Http\Requests;

use App\Enums\CriticalPositionCriticality;
use App\Models\CriticalPosition;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCriticalPositionRequest extends FormRequest
{
    protected $errorBag = 'createCriticalPosition';

    public function authorize(): bool
    {
        return $this->user()?->can('create', CriticalPosition::class) ?? false;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'position_id' => [
                'required',
                'integer',
                Rule::exists('positions', 'id')->where(fn (Builder $query): Builder => $query->where('active', true)),
                Rule::unique('critical_positions', 'position_id'),
            ],
            'criticality' => ['required', Rule::enum(CriticalPositionCriticality::class)],
            'assessment_reason' => ['required', 'string', 'max:2000'],
            'assessed_at' => ['required', 'date'],
            'review_due_at' => ['nullable', 'date', 'after_or_equal:assessed_at'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'position_id.required' => 'Position wajib dipilih.',
            'position_id.integer' => 'Position yang dipilih tidak valid.',
            'position_id.exists' => 'Position yang dipilih tidak tersedia atau sudah nonaktif.',
            'position_id.unique' => 'Position tersebut sudah terdaftar sebagai Critical Position.',
            'criticality.required' => 'Criticality wajib dipilih.',
            'criticality.enum' => 'Criticality harus berupa High, Medium, atau Low.',
            'assessment_reason.required' => 'Alasan penilaian wajib diisi.',
            'assessment_reason.max' => 'Alasan penilaian maksimal 2.000 karakter.',
            'assessed_at.required' => 'Tanggal penilaian wajib diisi.',
            'assessed_at.date' => 'Tanggal penilaian tidak valid.',
            'review_due_at.date' => 'Tanggal review berikutnya tidak valid.',
            'review_due_at.after_or_equal' => 'Tanggal review berikutnya harus sama atau setelah tanggal penilaian.',
            'notes.max' => 'Catatan maksimal 5.000 karakter.',
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->filled('criticality')) {
            $this->merge(['criticality' => mb_strtolower(trim((string) $this->input('criticality')))]);
        }
    }
}
