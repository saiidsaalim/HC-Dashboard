<?php

namespace App\Http\Requests;

use App\Models\Successor;
use Illuminate\Validation\Rule;

class UpdateSuccessorRequest extends StoreSuccessorRequest
{
    protected $errorBag = 'updateSuccessor';

    public function authorize(): bool
    {
        $successor = $this->route('successor');

        return $successor instanceof Successor
            && ($this->user()?->can('update', $successor) ?? false);
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        $successor = $this->route('successor');

        return [
            ...parent::rules(),
            'successor_id' => ['required', 'integer', Rule::in([$successor?->getKey()])],
        ];
    }
}
