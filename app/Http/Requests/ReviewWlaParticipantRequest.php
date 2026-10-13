<?php

namespace App\Http\Requests;

use App\Models\WlaParticipant;
use Illuminate\Foundation\Http\FormRequest;

class ReviewWlaParticipantRequest extends FormRequest
{
    public function authorize(): bool
    {
        $participant = $this->route('participant');

        return $participant instanceof WlaParticipant
            && ($this->user()?->can('review', $participant) ?? false);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'review_note' => ['required', 'string', 'max:2000'],
        ];
    }
}
