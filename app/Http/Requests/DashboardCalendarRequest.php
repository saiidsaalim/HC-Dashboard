<?php

namespace App\Http\Requests;

use App\Models\FitProperActivity;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class DashboardCalendarRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() !== null
            && $this->user()->hasPermission('dashboard.view')
            && $this->user()->hasPermission('workspace.hc-od-career-management.view')
            && $this->user()->can('viewAny', FitProperActivity::class);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'start' => ['required', 'string', 'date_format:Y-m-d'],
            'end' => ['required', 'string', 'date_format:Y-m-d', 'after:start'],
        ];
    }

    /** @return list<callable> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $start = CarbonImmutable::parse($this->input('start'), 'UTC');
            $end = CarbonImmutable::parse($this->input('end'), 'UTC');
            if ($start->diffInDays($end) > 62) {
                $validator->errors()->add('end', 'Rentang kalender maksimal 62 hari.');
            }
        }];
    }
}
