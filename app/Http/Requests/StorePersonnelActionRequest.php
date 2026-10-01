<?php

namespace App\Http\Requests;

class StorePersonnelActionRequest extends PersonnelActionRequest
{
    public function authorize(): bool
    {
        return true;
    }
}
