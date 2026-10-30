<?php

namespace App\Http\Requests;

use App\Services\FitProperCalculationService;
use Illuminate\Foundation\Http\FormRequest;

class SaveFitProperScoresRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['intent' => ['sometimes', 'in:draft,submit'], 'scores' => ['required', 'array']];
    }

    /**
     * Rules are also applied inside the service transaction against locked activity membership.
     *
     * @param  list<int>  $participantIds
     * @return array<string, array<int, string>|string>
     */
    public static function scoreRules(array $participantIds, bool $submit): array
    {
        $rules = ['scores' => 'required|array'];
        foreach ($participantIds as $participantId) {
            $rules['scores.'.$participantId] = ['sometimes', 'array'];
            foreach (array_keys(FitProperCalculationService::INDICATORS) as $key) {
                $rules['scores.'.$participantId.'.'.$key] = [$submit ? 'required' : 'nullable', 'numeric', 'decimal:0,6', 'between:1,5'];
            }
            $rules['scores.'.$participantId.'.recommendation'] = 'nullable|string|max:10000';
            $rules['scores.'.$participantId.'.notes'] = 'nullable|string|max:10000';
        }

        return $rules;
    }
}
